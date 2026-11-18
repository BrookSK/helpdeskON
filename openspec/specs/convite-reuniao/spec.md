# convite-reuniao Specification

## Purpose
Convite da reunião enviado por WhatsApp e e-mail aos convidados externos e aos participantes internos.

## Requirements

### Requirement: Convite leva o link de entrada da reunião
Quando a opção de reunião é "Sala de vídeo do sistema", o sistema SHALL garantir que a reunião tenha a sala criada e o link de entrada gravado antes de enviar qualquer convite, mesmo que o formulário tenha sido salvo sem o link. O convite por WhatsApp e por e-mail aos convidados externos e aos participantes internos MUST incluir esse link. O sistema MUST NOT sobrescrever um link de reunião já existente nem criar uma segunda sala para a mesma reunião. O formulário MUST NOT permitir salvar enquanto o link ainda está sendo gerado.

#### Scenario: Sala privada salva sem criar a sala no formulário
- **WHEN** um convite externo é salvo com a opção "Sala de vídeo do sistema" e sem link de reunião
- **THEN** o servidor cria a sala com a privacidade e os administradores escolhidos, grava o link na reunião e os convites saem com o link

#### Scenario: Link já gerado no formulário
- **WHEN** a reunião é salva com o link da sala já gerado
- **THEN** o convite leva esse link e nenhuma sala nova é criada

#### Scenario: Reenvio de reunião com sala vinculada e sem link
- **WHEN** os convites de uma reunião que tem sala do sistema vinculada mas está sem link gravado são reenviados
- **THEN** o link da sala vinculada é recuperado e vai nos convites

#### Scenario: Salvar durante a geração do link
- **WHEN** o usuário clica em salvar enquanto o link da reunião ainda está sendo gerado
- **THEN** o formulário pede para aguardar e não salva

#### Scenario: Opção "Nenhuma"
- **WHEN** a reunião é salva com a opção "Nenhuma"
- **THEN** nenhuma sala é criada e o convite sai sem link de reunião
