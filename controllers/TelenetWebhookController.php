<?php
// Controller para receber o webhook da Telenet
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper;

class TelenetWebhookController {
    private const ENTITY_TYPE_ID = 1054;
    private const CAMPO_PROTOCOLO = 'ufCrm41_1727802471';

    public function executar() {
        header('Content-Type: application/json');
        
        try {
            // 1. Ler e validar JSON
            $input = file_get_contents('php://input');
            $dados = json_decode($input, true);

            if (!$dados) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'JSON inválido ou não enviado']);
                return;
            }

            $protocolo = $dados['protocolo'] ?? null;
            if (!$protocolo) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Campo protocolo obrigatório não informado']);
                return;
            }

            // 2. Buscar deal pelo protocolo
            $filtros = [self::CAMPO_PROTOCOLO => $protocolo];
            $resultadoBusca = BitrixHelper::listarItensCrm(
                self::ENTITY_TYPE_ID, 
                $filtros, 
                ['id', 'title', self::CAMPO_PROTOCOLO]
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
            if (isset($dados['nome_arquivo'])) {
                // Aqui você adicionaria o campo correspondente no Bitrix
                // $camposParaAtualizar['ufCrm41_XXXXXX'] = $dados['nome_arquivo'];
            }
            
            if (isset($dados['mensagem'])) {
                // $camposParaAtualizar['ufCrm41_YYYYYYY'] = $dados['mensagem'];
            }

            // 4. Resposta de sucesso
            echo json_encode([
                'success' => true,
                'deal_id' => $dealId
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}
