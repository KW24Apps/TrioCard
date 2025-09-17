<?php
// Controller para receber o webhook da Telenet
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixCompanyHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../Repositories/DatabaseRepository.php';

use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixCompanyHelper;
use Helpers\LogHelper;
use Repositories\DatabaseRepository;

class TelenetWebhookController {
    // Configurações do Bitrix
    private const BITRIX_CONFIG = [
        'entity_type_id' => 1042,
        'category' => 14,
        'mapeamento_campos' => [
            'nome_arquivo' => 'ufCrm8_1756758446',
            'protocolo' => 'ufCrm8_1756758502',
            'mensagem' => 'ufCrm8_1756758530', 
            'cliente' => 'ufCrm8_1756758572',
            'cnpj' => 'ufCrm8_1756758552',
            'data_solicitacao' => 'ufCrm8_1756758589',
            'data_retorno_solicitacao' => 'ufCrm8_1756758616',
            'codigo_cliente' => '',
            'quant_registros' => '',
            'cnpj_consulta_empresa' => 'ufCrm1741654678'
        ]
    ];

    // Limpa e corrige uma string JSON malformada.
    private function corrigirJson($jsonString) {
        // 1. Remover todos os tipos de espaços em branco (incluindo non-breaking spaces) e 
        // caracteres de controle do início e do fim da string. Isso é um "super trim".
        $jsonString = preg_replace('/^[\p{Z}\p{C}]+|[\p{Z}\p{C}]+$/u', '', $jsonString);

        // 2. Corrigir quebras de linha que ocorrem logo após um ':'.
        // Isso corrige casos como "key": \n "value".
        $jsonString = preg_replace('/:\s*[\r\n]+\s*/', ': ', $jsonString);
        
        // 3. Adicionar vírgulas faltantes entre os pares chave-valor que estão em linhas separadas.
        // Procura por uma aspa dupla, seguida por espaços/nova linha, mas somente se
        // o que vem depois NÃO é uma vírgula ou uma chave de fechamento.
        $jsonString = preg_replace('/"(\s*[\r\n]+\s*)"(?![,\}])/', '",\1"', $jsonString);

        // 4. Remover vírgula extra antes de uma chave de fechamento '}'.
        // Isso pode acontecer se a lógica anterior adicionar uma vírgula no final.
        $jsonString = preg_replace('/,\s*}/', ' }', $jsonString);

        return $jsonString;
    }

    // Valida um CNPJ com base no algoritmo do Ministério da Fazenda.
    private function validarCnpj($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);

