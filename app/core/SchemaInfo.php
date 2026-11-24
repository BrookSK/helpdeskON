<?php
/**
 * Consulta (com cache por requisição) se uma coluna existe no banco atual.
 *
 * Usado por recursos que dependem de migrations novas: o código continua
 * funcionando (sem gravar o campo novo) enquanto a migration não for aplicada
 * no servidor. Mesmo padrão já usado em LeadResolver::hasLinkedinColumn.
 */
class SchemaInfo
{
    private static $cache = [];

    public static function hasColumn(string $table, string $column): bool
    {
        $key = strtolower($table . '.' . $column);
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        try {
            $r = Database::getInstance()->fetch(
                "SELECT COUNT(*) c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            );
            self::$cache[$key] = ((int) ($r['c'] ?? 0) > 0);
        } catch (\Throwable $e) {
            self::$cache[$key] = false;
        }
        return self::$cache[$key];
    }

    /** Limpa o cache (testes que criam/derrubam colunas). */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
