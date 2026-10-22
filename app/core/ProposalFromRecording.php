<?php

/**
 * Cria o rascunho de proposta a partir da minuta de uma gravação.
 *
 * Cliente (contato, nome, e-mail, telefone) e card do CRM vêm da reunião ligada
 * à sala. O entendimento do negócio vem da ata estruturada. Os itens são
 * sugeridos pela IA só entre os serviços ativos do catálogo (ProposalDraftRules).
 * Se a IA falhar, o rascunho nasce mesmo assim, com cliente e vínculo.
 * O cliente de IA é injetado (OpenAiClient em produção).
 */
class ProposalFromRecording
{
    private $db;
    private $ai;
    private $proposals;

    /** @param object $ai objeto com isConfigured() e chat($messages, $options) */
    public function __construct($ai)
    {
        $this->db = Database::getInstance();
        $this->ai = $ai;
        $this->proposals = new Proposal();
    }

    /** Proposta mais recente já gerada desta gravação, ou null. */
    public function existingFor(int $recordingId): ?array
    {
        $row = $this->db->fetch("SELECT id, title, status FROM proposals WHERE recording_id = ? ORDER BY id DESC LIMIT 1", [$recordingId]);
        return $row ?: null;
    }

    /**
     * @return array{id:int,ai_ok:bool,items:int}
     */
    public function create(array $rec, int $userId): array
    {
        $minutes = MeetingMinutesRules::normalizeStructured($rec['summary'] ?? null)
            ?? MeetingMinutesRules::normalizeStructured(['resumo' => (string)($rec['minutes'] ?? '')]);

        $room = $this->db->fetch("SELECT * FROM video_rooms WHERE id = ?", [$rec['room_id']]);
        $meeting = (!empty($room['meeting_id']))
            ? $this->db->fetch(
                "SELECT m.*, wc.lead_email, wc.phone AS crm_phone, wc.contact_name AS crm_name
                 FROM agenda_meetings m LEFT JOIN whatsapp_contacts wc ON wc.id = m.contact_id
                 WHERE m.id = ?",
                [$room['meeting_id']]
            )
            : null;
        $contactId = !empty($meeting['contact_id']) ? (int)$meeting['contact_id'] : null;
        $card = $contactId
            ? $this->db->fetch("SELECT id FROM crm_cards WHERE contact_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1", [$contactId])
            : null;

        $clientName = trim((string)($meeting['client_name'] ?? '')) ?: trim((string)($meeting['crm_name'] ?? ''));
        $title = 'Proposta' . ($clientName !== '' ? ' - ' . $clientName : (!empty($room['title']) ? ' - ' . $room['title'] : ''));

        // Sugestão de itens pela IA (só catálogo ativo). Falha não bloqueia.
        $services = (new ServiceCatalog())->getAll(true);
        $filtered = ['items' => [], 'notes' => [], 'outside' => [], 'discarded' => 0];
        $aiOk = false;
        if ($this->ai->isConfigured() && $services) {
            $res = $this->ai->chat(ProposalDraftRules::buildMessages($minutes, $services), [
                'model' => 'gpt-4o-mini', 'temperature' => 0.2, 'max_tokens' => 1500,
                'response_format' => ['type' => 'json_object'], 'timeout' => 60,
            ]);
            if (!empty($res['success']) && is_array(json_decode((string)($res['content'] ?? ''), true))) {
                $filtered = ProposalDraftRules::filterSuggestions((string)$res['content'], $services);
                $aiOk = true;
            }
        }

        $content = array_merge(ProposalRules::defaultContent(), ProposalDraftRules::businessContent($minutes));
        $id = (int)$this->proposals->create([
            'title' => mb_substr($title, 0, 200),
            'contact_id' => $contactId,
            'card_id' => $card ? (int)$card['id'] : null,
            'recording_id' => (int)$rec['id'],
            'client_name' => $clientName !== '' ? mb_substr($clientName, 0, 200) : null,
            'client_email' => trim((string)($meeting['client_email'] ?? '')) ?: (trim((string)($meeting['lead_email'] ?? '')) ?: null),
            'client_phone' => trim((string)($meeting['client_phone'] ?? '')) ?: (trim((string)($meeting['crm_phone'] ?? '')) ?: null),
            'contract_type' => ProposalRules::normalizeContractType(''),
            'observations' => ProposalDraftRules::observations($filtered) ?: null,
            'status' => ProposalRules::STATUS_DRAFT,
            'total' => 0,
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
        ]);
        if ($filtered['items']) $this->proposals->replaceItems($id, $filtered['items']);
        $this->proposals->addEvent($id, $userId, 'created', 'Proposta criada a partir da minuta da gravação ' . $rec['token']);

        return ['id' => $id, 'ai_ok' => $aiOk, 'items' => count($filtered['items'])];
    }
}