        if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }

        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
    }

    // Valida, limpa e formata um CNPJ para o padrão XX.XXX.XXX/XXXX-XX.
    private function formatarCnpj($cnpj) {
        // 1. Valida o CNPJ. Se não for válido, retorna o valor original.
        if (!$this->validarCnpj($cnpj)) {
            return $cnpj;
        }

        // 2. Remove tudo que não for dígito para garantir a limpeza.
        $cnpjLimpo = preg_replace('/[^0-9]/', '', (string) $cnpj);

        // 3. Aplica a máscara.
        return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($cnpjLimpo));
    }

    // Vincula uma empresa existente ao deal com base no CNPJ.
    private function vincularEmpresaPorCnpj($dealId, $cnpj) {
        if (empty($cnpj)) {
            return;
        }

        // Garante que o CNPJ esteja formatado antes da busca, conforme solicitado.
        $cnpjFormatado = $this->formatarCnpj($cnpj);

        $campoCnpjEmpresa = self::BITRIX_CONFIG['mapeamento_campos']['cnpj_consulta_empresa'];
        $filtros = [$campoCnpjEmpresa => $cnpjFormatado];

        LogHelper::logBitrixHelpers("Iniciando busca de empresa para o Deal ID: $dealId com CNPJ formatado: " . json_encode($filtros), __CLASS__ . '::' . __FUNCTION__);
        
        // Busca a empresa pelo CNPJ (entityTypeId 4 para Company)
        $resultadoBusca = BitrixHelper::listarItensCrm(
            4, // Corrigido: 4 é o entityTypeId para Company
            $filtros,
            ['id'],
            1
        );

        LogHelper::logBitrixHelpers("Resultado da busca de empresa: " . json_encode($resultadoBusca), __CLASS__ . '::' . __FUNCTION__);

        if ($resultadoBusca['success'] && !empty($resultadoBusca['items'])) {
            $companyId = $resultadoBusca['items'][0]['id'];
            LogHelper::logBitrixHelpers("Empresa encontrada com ID: $companyId. Vinculando ao Deal ID: $dealId.", __CLASS__ . '::' . __FUNCTION__);

            // Vincula a empresa encontrada ao deal
            BitrixDealHelper::editarDeal(
                self::BITRIX_CONFIG['entity_type_id'],
                $dealId,
                ['companyId' => $companyId]
            );
        } else {
            LogHelper::logBitrixHelpers("Nenhuma empresa encontrada para o CNPJ formatado: $cnpjFormatado. Nenhuma vinculação será feita.", __CLASS__ . '::' . __FUNCTION__);
        }
    }

    // Método principal que executa a lógica do webhook
    public function executar() {
        header('Content-Type: application/json');
        
        try {
            // 1. Ler, corrigir e validar JSON
            $rawInput = file_get_contents('php://input');
            $jsonCorrigido = $this->corrigirJson($rawInput);
            $dados = json_decode($jsonCorrigido, true);

            if (!$dados || !isset($dados['protocolo']) || empty($dados['protocolo'])) {
                http_response_code(400);
                $erro = !$dados ? 'JSON inválido ou não enviado' : 'Campo protocolo obrigatório não informado';
                echo json_encode(['success' => false, 'error' => $erro]);
                return;
            }

            // 2. Formatar campos, como o CNPJ
            if (isset($dados['cnpj'])) {
                $dados['cnpj'] = $this->formatarCnpj($dados['cnpj']);
            }

            $protocolo = $dados['protocolo'];
            $mensagem = $dados['mensagem'] ?? '';
            
            // Lógica de Negócio baseada na Mensagem
            if ($mensagem === 'Arquivo gerado') {
                // Se a mensagem é 'Arquivo gerado', sempre cria um novo deal.
                $this->criarDeal($dados, $protocolo);

            } elseif (in_array($mensagem, ['Arquivo retornado', 'Sem retorno'])) {
                // Se for uma mensagem de atualização, busca o deal para atualizar.
                $campoProtocolo = self::BITRIX_CONFIG['mapeamento_campos']['protocolo'];
                $filtros = [$campoProtocolo => $protocolo];
                
                $resultadoBusca = BitrixHelper::listarItensCrm(
                    self::BITRIX_CONFIG['entity_type_id'],
                    $filtros,
                    ['id', $campoProtocolo],
                    1
                );
                
                if (isset($resultadoBusca['success']) && $resultadoBusca['success'] && !empty($resultadoBusca['items'])) {
                    $this->atualizarDeal($resultadoBusca['items'][0], $dados, $protocolo);
                } else {
                    // Se era para atualizar mas não encontrou, retorna erro.
                    http_response_code(400); // Alterado de 404 para 400
                    echo json_encode([
                        'success' => false, 
                        'error' => "Deal com protocolo '$protocolo' não encontrado para atualização."
                    ]);
                    return;
                }
            } else {
                // Se a mensagem não for reconhecida, retorna um erro.
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => "Mensagem '$mensagem' não reconhecida. Valores aceitos: Arquivo gerado, Arquivo retornado, Sem retorno."
                ]);
                return;
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
    
    // Atualizar apenas: nome_arquivo, data_retorno_solicitacao e mensagem
    private function atualizarDeal($dealExistente, $dados, $protocolo) {
        
        $camposParaAtualizar = [];
        $camposAtualizacao = ['nome_arquivo', 'data_retorno_solicitacao', 'mensagem'];
        
        foreach ($camposAtualizacao as $campo) {
            $ufBitrix = self::BITRIX_CONFIG['mapeamento_campos'][$campo];
            if (isset($dados[$campo]) && $dados[$campo] !== '' && $ufBitrix !== '') {
                $camposParaAtualizar[$ufBitrix] = $dados[$campo];
            }
        }
        
        $resultado = BitrixDealHelper::editarDeal(
            self::BITRIX_CONFIG['entity_type_id'],
            $dealExistente['id'],
            $camposParaAtualizar
        );
        
        if (!$resultado['success']) {
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar deal no Bitrix: ' . ($resultado['error'] ?? 'Erro desconhecido')]);
            return;
        }

        // Adicionar comentário na timeline
        $entityTypeTimeline = 'dynamic_' . self::BITRIX_CONFIG['entity_type_id'];
        
        $baseComment = '';
        switch ($dados['mensagem']) {
            case 'Arquivo retornado':
                $baseComment = 'TeleNet: Arquivo retornado Rede Compras.';
                break;
            case 'Sem retorno':
                $baseComment = 'TeleNet: Sem retorno Rede Compras.';
                break;
            default:
                $baseComment = "TeleNet: {$dados['mensagem']} Rede Compras.";
                break;
        }
        $comment = $baseComment . "\nProtocolo TeleNet: " . $protocolo;

        BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $dealExistente['id'], $comment, 36);
        
        echo json_encode([
            'success' => true,
            'message' => 'Deal atualizado com sucesso',
            'protocolo' => $protocolo,
            'deal_id' => $dealExistente['id'],
            'acao' => 'atualizado',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
 
    // Criar com todos os campos exceto data_retorno_solicitacao
    private function criarDeal($dados, $protocolo) {
        $camposParaCriar = [];
        foreach (self::BITRIX_CONFIG['mapeamento_campos'] as $campoJson => $ufBitrix) {
            if ($campoJson !== 'data_retorno_solicitacao' && isset($dados[$campoJson]) && $dados[$campoJson] !== '' && $ufBitrix !== '') {
                $camposParaCriar[$ufBitrix] = $dados[$campoJson];
            }
        }
        
        $resultado = BitrixDealHelper::criarDeal(
            self::BITRIX_CONFIG['entity_type_id'],
            self::BITRIX_CONFIG['category'],
            $camposParaCriar
        );
        
        if (!$resultado['success']) {
            echo json_encode(['success' => false, 'error' => 'Erro ao criar deal no Bitrix: ' . ($resultado['error'] ?? 'Erro desconhecido')]);
            return;
        }

        // Adicionar comentário na timeline
        $newDealId = $resultado['id'];
        $entityTypeTimeline = 'dynamic_' . self::BITRIX_CONFIG['entity_type_id'];

        $baseComment = 'TeleNet: Arquivo Gerado na Rede Compras.'; // Mensagem específica para criação
        $comment = $baseComment . "\nProtocolo TeleNet: " . $protocolo;

        BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $newDealId, $comment, 36);

        // Tenta vincular a empresa pelo CNPJ
        $this->vincularEmpresaPorCnpj($newDealId, $dados['cnpj'] ?? null);
        
        // Salvar informações iniciais no banco de dados local
        $databaseRepository = new DatabaseRepository();
        $dadosParaSalvar = [
            'protocolo_telenet' => $protocolo,
            'nome_arquivo_telenet' => $dados['nome_arquivo'] ?? null,
            'nome_cliente_telenet' => $dados['cliente'] ?? null,
            'cnpj_cliente_telenet' => $dados['cnpj'] ?? null,
            'id_deal_bitrix' => $newDealId,
            'vinculacao_jallcard' => 'PENDENTE' // Status inicial
        ];

        try {
            $databaseRepository->inserirPedidoIntegracao($dadosParaSalvar);
            LogHelper::logBitrixHelpers("Dados iniciais do pedido TeleNet salvos no banco de dados local para o Deal ID: $newDealId.", __CLASS__ . '::' . __FUNCTION__);
        } catch (PDOException $e) {
            LogHelper::logBitrixHelpers("Erro ao salvar dados iniciais do pedido TeleNet no banco de dados local para o Deal ID: $newDealId: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'error');
            // Não impede a criação do deal no Bitrix, mas registra o erro.
        }

        echo json_encode([
            'success' => true,
            'message' => 'Deal criado com sucesso',
            'protocolo' => $protocolo,
            'deal_id' => $newDealId,
            'acao' => 'criado',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
