## ADDED Requirements

### Requirement: Entrada espera a câmera e o microfone
Ao entrar na chamada, a sala SHALL esperar a câmera e o microfone terminarem de abrir, por no máximo 5 segundos, antes de criar as conexões com os outros participantes.

#### Scenario: Câmera abre em 2 segundos
- **WHEN** o participante clica em entrar antes de a câmera abrir e ela abre 2 segundos depois
- **THEN** ele entra depois que a câmera abriu e os outros já o veem e ouvem

#### Scenario: Mudo escolhido antes de o microfone abrir
- **WHEN** o participante desliga o microfone na tela de entrada antes de o microfone abrir e entra
- **THEN** ele entra com o microfone mudo

### Requirement: Mídia que fica pronta depois da entrada é publicada
Quando a câmera ou o microfone de um participante ficam prontos depois que ele entrou, a sala SHALL enviar essa mídia a todos os participantes já conectados, sem que ele precise sair e entrar de novo.

#### Scenario: Primeiro participante entra antes de a mídia abrir
- **WHEN** o primeiro participante entra sem mídia, outro participante entra em seguida e a mídia do primeiro fica pronta depois
- **THEN** o segundo passa a ver e ouvir o primeiro, e o primeiro continua vendo e ouvindo o segundo

### Requirement: Áudio do participante independe do vídeo
A sala de vídeo SHALL tocar o áudio de cada participante remoto independentemente do vídeo dele. O áudio MUST tocar mesmo que o participante não envie vídeo ou que o vídeo dele não entregue nenhum quadro.

#### Scenario: Participante entra sem câmera
- **WHEN** um participante entra só com o microfone porque a câmera não pôde ser aberta
- **THEN** os outros participantes ouvem o áudio dele

#### Scenario: Câmera que não entrega quadros
- **WHEN** a câmera de um participante é aberta mas não entrega nenhum quadro
- **THEN** os outros participantes ouvem o áudio dele

### Requirement: Som da tela compartilhada não substitui o microfone
Quando um participante compartilha a tela com som, a sala SHALL tocar o som da tela junto com o microfone dele. Ao parar o compartilhamento, a sala SHALL parar só o som da tela.

#### Scenario: Compartilhamento com som
- **WHEN** um participante compartilha a tela com som e depois para de compartilhar
- **THEN** os outros ouvem o microfone dele durante e depois do compartilhamento, e o som da tela só durante

### Requirement: Participante sem câmera aparece na sala
A sala de vídeo SHALL mostrar um bloco para cada participante que envia áudio ou vídeo. Sem vídeo, o bloco SHALL mostrar o avatar com a inicial do nome.

#### Scenario: Participante só com áudio
- **WHEN** um participante entra só com o microfone
- **THEN** os outros veem o bloco dele com o avatar no lugar da câmera

### Requirement: Negociação da conexão em ordem
A sala SHALL enviar oferta e resposta de uma mesma conexão na ordem em que foram geradas e SHALL tratar os sinais recebidos na ordem do servidor. Cada conexão MUST NOT ter duas ofertas próprias em curso ao mesmo tempo.

#### Scenario: Oferta logo depois de uma resposta
- **WHEN** um participante responde uma oferta e envia uma oferta própria em seguida
- **THEN** o outro lado recebe a resposta antes da oferta e as duas são aplicadas
