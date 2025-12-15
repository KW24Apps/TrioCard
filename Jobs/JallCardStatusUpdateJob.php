<?php
error_reporting(E_ALL); // Reporta todos os erros
ini_set('display_errors', 1); // Exibe erros no output

date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_STATUS_UPDATER');
}

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php'; // Adicionado para adicionar comentário na timeline

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper; // Adicionado para adicionar comentário na timeline

class JallCardStatusUpdateJob {
    private static $config;

    public static function init() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    public static function executar() {
        self::init(); // Garante que a configuração seja carregada
        $bitrixConfig = self::$config['bitrix'];

        // Gera traceId para toda execução do job
        LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    LogHelper::logTrioCardGeral("Iniciando JallCardStatusUpdateJob: Atualização de status de pedidos vinculados.", __CLASS__ . '::' . __FUNCTION__, 'INFO');

    // 1. Obter todos os pedidos vinculados da tabela principal
    $pedidosVinculados = $databaseRepository->getPedidosVinculados();
    LogHelper::logTrioCardGeral("Pedidos vinculados encontrados para atualização de status: " . count($pedidosVinculados) . " itens.", __CLASS__ . '::' . __FUNCTION__, 'INFO');

    if (empty($pedidosVinculados)) {
        LogHelper::logTrioCardGeral("Nenhum pedido vinculado encontrado para atualização de status.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
        exit("Nenhum pedido vinculado encontrado para atualização de status.\n");
    }

    // 2. Para cada pedido vinculado, consultar o status na JallCard e atualizar o banco local e o Bitrix
    foreach ($pedidosVinculados as $pedido) {
        echo "DEBUG: Entrando no loop foreach para Deal ID: {$pedido['id_deal_bitrix']}\n";
        LogHelper::logJallCard("DEBUG: Entrando no loop foreach para Deal ID: {$pedido['id_deal_bitrix']}", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');

        $opJallCard = $pedido['op_jallcard'];
        $idDealBitrix = $pedido['id_deal_bitrix'];
        $statusAtualLocal = $pedido['status_jallcard'] ?? 'INDEFINIDO'; // Status atual no banco local

        if (empty($opJallCard)) {
            LogHelper::logTrioCardGeral("OP JallCard vazia para Deal ID: {$idDealBitrix}. Ignorando atualização de status.", 'JallCardStatusUpdateJob::executar', 'WARNING');
            echo "DEBUG: OP JallCard vazia para Deal ID: {$idDealBitrix}. Ignorando.\n";
            continue;
        }

        echo "DEBUG: Chamando JallCardHelper::getOrdemProducao para OP: {$opJallCard}\n";
        $ordemProducao = JallCardHelper::getOrdemProducao($opJallCard);
        echo "DEBUG: Retorno de JallCardHelper::getOrdemProducao: " . json_encode($ordemProducao) . "\n";

        if ($ordemProducao && isset($ordemProducao['producao'])) {
            echo "DEBUG: Entrou no bloco 'if (ordemProducao && producao)' para OP: {$opJallCard}\n";
            $dataGravacao = $ordemProducao['producao']['gravacao'] ?? null;
            $dataPreExpedicao = $ordemProducao['producao']['preExpedicao'] ?? null;
            $dataExpedicao = $ordemProducao['producao']['expedicao'] ?? null;

            $statusDetalhado = '';
            $dataStatus = '';
            $mensagemStatus = '';
            $commentTimeline = '';
            $novoStatusParaDB = ''; // O status que será salvo no DB

            $idRastreamento = null;
            $transportadora = null;

            // Determinar o status mais recente e sua data com base na prioridade
            if (!empty($dataExpedicao)) {
                $statusDetalhado = 'Expedição';
                $dataStatus = (new DateTime($dataExpedicao))->format('d/m/Y H:i:s');
                $novoStatusParaDB = 'EXPEDICAO';
            } elseif (!empty($dataPreExpedicao)) {
                $statusDetalhado = 'Pré-Expedição';
                $dataStatus = (new DateTime($dataPreExpedicao))->format('d/m/Y H:i:s');
                $novoStatusParaDB = 'PRE_EXPEDICAO';
            } elseif (!empty($dataGravacao)) {
                $statusDetalhado = 'Gravação';
                $dataStatus = (new DateTime($dataGravacao))->format('d/m/Y H:i:s');
                $novoStatusParaDB = 'GRAVACAO';
            } else {
                // Se nenhuma das datas de produção estiver preenchida, usar o status geral da JallCard
                $statusDetalhado = $ordemProducao['status'] ?? 'INDEFINIDO';
                $novoStatusParaDB = $ordemProducao['status'] ?? 'INDEFINIDO';
            }

            LogHelper::logJallCard("DEBUG: OP {$opJallCard}, Status determinado: {$novoStatusParaDB}", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');

            // Buscar dados da transportadora e ID de rastreamento SOMENTE se o status for Expedição ou FINALIZADA
            if (in_array($novoStatusParaDB, ['EXPEDICAO', 'FINALIZADA'])) {
                LogHelper::logJallCard("DEBUG: Status é Expedição ou Finalizada. Consultando documentos para OP {$opJallCard}.", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
                $documentos = JallCardHelper::getDocumentosByOp($opJallCard, true);
                if (!empty($documentos) && isset($documentos[0])) {
                    $doc = $documentos[0];
                    $transportadora = $doc['entregadora'] ?? null;
                    $idRastreamento = $doc['codigoPostagem'] ?? null;
                    LogHelper::logJallCard("DEBUG: Documentos encontrados. Transportadora: {$transportadora}, ID Rastreamento: {$idRastreamento}", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
                } else {
                    LogHelper::logJallCard("Nenhum documento encontrado para OP {$opJallCard} para buscar ID de rastreamento e transportadora (Status: {$novoStatusParaDB}).", 'JallCardStatusUpdateJob::executar', 'WARNING');
                }
            } else {
                LogHelper::logJallCard("DEBUG: Status não é Expedição nem Finalizada ({$novoStatusParaDB}). Não consultando documentos para OP {$opJallCard}.", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
            }

            // Definir mensagem de status e comentário da timeline após a possível busca de rastreamento
            $mensagemStatus = '';
            $commentTimeline = '';

            if ($novoStatusParaDB === 'EXPEDICAO') {
                if ($transportadora && $idRastreamento) {
                    $mensagemStatus = "JallCard: Recolhido pela transportadora";
                    $commentTimeline = "JallCard: Status finalizado: {$transportadora} - {$idRastreamento}.\nData: {$dataStatus}";
                } else {
                    $mensagemStatus = "JallCard: Aguardando recolhimento da transportadora";
                    $commentTimeline = "JallCard: Status atualizado para 'Expedição'.\nData: {$dataStatus}";
                }
            } elseif ($novoStatusParaDB === 'PRE_EXPEDICAO') {
                $mensagemStatus = "JallCard: Aguardando recolhimento da transportadora";
                $commentTimeline = "JallCard: Aguardando recolhimento da transportadora.\nData: {$dataStatus}";
            } elseif ($novoStatusParaDB === 'GRAVACAO') {
                $mensagemStatus = "JallCard: Cartões enviados para gravação";
                $commentTimeline = "JallCard: Cartões enviados para gravação.\nData: {$dataStatus}";
            } else {
                $mensagemStatus = "JallCard: Status atualizado para '{$statusDetalhado}'";
                $commentTimeline = "JallCard: Status atualizado para '{$statusDetalhado}'.";
            }

            // Verificar se o status determinado (novoStatusParaDB) é diferente do status atual local
            // E se o novo status é um "avanço" em relação ao status atual local
            $statusOrder = ['INDEFINIDO' => 0, 'ABERTA' => 1, 'GRAVACAO' => 2, 'PRE_EXPEDICAO' => 3, 'EXPEDICAO' => 4, 'FINALIZADA' => 5, 'CANCELADA' => 6];

            $currentStatusOrder = $statusOrder[$statusAtualLocal] ?? 0;
            $newStatusOrder = $statusOrder[$novoStatusParaDB] ?? 0;

            if ($novoStatusParaDB !== $statusAtualLocal && $newStatusOrder > $currentStatusOrder) {
                // Atualizar status no banco de dados local
                $databaseRepository->atualizarStatusJallCard($opJallCard, $novoStatusParaDB);
                LogHelper::logTrioCardGeral("Status JallCard para OP {$opJallCard} atualizado para '{$novoStatusParaDB}' no banco local (anterior: '{$statusAtualLocal}').", 'JallCardStatusUpdateJob::executar', 'INFO');

                // Atualizar status no Bitrix (campo retorno)
                $campoRetornoBitrix = $bitrixConfig['mapeamento_campos_jallcard']['campo_retorno_telenet'];
                $camposBitrix = [$campoRetornoBitrix => $mensagemStatus];

                // Adicionar campos de rastreamento e transportadora se encontrados
                $campoNomeTransportadoraBitrix = $bitrixConfig['mapeamento_campos_jallcard']['nome_transportadora'];
                $campoIdRastreamentoBitrix = $bitrixConfig['mapeamento_campos_jallcard']['id_rastreamento'];

                if ($transportadora && !empty($campoNomeTransportadoraBitrix)) {
                    $camposBitrix[$campoNomeTransportadoraBitrix] = $transportadora;
                    LogHelper::logBitrix("Adicionando Nome da Transportadora '{$transportadora}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar', 'INFO');
                }
                if ($idRastreamento && !empty($campoIdRastreamentoBitrix)) {
                    $camposBitrix[$campoIdRastreamentoBitrix] = $idRastreamento;
                    LogHelper::logBitrix("Adicionando ID de rastreamento '{$idRastreamento}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar', 'INFO');
                }

                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal($bitrixConfig['entity_type_id_deal'], $idDealBitrix, $camposBitrix);

                if ($resultadoUpdateBitrix['success']) {
                    LogHelper::logBitrix("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com mensagem de status e/ou rastreamento: '{$mensagemStatus}'.", 'JallCardStatusUpdateJob::executar', 'INFO');
                } else {
                    LogHelper::logBitrix("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24 com status: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJob::executar', 'ERROR');
                }

                // Salvar id_rastreamento no banco de dados local (pedidos_integracao)
                // Verifica se o idRastreamento foi obtido e se é diferente do que já está no banco (ou se o campo no banco está vazio/nulo)
                $idRastreamentoNoBanco = $pedido['id_rastreio_transportador'] ?? null;
                if ($idRastreamento && ($idRastreamentoNoBanco === null || $idRastreamentoNoBanco === '' || $idRastreamentoNoBanco !== $idRastreamento)) {
                    $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, 'id_rastreio_transportador', $idRastreamento);
                    LogHelper::logTrioCardGeral("ID de rastreamento '{$idRastreamento}' salvo/atualizado no banco local para Deal ID: {$idDealBitrix}.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                }
                // O campo 'transportadora_rastreio' não será salvo conforme decisão anterior.

                // Adicionar comentário na Timeline do Deal
                $entityTypeTimeline = 'dynamic_' . $bitrixConfig['entity_type_id_deal'];
                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, $bitrixConfig['user_id_comments']);

                if ($resultadoCommentBitrix['success']) {
                    LogHelper::logBitrix("Comentário de status adicionado à timeline do Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar', 'INFO');
                } else {
                    LogHelper::logBitrix("Erro ao adicionar comentário de status à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJob::executar', 'ERROR');
                }
            } else {
                LogHelper::logTrioCardGeral("Status JallCard para OP {$opJallCard} não avançou ou não mudou (API: '{$novoStatusParaDB}', Local: '{$statusAtualLocal}'). Nenhuma atualização no Bitrix.", 'JallCardStatusUpdateJob::executar', 'DEBUG');
            }
        }
        echo "DEBUG: Saindo do loop foreach para Deal ID: {$pedido['id_deal_bitrix']}\n";
    } // Fim do foreach

    LogHelper::logTrioCardGeral("JallCardStatusUpdateJob finalizado.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
    exit("JallCardStatusUpdateJob finalizado com sucesso.\n");

} catch (PDOException $e) {
    LogHelper::logTrioCardGeral("Erro de banco de dados no JallCardStatusUpdateJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'CRITICAL');
    echo "ERRO CRÍTICO (PDO): " . $e->getMessage() . "\n"; // Adicionado echo para erros críticos
    exit("Erro de banco de dados: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    LogHelper::logTrioCardGeral("Erro geral no JallCardStatusUpdateJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'CRITICAL');
    echo "ERRO CRÍTICO (GERAL): " . $e->getMessage() . "\n"; // Adicionado echo para erros críticos
    exit("Erro geral: " . $e->getMessage() . "\n");
}
} // Fecha o método executar
} // Fecha a classe JallCardStatusUpdateJob

// Chama o método estático executar
JallCardStatusUpdateJob::executar();
