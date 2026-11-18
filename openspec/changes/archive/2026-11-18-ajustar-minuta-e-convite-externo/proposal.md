## Why

O cliente fez uma reunião do tipo "convite externo" (com ele, a Julia e os clientes convidados por WhatsApp) e relatou três problemas: a minuta foi gerada mas não foi enviada para ninguém; a mensagem de convite chegou aos clientes sem o link da reunião; e a minuta veio rasa, sem dizer o que precisa ser feito depois da reunião e por quem.

A minuta parou de sair sozinha quando passou a nascer como rascunho (revisão antes do envio), regra criada para a reunião comercial e que acabou valendo para todos os tipos. Além disso, os convidados externos e o organizador nunca entravam na lista de destinatários da minuta.

## What Changes

- A minuta gerada depois da transcrição é enviada automaticamente (WhatsApp e e-mail) nas reuniões de convite externo, operacionais e internas. A reunião comercial continua como rascunho, com revisão antes do envio.
- Os destinatários da minuta passam a incluir o organizador da reunião, os convidados externos e todos os clientes da reunião, além dos participantes internos.
- O convite da reunião de convite externo sempre leva o link de entrada da reunião.
- A ata fica mais completa: resumo com contexto e resultado, pontos discutidos em frases informativas e ações pós-reunião com responsável e prazo, separando o que cabe à equipe do que cabe ao cliente.

## Capabilities

### New Capabilities
- `minuta-reuniao`: geração, destinatários e envio da minuta (ata) da reunião gravada.
- `convite-reuniao`: conteúdo do convite enviado aos convidados e participantes da reunião.

### Modified Capabilities

## Impact

- `app/controllers/VideocallController.php` (envio automático, destinatários, contexto da ata)
- `app/core/MeetingMinutesDelivery.php` (regra de envio automático e destinatários)
- `app/core/MeetingMinutesRules.php`, `app/core/MeetingMinutesGenerator.php` (prompts e texto da ata)
- `app/controllers/AgendaController.php`, `app/core/AgendaRules.php` (link no convite externo)
- `app/views/videocall/watch.php`, `app/views/videocall/minutes.php` (exibição da ata, se houver campo novo)
- Testes: `tests/Unit/MeetingMinutesDeliveryTest.php`, `tests/Unit/MeetingMinutesRulesTest.php`, `tests/Unit/MeetingMinutesGeneratorTest.php`, `tests/Integration/MeetingMinutesAutoSendTest.php` e testes de agenda
