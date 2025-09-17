<?php

namespace Jobs;

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;
use PDOException;
use Exception;

class JallCardLinkJob
{
    private $databaseRepository;

    public function __construct()
    {
        $this->databaseRepository = new DatabaseRepository();
    }

    public function executar()
    {
        LogHelper::logBitrixHelpers("Iniciando JallCardLinkJob: Vinculação de pedidos pendentes.", __CLASS__ . '::' . __FUNCTION__);

        try {
            // 1. Obter pedidos pendentes de vinculação na tabela principal (Bitrix)
            $pedidosPendentesBitrix = $this->databaseRepository->getPedidosPendentesVinculacao();

            if (empty($pedidosPendentesBitrix)) {
                LogHelper::logBitrixHelpers("Nenhum pedido pendente de vinculação encontrado na tabela principal.", __CLASS__ . '::' . __FUNCTION__);
                return;
            }

            // 2. Para cada pedido pendente, tentar encontrar um match na tabela temporária da JallCard
            foreach ($pedidosPendentesBitrix as $pedidoBitrix) {
                $idDealBitrix = $pedidoBitrix['id_deal_bitrix'];
                $nomeArquivoTelenet = $pedidoBitrix['nome_arquivo_telenet'];
                $nomeClienteTelenet = $pedidoBitrix['nome_cliente_telenet']; // Usado para desambiguação, se necessário

                // Buscar na tabela temporária por correspondência
                $matchJallCard = $this->databaseRepository->findMatchForBitrixPedido(
                    $nomeClienteTelenet, // Passando o nome do cliente para a lógica de desambiguação
                    $nomeArquivoTelenet
                );

                if ($matchJallCard) {
                    // Vínculo encontrado! Atualizar tabela principal
                    $this->databaseRepository->atualizarVinculacaoJallCard(
                        $idDealBitrix,
                        $matchJallCard['pedido_producao_jallcard'],
                        $matchJallCard['op_jallcard']
                    );
                    // Atualizar status na tabela temporária para evitar reprocessamento
                    $this->databaseRepository->updateVinculacaoJallCardStatusTemp(
                        $matchJallCard['pedido_producao_jallcard'],
                        'VINCULADO_COM_SUCESSO'
                    );
                    LogHelper::logBitrixHelpers("Vínculo estabelecido para Deal ID: {$idDealBitrix} com JallCard PedidoProducao: {$matchJallCard['pedido_producao_jallcard']}.", __CLASS__ . '::' . __FUNCTION__);
                } else {
                    LogHelper::logBitrixHelpers("Nenhum vínculo encontrado na JallCard para Deal ID: {$idDealBitrix} (Cliente: {$nomeClienteTelenet}, Arquivo: {$nomeArquivoTelenet}).", __CLASS__ . '::' . __FUNCTION__);
                    // Opcional: Atualizar status de vinculação na tabela principal para 'ERRO_VINCULACAO' após X tentativas ou um certo período
                }
            }

            LogHelper::logBitrixHelpers("JallCardLinkJob finalizado.", __CLASS__ . '::' . __FUNCTION__);

        } catch (PDOException $e) {
            LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardLinkJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
        } catch (Exception $e) {
            LogHelper::logBitrixHelpers("Erro geral no JallCardLinkJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
        }
    }
}
