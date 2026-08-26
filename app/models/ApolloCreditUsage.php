<?php

/**
 * Controle de consumo diário de créditos Apollo por usuário.
 * O limite (users.apollo_daily_credits) é definido por usuário; 0 = sem limite.
 * O consumo é registrado por dia e reinicia automaticamente no dia seguinte.
 */
class ApolloCreditUsage
{
    // Custos conforme a documentação oficial do Apollo (People Enrichment):
    // 1 crédito para e-mail/dados demográficos; +8 créditos se um celular for retornado.
    const COST_EMAIL = 1;
    const COST_MOBILE = 8;

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Créditos já consumidos hoje pelo usuário. */
    public function usedToday($userId)
    {
        $row = $this->db->fetch(
            "SELECT credits_used FROM apollo_credit_usage WHERE user_id = ? AND usage_date = CURDATE()",
            [$userId]
        );
        return (int)($row['credits_used'] ?? 0);
    }

    /**
     * Verifica se o usuário pode consumir $cost créditos agora.
     * Retorna ['allowed'=>bool, 'limit'=>int, 'used'=>int, 'remaining'=>int].
     */
    public function check($user, $cost = 1)
    {
        $limit = (int)($user['apollo_daily_credits'] ?? 0);
        $used = $this->usedToday($user['id']);
        // limite 0 = ilimitado
        if ($limit <= 0) {
            return ['allowed' => true, 'limit' => 0, 'used' => $used, 'remaining' => -1];
        }
        $remaining = max(0, $limit - $used);
        return [
            'allowed' => ($used + $cost) <= $limit,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
        ];
    }

    /** Registra o consumo de $cost créditos hoje (upsert por usuário/dia). */
    public function consume($userId, $cost = 1)
    {
        $this->db->query(
            "INSERT INTO apollo_credit_usage (user_id, usage_date, credits_used)
             VALUES (?, CURDATE(), ?)
             ON DUPLICATE KEY UPDATE credits_used = credits_used + VALUES(credits_used)",
            [$userId, $cost]
        );
    }
}
