<?php

/**
 * Texto fixo da proposta comercial (quem somos, o que fazemos, clientes,
 * como fazemos, condições de pagamento, notas). Toda proposta traz todos os
 * blocos, na ordem de ProposalRules::defaultBlocks().
 */
class ProposalBlock
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Blocos na ordem do PDF. Cria os que faltarem com o texto padrão. */
    public function getAll(): array
    {
        $rows = [];
        foreach ($this->db->fetchAll("SELECT * FROM proposal_blocks") as $r) {
            $rows[$r['block_key']] = $r;
        }
        $out = [];
        foreach (ProposalRules::defaultBlocks() as $def) {
            $key = $def['block_key'];
            if (!isset($rows[$key])) {
                $id = $this->db->insert('proposal_blocks', [
                    'block_key' => $key,
                    'title' => $def['title'],
                    'body' => $def['body'],
                    'position' => (int)$def['position'],
                    'active' => 1,
                ]);
                $rows[$key] = ['id' => $id, 'block_key' => $key, 'title' => $def['title'], 'body' => $def['body']];
            }
            $out[] = $rows[$key] + ['kind' => $def['kind']];
        }
        return $out;
    }

    /**
     * Atualiza título e texto de cada bloco.
     *
     * @param array<int,array> $blocks
     */
    public function saveAll(array $blocks): void
    {
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            $id = (int)($b['id'] ?? 0);
            if ($id <= 0) continue;
            $this->db->update('proposal_blocks', [
                'title' => mb_substr(trim((string)($b['title'] ?? '')), 0, 200),
                'body' => trim((string)($b['body'] ?? '')),
            ], 'id = ?', [$id]);
        }
    }

    /** Payload para gravar em proposals.fixed_snapshot. */
    public function snapshot(): array
    {
        $out = [];
        foreach ($this->getAll() as $b) {
            $out[] = [
                'block_key' => (string)$b['block_key'],
                'title' => (string)$b['title'],
                'body' => (string)($b['body'] ?? ''),
            ];
        }
        return $out;
    }
}
