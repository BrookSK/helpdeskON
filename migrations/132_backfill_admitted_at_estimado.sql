-- Migration 132: Backfill ESTIMADO de admitted_at para o histórico (demanda #251).
--
-- Contexto: a coluna tickets.admitted_at (migration 131) só passou a ser preenchida
-- a partir da ativação da medição. Tickets antigos ficaram com admitted_at NULL e,
-- por isso, fora dos indicadores de admissão/tratamento.
--
-- Esta migration preenche UMA ÚNICA VEZ o admitted_at desses tickets antigos com a
-- MELHOR ESTIMATIVA disponível do momento em que o trabalho começou:
--   1) planning_cards.start_date (início do desenvolvimento) — melhor fonte;
--   2) tickets.updated_at — fallback, quando não há start_date.
-- (COALESCE nessa ordem.)
--
-- De agora em diante o carimbo é REAL (feito pela aplicação em Ticket::stampAdmittedAt),
-- então não haverá mais estimativas novas — este backfill é só para o histórico.
--
-- Espelha Ticket::backfillEstimatedAdmittedAt() (mesma lógica em PHP, usada nos testes).
--
-- Regras/guard-rails (para não distorcer as médias):
--   - Só tickets que EFETIVAMENTE foram trabalhados: exclui os que estão em 'open'
--     (nunca iniciados) e os que foram para 'denied'/'archived' (saídas sem trabalho).
--   - Só tickets ainda SEM admitted_at (não sobrescreve o dado real já carimbado).
--   - admitted_at é "clampado" ao intervalo válido: nunca antes de created_at nem
--     depois de completed_at (quando concluído), evitando tempos negativos.

-- Passo 1: estimar admitted_at = COALESCE(MIN(start_date do card), updated_at),
-- respeitando o piso created_at.
UPDATE tickets t
SET t.admitted_at = GREATEST(
        t.created_at,
        COALESCE(
            (SELECT MIN(pc.start_date)
               FROM planning_cards pc
              WHERE pc.ticket_id = t.id
                AND pc.start_date IS NOT NULL),
            t.updated_at
        )
    )
WHERE t.admitted_at IS NULL
  AND t.status NOT IN ('open', 'denied', 'archived');

-- Passo 2: garantir que a admissão nunca fique depois da conclusão (teto = completed_at).
-- Isso mantém o Tempo de Tratamento (admissão -> conclusão) sempre >= 0.
UPDATE tickets t
SET t.admitted_at = t.completed_at
WHERE t.completed_at IS NOT NULL
  AND t.admitted_at IS NOT NULL
  AND t.admitted_at > t.completed_at;
