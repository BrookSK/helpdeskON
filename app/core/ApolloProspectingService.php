<?php

/**
 * Automação de prospecção via Apollo.io.
 *
 * Pipeline (economia de créditos — NUNCA revela antes de filtrar):
 *   Apollo People Search  → usa os dados profissionais já retornados
 *     → deduplicação (apollo_id / e-mail / telefone / nome+empresa / lead existente)
 *     → filtro ICP
 *     → Lead Score
 *     → seleção dos melhores (limite diário configurável)
 *     → REVEAL apenas do e-mail (telefone é progressivo, revelado só no step de WhatsApp)
 *     → cria/atualiza Lead (LeadResolver → whatsapp_contacts)
 *     → cria card no Board (coluna configurada)
 *     → inscreve na sequência (SequenceEngine)
 *
 * Toda a configuração (ICP, filtros, board, coluna, sequência, limites) vem da
 * tabela `apollo_campaigns` — populada pelo SQL de configuração. Nada é fixo no código.
 */
class ApolloProspectingService
{
    const LOCK_KEY = 'apollo_prospecting_lock';
    const LOCK_TTL = 600; // 10 min

    private $db;
    private $apollo;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->apollo = new ApolloApi();
    }

    /** Campanhas ativas (uma ou várias configurações de captação). */
    public function activeCampaigns()
    {
        return $this->db->fetchAll("SELECT * FROM apollo_campaigns WHERE is_active = 1 ORDER BY id ASC");
    }

    /**
     * Executa a captação de todas as campanhas ativas (chamado pelo cron).
     * Respeita janela de dias/horário e o limite diário de cada campanha.
     * @return array métricas por campanha
     */
    public function runDue()
    {
        if (!$this->apollo->isConfigured()) {
            return ['error' => 'Apollo não configurado.'];
        }
        if ($this->isLocked()) {
            return ['skipped' => true, 'reason' => 'Execução em andamento'];
        }
        $this->lock();

        $out = [];
        try {
            foreach ($this->activeCampaigns() as $camp) {
                if (!$this->isWithinSchedule($camp)) {
                    $out[] = ['campaign' => $camp['id'], 'skipped' => 'fora da janela'];
                    continue;
                }
                $already = $this->capturedToday($camp['id']);
                $target = max(0, (int)$camp['daily_target'] - $already);
                if ($target <= 0) {
                    $out[] = ['campaign' => $camp['id'], 'skipped' => 'meta diária atingida', 'captured_today' => $already];
                    continue;
                }
                $out[] = $this->runCampaign($camp, $target);
            }
        } finally {
            $this->unlock();
        }
        return $out;
    }

    /**
     * Executa uma campanha até capturar $target leads novos.
     * @return array métricas da campanha
     */
    public function runCampaign(array $camp, $target)
    {
        $m = [
            'campaign' => $camp['id'], 'name' => $camp['name'],
            'searched' => 0, 'duplicated' => 0, 'out_of_icp' => 0, 'low_score' => 0,
            'selected' => 0, 'revealed_email' => 0, 'reveal_failed' => 0, 'enrolled' => 0,
        ];

        $filters = json_decode($camp['search_filters'] ?? '{}', true) ?: [];
        $icp = json_decode($camp['icp_rules'] ?? '{}', true) ?: [];
        $minScore = (int)($camp['min_score'] ?? 0);
        $perPage = min(100, max(10, (int)($camp['search_per_page'] ?? 50)));

        // Paginação de busca: avança a página a cada execução para não repetir sempre os mesmos.
        $page = max(1, (int)($camp['search_page'] ?? 1));
        $filters['page'] = $page;
        $filters['per_page'] = $perPage;

        $res = $this->apollo->searchPeople($filters);
        if (empty($res['success'])) {
            $m['error'] = $res['error'] ?? 'Falha na busca';
            $this->logCampaign($camp['id'], 'search_failed', $m['error']);
            return $m;
        }
        $people = $res['data']['people'] ?? ($res['data']['contacts'] ?? []);
        $pagination = $res['data']['pagination'] ?? [];
        $m['searched'] = count($people);
        $this->logCampaign($camp['id'], 'searched', count($people) . ' candidatos (página ' . $page . ')');

        // Avança a página para a próxima execução (circular pelo total de páginas)
        $totalPages = (int)($pagination['total_pages'] ?? 1);
        $nextPage = ($totalPages > 0 && $page >= $totalPages) ? 1 : $page + 1;
        $this->db->update('apollo_campaigns', ['search_page' => $nextPage], 'id = ?', [$camp['id']]);

        // 1) Salva TODOS na staging (barato — usa dados que já vieram da busca) e qualifica
        $candidates = [];
        $leadModel = new ApolloLead();
        foreach ($people as $p) {
            if (empty($p['id'])) continue;

            // Dedup: já importado (com contact_id) ou já em sequência? descarta sem gastar crédito
            $existingStaging = $leadModel->findByApolloId($p['id']);
            if ($existingStaging && !empty($existingStaging['contact_id'])) { $m['duplicated']++; continue; }
            if ($this->alreadyKnownLead($p)) { $m['duplicated']++; continue; }

            // Preserva os dados da busca na staging (sem reveal)
            $localId = $leadModel->upsertFromApollo($p, null);
            if (!$localId) continue;

            // Filtro ICP sobre os dados da busca
            if (!$this->matchesIcp($p, $icp)) { $m['out_of_icp']++; continue; }

            // Lead Score sobre os dados da busca
            $score = $this->scoreProspect($p, $icp);
            if ($score < $minScore) { $m['low_score']++; continue; }

            $candidates[] = ['person' => $p, 'local_id' => $localId, 'score' => $score];
        }

        // 2) Ordena por score desc e seleciona os melhores até a meta
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        $selected = array_slice($candidates, 0, $target);
        $m['selected'] = count($selected);

        // 3) Só agora consome crédito: revela e-mail dos selecionados e cria o lead
        foreach ($selected as $c) {
            $r = $this->revealAndEnroll($camp, $c['person'], $c['local_id'], $c['score']);
            if ($r === 'enrolled') { $m['revealed_email']++; $m['enrolled']++; }
            elseif ($r === 'reveal_failed') { $m['reveal_failed']++; }
        }

        $this->logCampaign($camp['id'], 'done', json_encode($m, JSON_UNESCAPED_UNICODE));
        return $m;
    }

    /**
     * Revela o e-mail do prospect selecionado (1 crédito), cria/atualiza o lead,
     * adiciona ao board e inscreve na sequência.
     * @return string 'enrolled' | 'reveal_failed' | 'skipped'
     */
    private function revealAndEnroll(array $camp, array $person, $localId, $score)
    {
        $leadModel = new ApolloLead();

        // Reaproveita e-mail já revelado, se houver (não gasta crédito de novo)
        $stored = $leadModel->findById($localId);
        $email = $this->extractEmail($person) ?: ($stored['email'] ?? null);
        $emailIsReal = $email && stripos($email, 'email_not_unlocked') === false && filter_var($email, FILTER_VALIDATE_EMAIL);

        if (!$emailIsReal) {
            // REVEAL apenas do e-mail (economia — telefone é progressivo)
            try {
                $res = $this->apollo->enrichPerson([
                    'id' => $person['id'],
                    'first_name' => $person['first_name'] ?? null,
                    'last_name' => $person['last_name'] ?? null,
                    'name' => $person['name'] ?? null,
                    'organization_name' => ($person['organization']['name'] ?? null),
                    'domain' => ($person['organization']['primary_domain'] ?? null),
                    'reveal_personal_emails' => true,
                ]);
            } catch (\Throwable $e) {
                $this->logCampaign($camp['id'], 'reveal_error', $e->getMessage());
                return 'reveal_failed';
            }
            if (empty($res['success'])) return 'reveal_failed';
            $revealed = $res['data']['person'] ?? null;
            if ($revealed) {
                $leadModel->upsertFromApollo($revealed, null);
                $person = array_merge($person, $revealed);
                $email = $this->extractEmail($revealed) ?: $email;
            }
            // Registra consumo de crédito (1 crédito por reveal de e-mail)
            $this->recordCredit($camp['id'], $localId, 'email', 1);
        }

        $emailIsReal = $email && stripos($email, 'email_not_unlocked') === false && filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$emailIsReal) return 'reveal_failed'; // sem e-mail não há como iniciar cold email

        // Monta as notas comerciais no padrão que o MessageTemplate lê (Cargo/Empresa/LinkedIn)
        $org = $person['organization'] ?? [];
        $notesParts = [];
        if (!empty($person['title'])) $notesParts[] = 'Cargo: ' . $person['title'];
        if (!empty($org['name'])) $notesParts[] = 'Empresa: ' . $org['name'];
        if (!empty($person['linkedin_url'])) $notesParts[] = 'LinkedIn: ' . $person['linkedin_url'];

        $name = $person['name'] ?? trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
        $name = $name ?: ($org['name'] ?? 'Lead Apollo');

        // Cria/atualiza o Lead via LeadResolver (dedup central; nunca base paralela)
        $resolver = new LeadResolver();
        $contactId = $resolver->resolve([
            'name' => $name,
            'email' => $email,
            'company' => $org['name'] ?? null,
            'source' => 'apollo',
            'assigned_to' => $camp['assigned_to'] ?: null,
            'briefing' => [
                'need' => $org['industry'] ?? null,
                'notes' => implode(' | ', $notesParts) ?: null,
            ],
        ], $camp['created_by'] ?: null);

        if (!$contactId) return 'reveal_failed';

        // Vincula o staging ao lead do CRM
        $leadModel->markImported($localId, $contactId, $camp['created_by'] ?: null);

        // Score inicial do lead
        try { (new LeadScoreService())->add($contactId, (int)$score, 'apollo_prospecting'); } catch (\Throwable $e) {}
        (new LeadTimelineService())->add($contactId, 'origin', 'Capturado via Apollo (campanha: ' . $camp['name'] . ')', ['score' => $score, 'campaign_id' => $camp['id']]);

        // Board: cria card na coluna configurada (evita duplicar se já houver)
        if (!empty($camp['column_id'])) {
            $exists = $this->db->fetch("SELECT id FROM crm_cards WHERE contact_id = ? LIMIT 1", [$contactId]);
            if (!$exists) {
                (new CrmBoard())->createCard([
                    'column_id' => (int)$camp['column_id'],
                    'title' => $name,
                    'contact_id' => $contactId,
                    'created_by' => $camp['created_by'] ?: null,
                    'assigned_to' => $camp['assigned_to'] ?: null,
                ]);
            }
        }

        // Sequência: inscreve o lead (idempotente por sequence+contact)
        if (!empty($camp['sequence_id'])) {
            (new SequenceEngine())->enroll((int)$camp['sequence_id'], $contactId, $camp['created_by'] ?: null);
        }

        return 'enrolled';
    }

    // ============ Deduplicação ============

    /** Verifica se o prospect já é um lead conhecido (por e-mail/telefone da busca). */
    private function alreadyKnownLead(array $person)
    {
        $resolver = new LeadResolver();
        $email = $resolver->normalizeEmail($this->extractEmail($person));
        if ($email) {
            $r = $this->db->fetch("SELECT id, unsubscribed FROM whatsapp_contacts WHERE lead_email = ? LIMIT 1", [$email]);
            if ($r) return true;
        }
        return false;
    }

    // ============ ICP + Score ============

    /**
     * Filtro ICP sobre os dados da busca (não gasta crédito).
     * $icp: { seniorities:[], titles_any:[], employee_min, employee_max, countries:[], require_website:bool }
     */
    private function matchesIcp(array $person, array $icp)
    {
        if (empty($icp)) return true;
        $org = $person['organization'] ?? [];

        if (!empty($icp['seniorities'])) {
            $sen = strtolower($person['seniority'] ?? '');
            $ok = false;
            foreach ($icp['seniorities'] as $s) if ($sen === strtolower($s)) { $ok = true; break; }
            if (!$ok) return false;
        }
        if (!empty($icp['titles_any'])) {
            $title = strtolower($person['title'] ?? '');
            $ok = false;
            foreach ($icp['titles_any'] as $t) if ($title !== '' && strpos($title, strtolower($t)) !== false) { $ok = true; break; }
            if (!$ok) return false;
        }
        $emp = (int)($org['estimated_num_employees'] ?? 0);
        if (!empty($icp['employee_min']) && $emp > 0 && $emp < (int)$icp['employee_min']) return false;
        if (!empty($icp['employee_max']) && $emp > 0 && $emp > (int)$icp['employee_max']) return false;

        if (!empty($icp['require_website']) && empty($org['website_url']) && empty($org['primary_domain'])) return false;

        return true;
    }

    /**
     * Lead Score sobre os dados da busca (configurável via icp_rules.score).
     * Pesos padrão: decisor +30, título-alvo +20, porte correto +15, região +10, site +5, tecnologia +10.
     */
    private function scoreProspect(array $person, array $icp)
    {
        $w = $icp['score'] ?? [];
        $org = $person['organization'] ?? [];
        $score = 0;

        $sen = strtolower($person['seniority'] ?? '');
        if (in_array($sen, ['owner', 'founder', 'c_suite', 'partner', 'vp', 'head', 'director'])) {
            $score += (int)($w['decisor'] ?? 30);
        }
        if (!empty($icp['titles_any'])) {
            $title = strtolower($person['title'] ?? '');
            foreach ($icp['titles_any'] as $t) {
                if ($title !== '' && strpos($title, strtolower($t)) !== false) { $score += (int)($w['title'] ?? 20); break; }
            }
        }
        $emp = (int)($org['estimated_num_employees'] ?? 0);
        if ($emp > 0 && (empty($icp['employee_min']) || $emp >= (int)$icp['employee_min'])
            && (empty($icp['employee_max']) || $emp <= (int)$icp['employee_max'])) {
            $score += (int)($w['size'] ?? 15);
        }
        if (!empty($person['country']) || !empty($org['country'])) $score += (int)($w['region'] ?? 10);
        if (!empty($org['website_url']) || !empty($org['primary_domain'])) $score += (int)($w['website'] ?? 5);
        if (!empty($org['technology_names'])) $score += (int)($w['technology'] ?? 10);

        return $score;
    }

    // ============ Helpers ============

    private function extractEmail(array $person)
    {
        $isReal = fn($e) => !empty($e) && stripos($e, 'email_not_unlocked') === false && stripos($e, 'domain.com') === false && filter_var($e, FILTER_VALIDATE_EMAIL);
        if ($isReal($person['email'] ?? null)) return $person['email'];
        foreach (['personal_emails', 'contact_emails'] as $k) {
            if (!empty($person[$k]) && is_array($person[$k])) {
                foreach ($person[$k] as $item) {
                    $v = is_array($item) ? ($item['email'] ?? null) : $item;
                    if ($isReal($v)) return $v;
                }
            }
        }
        return null;
    }

    private function capturedToday($campaignId)
    {
        $r = $this->db->fetch(
            "SELECT COUNT(*) t FROM apollo_prospecting_log
             WHERE campaign_id = ? AND action = 'enrolled' AND DATE(created_at) = CURDATE()",
            [$campaignId]
        );
        return (int)($r['t'] ?? 0);
    }

    private function isWithinSchedule(array $camp)
    {
        // days_of_week: "1,2,3,4,5" (ISO-8601, 1=segunda). Vazio = todos os dias.
        $days = trim((string)($camp['days_of_week'] ?? ''));
        if ($days !== '') {
            $allowed = array_map('intval', explode(',', $days));
            if (!in_array((int)date('N'), $allowed, true)) return false;
        }
        $start = $camp['window_start'] ?: '08:00:00';
        $end = $camp['window_end'] ?: '18:00:00';
        $now = date('H:i:s');
        return $now >= $start && $now <= $end;
    }

    private function recordCredit($campaignId, $localId, $type, $credits)
    {
        try {
            $this->db->insert('apollo_prospecting_log', [
                'campaign_id' => $campaignId,
                'apollo_lead_id' => $localId,
                'action' => 'reveal_' . $type,
                'detail' => $credits . ' crédito(s)',
                'credits' => $credits,
            ]);
        } catch (\Throwable $e) { /* silencioso */ }
    }

    private function logCampaign($campaignId, $action, $detail = null)
    {
        try {
            $this->db->insert('apollo_prospecting_log', [
                'campaign_id' => $campaignId,
                'action' => $action,
                'detail' => $detail,
            ]);
        } catch (\Throwable $e) { /* silencioso */ }
    }

    // ============ Lock ============
    private function isLocked()
    {
        $r = $this->db->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [self::LOCK_KEY]);
        if (!$r) return false;
        $ts = strtotime($r['setting_value'] ?? '');
        if (!$ts || (time() - $ts) > self::LOCK_TTL) { $this->unlock(); return false; }
        return true;
    }
    private function lock()
    {
        $v = date('Y-m-d H:i:s');
        $ex = $this->db->fetch("SELECT id FROM settings WHERE setting_key = ?", [self::LOCK_KEY]);
        if ($ex) $this->db->update('settings', ['setting_value' => $v], 'setting_key = ?', [self::LOCK_KEY]);
        else $this->db->insert('settings', ['setting_key' => self::LOCK_KEY, 'setting_value' => $v]);
    }
    private function unlock()
    {
        $this->db->query("DELETE FROM settings WHERE setting_key = ?", [self::LOCK_KEY]);
    }
}
