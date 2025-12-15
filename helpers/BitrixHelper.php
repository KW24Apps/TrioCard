<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;

class BitrixHelper
{
    private static $config;

    public static function init() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    // Envia requisição para API Bitrix com endpoint e parâmetros fornecidos
    public static function chamarApi($endpoint, $params, $opcoes = [])
    {
        self::init();
        $bitrixConfig = self::$config['bitrix'];
        $webhookBase = trim($bitrixConfig['webhook_base']);

        if (!$webhookBase) {
            LogHelper::logBitrix("Webhook não informado para chamada do endpoint: $endpoint", __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            return ['error' => 'Webhook não informado'];
        }

        $url = $webhookBase . '/' . $endpoint . '.json';
        $postData = http_build_query($params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $resposta = curl_exec($ch);
        $curlErro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respostaJson = json_decode($resposta, true);
         
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $resumo = "[$traceId] Endpoint: $endpoint | HTTP: $httpCode | Erro: $curlErro";
        
        if (!empty($respostaJson['error_description'])) {
            $resumo .= " | Descrição: " . $respostaJson['error_description'];
        }

        LogHelper::logBitrix($resumo, __CLASS__ . '::' . __FUNCTION__, 'DEBUG');

        return $respostaJson;
    }

    // Consulta os campos de uma entidade CRM (SPA, Deals, etc.) no Bitrix24
    public static function consultarCamposSpa($entityTypeId)
    {
        // Para deals, usa endpoint específico
        if ($entityTypeId === 'crm.deal.fields') {
            $respostaApi = BitrixHelper::chamarApi('crm.deal.fields', []);
            return $respostaApi['result'] ?? [];
        }
        
        // Para SPAs, usa endpoint genérico
        $params = [
            'entityTypeId' => $entityTypeId,
        ];

        $respostaApi = BitrixHelper::chamarApi('crm.item.fields', $params);
        return $respostaApi['result']['fields'] ?? [];
    }
    
    // Formata os campos conforme o padrão esperado pelo Bitrix (camelCase)
    public static function formatarCampos($dados)
    {
        $fields = [];

        foreach ($dados as $campo => $valor) {
            // Se já está no padrão camelCase (ufCrm_ ou ufCrmXX_), não altera
            if (preg_match('/^ufCrm(\d+_)?\d+$/', $campo)) {
                $fields[$campo] = $valor;
                continue;
            }

            // Normaliza prefixos quebrados, aceita ufcrm_, uf_crm_, UF_CRM_...
            $campoNormalizado = strtoupper(str_replace(['ufcrm_', 'uf_crm_'], 'UF_CRM_', $campo));

            // SPA: UF_CRM_XX_YYYYYYY (XX = qualquer número de SPA, YYYYYYY = campo)
            if (preg_match('/^UF_CRM_(\d+)_([0-9]+)$/', $campoNormalizado, $m)) {
                $chaveConvertida = 'ufCrm' . $m[1] . '_' . $m[2];
                $fields[$chaveConvertida] = $valor;
            }
            // DEAL: UF_CRM_YYYYYYY
            elseif (preg_match('/^UF_CRM_([0-9]+)$/', $campoNormalizado, $m)) {
                $chaveConvertida = 'ufCrm_' . $m[1];
                $fields[$chaveConvertida] = $valor;
            }
            // Se não bate nenhum padrão, mantém como veio
            else {
                $fields[$campo] = $valor;
            }
        }

        return $fields;
    }
  
    // Mapeia valores enumerados de campos UF_CRM_* para seus textos correspondentes
    public static function mapearValoresEnumerados($dados, $fields)
    {
        foreach ($fields as $uf => $definicaoCampo) {
            if (!isset($dados[$uf])) {
                continue;
            }
            if (isset($definicaoCampo['type']) && $definicaoCampo['type'] === 'enumeration' && isset($definicaoCampo['items'])) {
                // Monta o mapa ID => VALUE para esse campo
                $mapa = [];
                foreach ($definicaoCampo['items'] as $item) {
                    $mapa[$item['ID']] = $item['VALUE'];
                }
                // Troca os valores numéricos por textos
                if (is_array($dados[$uf])) {
                    $dados[$uf] = array_map(function($v) use ($mapa) {
                        return $mapa[$v] ?? $v;
                    }, $dados[$uf]);
                } else {
                    $dados[$uf] = $mapa[$dados[$uf]] ?? $dados[$uf];
                }
            }
        }
        return $dados;
    }

    // Consulta as etapas de um tipo de entidade no Bitrix24 (usando crm.status.list para deals)
    public static function consultarEtapasPorTipo($entityTypeId)
    {
        $params = [
            'entityId' => $entityTypeId
        ];
        $resposta = BitrixHelper::chamarApi('crm.status.list', $params, []);
        return $resposta['result'] ?? [];
    }

    // Retorna o nome amigável da etapa a partir do ID e do array de etapas
    public static function mapearEtapaPorId($stageId, $stages)
    {
        foreach ($stages as $stage) {
            if (
                (isset($stage['ID']) && $stage['ID'] == $stageId) ||
                (isset($stage['STATUS_ID']) && $stage['STATUS_ID'] == $stageId) ||
                (isset($stage['statusId']) && $stage['statusId'] == $stageId) ||
                (isset($stage['id']) && $stage['id'] == $stageId)
            ) {
                return $stage['NAME'] ?? $stage['name'] ?? $stageId;
            }
        }
        return $stageId; // Se não encontrar, retorna o próprio ID
    }

    // Lista todos os itens de uma entidade CRM (genérico para Company, Deal, SPA, Contact) com paginação
    public static function listarItensCrm($entityTypeId, $filtros = [], $campos = ['*'], $limite = null)
    {
        $todosItens = [];
        $start = 0;
        $totalGeral = 0;
        $paginaAtual = 1;

        do {
            $params = [
                'entityTypeId' => $entityTypeId,
                'select' => $campos,
                'filter' => $filtros,
                'start' => $start
            ];

            $resultado = self::chamarApi('crm.item.list', $params, [
                'log' => true
            ]);

            if (!isset($resultado['result']['items'])) {
                return [
                    'success' => false,
                    'debug' => $resultado,
                    'error' => $resultado['error_description'] ?? 'Erro desconhecido ao listar itens CRM.'
                ];
            }

            $itensPagina = $resultado['result']['items'];
            $totalPagina = count($itensPagina);
            
            // Adiciona os itens desta página ao array total
            $todosItens = array_merge($todosItens, $itensPagina);
            $totalGeral += $totalPagina;

            // Verifica se há próxima página - o 'next' vem diretamente no resultado
            $temProximaPagina = isset($resultado['next']) && $resultado['next'] > 0;
            
            // Se há limite definido e já atingiu, para
            if ($limite && $totalGeral >= $limite) {
                $todosItens = array_slice($todosItens, 0, $limite);
                break;
            }

            // Prepara próxima página
            $start += 50; // Bitrix sempre retorna 50 por página
            $paginaAtual++;

            } while ($temProximaPagina && $totalPagina === 50); // Para quando não há mais páginas ou página incompleta

        return [
            'success' => true,
            'items' => $todosItens,
            'total' => count($todosItens),
            'paginas_processadas' => $paginaAtual
        ];
    }

    public static function adicionarComentarioTimeline(string $entityType, int $entityId, string $comment, ?int $authorId = null)
    {
        // Se authorId não for passado, tenta buscar da global
        if (!$authorId) {
            $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
            $configJson = $configExtra ? json_decode($configExtra, true) : [];
            
            if (!empty($configJson)) {
                $firstSpaKey = array_key_first($configJson);
                $authorId = $configJson[$firstSpaKey]['bitrix_user_id_comments'] ?? null;
            }
        }

        $params = [
            'fields' => [
                'ENTITY_ID' => $entityId,
                'ENTITY_TYPE' => $entityType,
                'COMMENT' => $comment
            ]
        ];

        if ($authorId) {
            $params['fields']['AUTHOR_ID'] = (int)$authorId;
        }

        $resultado = self::chamarApi('crm.timeline.comment.add', $params, ['log' => false]);

        // Log apenas em caso de erro
        if (!isset($resultado['result']) || empty($resultado['result'])) {
            LogHelper::logBitrix(
                "FALHA AO ADICIONAR COMENTÁRIO - EntityID: $entityId, EntityType: $entityType - Erro: " . json_encode($resultado, JSON_UNESCAPED_UNICODE),
                __CLASS__ . '::' . __FUNCTION__,
                'ERROR'
            );
            return ['success' => false, 'error' => $resultado['error_description'] ?? 'Erro desconhecido'];
        }

        return ['success' => true, 'result' => $resultado['result']];
    }

    /**
     * Adiciona múltiplos comentários na timeline de entidades do Bitrix24 em lote.
     *
     * @param array $commentsData Um array de arrays, onde cada item contém:
     *                            'entityType' (string, ex: 'deal', 'company', 'dynamic_191'),
     *                            'entityId' (int, o ID da entidade),
     *                            'comment' (string, o texto do comentário),
     *                            'authorId' (int|null, o ID do autor do comentário, opcional).
     * @param int $tamanhoLote O número de operações por lote na API batch.
     * @return array O resultado da operação em lote.
     */
    public static function adicionarComentariosTimelineEmLote(array $commentsData, int $tamanhoLote = 15): array
    {
        if (empty($commentsData)) {
            return [
                'status' => 'sucesso',
                'quantidade' => 0,
                'mensagem' => 'Nenhum comentário para adicionar.',
                'tempo_total_segundos' => 0,
                'tempo_total_minutos' => 0,
                'media_tempo_por_comentario_segundos' => 0
            ];
        }

        $chunks = array_chunk($commentsData, $tamanhoLote);
        $totalSucessos = 0;
        $totalErros = 0;

        $startTime = microtime(true);

        foreach ($chunks as $chunk) {
            $batchCommands = [];
            foreach ($chunk as $index => $commentItem) {
                if (!isset($commentItem['entityType']) || !isset($commentItem['entityId']) || !isset($commentItem['comment'])) {
                    // Log ou tratamento de erro para itens mal formatados no chunk
                    continue;
                }

                $authorId = $commentItem['authorId'] ?? null;
                if (!$authorId) {
                    self::init();
                    $bitrixConfig = self::$config['bitrix'];
                    $authorId = $bitrixConfig['user_id_comments'] ?? null;
                }

                $params = [
                    'fields' => [
                        'ENTITY_ID' => (int)$commentItem['entityId'],
                        'ENTITY_TYPE' => $commentItem['entityType'],
                        'COMMENT' => $commentItem['comment']
                    ]
                ];

                if ($authorId) {
                    $params['fields']['AUTHOR_ID'] = (int)$authorId;
                }
                $batchCommands["comment$index"] = 'crm.timeline.comment.add?' . http_build_query($params);
            }

            if (empty($batchCommands)) {
                continue; // Pula se o chunk não tiver comandos válidos
            }

            $resultado = self::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => true // Loga a chamada batch
            ]);

            $sucessosChunk = 0;
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    if (isset($res['result']) && !empty($res['result'])) { // Bitrix retorna o ID do comentário em caso de sucesso
                        $sucessosChunk++;
                    } else {
                        $chunkItemIndex = (int)str_replace('comment', '', $key);
                        if (isset($chunk[$chunkItemIndex])) {
                            $entityId = $chunk[$chunkItemIndex]['entityId'];
                            $entityType = $chunk[$chunkItemIndex]['entityType'];
                            LogHelper::logBitrix("ERRO ao adicionar comentário na timeline para EntityID: {$entityId}, EntityType: {$entityType} via batch. Resposta: " . json_encode($res, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                        }
                    }
                }
            } else {
                LogHelper::logBitrix("ERRO GERAL BATCH ao adicionar comentários na timeline. Resposta completa: " . json_encode($resultado, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            }
            $totalSucessos += $sucessosChunk;
            $totalErros += count($chunk) - $sucessosChunk;
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalTimeSeconds = round($totalTime, 2);
        $totalTimeMinutes = round($totalTime / 60, 2);
        $mediaPorComentario = $totalSucessos > 0 ? round($totalTime / $totalSucessos, 2) : 0;

        return [
            'status' => $totalSucessos > 0 ? 'sucesso' : 'erro',
            'quantidade' => $totalSucessos,
            'mensagem' => $totalSucessos > 0 
                ? "$totalSucessos comentários adicionados com sucesso" . ($totalErros > 0 ? " ($totalErros falharam)" : "")
                : "Falha ao adicionar comentários: $totalErros erros",
            'tempo_total_segundos' => $totalTimeSeconds,
            'tempo_total_minutos' => $totalTimeMinutes,
            'media_tempo_por_comentario_segundos' => $mediaPorComentario
        ];
    }
}
