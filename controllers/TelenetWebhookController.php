<?php
// Controller para receber o webhook da Telenet
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper;

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

    // Método principal que executa a lógica do webhook
    public function executar() {
        header('Content-Type: application/json');
        
        try {
            // 1. Ler e validar JSON
            $dados = json_decode(file_get_contents('php://input'), true);

            if (!$dados || !isset($dados['protocolo']) || empty($dados['protocolo'])) {
                http_response_code(400);
                $erro = !$dados ? 'JSON inválido ou não enviado' : 'Campo protocolo obrigatório não informado';
                echo json_encode(['success' => false, 'error' => $erro]);
                return;
            }

            $protocolo = $dados['protocolo'];
            $mensagem = $dados['mensagem'] ?? '';
            
            // Validar se a mensagem é uma das esperadas
            $mensagensValidas = ['Arquivo gerado', 'Arquivo retornado', 'Sem retorno'];
            if (!empty($mensagem) && !in_array($mensagem, $mensagensValidas)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Mensagem inválida. Valores aceitos: ' . implode(', ', $mensagensValidas)
                ]);
                return;
            }

            // 2. Buscar deal pelo protocolo
            $campoProtocolo = self::BITRIX_CONFIG['mapeamento_campos']['protocolo'];
            $filtros = [$campoProtocolo => $protocolo];
            
            $resultadoBusca = BitrixHelper::listarItensCrm(
                self::BITRIX_CONFIG['entity_type_id'],
                $filtros,
                ['id', $campoProtocolo],
                1
            );
            
            if ($resultadoBusca && !empty($resultadoBusca)) {
                $this->atualizarDeal($resultadoBusca[0], $dados, $protocolo);
            } else {
                $this->criarDeal($dados, $protocolo);
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
            $dealExistente['ID'],
            $camposParaAtualizar
        );
        
        if (!$resultado['success']) {
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar deal no Bitrix: ' . ($resultado['error'] ?? 'Erro desconhecido')]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Deal atualizado com sucesso',
            'protocolo' => $protocolo,
            'deal_id' => $dealExistente['ID'],
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
        
        echo json_encode([
            'success' => true,
            'message' => 'Deal criado com sucesso',
            'protocolo' => $protocolo,
            'deal_id' => $resultado['id'],
            'acao' => 'criado',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
