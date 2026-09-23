-- Migration 131: Adiciona o timestamp real de ADMISSÃO das demandas (demanda #251).
--
-- Fluxo real do ticket:
--   Criação (created_at) -> Admissão (admitted_at) -> Tratamento/Desenvolvimento
--   -> Homologação -> Conclusão (completed_at).
--
-- A "admissão" é o momento em que a demanda entra EFETIVAMENTE EM TRABALHO — ou
-- seja, quando entra em 'in_progress' (início do trabalho) ou avança para um
-- status posterior de tratamento (em_revisao_interna, em_homologacao,
-- aprovado_producao, completed) VINDO de um status que já era de trabalho.
-- NÃO contam como admissão: a simples atribuição de atendente; permanecer 'open';
-- as saídas para 'waiting_client', 'denied' ou 'archived'; e os SALTOS a partir de
-- um status de não-trabalho direto para um status posterior (ex.: open -> completed),
-- pois nesses casos nunca houve início de trabalho registrado.
-- Antes desta migration esse instante não era registrado; a tela de Performance
-- usava updated_at como proxy, o que é impreciso. A coluna abaixo passa a guardar
-- o instante REAL da admissão, carimbado pela aplicação (Ticket::stampAdmittedAt),
-- inclusive na criação quando o ticket já nasce em um status de trabalho.

ALTER TABLE tickets
    ADD COLUMN admitted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Data/hora em que a demanda foi admitida (saiu de open / recebeu atendente)' AFTER completed_at;

CREATE INDEX idx_tickets_admitted_at ON tickets(admitted_at);

-- IMPORTANTE: NÃO fazemos backfill de admitted_at.
--
-- Tickets antigos, admitidos antes desta implementação, não possuem uma data
-- real de admissão. Usar updated_at (ou created_at) como aproximação reintroduz
-- justamente o proxy impreciso que a demanda #251 quer eliminar. Portanto esses
-- tickets permanecem com admitted_at = NULL e, por definição, ficam FORA dos
-- cálculos de tempo médio de admissão e de tratamento (as consultas exigem
-- admitted_at IS NOT NULL).
--
-- A coluna só passa a ser preenchida a partir de agora, quando um ticket for
-- efetivamente admitido (atribuição de atendente / saída do status 'open'),
-- via Ticket::stampAdmittedAt().
