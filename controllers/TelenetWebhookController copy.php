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
        'campo_protocolo' => 'ufCrm41_1727802471',
        'mapeamento_campos' => [
            'nome_arquivo' => 'ufCrm41_1737477674',
            'mensagem' => 'ufCrm41_1737476071', 
            'cliente' => 'ufCrm41_1727805418',
            'cnpj' => 'ufCrm41_1727873180',
            'data_solicitacao' => 'ufCrm41_1737476250',
            'data_retorno_solicitacao' => 'ufCrm41_1742081702'
        ]
    ];

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

            // 2. Buscar deal pelo protocolo
            $filtros = [self::BITRIX_CONFIG['campo_protocolo'] => $protocolo];
            $resultadoBusca = BitrixHelper::listarItensCrm(
                self::BITRIX_CONFIG['entity_type_id'], 
                $filtros, 
                ['id', 'title', self::BITRIX_CONFIG['campo_protocolo']]
            );

            if (!$resultadoBusca['success']) {
                echo json_encode(['success' => false, 'error' => 'Erro ao buscar deal no Bitrix: ' . ($resultadoBusca['error'] ?? 'Erro desconhecido')]);
                return;
            }

            $deals = $resultadoBusca['items'] ?? [];
            
            if (empty($deals)) {
                echo json_encode(['success' => false, 'error' => "Deal não encontrado para o protocolo: $protocolo"]);
                return;
            }

            $deal = $deals[0]; // Pega o primeiro (deveria ser único)
            $dealId = $deal['id'];

            // 3. Preparar campos para atualizar
            $camposParaAtualizar = [];
            
            // Mapear campos do JSON para campos do Bitrix
            foreach (self::BITRIX_CONFIG['mapeamento_campos'] as $campoJson => $ufBitrix) {
                if (isset($dados[$campoJson]) && !empty($dados[$campoJson])) {
                    $camposParaAtualizar[$ufBitrix] = $dados[$campoJson];
                }
            }

            // 4. Atualizar deal no Bitrix se há campos para atualizar
            if (!empty($camposParaAtualizar)) {
                $resultadoAtualizacao = BitrixDealHelper::editarDeal(
                    self::BITRIX_CONFIG['entity_type_id'],
                    $dealId,
                    $camposParaAtualizar
                );

                if (!$resultadoAtualizacao['success']) {
                    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar deal no Bitrix: ' . ($resultadoAtualizacao['error'] ?? 'Erro desconhecido')]);
                    return;
                }
            }

            // 5. Resposta de sucesso
            echo json_encode([
                'success' => true,
                'message' => 'Dados recebidos e processados com sucesso',
                'protocolo' => $protocolo,
                'deal_id' => $dealId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}
