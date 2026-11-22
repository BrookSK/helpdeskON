## Why

Dois casos relatados pelo cliente na sala de vídeo:

- Uma reunião foi gravada com a imagem congelada. O áudio e a transcrição saíram normais.
- Com frequência, quem entra primeiro vê e ouve os outros, mas os outros não o veem nem ouvem. Só resolve saindo e entrando de novo.

Causas encontradas no código e reproduzidas na sala:

- O mosaico da gravação é redesenhado com `requestAnimationFrame`. O Chrome suspende essa função quando a aba fica oculta (outra aba, janela minimizada ou coberta). O mosaico para de mudar e o vídeo gravado congela; o áudio segue.
- "Entrar na chamada" não espera a câmera e o microfone abrirem. Quem entra antes disso cria as conexões sem mídia, e elas nunca recebem a mídia depois. A pessoa vê e ouve os outros; ninguém a vê nem ouve até ela sair e entrar de novo.
- O áudio de cada participante só toca pelo `<video>` do tile dele, e o tile só é criado quando chega uma track de vídeo. Quem entra sem câmera (câmera em uso por outro programa, permissão negada) não é visto nem ouvido por ninguém.
- Um `<video>` que recebe áudio e vídeo só começa a tocar depois do primeiro quadro de vídeo. Se a câmera de alguém não entrega nenhum quadro, o áudio dessa pessoa também não toca.
- Oferta e resposta da conexão são enviadas em requisições paralelas e tratadas em paralelo na chegada. Uma oferta enviada logo depois de uma resposta pode chegar antes dela; o outro lado a descarta como colisão e a conexão fica pela metade.

## What Changes

- O mosaico da gravação passa a ser redesenhado por um temporizador em Web Worker (25 quadros por segundo), que não para com a aba oculta. Sem Worker, usa `setInterval`.
- "Entrar na chamada" espera a câmera e o microfone abrirem, por até 5 segundos.
- Mídia que fica pronta depois da entrada é publicada nas conexões já abertas. O microfone também entra na gravação já iniciada.
- O áudio de cada participante passa a tocar em um elemento `<audio>` próprio, só com a track de áudio. O `<video>` do tile fica sempre mudo.
- O som da tela compartilhada toca em um elemento próprio, junto com o microfone de quem compartilha.
- O tile do participante é criado na primeira track que chega, de áudio ou de vídeo. Sem vídeo, mostra o avatar.
- Participante sem câmera entra no mosaico da gravação como avatar, e o áudio dele entra no mix mesmo quando a gravação começa depois da entrada dele.
- Oferta e resposta de uma mesma conexão são enviadas uma de cada vez e os sinais recebidos são tratados em ordem. Cada conexão cria uma oferta por vez.

## Capabilities

### New Capabilities
- `sala-video`: mídia dos participantes na sala de vídeo (publicação, áudio e tile independentes da câmera).

### Modified Capabilities
- `gravacao-transcricao`: a gravação continua com a aba de quem grava oculta.

## Impact

- `app/views/videocall/room.php`
- `tests-e2e/videocall-media-resilience.spec.ts` (novo)
- Sem mudança de banco nem de servidor.
