<?php

/**
 * Diagnóstico do módulo de Captação de Leads (99Freelas).
 *
 * Roda uma bateria de verificações e consolida request/resposta/erros para debug,
 * no mesmo espírito do diagnóstico da integração Apollo.
 *
 * Cobre:
 *  - Config/DB (settings, termos, tabelas);
 *  - Parser contra as fixtures reais (regressão — usa os HTMLs de docs/);
 *  - Normalizer (datas, moeda, número pt-BR, external_id, canonical);
 *  - URL builder + paginação;
 *  - Coleta HTTP ao vivo (página 1 de um termo);
 *  - Métrica cards vs parseados (detecta parser quebrado).
 */
class Freelas99Diagnostics
{
    const FIXTURE_DIR = 'docs/99freelas_fixtures';

    public function run()
    {
        $steps = [];

        $steps[] = $this->checkDatabase();
        $steps[] = $this->checkSettings();
        $steps[] = $this->checkFixturesPresent();
        $steps[] = $this->checkParserWithResults();
        $steps[] = $this->checkParserPage2();
        $steps[] = $this->checkParserEmpty();
        $steps[] = $this->checkNormalizer();
        $steps[] = $this->checkUrlBuilder();
        $steps[] = $this->checkLiveCollect();

        $total = count($steps);
        $ok = 0; $failed = 0; $warn = 0;
        foreach ($steps as $s) {
            if ($s['level'] === 'ok') $ok++;
            elseif ($s['level'] === 'warn') $warn++;
            else $failed++;
        }

        return [
            'summary' => ['total' => $total, 'ok' => $ok, 'failed' => $failed, 'warn' => $warn, 'ran_at' => date('Y-m-d H:i:s')],
            'results' => $steps,
        ];
    }

    // ---- Passos ----

    private function checkDatabase()
    {
        try {
            $db = Database::getInstance();
            $tables = ['opportunities', 'search_terms', 'source_settings', 'collection_runs', 'source_health'];
            $missing = [];
            foreach ($tables as $t) {
                // information_schema aceita placeholder (SHOW TABLES LIKE ? não aceita bind)
                $r = $db->fetch(
                    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                    [$t]
                );
                if (!$r) $missing[] = $t;
            }
            if ($missing) {
                return $this->step('Banco de dados', 'failed', 'Tabelas ausentes: ' . implode(', ', $missing) . '. Rode a migration 061.', ['missing' => $missing]);
            }
            return $this->step('Banco de dados', 'ok', 'Todas as 5 tabelas do módulo existem.', ['tables' => $tables]);
        } catch (\Throwable $e) {
            return $this->step('Banco de dados', 'failed', 'Erro: ' . $e->getMessage());
        }
    }

    private function checkSettings()
    {
        try {
            $model = new Opportunity();
            $settings = $model->getSettings('freelas99');
            $terms = $model->getActiveTermStrings();
            $enabled = !empty($settings['enabled']);
            $data = [
                'enabled' => $enabled,
                'max_pages' => (int) $settings['max_pages'],
                'collect_general' => !empty($settings['collect_general']),
                'active_terms' => count($terms),
                'terms_sample' => array_slice($terms, 0, 5),
            ];
            if (!$enabled) return $this->step('Configuração', 'warn', 'A fonte 99Freelas está DESABILITADA.', $data);
            if (empty($terms) && empty($settings['collect_general'])) {
                return $this->step('Configuração', 'warn', 'Nenhum termo ativo e listagem geral desligada — a coleta não traria nada.', $data);
            }
            return $this->step('Configuração', 'ok', count($terms) . ' termo(s) ativo(s), máx. ' . (int)$settings['max_pages'] . ' páginas.', $data);
        } catch (\Throwable $e) {
            return $this->step('Configuração', 'failed', 'Erro: ' . $e->getMessage());
        }
    }

    private function checkFixturesPresent()
    {
        $files = ['search_automacao.html', 'search_automacao_p2.html', 'search_empty.html'];
        $status = [];
        $missing = [];
        foreach ($files as $f) {
            $path = BASE_PATH . '/' . self::FIXTURE_DIR . '/' . $f;
            $exists = is_file($path);
            $status[$f] = $exists ? (round(filesize($path) / 1024) . ' KB') : 'AUSENTE';
            if (!$exists) $missing[] = $f;
        }
        if ($missing) {
            return $this->step('Fixtures reais', 'warn', 'Fixtures ausentes: ' . implode(', ', $missing) . '. Os testes de regressão do parser não rodam sem elas.', $status);
        }
        return $this->step('Fixtures reais', 'ok', 'As 3 capturas reais estão presentes.', $status);
    }

