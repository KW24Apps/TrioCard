<?php
// Controller para receber o webhook da Telenet
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper;
use Helpers\LogHelper;

class TelenetWebhookController {
    // Configurações do Bitrix
    private const BITRIX_CONFIG = [
        'entity_type_id' => 1054,
        'category' => 195,
        'mapeamento_campos' => [
            'nome_arquivo' => 'ufCrm41_1737477674',
            'protocolo' => 'ufCrm41_1727802471',
            'mensagem' => 'ufCrm41_1737476071', 
            'cliente' => 'ufCrm41_1727805418',
            'cnpj' => 'ufCrm41_1727873180',
            'data_solicitacao' => 'ufCrm41_1737476250',
            'data_retorno_solicitacao' => 'ufCrm41_1742081702',
            'codigo_cliente' => '',
            'quant_registros' => ''
        ]
    ];

    /**
     * Limpa e corrige uma string JSON malformada.
     * Remove espaços extras, caracteres de controle e tenta adicionar vírgulas faltantes.
     *
     * @param string $jsonString A string JSON bruta.
     * @return string A string JSON tratada.
     */
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

        BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $dealExistente['id'], $comment);
        
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

        BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $newDealId, $comment);
        
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
