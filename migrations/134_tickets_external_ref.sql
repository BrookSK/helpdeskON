-- Idempotência da API de criação de chamados (demanda: API v1).
--
-- external_ref é um identificador OPCIONAL enviado pelo sistema externo. Quando
-- presente, evita que um retry da integração crie o mesmo chamado duas vezes.
--
-- O índice UNIQUE é composto por (client_id, external_ref): como o client_id na
-- API é o usuário de integração da empresa, isso garante unicidade por empresa,
-- permitindo que sistemas de empresas diferentes usem a mesma referência sem
-- colidir. No MySQL, múltiplos NULL são permitidos em índice UNIQUE, então
-- tickets sem external_ref (todo o fluxo atual) não conflitam entre si.
ALTER TABLE tickets ADD COLUMN external_ref VARCHAR(191) NULL AFTER client_ticket_number;
ALTER TABLE tickets ADD UNIQUE KEY uq_ticket_client_extref (client_id, external_ref);