    private function checkParserWithResults()
    {
        $html = $this->loadFixture('search_automacao.html');
        if ($html === null) return $this->step('Parser · página com resultados', 'warn', 'Fixture ausente — teste ignorado.');

        $cards = Freelas99Parser::countProjectLinks($html);
        $projects = Freelas99Parser::parse($html);
        $parsed = count($projects);

        $sample = $projects[0] ?? null;
        $detail = [
            'cards_detected' => $cards,
            'projects_parsed' => $parsed,
            'sample' => $sample ? [
                'external_id' => $sample['external_id'],
                'title' => mb_substr($sample['title'], 0, 60),
                'category' => $sample['category'],
                'proposal_count' => $sample['proposal_count'],
                'interested_count' => $sample['interested_count'],
                'client_name' => $sample['client_name'],
                'published_ts' => $sample['published_ts'],
                'canonical_url' => $sample['canonical_url'],
            ] : null,
        ];

        // Falha silenciosa clássica: HTML tem cards mas parser extraiu 0
        if ($cards > 0 && $parsed === 0) {
            return $this->step('Parser · página com resultados', 'failed', "PARSER QUEBRADO: {$cards} cards no HTML, 0 extraídos. O 99Freelas pode ter mudado o HTML.", $detail);
        }
        if ($parsed < $cards) {
            return $this->step('Parser · página com resultados', 'warn', "Extraiu {$parsed} de {$cards} cards — alguns podem ter estrutura diferente.", $detail);
        }
        // Validações de campos essenciais no primeiro card
        if (!$sample || empty($sample['external_id']) || empty($sample['title'])) {
            return $this->step('Parser · página com resultados', 'failed', 'Cards extraídos sem external_id/título.', $detail);
        }
        return $this->step('Parser · página com resultados', 'ok', "{$parsed} projetos extraídos de {$cards} cards, campos essenciais presentes.", $detail);
    }

    private function checkParserPage2()
    {
        $html1 = $this->loadFixture('search_automacao.html');
        $html2 = $this->loadFixture('search_automacao_p2.html');
        if ($html1 === null || $html2 === null) return $this->step('Parser · paginação', 'warn', 'Fixture da página 2 ausente — teste ignorado.');

        $ids1 = array_column(Freelas99Parser::parse($html1), 'external_id');
        $ids2 = array_column(Freelas99Parser::parse($html2), 'external_id');
        $overlap = array_intersect($ids1, $ids2);

        $detail = ['ids_page1' => count($ids1), 'ids_page2' => count($ids2), 'overlap' => count($overlap)];
        if (empty($ids2)) return $this->step('Parser · paginação', 'failed', 'Página 2 não extraiu projetos.', $detail);
        if (count($overlap) === count($ids2) && count($ids2) > 0) {
            return $this->step('Parser · paginação', 'failed', 'Página 2 idêntica à 1 — parâmetro de paginação errado.', $detail);
        }
        return $this->step('Parser · paginação', 'ok', 'Página 2 traz IDs diferentes da página 1.', $detail);
    }

    private function checkParserEmpty()
    {
        $html = $this->loadFixture('search_empty.html');
        if ($html === null) return $this->step('Parser · busca vazia', 'warn', 'Fixture de busca vazia ausente — teste ignorado.');
        $projects = Freelas99Parser::parse($html);
        $cards = Freelas99Parser::countProjectLinks($html);
        $detail = ['cards_detected' => $cards, 'projects_parsed' => count($projects)];
        if (count($projects) === 0) {
            return $this->step('Parser · busca vazia', 'ok', 'Busca sem resultado retorna [] corretamente (não é falha).', $detail);
        }
        return $this->step('Parser · busca vazia', 'warn', 'Busca "vazia" retornou ' . count($projects) . ' — a fixture pode conter projetos.', $detail);
    }

