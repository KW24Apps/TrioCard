<?php
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

    // LogHelper::logTrioCardGeral("Iniciando JallCardStatusUpdateJob: Atualização de status de pedidos vinculados.", 'JallCardStatusUpdateJob::executar', 'INFO'); // Removido: log positivo não essencial

    // 1. Obter todos os pedidos vinculados da tabela principal
    // (Opcional: filtrar por status_jallcard diferente de 'FINALIZADA' ou 'CANCELADA' para otimizar)
    $pedidosVinculados = $databaseRepository->getPedidosVinculados(); // Este método precisa ser adicionado ao DatabaseRepository
    // LogHelper::logTrioCardGeral("Pedidos vinculados encontrados para atualização de status: " . count($pedidosVinculados) . " itens.", 'JallCardStatusUpdateJob::executar', 'DEBUG'); // Removido: log positivo não essencial

    if (empty($pedidosVinculados)) {
        LogHelper::logTrioCardGeral("Nenhum pedido vinculado encontrado para atualização de status.", 'JallCardStatusUpdateJob::executar', 'INFO');
        exit("Nenhum pedido vinculado encontrado para atualização de status.\n");
    }

    // 2. Para cada pedido vinculado, consultar o status na JallCard e atualizar o banco local e o Bitrix
    foreach ($pedidosVinculados as $pedido) {
        $opJallCard = $pedido['op_jallcard'];
        $idDealBitrix = $pedido['id_deal_bitrix'];
        $statusAtualLocal = $pedido['status_jallcard'] ?? 'INDEFINIDO'; // Status atual no banco local

        // LogHelper::logTrioCardGeral("Processando Deal ID: {$idDealBitrix}, OP JallCard: {$opJallCard}. Status local: {$statusAtualLocal}", 'JallCardStatusUpdateJob::executar', 'DEBUG'); // Removido: log positivo não essencial

        if (empty($opJallCard)) {
            LogHelper::logTrioCardGeral("OP JallCard vazia para Deal ID: {$idDealBitrix}. Ignorando atualização de status.", 'JallCardStatusUpdateJob::executar', 'WARNING');
            continue;
        }

        $ordemProducao = JallCardHelper::getOrdemProducao($opJallCard);
        // LogHelper::logJallCard("Resposta da API JallCard para OP {$opJallCard}: " . json_encode($ordemProducao), 'JallCardStatusUpdateJob::executar', 'DEBUG'); // Removido: log positivo não essencial

        if ($ordemProducao && isset($ordemProducao['status'])) {
            $novoStatusJallCard = $ordemProducao['status'];
            $dataStatus = '';
            $statusDetalhado = '';
            $mensagemStatus = '';
            $commentTimeline = '';
            $idRastreamento = null;
            $transportadora = null;

            // Buscar dados da transportadora e ID de rastreamento o mais cedo possível
            // LogHelper::logJallCard("Buscando documentos para OP {$opJallCard} para ID de rastreamento e transportadora.", 'JallCardStatusUpdateJob::executar', 'DEBUG'); // Removido: log positivo não essencial
            $documentos = JallCardHelper::getDocumentosByOp($opJallCard, true);
            // LogHelper::logJallCard("Resposta da API JallCard /documentos para OP {$opJallCard}: " . json_encode($documentos), 'JallCardStatusUpdateJob::executar', 'DEBUG'); // Removido: log positivo não essencial

            if (!empty($documentos) && isset($documentos[0])) {
                    $doc = $documentos[0];
                    $transportadora = $doc['entregadora'] ?? null;
                    $idRastreamento = $doc['codigoPostagem'] ?? null;
                    // LogHelper::logJallCard("Dados de rastreamento encontrados para OP {$opJallCard}: Transportadora: " . ($transportadora ?? 'N/A') . ", ID: " . ($idRastreamento ?? 'N/A'), 'JallCardStatusUpdateJob::executar', 'DEBUG'); // Removido: log positivo não essencial
                } else {
                    LogHelper::logJallCard("Nenhum documento encontrado para OP {$opJallCard} para buscar ID de rastreamento e transportadora.", 'JallCardStatusUpdateJob::executar', 'WARNING');
                }

            // Determinar o status mais recente e sua data
            if (!empty($ordemProducao['producao']['expedicao'])) {
                $statusDetalhado = 'Expedição';
                $dataStatus = (new DateTime($ordemProducao['producao']['expedicao']))->format('d/m/Y H:i:s');
                
                if ($transportadora && $idRastreamento) {
                    $mensagemStatus = "JallCard: Recolhido pela transportadora"; // Mensagem simplificada para campo retorno
                    $commentTimeline = "JallCard: Status finalizado: {$transportadora} - {$idRastreamento}.\nData: {$dataStatus}";
                } else {
                    $mensagemStatus = "JallCard: Aguardando recolhimento da transportadora"; // Mensagem simplificada para campo retorno
                    $commentTimeline = "JallCard: Status atualizado para 'Expedição'.\nData: {$dataStatus}";
                }

            } elseif (!empty($ordemProducao['producao']['preExpedicao'])) {
                $statusDetalhado = 'Pré-Expedição';
                $dataStatus = (new DateTime($ordemProducao['producao']['preExpedicao']))->format('d/m/Y H:i:s');
                $mensagemStatus = "JallCard: Aguardando recolhimento da transportadora"; // Mensagem simplificada para campo retorno
                $commentTimeline = "JallCard: Aguardando recolhimento da transportadora.\nData: {$dataStatus}";
            } elseif (!empty($ordemProducao['producao']['gravacao'])) {
                $statusDetalhado = 'Gravação';
                $dataStatus = (new DateTime($ordemProducao['producao']['gravacao']))->format('d/m/Y H:i:s');
                $mensagemStatus = "JallCard: Cartões enviados para gravação"; // Mensagem simplificada para campo retorno
                $commentTimeline = "JallCard: Cartões enviados para gravação.\nData: {$dataStatus}";
            } else {
                $statusDetalhado = $novoStatusJallCard; // Usar o status geral se não houver etapas de produção
                $mensagemStatus = "JallCard: Outros status"; // Mensagem simplificada para campo retorno
                $commentTimeline = "JallCard: Status atualizado para '{$statusDetalhado}'.";
            }

            // Verificar se o status mudou antes de atualizar
            if ($novoStatusJallCard !== $statusAtualLocal) {
                // Atualizar status no banco de dados local
                $databaseRepository->atualizarStatusJallCard($opJallCard, $novoStatusJallCard);
                LogHelper::logTrioCardGeral("Status JallCard para OP {$opJallCard} atualizado para '{$novoStatusJallCard}' no banco local.", 'JallCardStatusUpdateJob::executar', 'INFO');

                // Atualizar status no Bitrix (campo retorno)
                $campoRetornoBitrix = $bitrixConfig['mapeamento_campos_jallcard']['campo_retorno_telenet'];
                $camposBitrix = [$campoRetornoBitrix => $mensagemStatus];

                // Adicionar campos de rastreamento e transportadora se encontrados
                $campoNomeTransportadoraBitrix = $bitrixConfig['mapeamento_campos_jallcard']['nome_transportadora'];
                $campoIdRastreamentoBitrix = $bitrixConfig['mapeamento_campos_jallcard']['id_rastreamento'];
                $campoIdRastreamentoDB = 'id_rastreio_transportador'; // Placeholder: Nome do campo no banco de dados local

                if ($transportadora && !empty($campoNomeTransportadoraBitrix)) {
                    $camposBitrix[$campoNomeTransportadoraBitrix] = $transportadora;
                    LogHelper::logBitrix("Adicionando Nome da Transportadora '{$transportadora}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar', 'INFO');
                }
                if ($idRastreamento && !empty($campoIdRastreamentoBitrix)) {
                    $camposBitrix[$campoIdRastreamentoBitrix] = $idRastreamento;
                    LogHelper::logBitrix("Adicionando ID de rastreamento '{$idRastreamento}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar', 'INFO');
                }

                // TODO: Salvar ID de rastreamento no banco de dados local (pedidos_integracao)
                // if ($idRastreamento && !empty($campoIdRastreamentoDB)) {
                //     $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, $campoIdRastreamentoDB, $idRastreamento); // Esta função precisa ser criada no DatabaseRepository
                //     LogHelper::logTrioCardGeral("ID de rastreamento '{$idRastreamento}' salvo no banco de dados local para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar', 'INFO');
                // }

                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal($bitrixConfig['entity_type_id_deal'], $idDealBitrix, $camposBitrix);

                if ($resultadoUpdateBitrix['success']) {
                    LogHelper::logBitrix("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com mensagem de status e/ou rastreamento: '{$mensagemStatus}'.", 'JallCardStatusUpdateJob::executar', 'INFO');
                } else {
                    LogHelper::logBitrix("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24 com status: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJob::executar', 'ERROR');
                }

                // Adicionar comentário na Timeline do Deal
                $entityTypeTimeline = 'dynamic_' . $bitrixConfig['entity_type_id_deal'];
                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, $bitrixConfig['user_id_comments']);

                if ($resultadoCommentBitrix['success']) {
                    LogHelper::logBitrix("Comentário de status adicionado à timeline do Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar', 'INFO');
                } else {
                    LogHelper::logBitrix("Erro ao adicionar comentário de status à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJob::executar', 'ERROR');
                }
            } else {
                // LogHelper::logTrioCardGeral("Status JallCard para OP {$opJallCard} não mudou ('{$novoStatusJallCard}'). Nenhuma atualização no Bitrix.", 'JallCardStatusUpdateJob::executar', 'DEBUG'); // Removido: log positivo não essencial
            }
        } else {
            LogHelper::logJallCard("Não foi possível obter status ou a resposta da API JallCard está incompleta para OP: {$opJallCard}.", 'JallCardStatusUpdateJob::executar', 'ERROR');
        }
    }

    // LogHelper::logTrioCardGeral("JallCardStatusUpdateJob finalizado.", 'JallCardStatusUpdateJob::executar', 'INFO'); // Removido: log positivo não essencial
    exit("JallCardStatusUpdateJob finalizado com sucesso.\n");

} catch (PDOException $e) {
    LogHelper::logTrioCardGeral("Erro de banco de dados no JallCardStatusUpdateJob: " . $e->getMessage(), 'JallCardStatusUpdateJob::executar', 'CRITICAL');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    LogHelper::logTrioCardGeral("Erro geral no JallCardStatusUpdateJob: " . $e->getMessage(), 'JallCardStatusUpdateJob::executar', 'CRITICAL');
    exit("Erro geral: " . $e->getMessage() . "\n");
}
} // Fecha o método executar
} // Fecha a classe JallCardStatusUpdateJob

// Chama o método estático executar
JallCardStatusUpdateJob::executar();
