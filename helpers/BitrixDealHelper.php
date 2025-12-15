<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixDealHelper
{
    // Cria um negócio no Bitrix24 via API
    public static function criarDeal($entityId, $categoryId, $fields): array
    {
        // Formata os campos recebidos
        $formattedFields = BitrixHelper::formatarCampos($fields);

        if ($categoryId) {
            $formattedFields['categoryId'] = $categoryId;
        }

        $params = [
            'entityTypeId' => $entityId,
            'fields' => $formattedFields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.add', $params, [
            'log' => true
        ]);

        if (isset($resultado['result']['item']['id'])) {
            return [
                'success' => true,
                'id' => $resultado['result']['item']['id']
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao criar negócio.'
        ];
    }

    // Edita um negócio existente no Bitrix24 via API
    public static function editarDeal($entityId, $dealId, array $fields): array
    {
        if (!$entityId || !$dealId || empty($fields)) {
            return [
                'success' => false,
                'error' => 'Parâmetros obrigatórios não informados.'
            ];
        }

        $fields = BitrixHelper::formatarCampos($fields);

        $params = [
            'entityTypeId' => $entityId,
            'id' => (int)$dealId,
            'fields' => $fields
        ];

        $resultado = BitrixHelper::chamarApi('crm.item.update', $params, [
            'log' => true
        ]);

        if (isset($resultado['result'])) {
            return [
                'success' => true,
                'id' => $dealId
            ];
        }

        return [
            'success' => false,
            'debug' => $resultado,
            'error' => $resultado['error_description'] ?? 'Erro desconhecido ao editar negócio.'
        ];
    }

    // Consulta uma Negócio específico no Bitrix24 via ID
    public static function consultarDeal($entityId, $dealId, $fields)
    {
        // 1. Normaliza campos para array e remove espaços
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        } else {
            $fields = array_map('trim', $fields);
        }

        if (!in_array('id', $fields)) {
            array_unshift($fields, 'id');
        }

        // 2. Consulta o negócio (deal)
        $params = [
            'entityTypeId' => $entityId,
            'id' => $dealId,
        ];
        $respostaApi = BitrixHelper::chamarApi('crm.item.get', $params, []);
        $dadosBrutos = $respostaApi['result']['item'] ?? [];

        // 3. Consulta os campos da SPA
        $camposSpa = BitrixHelper::consultarCamposSpa($entityId);

        // 4. Consulta as etapas do tipo
        $etapas = BitrixHelper::consultarEtapasPorTipo($entityId);

        // 5. Formata os campos para o padrão camelCase
        $camposFormatados = BitrixHelper::formatarCampos(array_fill_keys($fields, null));
        
        $valoresBrutos = [];
        foreach (array_keys($camposFormatados) as $campoConvertido) {
            $valoresBrutos[$campoConvertido] = $dadosBrutos[$campoConvertido] ?? null;
        }
        
        // Garantir que companyId seja incluído se existir nos dados brutos
        if (isset($dadosBrutos['companyId']) && !isset($valoresBrutos['companyId'])) {
            $valoresBrutos['companyId'] = $dadosBrutos['companyId'];
        }

        // 6. Mapeia valores enumerados
        $valoresConvertidos = BitrixHelper::mapearValoresEnumerados($valoresBrutos, $camposSpa);

        // 7. Mapeia o nome amigável da etapa, se existir campo de etapa
        $stageName = null;
        if (isset($valoresBrutos['stageId'])) {
            $stageName = BitrixHelper::mapearEtapaPorId($valoresBrutos['stageId'], $etapas);
        }

        // 8. Monta resposta amigável SEMPRE incluindo todos os campos solicitados
        $resultadoFinal = [];
        foreach ($fields as $campoOriginal) {
            // Converte o campo para o padrão Bitrix
            $campoConvertidoArr = BitrixHelper::formatarCampos([$campoOriginal => null]);
            $campoConvertido = array_key_first($campoConvertidoArr);
            $valorBruto = $valoresBrutos[$campoConvertido] ?? null;
            $valorConvertido = $valoresConvertidos[$campoConvertido] ?? $valorBruto;
            $spa = $camposSpa[$campoConvertido] ?? [];
            $nomeAmigavel = $spa['title'] ?? $campoOriginal;
            $texto = $valorConvertido;
            $type = $spa['type'] ?? null;
            $isMultiple = $spa['isMultiple'] ?? false;
            // Se for stageId, usa o nome da etapa como texto
            if ($campoConvertido === 'stageId') {
                $texto = $stageName ?? $valorBruto;
                $nomeAmigavel = 'Fase';
            }
            $resultadoFinal[$campoConvertido] = [
                'nome' => $nomeAmigavel,
                'valor' => $valorBruto,
                'texto' => $texto,
                'type' => $type,
                'isMultiple' => $isMultiple
            ];
        }
        // Sempre inclui o id bruto
        if (isset($valoresBrutos['id'])) {
            $resultadoFinal['id'] = $valoresBrutos['id'];
        }

        return ['result' => $resultadoFinal];
    }

    /**
     * Edita um ou vários negócios existentes no Bitrix24 via API (sempre em batch, sem agendamento).
     *
     * @param int $entityId O ID do tipo de entidade (ex: 191 para SPA).
     * @param array $editData Um array de arrays, onde cada item contém 'id' (ID do deal) e 'fields' (campos a serem atualizados).
     * @param int $tamanhoLote O número de operações por lote na API batch.
     * @return array O resultado da operação em lote.
     */
    public static function editarDealsEmLote($entityId, array $editData, int $tamanhoLote = 15): array
    {
        if (empty($editData)) {
            return [
                'status' => 'sucesso',
                'quantidade' => 0,
                'ids' => '',
                'mensagem' => 'Nenhum deal para editar.',
                'tempo_total_segundos' => 0,
                'tempo_total_minutos' => 0,
                'media_tempo_por_deal_segundos' => 0
            ];
        }

        $chunks = array_chunk($editData, $tamanhoLote);
        $todosIds = [];
        $totalSucessos = 0;
        $totalErros = 0;

        $startTime = microtime(true);

        foreach ($chunks as $chunk) {
            $batchCommands = [];
            foreach ($chunk as $index => $editItem) {
                if (!isset($editItem['id']) || !isset($editItem['fields']) || empty($editItem['fields'])) {
                    // Log ou tratamento de erro para itens mal formatados no chunk
                    continue;
                }
                // Formata nomes e valida/formata valores dos campos
                $fieldsFormatados = BitrixHelper::formatarCampos($editItem['fields'], $entityId, true);
                $params = [
                    'entityTypeId' => $entityId,
                    'id' => (int)$editItem['id'],
                    'fields' => $fieldsFormatados
                ];
                $batchCommands["edit$index"] = 'crm.item.update?' . http_build_query($params);
            }

            if (empty($batchCommands)) {
                continue; // Pula se o chunk não tiver comandos válidos
            }

            $resultado = BitrixHelper::chamarApi('batch', ['cmd' => $batchCommands], [
                'log' => true // Loga a chamada batch
            ]);
            
            $sucessosChunk = 0;
            $idsChunk = [];
            
            if (isset($resultado['result']['result'])) {
                foreach ($resultado['result']['result'] as $key => $res) {
                    $chunkItemIndex = (int)str_replace('edit', '', $key);
                    // Garante que o índice existe no chunk original
                    if (isset($chunk[$chunkItemIndex])) {
                        $dealId = $chunk[$chunkItemIndex]['id'];
                        // Verifica se a operação foi bem-sucedida (Bitrix retorna 'item' ou 'result: true')
                        if (isset($res['item']) || (isset($res['result']) && $res['result'] === true)) {
                            $sucessosChunk++;
                            $idsChunk[] = $dealId;
                            $todosIds[] = $dealId;
                        } else {
                            LogHelper::logBitrix("ERRO ao editar Deal ID: {$dealId} no Bitrix24 via batch. Resposta: " . json_encode($res, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                        }
                    }
                }
            } else {
                LogHelper::logBitrix("ERRO GERAL BATCH ao editar deals. Resposta completa: " . json_encode($resultado, JSON_UNESCAPED_UNICODE), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            }
            $totalSucessos += $sucessosChunk;
            $totalErros += count($chunk) - $sucessosChunk;
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalTimeSeconds = round($totalTime, 2);
        $totalTimeMinutes = round($totalTime / 60, 2);
        $mediaPorDeal = $totalSucessos > 0 ? round($totalTime / $totalSucessos, 2) : 0;

        $idsString = implode(', ', $todosIds);
        return [
            'status' => $totalSucessos > 0 ? 'sucesso' : 'erro',
            'quantidade' => $totalSucessos,
            'ids' => $idsString,
            'mensagem' => $totalSucessos > 0 
                ? "$totalSucessos deals editados com sucesso" . ($totalErros > 0 ? " ($totalErros falharam)" : "")
                : "Falha ao editar deals: $totalErros erros",
            'tempo_total_segundos' => $totalTimeSeconds,
            'tempo_total_minutos' => $totalTimeMinutes,
            'media_tempo_por_deal_segundos' => $mediaPorDeal
        ];
    }
}
