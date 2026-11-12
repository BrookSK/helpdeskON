<?php

/**
 * Regras de negócio puras (sem banco/HTTP) para a vinculação de usuários aos
 * campos de responsabilidade de uma demanda (card de Planejamento).
 *
 * Fonte única e testável de QUAL papel (users.role) pode ocupar cada campo:
 *   - Atendente (assigned_to)
 *   - Técnico  (technical_responsible_id)
 *   - Analista (analyst_id)
 *
 * Hierarquia de funções exigida pelo negócio (traduzida para os papéis reais
 * do sistema — repare que não existe o papel "tecnico": o técnico de fato é o
 * developer, e atendente/analista TAMBÉM podem ser escolhidos como técnico):
 *   - Atendente (attendant) PODE ser Técnico.
 *   - Analista  (analyst)   PODE ser Técnico.
 *   - Técnico   (developer) NÃO PODE ser Analista.
 *
 * Usada tanto para montar os seletores (quem aparece no dropdown) quanto para
 * validar no servidor o valor recebido (defesa contra POST forjado). As duas
 * pontas consomem a MESMA regra, evitando divergência entre tela e persistência.
 */
class AssignmentRules
{
    /** Campos de vinculação suportados. */
    public const FIELD_ATTENDANT = 'atendente';
    public const FIELD_TECHNICAL = 'tecnico';
    public const FIELD_ANALYST   = 'analista';

    /**
     * Mapa campo => papéis elegíveis.
     *
     * Atendente: mantém o conjunto histórico (dono do card pode ser qualquer
     *            membro interno, inclusive developer e analyst).
     * Técnico:   developer + attendant + analyst + super_admin.
     * Analista:  analyst + super_admin (developer/"técnico" fica de fora — é a
     *            regra central "técnico não pode ser analista").
     */
    private const ELIGIBLE = [
        self::FIELD_ATTENDANT => ['super_admin', 'attendant', 'whatsapp_agent', 'analyst', 'developer'],
        self::FIELD_TECHNICAL => ['developer', 'attendant', 'analyst', 'super_admin'],
        self::FIELD_ANALYST   => ['analyst', 'super_admin'],
    ];

    /**
     * Lista de papéis elegíveis para um campo. Retorna [] para campo desconhecido.
     */
    public static function rolesForField(string $field): array
    {
        return self::ELIGIBLE[$field] ?? [];
    }

    /**
     * Um usuário com o papel $role pode ocupar o campo $field?
     *
     * $role vazio/nulo => false (não há papel para validar). A ausência de
     * atribuição (valor vazio no POST) é tratada ANTES, no controller, como
     * "remover vínculo"; esta checagem só vale quando há de fato um papel.
     */
    public static function canAssign(string $field, ?string $role): bool
    {
        if ($role === null || $role === '') {
            return false;
        }
        return in_array($role, self::rolesForField($field), true);
    }

    /** Coluna do card de Planejamento correspondente a cada campo. */
    public const CARD_COLUMNS = [
        self::FIELD_ATTENDANT => 'assigned_to',
        self::FIELD_TECHNICAL => 'technical_responsible_id',
        self::FIELD_ANALYST   => 'analyst_id',
    ];

    /**
     * Na EDIÇÃO de um card, quais responsáveis precisam ser validados: só os
     * que vieram na requisição E mudaram em relação ao card gravado.
     *
     * O modal do Planejamento envia sempre os três campos. Validar também os
     * que não mudaram fazia um card antigo, com um valor fora da regra atual
     * (ex.: developer como Analista), bloquear qualquer edição — inclusive a
     * troca do Atendente. Um valor NOVO continua sempre validado.
     *
     * @param array $card Card atual (colunas assigned_to, technical_responsible_id, analyst_id).
     * @param array $data Campos recebidos para gravação.
     * @return array<string,int|string|null> campo (FIELD_*) => id a validar.
     */
    public static function changedForValidation(array $card, array $data): array
    {
        $out = [];
        foreach (self::CARD_COLUMNS as $field => $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $new = (int) ($data[$col] ?? 0);
            $old = (int) ($card[$col] ?? 0);
            if ($new !== $old) {
                $out[$field] = $data[$col];
            }
        }
        return $out;
    }

    /** Todos os campos suportados (útil para iterar na validação). */
    public static function fields(): array
    {
        return [self::FIELD_ATTENDANT, self::FIELD_TECHNICAL, self::FIELD_ANALYST];
    }
}
