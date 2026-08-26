<?php

/**
 * Orquestra uma coleta completa. Chamado tanto pelo botão manual quanto pelo
 * scheduler — implementação ÚNICA.
 *
 * Responsabilidades:
 *  - ler configuração e termos do banco;
 *  - lock (evita coletas simultâneas), com expiração (não trava após crash);
 *  - iterar conectores registrados (hoje: freelas99) × termos × páginas;
 *  - parser → normalizer → upsert;
 *  - contabilizar métricas (cards vs parseados, novos vs conhecidos);
 *  - atualizar collection_runs e source_health;
 *  - timeout global => status 'partial'.
 */
class CollectionRunner
{
    const LOCK_KEY = 'lead_capture_lock';
    const LOCK_TTL = 300;      // 5 min — lock expira mesmo após crash
    const GLOBAL_TIMEOUT = 300; // 5 min de teto para a run

    private $db;
    private $model;
    private $connectors = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->model = new Opportunity();
        // Registry de conectores. Hoje: apenas 99Freelas.
        $this->connectors[] = new Freelas99Connector();
    }

    /** @return array|false ['user_id'=>, 'user_name'=>, 'locked_at'=>] ou false se livre */
    public function currentLock()
    {
        $row = $this->db->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [self::LOCK_KEY]);
        if (!$row) return false;
        $data = json_decode($row['setting_value'] ?? '{}', true);
        if (!$data) return false;
        $lockedAt = strtotime($data['locked_at'] ?? '');
        if (!$lockedAt || (time() - $lockedAt) > self::LOCK_TTL) {
            $this->releaseLock();
            return false;
        }
        return $data;
    }

    private function acquireLock($userId, $userName)
    {
        $value = json_encode(['user_id' => $userId, 'user_name' => $userName, 'locked_at' => date('Y-m-d H:i:s')]);
        $exists = $this->db->fetch("SELECT id FROM settings WHERE setting_key = ?", [self::LOCK_KEY]);
        if ($exists) {
            $this->db->update('settings', ['setting_value' => $value], 'setting_key = ?', [self::LOCK_KEY]);
        } else {
            $this->db->insert('settings', ['setting_key' => self::LOCK_KEY, 'setting_value' => $value]);
        }
    }

    private function releaseLock()
    {
        $this->db->query("DELETE FROM settings WHERE setting_key = ?", [self::LOCK_KEY]);
    }

    /**
     * Executa a coleta de forma síncrona.
     * @return array ['run_id'=>, 'status'=>, ...contadores] ou ['error'=>, 'locked_by'=>]
     */
    public function run($trigger = 'manual', $userId = null, $userName = null)
    {
        $settings = $this->model->getSettings('freelas99');
        if (empty($settings['enabled'])) {
            return ['error' => 'A fonte 99Freelas está desabilitada.'];
        }

        $terms = $this->model->getActiveTermStrings();
        $collectGeneral = !empty($settings['collect_general']);
        if (empty($terms) && !$collectGeneral) {
            return ['error' => 'Nenhum termo ativo e a listagem geral está desligada.'];
        }

        // Lock
        $lock = $this->currentLock();
        if ($lock) {
            return ['error' => 'Coleta em andamento', 'locked_by' => $lock['user_name'] ?? null, 'busy' => true];
        }
        $this->acquireLock($userId, $userName ?: 'sistema');

        $runId = $this->model->createRun($trigger, $terms);
        $startTime = time();
        $maxPages = max(1, min(10, (int) $settings['max_pages']));

        $metrics = [
            'pages_fetched' => 0, 'cards_detected' => 0, 'projects_parsed' => 0,
            'projects_found' => 0, 'projects_new' => 0, 'projects_known' => 0,
            'http_errors' => 0, 'parser_errors' => 0,
        ];
        $lastError = null;
        $termsFailed = 0;
        $timedOut = false;

        // Lista de "buscas": cada termo + (opcional) listagem geral (term=null)
        $searches = $terms;
        if ($collectGeneral) $searches[] = null;

        try {
            foreach ($searches as $term) {
                if (time() - $startTime > self::GLOBAL_TIMEOUT) { $timedOut = true; break; }

                $termHadError = false;
                for ($page = 1; $page <= $maxPages; $page++) {
                    if (time() - $startTime > self::GLOBAL_TIMEOUT) { $timedOut = true; break; }

                    try {
                        $connector = $this->connectors[0]; // freelas99
                        $res = $connector->collect($term, $page);
                    } catch (\Throwable $e) {
                        $metrics['http_errors']++;
                        $lastError = $e->getMessage();
                        $termHadError = true;
                        break; // erro numa página interrompe a paginação deste termo, não os demais
                    }

                    $metrics['pages_fetched']++;
                    $metrics['cards_detected'] += $res['cardsDetected'];
                    $parsedCount = count($res['raw']);
                    $metrics['projects_parsed'] += $parsedCount;

                    // Falha silenciosa: HTML tinha cards mas o parser não extraiu nada
                    if ($res['cardsDetected'] > 0 && $parsedCount === 0) {
                        $metrics['parser_errors']++;
                        $lastError = 'Parser não extraiu projetos apesar de ' . $res['cardsDetected'] . ' cards no HTML (página ' . $page . ').';
                        $termHadError = true;
                    }

                    // Página vazia interrompe a paginação deste termo
                    if ($parsedCount === 0) break;

                    // Categorias ativas (recorte definido pelo usuário nas Configurações).
                    // NÃO criamos novas categorias automaticamente: apenas as ativas são gravadas.
                    $activeCats = $this->model->getActiveCategoryNames();

                    foreach ($res['raw'] as $rawProject) {
                        $norm = LeadNormalizer::normalize($rawProject, $terms);
                        if (!$norm) continue;

                        // Filtro estrito por categoria ativa: só grava projetos cuja
                        // categoria esteja marcada como ativa. Se há recorte definido,
                        // projetos sem categoria ou de categorias não selecionadas são
                        // descartados (evita "pegar tudo" e criar categorias novas).
                        if (!empty($activeCats)) {
                            if (empty($norm['category']) || !in_array($norm['category'], $activeCats, true)) {
                                continue;
                            }
                        }

                        $metrics['projects_found']++;
                        $outcome = $this->model->upsert($norm);
                        if ($outcome === 'new') $metrics['projects_new']++;
                        else $metrics['projects_known']++;
                    }
                }
                if ($termHadError) $termsFailed++;
            }
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
        }

        // Status final
        $status = 'success';
        if ($timedOut) {
            $status = 'partial';
        } elseif ($metrics['http_errors'] > 0 || $metrics['parser_errors'] > 0) {
            $status = ($metrics['projects_found'] > 0) ? 'partial' : 'failed';
        }

        $durationMs = (time() - $startTime) * 1000;
        $this->model->updateRun($runId, array_merge($metrics, [
            'status' => $status,
            'finished_at' => date('Y-m-d H:i:s'),
            'duration_ms' => $durationMs,
            'last_error' => $lastError,
        ]));

        $this->updateHealth($status, $metrics, $durationMs, $lastError);
        $this->releaseLock();

        return array_merge(['run_id' => $runId, 'status' => $status, 'terms_failed' => $termsFailed], $metrics);
    }

    private function updateHealth($status, $metrics, $durationMs, $lastError)
    {
        $health = $this->model->getHealth('freelas99');
        $now = date('Y-m-d H:i:s');
        $isFailure = ($status === 'failed');

        $data = [
            'last_run_at' => $now,
            'projects_found_last_run' => $metrics['projects_found'],
            'last_duration_ms' => $durationMs,
            'last_error' => $lastError,
            'cards_detected_last_run' => $metrics['cards_detected'],
            'projects_parsed_last_run' => $metrics['projects_parsed'],
        ];
        if ($isFailure) {
            $data['last_failure_at'] = $now;
            $data['consecutive_failures'] = (int) ($health['consecutive_failures'] ?? 0) + 1;
        } else {
            $data['last_success_at'] = $now;
            $data['consecutive_failures'] = 0;
        }
        $this->model->saveHealth('freelas99', $data);
    }
}
