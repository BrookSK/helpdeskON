<?php

/**
 * Score comercial ÚNICO do Lead (automático, por interação).
 * Não há score Apollo/Sequence/Manual separados.
 *
 * Pesos: abertura +1 · clique +3 · resposta +5 · bounce -5
 * Classificação: 0 frio · 1-2 morno · 3-4 engajado · 5+ quente
 */
class LeadScoreService
{
    private $db;

    const W_OPEN = 1;
    const W_CLICK = 3;
    const W_REPLY = 5;
    const W_BOUNCE = -5;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Garante a existência de uma linha de score para o lead. */
    public function ensure($contactId)
    {
        $r = $this->db->fetch("SELECT contact_id FROM lead_score WHERE contact_id = ?", [$contactId]);
        if (!$r) {
            $this->db->insert('lead_score', ['contact_id' => $contactId, 'score' => 0, 'classification' => 'frio']);
        }
    }

    public function add($contactId, $delta, $reason = null, $userId = null)
    {
        $this->ensure($contactId);
        $current = (int) ($this->db->fetch("SELECT score FROM lead_score WHERE contact_id = ?", [$contactId])['score'] ?? 0);
        $new = max(0, $current + (int) $delta);
        $classification = $this->classify($new);
        $this->db->update('lead_score', [
            'score' => $new,
            'classification' => $classification,
        ], 'contact_id = ?', [$contactId]);

        // Sincroniza a temperatura no briefing (campo já usado pelo CRM)
        $tempMap = ['frio' => 'frio', 'morno' => 'morno', 'engajado' => 'morno', 'quente' => 'quente'];
        try {
            (new WhatsappContact())->saveBriefing($contactId, ['lead_temperature' => $tempMap[$classification]], $userId);
        } catch (\Throwable $e) { /* silencioso */ }

        (new LeadTimelineService())->add($contactId, 'score',
            'Score atualizado para ' . $new . ' (' . $classification . ')' . ($reason ? ' — ' . $reason : ''),
            ['delta' => $delta, 'score' => $new, 'classification' => $classification], $userId);

        return ['score' => $new, 'classification' => $classification];
    }

    public function get($contactId)
    {
        $r = $this->db->fetch("SELECT score, classification FROM lead_score WHERE contact_id = ?", [$contactId]);
        return $r ?: ['score' => 0, 'classification' => 'frio'];
    }

    private function classify($score)
    {
        if ($score >= 5) return 'quente';
        if ($score >= 3) return 'engajado';
        if ($score >= 1) return 'morno';
        return 'frio';
    }
}
