<?php

/**
 * Resolução de identidade única do Lead.
 *
 * TODAS as fontes (Apollo, 99Freelas, manual, envio de e-mail, import) passam por
 * aqui antes de criar/atualizar um Lead. O Lead é o whatsapp_contacts existente —
 * não há base paralela.
 *
 * Ordem de deduplicação (usa o que estiver disponível):
 *   1) e-mail normalizado (lead_email)
 *   2) telefone (últimos 8 dígitos)
 *   3) nome + empresa (heurística fraca — só quando não há e-mail/telefone)
 *
 * Retorna sempre o contact_id (Lead) e registra a origem/interação na timeline.
 */
class LeadResolver
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Encontra ou cria o Lead a partir de dados de qualquer fonte.
     *
     * @param array $data {
     *   name, email, phone, company, source ('apollo'|'freelas99'|'manual'|'manual_email'|'import'|'form'|'api'),
     *   assigned_to, title, description, source_url, briefing (array opcional)
     * }
     * @param int|null $userId  quem originou (para timeline)
     * @return int|null contact_id, ou null se não foi possível criar
     */
    public function resolve(array $data, $userId = null)
    {
        $email = $this->normalizeEmail($data['email'] ?? null);
        $phoneDigits = $this->normalizePhone($data['phone'] ?? null);

        $linkedin = $this->normalizeLinkedin($data['linkedin_url'] ?? null);
        $existing = $this->findExisting($email, $phoneDigits, $data['name'] ?? null, $data['company'] ?? null, $linkedin);

        if ($existing) {
            $contactId = (int) $existing['id'];
            $this->enrichExisting($contactId, $existing, $data);
            // Registra a nova origem/interação como evento na timeline (não muda origem inicial)
            $this->timeline($contactId, 'origin', 'Nova interação via ' . ($data['source'] ?? 'origem'), [
                'source' => $data['source'] ?? null,
            ], $userId);
            return $contactId;
        }

        // Não existe: cria o Lead
        return $this->createLead($data, $email, $phoneDigits, $userId);
    }

    // ---- Busca de duplicidade ----

    private function findExisting($email, $phoneDigits, $name, $company, $linkedin = null)
    {
        // 1) e-mail
        if ($email) {
            $r = $this->db->fetch(
                "SELECT * FROM whatsapp_contacts WHERE lead_email = ? AND COALESCE(is_group,0)=0 LIMIT 1",
                [$email]
            );
            if ($r) return $r;
        }
        // 1.5) LinkedIn URL (identificador forte da prospecção híbrida)
        if ($linkedin) {
            $r = $this->db->fetch(
                "SELECT * FROM whatsapp_contacts WHERE linkedin_url = ? AND COALESCE(is_group,0)=0 LIMIT 1",
                [$linkedin]
            );
            if ($r) return $r;
        }
        // 2) telefone (últimos 8 dígitos)
        if ($phoneDigits && strlen($phoneDigits) >= 8) {
            $last8 = substr($phoneDigits, -8);
            $r = $this->db->fetch(
                "SELECT * FROM whatsapp_contacts
                 WHERE COALESCE(is_group,0)=0
                   AND REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') LIKE ?
                 LIMIT 1",
                ['%' . $last8]
            );
            if ($r) return $r;
        }
        // 3) nome + empresa (heurística fraca — só se não há e-mail nem telefone)
        if (!$email && !$phoneDigits && !empty($name) && !empty($company)) {
            $r = $this->db->fetch(
                "SELECT wc.* FROM whatsapp_contacts wc
                 LEFT JOIN commercial_briefings b ON b.contact_id = wc.id
                 WHERE wc.contact_name = ? AND COALESCE(is_group,0)=0 LIMIT 1",
                [$name]
            );
            if ($r) return $r;
        }
        return null;
    }

    // ---- Criação ----

    private function createLead(array $data, $email, $phoneDigits, $userId)
    {
        $instance = $this->db->fetch("SELECT id FROM whatsapp_instances WHERE is_default = 1 LIMIT 1")
            ?: $this->db->fetch("SELECT id FROM whatsapp_instances LIMIT 1");
        if (!$instance) return null; // sem instância não é possível criar contato

        $jid = ($phoneDigits ?: 'lead_' . uniqid()) . '@s.whatsapp.net';
        $contactId = $this->db->insert('whatsapp_contacts', [
            'instance_id' => $instance['id'],
            'remote_jid' => $jid,
            'phone' => $phoneDigits ?: null,
            'lead_email' => $email ?: null,
            'linkedin_url' => $this->normalizeLinkedin($data['linkedin_url'] ?? null),
            'contact_name' => $data['name'] ?? 'Lead',
            'assigned_to' => $data['assigned_to'] ?? null,
            'lead_source_url' => $data['source_url'] ?? null,
        ]);

        // Briefing (origem + campos comerciais opcionais)
        $briefing = $data['briefing'] ?? [];
        $briefing['lead_source'] = $data['source'] ?? 'manual';
        if (!empty($data['description']) && empty($briefing['need'])) {
            $briefing['need'] = mb_substr($data['description'], 0, 500);
        }
        $this->saveBriefing($contactId, $briefing, $userId);

        $this->timeline($contactId, 'created', 'Lead criado', ['source' => $data['source'] ?? 'manual'], $userId);
        (new LeadScoreService())->ensure($contactId);

        return $contactId;
    }

    private function enrichExisting($contactId, $existing, array $data)
    {
        $update = [];
        // Preenche e-mail/telefone se estavam vazios (não sobrescreve)
        if (empty($existing['lead_email']) && !empty($data['email'])) {
            $update['lead_email'] = $this->normalizeEmail($data['email']);
        }
        if (empty($existing['phone']) && !empty($data['phone'])) {
            $update['phone'] = $this->normalizePhone($data['phone']);
        }
        if (empty($existing['lead_source_url']) && !empty($data['source_url'])) {
            $update['lead_source_url'] = $data['source_url'];
        }
        // LinkedIn: preenche se o contato ainda não tinha (não sobrescreve).
        if (empty($existing['linkedin_url']) && !empty($data['linkedin_url'])) {
            $update['linkedin_url'] = $this->normalizeLinkedin($data['linkedin_url']);
        }
        if (!empty($update)) {
            $this->db->update('whatsapp_contacts', $update, 'id = ?', [$contactId]);
        }
    }

    // ---- Helpers ----

    /** Normaliza a URL do LinkedIn (trim; ignora vazio). Não faz scraping. */
    public function normalizeLinkedin($url)
    {
        $url = trim((string) $url);
        return $url !== '' ? $url : null;
    }

    public function normalizeEmail($email)
    {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        return mb_strtolower($email);
    }

    public function normalizePhone($phone)
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        return $d !== '' ? $d : null;
    }

    private function saveBriefing($contactId, $data, $userId)
    {
        try {
            (new WhatsappContact())->saveBriefing($contactId, $data, $userId);
        } catch (\Throwable $e) { /* silencioso */ }
    }

    private function timeline($contactId, $type, $desc, $meta, $userId)
    {
        try {
            (new LeadTimelineService())->add($contactId, $type, $desc, $meta, $userId);
        } catch (\Throwable $e) { /* silencioso */ }
    }

    /**
     * Localiza um Lead apenas por e-mail (usado no envio manual e reply detection).
     * @return int|null contact_id
     */
    public function findByEmail($email)
    {
        $email = $this->normalizeEmail($email);
        if (!$email) return null;
        $r = $this->db->fetch("SELECT id FROM whatsapp_contacts WHERE lead_email = ? LIMIT 1", [$email]);
        return $r ? (int) $r['id'] : null;
    }
}
