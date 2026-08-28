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
     * Roteia conforme a origem configurada (Apollo x Meus Leads).
     * @return array métricas da campanha
     */
    public function runCampaign(array $camp, $target)
    {
        $source = $camp['lead_source'] ?? 'apollo';
        if ($source === 'my_leads') {
            return $this->runMyLeadsCampaign($camp, $target);
        }
        return $this->runApolloCampaign($camp, $target);
    }

    /**
     * Origem APOLLO — fonte contínua de novos leads.
     *
     * Fluxo (a ordem importa; economia de créditos):
     *   Apollo → Aplicar ICP/Filtros → Identificar candidatos → Verificar duplicidade
     *   → Revelar dados → Criar em Meus Leads → Atribuir ao Super Admin → Inscrever na sequência
     *
     * Pagina continuamente até atingir $target NOVOS elegíveis ou esgotar resultados.
     * @return array métricas da campanha
     */
    public function runApolloCampaign(array $camp, $target)
    {
        $m = [
            'campaign' => $camp['id'], 'name' => $camp['name'], 'source' => 'apollo',
            'searched' => 0, 'analyzed' => 0, 'duplicated' => 0, 'out_of_icp' => 0, 'low_score' => 0,
            'selected' => 0, 'revealed_email' => 0, 'reveal_failed' => 0, 'imported' => 0, 'enrolled' => 0,
            'pages' => 0,
        ];

        $filters = json_decode($camp['search_filters'] ?? '{}', true) ?: [];
        $icp = json_decode($camp['icp_rules'] ?? '{}', true) ?: [];
        $minScore = (int)($camp['min_score'] ?? 0);
        $perPage = min(100, max(10, (int)($camp['search_per_page'] ?? 50)));
        $leadModel = new ApolloLead();

        // Estado da busca: continua da página onde parou (não recomeça do zero).
        $page = max(1, (int)($camp['search_page'] ?? 1));
        $totalPages = null;
        $maxPagesPerRun = 20; // trava de segurança contra loop/consumo excessivo

        // Continua paginando enquanto não atingir a meta de NOVOS elegíveis
        // e ainda houver resultados disponíveis para esta busca.
        while ($m['enrolled'] < $target && $m['pages'] < $maxPagesPerRun) {
            $filters['page'] = $page;
            $filters['per_page'] = $perPage;

            $res = $this->apollo->searchPeople($filters);
            if (empty($res['success'])) {
                $m['error'] = $res['error'] ?? 'Falha na busca';
                $this->logCampaign($camp['id'], 'search_failed', $m['error'] . ' (página ' . $page . ')');
                break;
            }
            $people = $res['data']['people'] ?? ($res['data']['contacts'] ?? []);
            $pagination = $res['data']['pagination'] ?? [];
            $totalPages = (int)($pagination['total_pages'] ?? ($totalPages ?? 1));
            $m['pages']++;
            $m['searched'] += count($people);
            $this->logCampaign($camp['id'], 'searched', count($people) . ' candidatos (página ' . $page . ')');

            if (empty($people)) { $page = 1; break; } // sem resultados: reinicia ciclo

            // Qualifica os candidatos desta página (tudo barato: usa dados da busca)
            $candidates = [];
            foreach ($people as $p) {
                if (empty($p['id'])) continue;
                $m['analyzed']++;

                // DEDUP antes de qualquer reveal — jamais gasta crédito com quem já conhecemos.
                if ($this->isDuplicate($p, $camp)) { $m['duplicated']++; continue; }

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

            // Seleciona os melhores desta página até completar a meta restante
            usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
            $remaining = $target - $m['enrolled'];
            $selected = array_slice($candidates, 0, max(0, $remaining));
            $m['selected'] += count($selected);

            // Só agora consome crédito: revela, cria o lead e inscreve
            foreach ($selected as $c) {
                $r = $this->revealAndEnroll($camp, $c['person'], $c['local_id'], $c['score']);
                if ($r === 'enrolled') { $m['revealed_email']++; $m['imported']++; $m['enrolled']++; }
                elseif ($r === 'reveal_failed') { $m['reveal_failed']++; }
            }

            // Avança a página (circular pelo total conhecido)
            $page = ($totalPages > 0 && $page >= $totalPages) ? 1 : $page + 1;
            if ($totalPages > 0 && $m['pages'] >= $totalPages) break; // varreu tudo
        }

        // Persiste o cursor de busca e os contadores de estado
        $this->db->update('apollo_campaigns', ['search_page' => $page], 'id = ?', [$camp['id']]);
        $this->saveCampaignStats($camp['id'], $m);
        $this->logCampaign($camp['id'], 'done', json_encode($m, JSON_UNESCAPED_UNICODE));
        return $m;
    }

    /**
     * Origem MEUS LEADS — inscreve leads já existentes no CRM na sequência.
     * NÃO faz busca/reveal na Apollo e NÃO altera o responsável do lead.
     *
     * Fluxo: Meus Leads → aplicar filtros → verificar elegibilidade/dup → inscrever.
     * @return array métricas da campanha
     */
    public function runMyLeadsCampaign(array $camp, $target)
    {
        $m = [
            'campaign' => $camp['id'], 'name' => $camp['name'], 'source' => 'my_leads',
            'searched' => 0, 'analyzed' => 0, 'duplicated' => 0, 'out_of_icp' => 0, 'low_score' => 0,
            'selected' => 0, 'revealed_email' => 0, 'reveal_failed' => 0, 'imported' => 0, 'enrolled' => 0,
            'pages' => 0,
        ];

        $sequenceId = (int)($camp['sequence_id'] ?? 0);
        if (!$sequenceId) {
            $m['error'] = 'Campanha sem sequência configurada.';
            $this->saveCampaignStats($camp['id'], $m);
            $this->logCampaign($camp['id'], 'done', json_encode($m, JSON_UNESCAPED_UNICODE));
            return $m;
        }

        $filters = json_decode($camp['my_leads_filters'] ?? '{}', true) ?: [];
        // Candidatos: leads do CRM com e-mail, não descadastrados, elegíveis pelos filtros.
        $rows = $this->fetchMyLeadsCandidates($filters, max(1, $target) * 5);
        $m['searched'] = count($rows);

        $engine = new SequenceEngine();
        foreach ($rows as $lead) {
            if ($m['enrolled'] >= $target) break;
            $m['analyzed']++;

            // Já inscrito nesta sequência (ativo/pausado)? ignora.
            if ($this->alreadyInSequence((int)$lead['id'], $sequenceId)) { $m['duplicated']++; continue; }

            $r = $engine->enroll($sequenceId, (int)$lead['id'], $camp['created_by'] ?: null);
            if (!empty($r['success'])) {
                $m['enrolled']++;
                $this->logEnrolled($camp['id'], (int)$lead['id'], 'Meus Leads → sequência');
            } else {
                $m['duplicated']++;
            }
        }

        $this->saveCampaignStats($camp['id'], $m);
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

        // REGRA DE PROPRIEDADE: todo lead NOVO captado automaticamente pela Apollo
        // pertence ao Super Admin (sem round-robin, sem o criador, sem último responsável).
        $superAdminId = $this->superAdminId();

        // Existe? (dedup central por e-mail revelado) — para preservar dono de lead já existente.
        $resolver = new LeadResolver();
        $preExistingId = $resolver->findByEmail($email);

        // Cria/atualiza o Lead via LeadResolver (dedup central; nunca base paralela).
        // Só define assigned_to (Super Admin) quando o lead é NOVO.
        $contactId = $resolver->resolve([
            'name' => $name,
            'email' => $email,
            'company' => $org['name'] ?? null,
            'source' => 'apollo',
            'assigned_to' => $preExistingId ? null : ($superAdminId ?: null),
            'briefing' => [
                'need' => $org['industry'] ?? null,
                'notes' => implode(' | ', $notesParts) ?: null,
            ],
        ], $camp['created_by'] ?: null);

        if (!$contactId) return 'reveal_failed';

        // Garante a atribuição ao Super Admin para leads NOVOS (mesmo que o resolver
        // não a tenha aplicado). Nunca sobrescreve o dono de um lead pré-existente.
        if (!$preExistingId && $superAdminId) {
            $this->db->update('whatsapp_contacts', ['assigned_to' => $superAdminId], 'id = ? AND (assigned_to IS NULL OR assigned_to = 0)', [$contactId]);
        }

        // Vincula o staging ao lead do CRM + registra origem/campanha/ICP
        $leadModel->markImported($localId, $contactId, $camp['created_by'] ?: null);

        // Score inicial do lead
        try { (new LeadScoreService())->add($contactId, (int)$score, 'apollo_prospecting'); } catch (\Throwable $e) {}
        (new LeadTimelineService())->add($contactId, 'origin',
            'Capturado via Apollo (campanha: ' . $camp['name'] . ')',
            ['score' => $score, 'campaign_id' => $camp['id'], 'icp' => json_decode($camp['icp_rules'] ?? '{}', true)]);
        $this->logImported($camp['id'], $localId, $contactId, $name . ($preExistingId ? ' (existente)' : ' (novo → Super Admin)'));

        // Board: cria card na coluna configurada (evita duplicar se já houver)
        if (!empty($camp['column_id'])) {
            $exists = $this->db->fetch("SELECT id FROM crm_cards WHERE contact_id = ? LIMIT 1", [$contactId]);
            if (!$exists) {
                (new CrmBoard())->createCard([
                    'column_id' => (int)$camp['column_id'],
                    'title' => $name,
                    'contact_id' => $contactId,
                    'created_by' => $camp['created_by'] ?: null,
                    'assigned_to' => $preExistingId ? null : ($superAdminId ?: null),
                ]);
            }
        }

        // Sequência: inscreve o lead (idempotente por sequence+contact)
        if (!empty($camp['sequence_id'])) {
            (new SequenceEngine())->enroll((int)$camp['sequence_id'], $contactId, $camp['created_by'] ?: null);
            $this->logEnrolled($camp['id'], $contactId, 'Apollo → sequência');
        }

        return 'enrolled';
    }

    /** ID do primeiro Super Admin ativo (destino padrão dos leads novos da Apollo). */
    private function superAdminId()
    {
        $r = $this->db->fetch("SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1");
        return $r ? (int)$r['id'] : null;
    }

    // ============ Deduplicação ============

    /**
     * Deduplicação COMPLETA antes de gastar qualquer crédito.
     * Uma pessoa jamais é prospectada duas vezes. Verifica, prioritariamente por
     * IDs únicos da Apollo e complementarmente por e-mail/telefone:
     *   1) staging Apollo já importado (apollo_id → contact_id)
     *   2) já processado/prospectado nesta campanha (log enrolled com este apollo_lead)
     *   3) já captado por QUALQUER campanha automática (quando global_dedupe=1)
     *   4) já existe em Meus Leads (por e-mail ou telefone)
     *   5) já está inscrito na sequência da campanha
     * NÃO usa nome como critério.
     */
    private function isDuplicate(array $person, array $camp)
    {
        $apolloId = $person['id'] ?? null;
        $leadModel = new ApolloLead();

        // 1) Staging Apollo já vinculado a um lead do CRM
        if ($apolloId) {
            $staging = $leadModel->findByApolloId($apolloId);
            if ($staging && !empty($staging['contact_id'])) {
                // 2/3) já prospectado por esta ou outra campanha
                if ($this->stagingAlreadyProspected((int)$staging['id'], $camp)) return true;
                // 4) o contato existe → verifica sequência
                if ($this->alreadyInSequence((int)$staging['contact_id'], (int)($camp['sequence_id'] ?? 0))) return true;
                return true; // já importado antes: nunca revela de novo
            }
        }

        // 4) Já existe em Meus Leads por e-mail (dado da busca, quando presente) ou telefone
        $resolver = new LeadResolver();
        $email = $resolver->normalizeEmail($this->extractEmail($person));
        $contactId = null;
        if ($email) {
            $r = $this->db->fetch("SELECT id FROM whatsapp_contacts WHERE lead_email = ? LIMIT 1", [$email]);
            if ($r) $contactId = (int)$r['id'];
        }
        if (!$contactId) {
            $phone = $resolver->normalizePhone($this->extractPhoneFromPerson($person));
            if ($phone && strlen($phone) >= 8) {
                $last8 = substr($phone, -8);
                $r = $this->db->fetch(
                    "SELECT id FROM whatsapp_contacts
                     WHERE COALESCE(is_group,0)=0
                       AND REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') LIKE ?
                     LIMIT 1",
                    ['%' . $last8]
                );
                if ($r) $contactId = (int)$r['id'];
            }
        }
        if ($contactId) {
            // 5) já inscrito na sequência? de qualquer forma, lead já conhecido → não reprospecta
            return true;
        }

        return false;
    }

    /** Verifica se o staging já foi prospectado (log 'enrolled') nesta campanha ou em qualquer uma (global). */
    private function stagingAlreadyProspected($stagingId, array $camp)
    {
        $global = !empty($camp['global_dedupe']);
        if ($global) {
            $r = $this->db->fetch(
                "SELECT id FROM apollo_prospecting_log WHERE apollo_lead_id = ? AND action = 'enrolled' LIMIT 1",
                [$stagingId]
            );
            return (bool)$r;
        }
        $r = $this->db->fetch(
            "SELECT id FROM apollo_prospecting_log WHERE apollo_lead_id = ? AND campaign_id = ? AND action = 'enrolled' LIMIT 1",
            [$stagingId, $camp['id']]
        );
        return (bool)$r;
    }

    /** Verifica se o contato já é participante ativo/pausado/finalizado da sequência. */
    private function alreadyInSequence($contactId, $sequenceId)
    {
        if (!$contactId || !$sequenceId) return false;
        $r = $this->db->fetch(
            "SELECT id FROM sequence_participants WHERE sequence_id = ? AND contact_id = ? LIMIT 1",
            [$sequenceId, $contactId]
        );
        return (bool)$r;
    }

    /** Extrai o primeiro telefone disponível do payload da busca (sem reveal). */
    private function extractPhoneFromPerson(array $person)
    {
        if (!empty($person['phone_numbers']) && is_array($person['phone_numbers'])) {
            foreach ($person['phone_numbers'] as $ph) {
                if (!empty($ph['sanitized_number'])) return $ph['sanitized_number'];
                if (!empty($ph['raw_number'])) return $ph['raw_number'];
            }
        }
        return $person['sanitized_phone'] ?? ($person['phone'] ?? null);
    }

    // ============ Meus Leads (origem alternativa) ============

    /**
     * Busca candidatos em "Meus Leads" (CRM) elegíveis à inscrição na sequência.
     * Só retorna leads com e-mail válido e não descadastrados. Aplica filtros
     * opcionais: temperatura, fonte, responsável.
     */
    private function fetchMyLeadsCandidates(array $filters, $limit)
    {
        $sql = "SELECT c.id, c.contact_name, c.lead_email
                FROM whatsapp_contacts c
                LEFT JOIN commercial_briefings b ON b.contact_id = c.id
                WHERE COALESCE(c.is_group,0)=0
                  AND c.lead_email IS NOT NULL AND c.lead_email <> ''
                  AND COALESCE(c.unsubscribed,0)=0
                  AND COALESCE(c.email_bounced,0)=0
                  AND COALESCE(c.crm_archived,0)=0";
        $params = [];

        if (!empty($filters['temperature'])) { $sql .= " AND b.lead_temperature = ?"; $params[] = $filters['temperature']; }
        if (!empty($filters['source']))      { $sql .= " AND b.lead_source = ?";      $params[] = $filters['source']; }
        if (!empty($filters['assigned_to'])) { $sql .= " AND c.assigned_to = ?";       $params[] = (int)$filters['assigned_to']; }

        $sql .= " ORDER BY c.last_message_at IS NULL, c.last_message_at DESC LIMIT " . (int)$limit;
        return $this->db->fetchAll($sql, $params);
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

    /** Log de importação do lead em Meus Leads (com vínculo ao contato). */
    private function logImported($campaignId, $stagingId, $contactId, $detail = null)
    {
        try {
            $this->db->insert('apollo_prospecting_log', [
                'campaign_id' => $campaignId,
                'apollo_lead_id' => $stagingId,
                'contact_id' => $contactId,
                'action' => 'imported',
                'detail' => $detail,
            ]);
        } catch (\Throwable $e) { /* silencioso */ }
    }

    /**
     * Log de inscrição na sequência. É a métrica usada por capturedToday() e pelas
     * estatísticas "captado" — grava contact_id para relatórios.
     */
    private function logEnrolled($campaignId, $contactId, $detail = null)
    {
        try {
            $this->db->insert('apollo_prospecting_log', [
                'campaign_id' => $campaignId,
                'contact_id' => $contactId,
                'action' => 'enrolled',
                'detail' => $detail,
            ]);
        } catch (\Throwable $e) { /* silencioso */ }
    }

    /** Persiste os contadores de estado da última execução na própria campanha. */
    private function saveCampaignStats($campaignId, array $m)
    {
        try {
            $this->db->update('apollo_campaigns', [
                'stat_analyzed'   => (int)($m['analyzed'] ?? 0),
                'stat_discarded'  => (int)($m['out_of_icp'] ?? 0) + (int)($m['low_score'] ?? 0),
                'stat_duplicated' => (int)($m['duplicated'] ?? 0),
                'stat_revealed'   => (int)($m['revealed_email'] ?? 0),
                'stat_imported'   => (int)($m['imported'] ?? 0),
                'stat_enrolled'   => (int)($m['enrolled'] ?? 0),
                'last_run_at'     => date('Y-m-d H:i:s'),
                'last_error'      => $m['error'] ?? null,
            ], 'id = ?', [$campaignId]);
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
