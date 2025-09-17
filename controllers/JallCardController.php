<?php

namespace Controllers;

require_once __DIR__ . '/../Jobs/JallCardFetchJob.php';
require_once __DIR__ . '/../Jobs/JallCardLinkJob.php';
require_once __DIR__ . '/../Jobs/JallCardStatusUpdateJob.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Jobs\JallCardFetchJob;
use Jobs\JallCardLinkJob;
use Jobs\JallCardStatusUpdateJob;
use Helpers\LogHelper;
use Exception;

class JallCardController
{
    public function executarFetchJob()
    {
        try {
            $job = new JallCardFetchJob();
            $job->executar();
            echo json_encode(['success' => true, 'message' => 'JallCardFetchJob executado com sucesso.']);
        } catch (Exception $e) {
            LogHelper::logBitrixHelpers("Erro ao executar JallCardFetchJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'error');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao executar JallCardFetchJob: ' . $e->getMessage()]);
        }
    }

    public function executarLinkJob()
    {
        try {
            $job = new JallCardLinkJob();
            $job->executar();
            echo json_encode(['success' => true, 'message' => 'JallCardLinkJob executado com sucesso.']);
        } catch (Exception $e) {
            LogHelper::logBitrixHelpers("Erro ao executar JallCardLinkJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'error');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao executar JallCardLinkJob: ' . $e->getMessage()]);
        }
    }

    public function executarStatusUpdateJob()
    {
        try {
            $job = new JallCardStatusUpdateJob();
            $job->executar();
            echo json_encode(['success' => true, 'message' => 'JallCardStatusUpdateJob executado com sucesso.']);
        } catch (Exception $e) {
            LogHelper::logBitrixHelpers("Erro ao executar JallCardStatusUpdateJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'error');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao executar JallCardStatusUpdateJob: ' . $e->getMessage()]);
        }
    }
}
