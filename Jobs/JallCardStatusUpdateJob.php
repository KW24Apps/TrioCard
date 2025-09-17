<?php

namespace Jobs;

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php'; // Para atualizar o Bitrix

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use PDOException;
use Exception;

class JallCardStatusUpdateJob
{
    private $databaseRepository;

    // Configurações do Bitrix (repetido do TelenetWebhookController para independência do Job)
    private const BITRIX_CONFIG = [
        'entity_type_id' => 1042,
        'mapeamento_campos' => [
            'status_jallcard' => 'ufCrm8_STATUSJALLCARD' // Exemplo de campo UF para status JallCard no Bitrix
        ]
    ];

    public function __construct()
    {
        $this->databaseRepository = new DatabaseRepository();
    }

    public function executar()
    {
        LogHelper::logBitrixHelpers("Iniciando JallCardStatusUpdateJob: Atualização de status de pedidos vinculados.", __CLASS__ . '::' . __FUNCTION__);

        try {
            // 1. Obter todos os pedidos vinculados da tabela principal
            // (Opcional: filtrar por status_jallcard diferente de 'FINALIZADA' ou 'CANCELADA' para otimizar)
            $pedidosVinculados = $this->databaseRepository->getPedidosVinculados(); // Este método precisa ser adicionado ao DatabaseRepository

            if (empty($pedidosVinculados)) {
                LogHelper::logBitrixHelpers("Nenhum pedido vinculado encontrado para atualização de status.", __CLASS__ . '::' . __FUNCTION__);
                return;
            }

            // 2. Para cada pedido vinculado, consultar o status na JallCard e atualizar o banco local e o Bitrix
            foreach ($pedidosVinculados as $pedido) {
                $opJallCard = $pedido['op_jallcard'];
                $idDealBitrix = $pedido['id_deal_bitrix'];

                if (empty($opJallCard)) {
                    LogHelper::logBitrixHelpers("OP JallCard vazia para Deal ID: {$idDealBitrix}. Ignorando atualização de status.", __CLASS__ . '::' . __FUNCTION__);
                    continue;
                }

                $ordemProducao = JallCardHelper::getOrdemProducao($opJallCard);

                if ($ordemProducao && isset($ordemProducao['status'])) {
                    $novoStatusJallCard = $ordemProducao['status'];

                    // Atualizar status no banco de dados local
                    $this->databaseRepository->atualizarStatusJallCard($opJallCard, $novoStatusJallCard);
                    LogHelper::logBitrixHelpers("Status JallCard para OP {$opJallCard} atualizado para '{$novoStatusJallCard}' no banco local.", __CLASS__ . '::' . __FUNCTION__);

                    // Atualizar status no Bitrix
                    $campoStatusBitrix = self::BITRIX_CONFIG['mapeamento_campos']['status_jallcard'];
                    if (!empty($campoStatusBitrix)) {
                        BitrixDealHelper::editarDeal(
                            self::BITRIX_CONFIG['entity_type_id'],
                            $idDealBitrix,
                            [$campoStatusBitrix => $novoStatusJallCard]
                        );
                        LogHelper::logBitrixHelpers("Status JallCard para Deal ID: {$idDealBitrix} atualizado para '{$novoStatusJallCard}' no Bitrix.", __CLASS__ . '::' . __FUNCTION__);
                    } else {
                        LogHelper::logBitrixHelpers("Campo de mapeamento para status JallCard no Bitrix não configurado. Não foi possível atualizar o Bitrix para Deal ID: {$idDealBitrix}.", __CLASS__ . '::' . __FUNCTION__);
                    }
                } else {
                    LogHelper::logBitrixHelpers("Não foi possível obter status para OP JallCard: {$opJallCard}.", __CLASS__ . '::' . __FUNCTION__);
                }
            }

            LogHelper::logBitrixHelpers("JallCardStatusUpdateJob finalizado.", __CLASS__ . '::' . __FUNCTION__);

        } catch (PDOException $e) {
            LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardStatusUpdateJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
        } catch (Exception $e) {
            LogHelper::logBitrixHelpers("Erro geral no JallCardStatusUpdateJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
        }
    }
}
