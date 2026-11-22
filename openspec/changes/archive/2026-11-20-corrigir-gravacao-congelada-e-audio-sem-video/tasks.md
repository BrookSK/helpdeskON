## 1. Gravação

- [x] 1.1 Redesenhar o mosaico com temporizador em Web Worker (25 quadros por segundo), com reserva em `setInterval`
- [x] 1.2 Parar o temporizador ao encerrar a gravação

## 2. Entrada e publicação da mídia

- [x] 2.1 "Entrar na chamada" espera a câmera/microfone por até 5 s
- [x] 2.2 Publicar nas conexões abertas a mídia que fica pronta depois da entrada
- [x] 2.3 Publicar as tracks sob um rótulo de stream fixo
- [x] 2.4 Incluir na gravação em andamento o microfone que ficou pronto depois
- [x] 2.5 Atualizar botões e avisar na sala quando a mídia chega ou falha depois da entrada
- [x] 2.6 Aplicar o mudo e a câmera escolhidos no lobby antes e depois da espera

## 3. Áudio e tile

- [x] 3.1 Tocar o áudio de cada participante em `<audio>` próprio e deixar o `<video>` do tile mudo
- [x] 3.2 Criar o tile na primeira track (áudio ou vídeo), com avatar quando não há vídeo
- [x] 3.3 Remover o `<audio>` do participante quando ele sai ou a chamada termina
- [x] 3.4 Identificar a tela compartilhada pelo stream, para funcionar com quem entrou só com áudio
- [x] 3.5 Incluir participante sem câmera no mosaico e no mix de áudio da gravação
- [x] 3.6 Tocar e gravar o som da tela compartilhada em elemento próprio, sem trocar o microfone
- [x] 3.7 Retomar vídeos pausados ao montar o mosaico da gravação

## 4. Sinalização

- [x] 4.1 Enviar oferta e resposta de cada conexão em fila
- [x] 4.2 Tratar os sinais recebidos um por vez, em ordem
- [x] 4.3 Uma oferta própria por vez em cada conexão
- [x] 4.4 Limite de 10 s em cada envio de sinal

## 5. Testes

- [x] 5.1 E2E: o mosaico da gravação continua mudando sem `requestAnimationFrame`
- [x] 5.2 E2E: quem entra antes de a mídia ficar pronta passa a ser visto e ouvido
- [x] 5.3 E2E: quem entra sem câmera é visto (avatar) e ouvido
- [x] 5.4 E2E: câmera sem quadros não cala o áudio
- [x] 5.5 Conferir a gravação com a aba oculta no Chrome real
- [x] 5.6 E2E: entrar espera a mídia e respeita o mudo escolhido no lobby
- [x] 5.7 E2E: compartilhar a tela com som não cala o microfone
- [x] 5.8 Séries de entradas com 2 e 3 participantes, com e sem atraso de câmera
- [x] 5.9 Rodar Playwright e PHPUnit
