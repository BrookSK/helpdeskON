<?php

/**
 * Texto fixo da proposta comercial (quem somos, método, condições).
 * Serviços, valores, cliente e cronograma ficam na proposta, não aqui.
 */
class ProposalBlock
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Lista os blocos, semeando o texto padrão na primeira leitura. */
    public function getAll(): array
    {
        $this->ensureSeeded();
        return $this->db->fetchAll("SELECT * FROM proposal_blocks ORDER BY position ASC, id ASC");
    }

    /** Blocos com texto, na ordem do documento. */
    public function activeForDocument(): array
    {
        $rows = [];
        foreach ($this->getAll() as $b) {
            if ((int)($b['active'] ?? 1) !== 1) continue;
            if (trim((string)($b['body'] ?? '')) === '' && trim((string)($b['title'] ?? '')) === '') continue;
            $rows[] = $b;
        }
        return $rows;
    }

    /**
     * Atualiza título, texto e posição no documento (antes ou depois dos serviços).
     *
     * @param array<int,array> $blocks
     */
    public function saveAll(array $blocks): void
    {
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            $id = (int)($b['id'] ?? 0);
            if ($id <= 0) continue;
            $placement = (($b['placement'] ?? '') === 'after') ? 'after' : 'before';
            $this->db->update('proposal_blocks', [
                'title' => mb_substr(trim((string)($b['title'] ?? '')), 0, 200),
                'body' => trim((string)($b['body'] ?? '')),
                'placement' => $placement,
            ], 'id = ?', [$id]);
        }
    }

    /** Payload enxuto para gravar em proposals.fixed_snapshot. */
    public function snapshot(): array
    {
        $out = [];
        foreach ($this->activeForDocument() as $b) {
            $out[] = [
                'block_key' => (string)$b['block_key'],
                'title' => (string)$b['title'],
                'body' => (string)($b['body'] ?? ''),
                'placement' => (($b['placement'] ?? '') === 'after') ? 'after' : 'before',
            ];
        }
        return $out;
    }

    private function ensureSeeded(): void
    {
        $row = $this->db->fetch("SELECT COUNT(*) AS n FROM proposal_blocks");
        if ((int)($row['n'] ?? 0) > 0) return;
        foreach (ProposalRules::defaultBlocks() as $b) {
            $this->db->insert('proposal_blocks', [
                'block_key' => $b['block_key'],
                'title' => $b['title'],
                'body' => $b['body'],
                'placement' => $b['placement'],
                'position' => (int)$b['position'],
                'active' => 1,
            ]);
        }
    }
}
