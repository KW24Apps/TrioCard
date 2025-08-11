<?php
// Controller para webhook FlashPegasus - Consulta de entregas
require_once __DIR__ . '/../helpers/FlashPegasusHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\FlashPegasusHelper;
use Helpers\LogHelper;

class FlashWebhookController {
    
    // Método principal que executa a lógica do webhook (orquestrador)
    public function executar() {
        // Gerar trace ID único para rastreamento
        LogHelper::gerarTraceId();
        
        header('Content-Type: application/json');
        
        try {
            // 1. Ler e validar JSON da requisição
            $dados = json_decode(file_get_contents('php://input'), true);

            if (!$dados) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'JSON inválido ou não enviado']);
                return;
            }

            // 2. Decidir se é consulta individual ou em lote baseado na estrutura
            if (isset($dados['deliveries']) && is_array($dados['deliveries'])) {
                // Consulta em lote
                $this->consultarLote($dados);
            } else {
                // Consulta individual
                $this->consultarIndividual($dados);
            }

        } catch (Exception $e) {
            // Log do erro
            LogHelper::logFlashPegasus(
                "Erro no webhook: " . $e->getMessage(), 
                __CLASS__ . '::' . __FUNCTION__
            );

            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'Erro interno do servidor'
            ]);
        }
    }

    // Método privado para consulta individual
    private function consultarIndividual($dados) {
        // Validar campos obrigatórios
        $camposObrigatorios = ['clienteId', 'cttId', 'numEncCli'];
        foreach ($camposObrigatorios as $campo) {
            if (empty($dados[$campo])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => "Campo obrigatório '$campo' não informado"
                ]);
                return;
            }
        }

        $clienteId = $dados['clienteId'];
        $cttId = $dados['cttId'];
        $numEncCli = $dados['numEncCli'];

        LogHelper::logFlashPegasus(
            "Consulta individual iniciada - ClienteId: $clienteId, CttId: $cttId, NumEncCli: $numEncCli", 
            __CLASS__ . '::' . __FUNCTION__
        );

        // Consultar entrega na API FlashPegasus
        $resultado = FlashPegasusHelper::consultarEntrega($clienteId, $cttId, $numEncCli);

        if (!$resultado) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'Falha na comunicação com API FlashPegasus'
            ]);
            return;
        }

        // Verificar se houve erro na API
        if (isset($resultado['error'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Erro FlashPegasus: ' . $resultado['error'],
                'details' => $resultado
            ]);
            return;
        }

        // Retornar dados da entrega
        LogHelper::logFlashPegasus(
            "Consulta individual realizada com sucesso - ClienteId: $clienteId", 
            __CLASS__ . '::' . __FUNCTION__
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $resultado,
            'request' => [
                'clienteId' => $clienteId,
                'cttId' => $cttId,
                'numEncCli' => $numEncCli
            ]
        ]);
    }

    // Método privado para consulta em lote (múltiplas entregas)
    private function consultarLote($dados) {
        if (!isset($dados['deliveries']) || !is_array($dados['deliveries'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Campo "deliveries" obrigatório deve ser um array'
            ]);
            return;
        }

        $entregas = $dados['deliveries'];
        
        // Validar estrutura de cada entrega
        foreach ($entregas as $index => $entrega) {
            $camposObrigatorios = ['clienteId', 'cttId', 'numEncCli'];
            foreach ($camposObrigatorios as $campo) {
                if (empty($entrega[$campo])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false, 
                        'error' => "Campo '$campo' obrigatório na entrega índice $index"
                    ]);
                    return;
                }
            }
        }

        LogHelper::logFlashPegasus(
            "Consulta em lote iniciada - Total: " . count($entregas), 
            __CLASS__ . '::' . __FUNCTION__
        );

        // Consultar no FlashPegasus
        $resultado = FlashPegasusHelper::consultarEntregasLote($entregas);

        if (!$resultado) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'Falha na comunicação com API FlashPegasus'
            ]);
            return;
        }

        if (isset($resultado['error'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Erro FlashPegasus: ' . $resultado['error'],
                'details' => $resultado
            ]);
            return;
        }

        LogHelper::logFlashPegasus(
            "Consulta em lote realizada com sucesso", 
            __CLASS__ . '::' . __FUNCTION__
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $resultado,
            'request' => [
                'total_deliveries' => count($entregas)
            ]
        ]);
    }
}
