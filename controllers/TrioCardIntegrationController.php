<?php
// Controller integrado: Telenet → JallCard → Flash
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/FlashPegasusHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use Helpers\JallCardHelper;
use Helpers\FlashPegasusHelper;
use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper;

class TrioCardIntegrationController {
    
    // Configurações específicas do TrioCard
    private const BITRIX_CONFIG = [
        'entity_type_id' => 1054,
        'category' => 195,
        'campos' => [
            'protocolo' => 'ufCrm41_1727802471',
            'status_jallcard' => 'ufCrm41_1742081800', // Novo campo para status JallCard
            'ordem_producao' => 'ufCrm41_1742081801',  // Novo campo para código da ordem
            'codigo_expedicao' => 'ufCrm41_1742081802', // Novo campo para expedição
            'status_flash' => 'ufCrm41_1742081803'      // Novo campo para status Flash
        ]
    ];
    
    /**
     * Método principal: processa webhook Telenet e inicia fluxo integrado
     */
    public function processarWebhookTelenet() {
        header('Content-Type: application/json');
        
        try {
            // 1. Processar webhook Telenet (código existente)
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (!$dados || !isset($dados['protocolo'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Protocolo obrigatório']);
                return;
            }
            
            $protocolo = $dados['protocolo'];
            $mensagem = $dados['mensagem'] ?? '';
            
            // 2. Processar diferentes tipos de mensagem
            switch ($mensagem) {
                case 'Arquivo gerado':
                    $this->iniciarFluxoProducao($protocolo, $dados);
                    break;
                    
                case 'Arquivo retornado':
                    $this->verificarStatusProducao($protocolo);
                    break;
                    
                default:
                    $this->atualizarBitrixApenas($protocolo, $dados);
                    break;
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Fluxo 1: Arquivo gerado → Iniciar monitoramento JallCard
     */
    private function iniciarFluxoProducao($protocolo, $dados) {
        // 1. Atualizar/criar deal no Bitrix
        $dealId = $this->atualizarBitrixApenas($protocolo, $dados);
        
        // 2. Buscar informações na JallCard
        $dadosJallCard = $this->consultarJallCard($protocolo);
        
        if ($dadosJallCard && !isset($dadosJallCard['error'])) {
            // 3. Atualizar Bitrix com dados da JallCard
            $this->atualizarBitrixComJallCard($dealId, $dadosJallCard);
            
            // 4. Se produção finalizada, consultar Flash
            if ($this->isProducaoFinalizada($dadosJallCard)) {
                $this->consultarFlashParaEntrega($dealId, $dadosJallCard);
            }
        }
        
        echo json_encode([
            'success' => true,
            'protocolo' => $protocolo,
            'fluxo' => 'producao_iniciada',
            'jallcard_status' => $dadosJallCard['status'] ?? 'pendente'
        ]);
    }
    
    /**
     * Fluxo 2: Verificar status de produção existente
     */
    private function verificarStatusProducao($protocolo) {
        // 1. Buscar deal no Bitrix
        $deal = $this->buscarDealPorProtocolo($protocolo);
        
        if (!$deal) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Deal não encontrado']);
            return;
        }
        
        // 2. Consultar JallCard novamente
        $dadosJallCard = $this->consultarJallCard($protocolo);
        
        if ($dadosJallCard && !isset($dadosJallCard['error'])) {
            // 3. Atualizar status
            $this->atualizarBitrixComJallCard($deal['ID'], $dadosJallCard);
            
            // 4. Se finalizada agora, consultar Flash
            if ($this->isProducaoFinalizada($dadosJallCard)) {
                $this->consultarFlashParaEntrega($deal['ID'], $dadosJallCard);
            }
        }
        
        echo json_encode([
            'success' => true,
            'protocolo' => $protocolo,
            'fluxo' => 'status_verificado',
            'deal_id' => $deal['ID']
        ]);
    }
    
    /**
     * Consulta JallCard baseado no protocolo Telenet
     */
    private function consultarJallCard($protocolo) {
        // Configurar ambiente (mudar para 'homologacao' quando disponível)
        JallCardHelper::configurarAmbiente('producao');
        
        // Buscar dados relacionados ao protocolo
        return JallCardHelper::buscarDadosParaFlash($protocolo);
    }
    
    /**
     * Verifica se produção foi finalizada
     */
    private function isProducaoFinalizada($dadosJallCard) {
        if (isset($dadosJallCard['ordem_codigo'])) {
            $ordem = JallCardHelper::consultarOrdemPorCodigo($dadosJallCard['ordem_codigo']);
            return $ordem && ($ordem['status'] === 'FINALIZADA');
        }
        return false;
    }
    
    /**
     * Consulta Flash quando produção finalizada
     */
    private function consultarFlashParaEntrega($dealId, $dadosJallCard) {
        if (isset($dadosJallCard['dados_flash'])) {
            $codigoExpedicao = $dadosJallCard['dados_flash']['codigo_expedicao'];
            
            // Aqui você implementaria a lógica para consultar Flash
            // Baseado no FlashWebhookController existente
            
            // Por enquanto, apenas atualizar Bitrix com código de expedição
            $this->atualizarBitrixComFlash($dealId, [
                'codigo_expedicao' => $codigoExpedicao,
                'entregadora' => $dadosJallCard['dados_flash']['entregadora'],
                'status' => 'EM_TRANSITO'
            ]);
        }
    }
    
    /**
     * Atualiza Bitrix com dados da JallCard
     */
    private function atualizarBitrixComJallCard($dealId, $dadosJallCard) {
        $campos = [];
        
        if (isset($dadosJallCard['ordem_codigo'])) {
            $campos[self::BITRIX_CONFIG['campos']['ordem_producao']] = $dadosJallCard['ordem_codigo'];
        }
        
        if (isset($dadosJallCard['expedicao']['codigo'])) {
            $campos[self::BITRIX_CONFIG['campos']['codigo_expedicao']] = $dadosJallCard['expedicao']['codigo'];
        }
        
        $campos[self::BITRIX_CONFIG['campos']['status_jallcard']] = 'PROCESSADO';
        
        if (!empty($campos)) {
            BitrixDealHelper::editarDeal(
                self::BITRIX_CONFIG['entity_type_id'],
                $dealId,
                $campos
            );
        }
    }
    
    /**
     * Atualiza Bitrix com dados da Flash
     */
    private function atualizarBitrixComFlash($dealId, $dadosFlash) {
        $campos = [
            self::BITRIX_CONFIG['campos']['status_flash'] => $dadosFlash['status']
        ];
        
        BitrixDealHelper::editarDeal(
            self::BITRIX_CONFIG['entity_type_id'],
            $dealId,
            $campos
        );
    }
    
    /**
     * Busca deal por protocolo
     */
    private function buscarDealPorProtocolo($protocolo) {
        $filtros = [self::BITRIX_CONFIG['campos']['protocolo'] => $protocolo];
        
        $resultado = BitrixHelper::listarItensCrm(
            self::BITRIX_CONFIG['entity_type_id'],
            $filtros,
            ['id'],
            1
        );
        
        return $resultado ? $resultado[0] : null;
    }
    
    /**
     * Atualizar apenas Bitrix (comportamento original)
     */
    private function atualizarBitrixApenas($protocolo, $dados) {
        // Implementação igual ao TelenetWebhookController original
        $campoProtocolo = self::BITRIX_CONFIG['campos']['protocolo'];
        $filtros = [$campoProtocolo => $protocolo];
        
        $resultadoBusca = BitrixHelper::listarItensCrm(
            self::BITRIX_CONFIG['entity_type_id'],
            $filtros,
            ['id', $campoProtocolo],
            1
        );
        
        if ($resultadoBusca && !empty($resultadoBusca)) {
            // Atualizar deal existente
            $dealId = $resultadoBusca[0]['ID'];
            
            $camposParaAtualizar = [];
            $camposAtualizacao = ['nome_arquivo', 'data_retorno_solicitacao', 'mensagem'];
            
            foreach ($camposAtualizacao as $campo) {
                if (isset($dados[$campo]) && $dados[$campo] !== '') {
                    // Mapear campos conforme TelenetWebhookController
                    $camposParaAtualizar['uf' . $campo] = $dados[$campo];
                }
            }
            
            if (!empty($camposParaAtualizar)) {
                BitrixDealHelper::editarDeal(
                    self::BITRIX_CONFIG['entity_type_id'],
                    $dealId,
                    $camposParaAtualizar
                );
            }
            
            return $dealId;
        } else {
            // Criar novo deal
            $camposParaCriar = [];
            foreach ($dados as $campo => $valor) {
                if ($campo !== 'data_retorno_solicitacao' && $valor !== '') {
                    $camposParaCriar['uf' . $campo] = $valor;
                }
            }
            
            $resultado = BitrixDealHelper::criarDeal(
                self::BITRIX_CONFIG['entity_type_id'],
                self::BITRIX_CONFIG['category'],
                $camposParaCriar
            );
            
            return $resultado['success'] ? $resultado['id'] : null;
        }
    }
    
    /**
     * Endpoint para consulta manual do status
     */
    public function consultarStatusManual() {
        $protocolo = $_GET['protocolo'] ?? null;
        
        if (!$protocolo) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Protocolo obrigatório']);
            return;
        }
        
        $deal = $this->buscarDealPorProtocolo($protocolo);
        $dadosJallCard = $this->consultarJallCard($protocolo);
        
        echo json_encode([
            'success' => true,
            'protocolo' => $protocolo,
            'deal_bitrix' => $deal,
            'dados_jallcard' => $dadosJallCard,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
?>
