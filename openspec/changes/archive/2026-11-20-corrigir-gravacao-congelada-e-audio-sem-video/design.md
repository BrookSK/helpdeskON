## Context

A sala é uma malha WebRTC (cada participante conecta com cada um); a sinalização vai por requisições HTTP com consulta longa. A gravação roda no navegador de quem grava: um canvas de 1920x1080 recebe o mosaico das câmeras e é capturado com `captureStream(25)`; o áudio é mixado à parte com Web Audio. O fundo virtual já tinha trocado `requestAnimationFrame` por `setInterval` porque a câmera congelava para os outros com a aba oculta; a gravação ficou de fora.

Medições:

- Chrome, aba visível: 961 chamadas de `requestAnimationFrame` em 8 s. Aba oculta: 1.
- Um `<video autoplay>` com áudio e vídeo cujo vídeo não entrega quadros fica com `readyState 0` e `paused: true` mesmo com o áudio chegando.
- Sala real, dois navegadores: quem entrou antes de a mídia abrir criou a conexão sem nenhuma track (`getTransceivers()` vazio) e ficou assim. No mesmo caso, uma oferta enviada 1 ms depois de uma resposta foi descartada pelo outro lado e a conexão ficou presa em `have-local-offer`.

## Decisions

- **Temporizador em Web Worker para o mosaico.** Em aba oculta o Chrome também segura o `setInterval` da página em 1 disparo por segundo quando ela não está tocando som (quem grava sozinho, esperando os convidados). O temporizador de um Worker não sofre esse corte. O Worker só manda um aviso a cada 40 ms; o desenho continua na página. Se o Worker não puder ser criado, cai para `setInterval`.
- **25 quadros por segundo.** É o ritmo da captura (`captureStream(25)`). Antes o mosaico era desenhado a 60 por segundo sem ganho na gravação.
- **Disparos atrasados são descartados.** Com a página ocupada os avisos do Worker se acumulam; desenhar todos em sequência travaria ainda mais.
- **Entrada espera a mídia, com limite de 5 s.** No caso comum a conexão já nasce com câmera e microfone e não precisa renegociar. O limite existe porque a permissão do navegador pode ficar pendente; passado o limite a pessoa entra e a mídia é publicada depois.
- **Publicação tardia por `addTrack`.** Quando a mídia fica pronta (ou muda) depois de entrar, cada conexão que ainda não envia aquele tipo ganha a track; o navegador dispara a renegociação. Quem já envia só troca a track, como antes.
- **Rótulo fixo do stream publicado.** O `localStream` é recriado a cada troca de fundo. As tracks passam a ser publicadas sob um `MediaStream` fixo, para que áudio e vídeo cheguem no mesmo stream do outro lado mesmo quando publicados em momentos diferentes. Sem isso, um vídeo publicado depois do áudio seria confundido com tela compartilhada.
- **`<audio>` por participante, só com a track de áudio.** Tira do áudio qualquer dependência do vídeo. O elemento fica no objeto do participante e é removido quando ele sai. O `<video>` dos tiles fica mudo para o som não tocar duas vezes.
- **Tile na primeira track.** O tile nasce com áudio ou com vídeo. Se o stream não traz vídeo, o tile começa com o avatar; o estado enviado pelo participante a cada 3 s continua sendo a fonte da verdade para câmera ligada ou desligada.
- **Tela compartilhada identificada pelo stream.** A heurística de reserva (quando o aviso de tela chega atrasado) passa a comparar com o stream de câmera/microfone já conhecido, em vez de exigir que uma câmera tenha chegado. Assim funciona para quem entrou só com áudio.
- **Som da tela em elemento próprio.** Toda track que chega fora do stream de câmera/microfone é tratada como tela, inclusive o áudio. O som da tela toca em um `<audio>` separado e entra na gravação com chave própria; ao parar o compartilhamento só ele é removido. Antes o som da tela tomava o lugar do microfone de quem compartilhava, que seguia mudo depois.
- **Mudo e câmera do lobby valem antes e depois da espera.** A escolha feita na tela de entrada é aplicada antes de esperar a mídia (a que abrir durante a espera já nasce nesse estado) e de novo depois (a pessoa pode ter mudado durante a espera).
- **Vídeo pausado é retomado ao montar o mosaico.** O Chrome pode pausar um `<video>` mudo fora da tela; a gravação chama `play()` nos que estiverem pausados.
- **Sinalização em ordem.** Oferta e resposta de uma conexão saem por uma fila (uma requisição por vez); candidatos ICE seguem em paralelo, porque a ordem deles não importa e a fila atrasaria a conexão. Os sinais recebidos são tratados um por vez, na ordem do servidor. Cada envio tem limite de 10 s, para uma requisição presa não travar a fila.
- **Uma oferta por vez.** A chamada manual da negociação e o evento do navegador disparavam juntos e criavam duas ofertas concorrentes. A segunda agora é ignorada enquanto a primeira está em curso, e uma oferta criada antes de chegar a oferta do outro lado não é mais aplicada.

## Risks / Trade-offs

- Não há como confirmar qual das causas atingiu o cliente em cada ocorrência. A entrada antes de a mídia abrir é a que reproduz o relato por inteiro (quem entrou primeiro, o outro entra logo em seguida, só resolve saindo e entrando).
- Se a câmera não entrega quadros, a imagem continua preta para os outros; o som passa a funcionar.
- A espera na entrada atrasa em até 5 s quem está com a permissão pendente.
- Não foi testado em Safari nem em Firefox; os testes automatizados e as medições usaram Chrome/Chromium.
