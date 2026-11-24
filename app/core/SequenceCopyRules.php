<?php
/**
 * Regras puras da duplicação de sequência (lista de Sequências → "Duplicar").
 *
 * A cópia leva TUDO o que define a sequência (fluxo/grafo, canal, contas de
 * envio, limites, janela, dias, status), mas não leva os leads inscritos nem o
 * histórico: isso pertence à sequência original.
 */
class SequenceCopyRules
{
    /** Tamanho de email_sequences.name. */
    const NAME_MAX = 150;

    /** Colunas que identificam a linha original e não podem ser copiadas. */
    const NOT_COPIED = ['id', 'created_at', 'updated_at'];

    /**
     * Nome da cópia: "X (cópia)", depois "X (cópia 2)", "X (cópia 3)"...
     * Duplicar uma cópia não empilha sufixos ("X (cópia) (cópia)").
     *
     * @param string   $name     nome da sequência original
     * @param string[] $existing nomes já usados (comparação sem maiúsculas)
     */
    public static function copyName(string $name, array $existing): string
    {
        $base = trim(preg_replace('/\s*\(cópia(?:\s+\d+)?\)\s*$/iu', '', trim($name)));
        if ($base === '') $base = 'Sequência';

        $taken = [];
        foreach ($existing as $n) $taken[mb_strtolower(trim((string) $n))] = true;

        $i = 1;
        do {
            $suffix = $i === 1 ? ' (cópia)' : " (cópia {$i})";
            $candidate = mb_substr($base, 0, self::NAME_MAX - mb_strlen($suffix)) . $suffix;
            $i++;
        } while (isset($taken[mb_strtolower($candidate)]));

        return $candidate;
    }

    /**
     * Linha da cópia a partir da linha original (todas as colunas da tabela).
     * Mantém configurações e grafo; troca nome e autor.
     */
    public static function copyRow(array $original, string $newName, ?int $userId): array
    {
        $row = $original;
        foreach (self::NOT_COPIED as $col) unset($row[$col]);
        $row['name'] = $newName;
        if ($userId) $row['created_by'] = $userId;
        return $row;
    }
}
