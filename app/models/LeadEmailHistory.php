<?php
/**
 * Histórico de e-mails de um lead (aba "E-mails" do card no CRM).
 *
 * Lê o que o sistema registrou: email_messages (envios de sequência/manuais e
 * respostas do lead) e email_prospections (respostas mandadas pela Caixa de
 * entrada). A junção e o agrupamento em conversas ficam em EmailThreadRules.
 */
class LeadEmailHistory
{
    /** Teto de linhas por fonte (lead com histórico enorme não trava o card). */
    const LIMIT = 300;

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param bool $withBodies false = só os dados para contar (sem o corpo dos e-mails)
     * @return array ['summary' => [...], 'conversations' => [...]]
     */
    public function forContact(int $contactId, bool $withBodies = true): array
    {
        $contact = $this->db->fetch("SELECT id, lead_email FROM whatsapp_contacts WHERE id = ?", [$contactId]);
        if (!$contact) return EmailThreadRules::conversations([]);
        $email = mb_strtolower(trim((string) ($contact['lead_email'] ?? '')));

        return EmailThreadRules::conversations(EmailThreadRules::merge(
            $this->messages($contactId, $email, $withBodies),
            $this->prospections($contactId, $email, $withBodies)
        ));
    }

    private function messages(int $contactId, string $email, bool $withBodies): array
    {
        $cols = "m.id, m.direction, m.origin, m.subject, m.status, m.error_message, m.sent_at, m.created_at,
                 m.replied_at, m.open_count, m.click_count, m.first_open_at, m.message_id, m.in_reply_to,
                 m.recipient_email, u.name AS sender_name, s.name AS sequence_name";
        // Colunas da migration 140 (resposta casada por cabeçalho), se já aplicada.
        foreach (['received_at', 'references_header', 'reply_snippet'] as $c) {
            if (SchemaInfo::hasColumn('email_messages', $c)) $cols .= ", m.{$c}";
        }
        if ($withBodies) $cols .= ", m.body";

        // Respostas que chegaram do e-mail do lead mas ficaram em outro cadastro
        // (lead duplicado) também entram, pelo thread_key (= e-mail do remetente).
        $where = "m.contact_id = ?";
        $params = [$contactId];
        if ($email !== '') {
            $where .= " OR (m.direction = 'inbound' AND m.thread_key = ?)";
            $params[] = $email;
        }

        return $this->db->fetchAll(
            "SELECT $cols
             FROM email_messages m
             LEFT JOIN users u ON u.id = m.sent_by
             LEFT JOIN sequence_participants sp ON sp.id = m.sequence_participant_id
             LEFT JOIN email_sequences s ON s.id = sp.sequence_id
             WHERE $where
             ORDER BY m.id DESC LIMIT " . self::LIMIT,
            $params
        );
    }

    private function prospections(int $contactId, string $email, bool $withBodies): array
    {
        $cols = "p.id, p.subject, p.status, p.error_message, p.sent_at, p.created_at, p.recipient_email, u.name AS sender_name";
        if ($withBodies) $cols .= ", p.body";
        $where = "p.contact_id = ?";
        $params = [$contactId];
        if ($email !== '') {
            $where .= " OR p.recipient_email = ?";
            $params[] = $email;
        }
        try {
            return $this->db->fetchAll(
                "SELECT $cols
                 FROM email_prospections p
                 LEFT JOIN users u ON u.id = p.user_id
                 WHERE ($where) AND p.status <> 'draft'
                 ORDER BY p.id DESC LIMIT " . self::LIMIT,
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}
