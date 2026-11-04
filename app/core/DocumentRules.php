<?php

/**
 * Regras puras (sem banco/HTTP) dos DOCUMENTOS compartilhados (aba Documentos).
 *
 * Fonte única de verdade para a visibilidade, depois que a opção "Todos" (all)
 * foi removida. Hoje só existem dois valores:
 *   - 'company' : apenas para a empresa de destino (e visível à equipe interna).
 *   - 'team'    : apenas equipe interna; nunca exposto a clientes.
 *
 * Mesmo padrão de AgendaRules, CrmRules, Permissions, TicketAccess: lógica de
 * decisão isolada e testável por teste unitário.
 */
class DocumentRules
{
    /** Visibilidades válidas após a remoção de "Todos". */
    public const VISIBILITIES = ['company', 'team'];

    /**
     * Normaliza a visibilidade recebida do formulário para um valor válido.
     * Qualquer coisa fora de {company, team} — inclusive o antigo 'all' — vira
     * 'company' (padrão seguro: restrito à empresa, nunca global).
     */
    public static function normalizeVisibility($value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($v, self::VISIBILITIES, true) ? $v : 'company';
    }
}
