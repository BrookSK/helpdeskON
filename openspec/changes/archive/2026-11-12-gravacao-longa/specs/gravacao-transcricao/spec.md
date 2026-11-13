## ADDED Requirements

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
