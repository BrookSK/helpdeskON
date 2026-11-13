# gravacao-transcricao Specification

## Purpose
Transcrição no servidor das gravações de reunião: a fila de segmentos de áudio é processada pelos crons agendados, sem depender de configuração extra.

## Requirements

### Requirement: Fila de transcrição nos crons agendados
Os crons `/cron/runProspecting` e `/cron/finalizeRecordings` SHALL processar um lote da fila de transcrição das gravações a cada execução. O processamento MUST NOT depender de `cron_token` estar configurado.

#### Scenario: Produção sem cron_token
- **WHEN** `cron_token` não está configurado e o cron `/cron/runProspecting` roda
- **THEN** o sistema transcreve um lote dos segmentos de áudio na fila

#### Scenario: Gravação na fila
- **WHEN** uma gravação tem segmentos de áudio pendentes e o cron roda
- **THEN** os segmentos do lote são enviados para transcrição e a gravação sai de "Na fila de transcrição"

### Requirement: Endpoint dedicado protegido
O endpoint `/cron/transcribeRecordings` MUST exigir `cron_token` configurado e o token correto na chamada.

#### Scenario: Chamada sem token
- **WHEN** `/cron/transcribeRecordings` é chamado sem o token correto
- **THEN** o sistema responde 403 e não transcreve nada

### Requirement: Link público acompanha a transcrição
A página do link público da gravação (`/videocall/share/<token>`) SHALL consultar o status da transcrição enquanto ela estiver na fila ou em processamento e SHALL mostrar a transcrição quando ficar pronta, sem recarregar a página. O status público MUST NOT exigir login e MUST NOT devolver o rascunho da minuta nem o detalhe técnico de um erro.

#### Scenario: Transcrição termina com a página pública aberta
- **WHEN** alguém está com o link público aberto em "Na fila de transcrição" e a transcrição termina
- **THEN** a página mostra a transcrição em poucos segundos, sem recarregar

#### Scenario: Minuta ainda não enviada
- **WHEN** o status público de uma gravação transcrita é consultado e a minuta não foi enviada ao cliente
- **THEN** a resposta traz a transcrição e não traz a minuta

#### Scenario: Transcrição com erro
- **WHEN** o status público de uma gravação com erro de transcrição é consultado
- **THEN** a resposta traz o status de erro com mensagem genérica, sem o detalhe técnico

### Requirement: Gravação longa cabe no limite do servidor
A gravação de reunião SHALL limitar o vídeo a 1 Mbps para que reuniões de até cerca de 4 horas caibam no limite de 2 GiB por gravação.

#### Scenario: Reunião de 2 a 3 horas
- **WHEN** uma reunião de 2 a 3 horas é gravada
- **THEN** o vídeo inteiro sobe para o servidor e a gravação aparece em Gravações ao parar

### Requirement: Limite atingido não perde a gravação
Quando o vídeo atingir o limite do servidor, o sistema SHALL avisar quem grava, continuar enviando o áudio e, ao parar, publicar o vídeo enviado até o limite marcado como incompleto, com a transcrição da reunião toda. A cópia completa do vídeo SHALL ser baixada no computador de quem gravou.

#### Scenario: Vídeo passa do limite
- **WHEN** o servidor recusa um pedaço do vídeo por limite atingido
- **THEN** quem grava vê o aviso de limite e a gravação continua

#### Scenario: Parar depois do limite
- **WHEN** quem grava para a gravação depois do limite
- **THEN** a gravação aparece em Gravações marcada como incompleta, o áudio entra na fila de transcrição e a cópia completa é baixada

### Requirement: Publicação de arquivo grande sem FFmpeg
A publicação de uma gravação MUST NOT carregar na memória um arquivo acima de 64 MiB para injetar a duração. Sem FFmpeg, gravações grandes SHALL ser publicadas sem a duração no cabeçalho.

#### Scenario: Sessão abandonada de 2 GB sem FFmpeg
- **WHEN** o cron publica uma sessão abandonada de 2 GB num servidor sem FFmpeg
- **THEN** a gravação é registrada e aparece em Gravações, e o áudio entra na fila de transcrição
