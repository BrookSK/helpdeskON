## 1. Envio da minuta

- [x] 1.1 Regra `MeetingMinutesDelivery::shouldAutoSend` com teste unitário
- [x] 1.2 Destinatários com organizador, clientes da reunião e convidados externos (`buildRecipients`, `minutesMeeting`)
- [x] 1.3 Envio automático no fluxo pós-transcrição e rascunho no "gerar de novo" (`generateMinutesContent`, `dispatchNewMinutes`)
- [x] 1.4 Teste de integração do convite externo (`MeetingMinutesAutoSendTest`)

## 2. Link no convite

- [x] 2.1 Regra `AgendaRules::needsSystemRoomLink` com teste unitário
- [x] 2.2 Servidor garante sala e link em `create()`, `update()` e no reenvio
- [x] 2.3 Modal bloqueia salvar durante a geração do link e envia privacidade e administradores
- [x] 2.4 Teste de integração do link no convite (`AgendaInviteLinkTest`)

## 3. Conteúdo da ata

- [x] 3.1 Prompts com ações, responsável, lado e prazo (`structuredSystemPrompt`, `notesSystemPrompt`, `systemPrompt`)
- [x] 3.2 Cabeçalho com organizador e convidados (`buildMeetingContext`, `MeetingMinutesGenerator::header`)
- [x] 3.3 Próximos passos logo depois do resumo no texto, na tela da gravação, na cópia e no PDF
- [x] 3.4 Limite de saída maior e nova tentativa quando a resposta vem cortada

## 4. Verificação

- [x] 4.1 Rodar PHPUnit completo e comparar com a linha de base
- [x] 4.2 Prova de ponta a ponta: convite e minuta capturados em WhatsApp e e-mail falsos
