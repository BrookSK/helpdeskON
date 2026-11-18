## Context

A minuta é gerada depois da transcrição (cron, transcrição no servidor ou no navegador). Desde `207d5a2` ela nasce como rascunho e só o organizador recebe um aviso interno; o envio depende do botão na tela da gravação. Os destinatários vinham dos participantes internos e do cliente em `client_*`, campos que ficam vazios no convite externo (os convidados estão em `external_guests`).

No convite, o modal envia só `meeting_mode`, `meet_link` e `auto_record`. A sala é criada por uma chamada assíncrona no navegador; se o formulário é salvo sem o link (sala privada não criada, criação em andamento ou com falha), o servidor não criava a sala e o convite saía sem link.

## Goals / Non-Goals

- Enviar a minuta sozinha nos tipos de reunião que não têm revisão comercial, para equipe e convidados.
- Convite sempre com o link quando a opção é a sala do sistema.
- Ata mais completa, com ações, responsável e prazo.
- Fora do escopo: mudar o fluxo comercial (rascunho, revisão, reconhecimento do cliente), criar evento do Google no convite externo, tratar a falha total de entrega (WhatsApp e SMTP fora do ar).

## Decisions

- **Envio automático por tipo, não para todos.** `MeetingMinutesDelivery::shouldAutoSend` aceita `externo`, `operacional` e `interno`. A reunião comercial mantém o rascunho porque a minuta alimenta a proposta e vai ao cliente só depois da revisão. Alternativa descartada: enviar sempre, que desfaria a revisão comercial.
- **Só o fluxo automático envia.** `generateMinutesContent` recebe `$autoSend`; o "gerar de novo" passa `false`, porque quem regenera está revisando.
- **Sem destinatário, volta ao aviso interno.** Evita minuta pronta sem ninguém saber.
- **Destinatários montados num ponto só.** `minutesMeeting` soma organizador, clientes de `agenda_meeting_clients` e `external_guests`; `buildRecipients` ganhou um terceiro parâmetro opcional, sem quebrar as chamadas antigas.
- **Servidor garante a sala.** `AgendaRules::needsSystemRoomLink` (modo `room` sem link) faz `create()` e `update()` criarem ou reusarem a sala antes das notificações. O reenvio recupera o link da sala vinculada. O modal bloqueia o salvar durante a geração e envia privacidade e administradores para a sala criada no servidor.
- **Ações no campo existente.** `proximos_passos` continua lista de strings, com formato fixo `Responsável (Equipe ou Cliente): ação. Prazo: ...` (`MeetingMinutesRules::ACTION_FORMAT`). Um campo novo exigiria mudar normalização, tela de revisão, página pública e proposta para reorganizar a mesma informação.
- **Limite de saída maior e uma nova tentativa.** A ata final passa a 8000 tokens e as notas de parte a 6000, no mesmo `gpt-4o-mini`. Se a resposta vier cortada (`finish_reason = length`), há uma única nova tentativa pedindo versão mais curta.

## Risks / Trade-offs

- A minuta de convite externo sai sem revisão humana. Mitigação: o prompt proíbe inventar e marca o que não foi dito; a equipe recebe a mesma minuta e pode corrigir e reenviar pela tela.
- `deliverMinutes` marca a minuta como enviada quando há destinatário com canal, mesmo que o WhatsApp e o e-mail falhem. Comportamento anterior, mantido.
- Ata mais longa custa mais tokens de saída; o valor por reunião continua na casa de centavos.
