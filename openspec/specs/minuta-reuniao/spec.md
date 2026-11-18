# minuta-reuniao Specification

## Purpose
Minuta (ata) da reunião gravada: conteúdo gerado por IA, destinatários e envio do link por WhatsApp e e-mail.

## Requirements

### Requirement: Envio automático da minuta por tipo de reunião
Quando a minuta é gerada pelo fluxo automático depois da transcrição e ainda não foi enviada, o sistema SHALL enviar o link público da minuta por WhatsApp e e-mail aos destinatários da reunião se o tipo for convite externo, operacional ou interno. Em reunião comercial e em sala sem reunião vinculada a minuta MUST ficar como rascunho, com aviso interno ao organizador. Gerar a minuta de novo pela tela MUST NOT disparar envio automático. Uma falha no envio MUST NOT impedir a geração da minuta.

#### Scenario: Reunião de convite externo
- **WHEN** a transcrição de uma reunião de convite externo termina e a minuta é gerada
- **THEN** o link da minuta é enviado aos destinatários e a minuta fica marcada como enviada

#### Scenario: Reunião comercial
- **WHEN** a transcrição de uma reunião comercial termina e a minuta é gerada
- **THEN** a minuta fica como rascunho, nada é enviado ao cliente e o organizador recebe o aviso "Minuta pronta para revisão"

#### Scenario: Nenhum destinatário com canal
- **WHEN** a minuta de uma reunião com envio automático é gerada e ninguém da reunião tem WhatsApp ou e-mail
- **THEN** a minuta fica como rascunho e o organizador recebe o aviso interno

#### Scenario: Gerar de novo pela tela
- **WHEN** alguém usa "gerar de novo" numa minuta ainda não enviada
- **THEN** a minuta é refeita e nada é enviado automaticamente

#### Scenario: Minuta já enviada
- **WHEN** a minuta de uma gravação já enviada é gerada outra vez
- **THEN** nenhum envio é repetido

### Requirement: Destinatários da minuta
Os destinatários padrão da minuta SHALL ser, do lado da equipe, os participantes internos e o organizador da reunião (responsável, senão quem criou) e, do lado do cliente, o cliente da reunião, os demais clientes vinculados e os convidados externos. A lista MUST ser deduplicada por e-mail e por telefone e MUST NOT incluir quem não tem WhatsApp nem e-mail. A mesma lista vale para o envio automático e para a lista padrão do envio manual.

#### Scenario: Convite externo com organizador fora dos participantes
- **WHEN** uma reunião de convite externo tem um convidado com e-mail, um participante interno e um organizador que não se marcou como participante
- **THEN** a minuta vai para o convidado, para o participante e para o organizador

#### Scenario: Convidado sem canal de contato
- **WHEN** um convidado externo não tem WhatsApp nem e-mail
- **THEN** ele não entra na lista de destinatários

### Requirement: Ata com ações pós-reunião e responsáveis
A ata gerada por IA SHALL listar as ações combinadas para depois da reunião, uma por item, cada uma com o responsável, o lado (equipe ou cliente), a ação concreta e o prazo. Quando ninguém assumiu a ação o item MUST dizer "Responsável a definir" e quando não foi dito prazo MUST dizer "Prazo: não definido"; a ata MUST NOT inventar responsáveis, prazos, valores ou nomes. O resumo SHALL trazer contexto, objetivo e resultado da reunião, e os pontos discutidos SHALL ser frases com o que foi discutido e a conclusão. O texto da minuta SHALL mostrar as ações pós-reunião logo depois do resumo. A geração SHALL informar à IA quem é da equipe (organizador e participantes internos) e quem é convidado externo ou cliente. Atas já salvas MUST continuar sendo lidas, exibidas e editadas sem mudança de formato.

#### Scenario: Ação com responsável e prazo
- **WHEN** na reunião alguém da equipe assume enviar a proposta até sexta-feira
- **THEN** a ata traz um próximo passo com o nome da pessoa, a marca de equipe, a ação e o prazo citado

#### Scenario: Ação sem responsável e sem prazo
- **WHEN** uma ação é combinada sem dizer quem faz nem até quando
- **THEN** o item aparece com "Responsável a definir" e "Prazo: não definido"

#### Scenario: Ordem do texto da minuta
- **WHEN** o texto da minuta é montado a partir da ata estruturada
- **THEN** a seção de próximos passos vem logo depois do resumo

#### Scenario: Resposta da IA cortada no limite
- **WHEN** a resposta da IA é cortada pelo limite de tamanho
- **THEN** o sistema tenta gerar a ata mais uma vez pedindo uma versão mais curta que preserve ações e decisões

#### Scenario: Ata antiga
- **WHEN** uma ata salva antes desta mudança é aberta na tela da gravação ou na página pública
- **THEN** ela é exibida e pode ser editada normalmente
