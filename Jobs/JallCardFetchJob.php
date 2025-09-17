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

class JallCardFetchJob
{
    private $databaseRepository;

    public function __construct()
    {
        $this->databaseRepository = new DatabaseRepository();
    }

    public function executar()
    {
        LogHelper::logBitrixHelpers("Iniciando JallCardFetchJob: Coleta de pedidos da JallCard.", __CLASS__ . '::' . __FUNCTION__);

        try {
            // 1. Coletar dados da JallCard (últimos 7 dias)
            $pedidosJallCardRaw = JallCardHelper::getArquivosProcessadosUltimos7Dias();
            
            if (empty($pedidosJallCardRaw)) {
                LogHelper::logBitrixHelpers("Nenhum arquivo processado encontrado na JallCard nos últimos 7 dias.", __CLASS__ . '::' . __FUNCTION__);
                return;
            }

            // 2. Processar cada pedido da JallCard e salvar/atualizar na tabela vinculacao_jallcard
            foreach ($pedidosJallCardRaw as $pedidoJallCardItem) {
                $pedidoProducaoJallCard = $pedidoJallCardItem['pedidoProducao'];

                // Verificar se este pedidoProducao já foi processado na tabela temporária
                if ($this->databaseRepository->findVinculacaoJallCardByPedidoProducao($pedidoProducaoJallCard)) {
                    LogHelper::logBitrixHelpers("PedidoProducao {$pedidoProducaoJallCard} já processado na tabela temporária. Ignorando.", __CLASS__ . '::' . __FUNCTION__);
                    continue;
                }

                // Obter detalhes do pedido (OP e nomes de arquivos)
                $detalhesPedido = JallCardHelper::getPedidoProducao($pedidoProducaoJallCard);

                if (!$detalhesPedido || empty($detalhesPedido['ops'])) {
                    LogHelper::logBitrixHelpers("Não foi possível obter detalhes ou OP para PedidoProducao {$pedidoProducaoJallCard}.", __CLASS__ . '::' . __FUNCTION__);
                    continue;
                }

                $opJallCard = $detalhesPedido['ops'][0]; // Assumindo uma única OP por pedido

                $nomeArquivoOriginal = null;
                $nomeArquivoConvertido = null;

                foreach ($detalhesPedido['arquivos'] as $arquivo) {
                    if (str_ends_with($arquivo['nome'], '.TXT.ICS')) {
                        $nomeArquivoOriginal = $arquivo['nome'];
                    } elseif (str_ends_with($arquivo['nome'], '.env.fpl')) {
                        $nomeArquivoConvertido = $arquivo['nome'];
                    }
                }

                // Inserir na tabela temporária
                $this->databaseRepository->inserirVinculacaoJallCard([
                    'pedido_producao_jallcard' => $pedidoProducaoJallCard,
                    'op_jallcard' => $opJallCard,
                    'nome_arquivo_original_jallcard' => $nomeArquivoOriginal,
                    'nome_arquivo_convertido_jallcard' => $nomeArquivoConvertido,
                    'data_processamento_jallcard' => $pedidoJallCardItem['dataProcessamento']
                ]);
                LogHelper::logBitrixHelpers("PedidoProducao {$pedidoProducaoJallCard} inserido na tabela vinculacao_jallcard.", __CLASS__ . '::' . __FUNCTION__);
            }

            LogHelper::logBitrixHelpers("JallCardFetchJob finalizado.", __CLASS__ . '::' . __FUNCTION__);

        } catch (PDOException $e) {
            LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardFetchJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
        } catch (Exception $e) {
            LogHelper::logBitrixHelpers("Erro geral no JallCardFetchJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__);
        }
    }
}