    private function checkNormalizer()
    {
        $cases = [];

        // Datas
        $cases['data "há 2 horas"'] = LeadNormalizer::parseRelativeDate('há 2 horas') !== null;
        $cases['data "2 dias atrás"'] = LeadNormalizer::parseRelativeDate('2 dias atrás') !== null;
        $cases['data "ontem"'] = LeadNormalizer::parseRelativeDate('ontem') !== null;
        $cases['data "15/03/2025"'] = LeadNormalizer::parseRelativeDate('15/03/2025') !== null;
        $cases['data inválida → null'] = LeadNormalizer::parseRelativeDate('qualquer coisa') === null;

        // Número pt-BR
        $cases['"1.500,00" → 1500'] = (Freelas99Parser::parseBrNumber('1.500,00') === 1500.0);

        // Normalização completa a partir de um raw mínimo
        $raw = [
            'external_id' => '778582', 'canonical_url' => 'https://www.99freelas.com.br/project/x-778582',
            'title' => 'Automação de planilhas', 'description' => 'Excel e Power BI',
            'skills' => ['Excel'], 'published_ts' => 1787669316, 'proposal_count' => 3,
            'interested_count' => 5, 'currency' => 'BRL', 'budget_min' => 1500.0, 'budget_max' => 3000.0,
        ];
        $norm = LeadNormalizer::normalize($raw, ['Automação', 'Excel']);
        $cases['normalize retorna registro'] = is_array($norm) && $norm['external_id'] === '778582';
        $cases['published_at parseado do epoch'] = !empty($norm['published_at']);
        $cases['matched_terms detectados'] = in_array('Automação', $norm['matched_terms'] ?? []);
        $cases['score calculado'] = isset($norm['score']) && $norm['score'] > 0;
        $cases['raw sem id → null'] = LeadNormalizer::normalize(['title' => 'x'], []) === null;

        $failed = array_keys(array_filter($cases, fn($v) => !$v));
        $detail = array_map(fn($v) => $v ? 'OK' : 'FALHOU', $cases);
        if ($failed) {
            return $this->step('Normalizer', 'failed', 'Casos com falha: ' . implode('; ', $failed), $detail);
        }
        return $this->step('Normalizer', 'ok', count($cases) . ' casos passaram (datas, moeda, número, score, identidade).', $detail);
    }

    private function checkUrlBuilder()
    {
        $conn = new Freelas99Connector();
        $u1 = $conn->buildUrl('automação', 1);
        $u2 = $conn->buildUrl('automação', 2);
        $u3 = $conn->buildUrl('', 1);
        $canon = Freelas99Parser::canonicalUrl('/project/teste-778582?fs=t');

        $detail = ['term_page1' => $u1, 'term_page2' => $u2, 'geral' => $u3, 'canonical' => $canon];
        $ok = (strpos($u1, 'q=') !== false)
            && (strpos($u1, 'page=') === false)      // page 1 omite o parâmetro
            && (strpos($u2, 'page=2') !== false)
            && ($canon === 'https://www.99freelas.com.br/project/teste-778582'); // sem ?fs=t
        if (!$ok) return $this->step('URL builder', 'failed', 'Construção de URL/canonical divergente do esperado.', $detail);
        return $this->step('URL builder', 'ok', 'page=1 omitido, page=2 presente, canonical sem query.', $detail);
    }

    private function checkLiveCollect()
    {
        try {
            $conn = new Freelas99Connector();
            $url = $conn->buildUrl('automacao', 1);
            $res = $conn->collect('automacao', 1);
            $detail = [
                'url' => $url,
                'cards_detected' => $res['cardsDetected'],
                'projects_parsed' => count($res['raw']),
                'http_status' => $res['meta']['status'] ?? null,
                'bytes' => $res['meta']['bytes'] ?? null,
                'ms' => $res['meta']['durationMs'] ?? null,
                'sample_title' => $res['raw'][0]['title'] ?? null,
            ];
            if ($res['cardsDetected'] > 0 && count($res['raw']) === 0) {
                return $this->step('Coleta ao vivo (99Freelas)', 'failed', 'HTML ao vivo tem cards mas o parser extraiu 0 — parser desatualizado.', $detail);
            }
            if (count($res['raw']) === 0) {
                return $this->step('Coleta ao vivo (99Freelas)', 'warn', 'Coleta OK (HTTP 200) mas 0 projetos no momento.', $detail);
            }
            return $this->step('Coleta ao vivo (99Freelas)', 'ok', count($res['raw']) . ' projetos coletados ao vivo com sucesso.', $detail);
        } catch (\Throwable $e) {
            return $this->step('Coleta ao vivo (99Freelas)', 'failed', 'Falha na requisição: ' . $e->getMessage() . '. Se for 403/429, ajuste rate limit/headers.');
        }
    }

    // ---- utilitários ----

    private function loadFixture($file)
    {
        $path = BASE_PATH . '/' . self::FIXTURE_DIR . '/' . $file;
        if (!is_file($path)) return null;
        return file_get_contents($path);
    }

    private function step($label, $level, $message, $detail = null)
    {
        return ['label' => $label, 'level' => $level, 'message' => $message, 'detail' => $detail];
    }
}
