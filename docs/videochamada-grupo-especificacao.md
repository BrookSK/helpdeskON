# Especificação: Videochamada em Grupo (baseada na videochamada 1:1 existente)

> Documento de referência para o desenvolvedor implementar uma videochamada **em grupo**
> (5, 10, 15+ participantes simultâneos) em outro sistema, partindo do funcionamento
> da videochamada **1-para-1** que já existe no Agente-IA-Tuquinha.

---

## 1. Resumo do que existe hoje (sistema atual)

A videochamada atual é **1-para-1** (exatamente 2 participantes). Ela roda dentro do chat social
entre amigos e também no painel externo/parceiro. Características confirmadas no código:

| Item | Situação atual |
|------|----------------|
| Tecnologia | **WebRTC nativo do navegador** (sem SDK de terceiros: sem Agora, Twilio, Jitsi, Daily, LiveKit) |
| Topologia | 1:1 — um único `RTCPeerConnection`, um `localStream`, um `remoteStream` |
| Sinalização (signaling) | **HTTP long-polling com persistência em MySQL** (tabela `social_webrtc_signals`) |
| Sinalização alternativa | Existe um servidor Socket.IO (`realtime/server.js`) com handlers de WebRTC, mas **não é usado** pelas telas de chamada. O signaling ativo em produção é o HTTP-polling. |
| Servidores ICE | Apenas **STUN** público do Google, hardcoded. **Não há TURN.** |
| Controle de microfone | Sim — mutar/desmutar (`track.enabled`) |
| Controle de câmera | Sim — ligar/desligar (`track.enabled`) |
| Compartilhamento de tela | **NÃO existe hoje** (não há `getDisplayMedia` em nenhuma tela de chamada) |
| Autorização | Só entre amigos com amizade `accepted`; iniciar requer plano com `allow_video_chat` |

### Arquivos-chave do sistema atual

- `app/Views/social/chat_thread.php` — toda a lógica de frontend WebRTC (criação de `RTCPeerConnection`, `getUserMedia`, offer/answer/ICE, controles de mic/câmera, polling de sinalização, iniciar/aceitar/encerrar).
- `app/Views/external_dashboard/chat.php` — cópia praticamente idêntica para o painel externo.
- `app/Controllers/SocialWebRtcController.php` — servidor de sinalização real: `send`, `poll`, `incoming`, autorização por amizade e gating por plano.
- `app/Controllers/SocialSocketController.php` — geração de token HMAC-SHA256 para o Socket.IO.
- `realtime/server.js` — servidor Socket.IO (Node + Express + socket.io + mysql2).
- `public/index.php` — rotas HTTP de sinalização.
- Tabela `social_webrtc_signals` — fila de sinais.

### Rotas atuais

```
POST /social/webrtc/send      -> SocialWebRtcController@send      (envia offer/answer/ice/end/typing/media)
GET  /social/webrtc/poll      -> SocialWebRtcController@poll      (long-poll até 25s, busca sinais do usuário)
GET  /social/webrtc/incoming  -> SocialWebRtcController@incoming  (detecta chamada recebida)
GET  /social/socket/token     -> SocialSocketController@token     (token para Socket.IO)
```

### Servidores ICE atuais (frontend)

```js
pc = new RTCPeerConnection({
  iceServers: [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' }
  ]
});
```

---

## 2. Fluxo atual (1:1) resumido

1. **Autorização/gating**: precisam ser amigos `accepted`. Iniciar (enviar `offer`) exige plano com `allow_video_chat` (ou admin). Aceitar (enviar `answer`) é sempre permitido.
2. **Início (quem chama)**: `getUserMedia({video:true,audio:true})` → adiciona tracks ao `pc` → `createOffer()` + `setLocalDescription()` → envia `offer` via `sendSignal`.
3. **ICE**: cada `onicecandidate` envia um sinal `ice`. Candidatos recebidos antes de haver `remoteDescription` ficam bufferizados em `pendingIce`.
4. **Chamada recebida**: o destinatário detecta a `offer` (via `poll` na thread aberta ou via `incoming` em qualquer página) e mostra o modal "Entrar na chamada".
5. **Aceite**: `setRemoteDescription(offer)` → aplica ICE pendentes → `createAnswer()` + `setLocalDescription()` → envia `answer`.
6. **Conclusão**: quem chamou recebe a `answer`, faz `setRemoteDescription(answer)`, `ontrack` monta o `remoteStream`.
7. **Perfect negotiation**: `isPolite = currentUserId < otherUserId` decide quem cede em colisão de ofertas.
8. **Encerramento**: `end` fecha o `pc`, para as tracks locais e limpa a UI.

### Controles de mídia (o mecanismo a replicar)

- **Microfone**: `micMuted = !micMuted` → `audioTrack.enabled = !micMuted`.
- **Câmera**: `camOff = !camOff` → `videoTrack.enabled = !camOff`.
- Habilita/desabilita a track (NÃO remove/renegocia). Por isso mutar é instantâneo e não dispara renegociação.
- O estado é sincronizado para o outro lado via um sinal `kind='media'` com `{ micMuted, camOff }`, que mostra overlays/badges ("Câmera desligada", "Microfone mutado").

---

## 3. O que muda para GRUPO (decisão de arquitetura — leia primeiro)

O ponto central: a topologia atual (1 `RTCPeerConnection`) **não escala** para grupo.
Há três topologias possíveis. A escolha define quase todo o resto da implementação.

### Opção A — Mesh (P2P full-mesh)

Cada participante abre uma `RTCPeerConnection` com **cada** outro participante.
Para N participantes, cada cliente mantém **N-1** conexões e envia sua mídia N-1 vezes.

- **Vantagens**: reaproveita quase 100% do WebRTC nativo já existente; sem servidor de mídia; menor custo de infraestrutura.
- **Limite prático**: bom até ~4 pessoas. A partir de 5 já pesa muito na CPU e no upload de cada participante (envia o próprio vídeo para todos).
- **Veredito para o seu caso (5–15 pessoas)**: **NÃO recomendado.** Full-mesh com 15 pessoas significa 14 uploads de vídeo simultâneos por participante — inviável na maioria das conexões.

### Opção B — SFU (Selective Forwarding Unit) ✅ recomendado

Cada participante envia sua mídia **uma única vez** para um servidor (SFU), que redistribui para os demais.
Cada cliente ainda decodifica N-1 streams, mas só faz **1 upload**.

- **Vantagens**: escala bem para 5–50+ participantes; upload constante por cliente; permite qualidade adaptativa (simulcast).
- **Custo**: precisa de um servidor de mídia. Opções open-source: **mediasoup** (Node.js, encaixa no stack atual), **Janus**, **ion-sfu**, **LiveKit** (tem SFU + SDK pronta). Serviços gerenciados: LiveKit Cloud, Daily, Agora.
- **Veredito para 5–15 pessoas**: **é o caminho certo.**

### Opção C — MCU (Multipoint Control Unit)

O servidor mescla todos os streams em um único vídeo composto e envia um stream só para cada cliente.

- **Vantagens**: cliente recebe/decodifica só 1 stream (bom para dispositivos fracos).
- **Custo**: altíssimo no servidor (transcodifica tudo); menos flexível de layout. Raramente vale a pena hoje.
- **Veredito**: não recomendado salvo requisito específico de cliente muito fraco.

> **Recomendação para 5–15 participantes: Opção B (SFU).**
> Sugestão de menor esforço: usar **LiveKit** (SFU + SDK cliente + signaling prontos), ou **mediasoup**
> se quiser controlar tudo em Node.js aproveitando o `realtime/server.js` já existente no stack.

---

## 4. Modelo de dados para grupo

O modelo atual amarra a chamada a uma conversa 1:1 (`social_conversations` com `user1_id`/`user2_id`)
e cada sinal tem um `to_user_id` único. Para grupo, é preciso um conceito de **sala (room)** com N membros.

### Tabelas sugeridas

```sql
-- Sala de chamada em grupo
CREATE TABLE group_call_rooms (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  title         VARCHAR(150) NULL,
  status        ENUM('active','ended') NOT NULL DEFAULT 'active',
  max_participants SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  created_at    DATETIME NOT NULL,
  ended_at      DATETIME NULL
);

-- Participantes atuais/históricos da sala
CREATE TABLE group_call_participants (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  room_id    BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  role       ENUM('host','participant') NOT NULL DEFAULT 'participant',
  joined_at  DATETIME NOT NULL,
  left_at    DATETIME NULL,
  UNIQUE KEY uniq_room_user_active (room_id, user_id, left_at),
  INDEX idx_room (room_id)
);
```

Se optar por **mesh** (não recomendado, mas se for necessário), os sinais precisam ser roteados
**por par de participantes**, então a tabela de sinais muda de `to_user_id` único para roteamento
`from_user_id` + `to_user_id` dentro da `room_id`:

```sql
CREATE TABLE group_call_signals (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  room_id      BIGINT UNSIGNED NOT NULL,
  from_user_id BIGINT UNSIGNED NOT NULL,
  to_user_id   BIGINT UNSIGNED NULL,       -- NULL = broadcast para a sala (ex.: presença/entrou/saiu)
  kind         ENUM('offer','answer','ice','join','leave','media','end') NOT NULL,
  payload_json JSON NULL,
  created_at   DATETIME NOT NULL,
  delivered_at DATETIME NULL,
  INDEX idx_room_to (room_id, to_user_id, delivered_at)
);
```

> Se optar por **SFU**, você **não precisa** desta tabela de sinais par-a-par. O signaling passa a ser
> com o servidor SFU (normalmente via WebSocket), e o banco só guarda salas/participantes/presença.

---

## 5. Sinalização para grupo

O long-polling atual (loop de `poll` a cada 250ms consultando MySQL) funciona para 1:1, mas
para grupo com muita troca de sinais fica ineficiente. **Recomendação: usar WebSocket** (o
`realtime/server.js` com Socket.IO já existe no stack e pode ser estendido, ou usar o WebSocket
nativo do SFU escolhido).

### Eventos de signaling necessários no grupo

| Evento | Direção | Descrição |
|--------|---------|-----------|
| `room:join` | cliente → servidor | Usuário entra na sala; servidor valida permissão e devolve a lista de participantes atuais |
| `room:participants` | servidor → cliente | Lista de quem já está na sala (para o novo membro abrir conexões / se inscrever nas mídias) |
| `room:peer-joined` | servidor → clientes | Avisa os presentes que alguém entrou |
| `room:peer-left` | servidor → clientes | Avisa que alguém saiu (fechar a conexão/limpar o tile de vídeo) |
| `offer` / `answer` / `ice` | roteados | No SFU, é com o servidor. No mesh, roteados por par `from`/`to` |
| `media-state` | cliente → sala | `{ micMuted, camOff, screenSharing }` para atualizar badges/overlays de cada tile |
| `screen-share:start` / `screen-share:stop` | cliente → sala | Sinaliza início/fim do compartilhamento de tela |
| `room:end` / `leave` | cliente → servidor | Sair da chamada; host pode encerrar a sala inteira |

### Autenticação do WebSocket

Reaproveitar o esquema já existente em `SocialSocketController@token`:
token HMAC-SHA256 `base64(userId|exp|sig)`, validade 10 min, assinado com `SOCKET_IO_SECRET`,
validado no servidor. Estender para incluir/validar `room_id` e o papel do usuário.

---

## 6. Cliente (frontend) para grupo

### Diferença estrutural em relação ao 1:1

- Trocar a variável única `pc` por um **mapa de conexões por participante** (no mesh) ou por
  **uma conexão com o SFU** (no SFU).
- Trocar `remoteVideo` único por uma **grade de vídeos (video grid)**: um `<video>` por participante remoto,
  criado dinamicamente quando alguém entra e removido quando sai.
- Manter `localStream` único (a própria câmera/mic do usuário), enviado uma vez ao SFU (ou para cada peer no mesh).

### Esqueleto (SFU, conceitual)

```js
const localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
const remoteTiles = new Map(); // userId -> { videoEl, stream }

// Ao publicar mídia no SFU (API varia conforme mediasoup/LiveKit):
await room.localParticipant.publishTracks(localStream.getTracks());

// Ao receber a mídia de outro participante:
room.on('trackSubscribed', (track, participant) => {
  let tile = remoteTiles.get(participant.id);
  if (!tile) {
    const videoEl = document.createElement('video');
    videoEl.autoplay = true;
    videoEl.playsInline = true;
    document.getElementById('video-grid').appendChild(videoEl);
    tile = { videoEl, stream: new MediaStream() };
    remoteTiles.set(participant.id, tile);
    videoEl.srcObject = tile.stream;
  }
  tile.stream.addTrack(track);
});

room.on('participantDisconnected', (participant) => {
  const tile = remoteTiles.get(participant.id);
  if (tile) { tile.videoEl.remove(); remoteTiles.delete(participant.id); }
});
```

### Controles de mídia (reaproveitar a lógica atual, aplicada em grupo)

- **Microfone** e **câmera**: mesma abordagem `track.enabled = ...` do 1:1. Ao mudar, publicar um
  evento `media-state` para a sala para atualizar os badges de cada tile.
- **Compartilhamento de tela (NOVO — não existe hoje, você pediu)**:

```js
// Iniciar compartilhamento
const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
const screenTrack = screenStream.getVideoTracks()[0];

// SFU: publicar como uma track adicional (recomendado — deixa a câmera ligada em paralelo)
await room.localParticipant.publishTrack(screenTrack, { source: 'screen_share' });

// Mesh: usar replaceTrack em cada RTCPeerConnection, ou addTrack + renegociar
// pcSender.replaceTrack(screenTrack);

// Quando o usuário para pelo botão nativo do navegador:
screenTrack.onended = () => stopScreenShare();
```

> Detalhe importante do compartilhamento de tela: publicar como **track adicional** (source `screen_share`)
> é melhor que substituir a câmera, porque permite mostrar tela + rosto ao mesmo tempo e um layout
> "apresentador" (tela grande + miniaturas das câmeras).

---

## 7. Servidores ICE (STUN/TURN) — obrigatório revisar para grupo

O sistema atual usa **só STUN** (sem TURN). Em 1:1 entre amigos isso às vezes "passa", mas em grupo
com participantes atrás de NAT restritivo/firewall corporativo, **faltar TURN causa participantes que
não conectam**. Para grupo confiável:

- Se usar **SFU**, boa parte do problema de NAT some (todos falam com um servidor central), mas **ainda
  é recomendável TURN** para o próprio SFU em redes restritivas.
- Se usar **mesh**, **TURN é praticamente obrigatório**.
- Opções de TURN: **coturn** (open-source, self-hosted), ou TURN gerenciado (Twilio, Cloudflare, Xirsys, metered).

```js
iceServers: [
  { urls: 'stun:stun.l.google.com:19302' },
  {
    urls: 'turn:SEU_TURN_HOST:3478',
    username: 'USUARIO_GERADO',
    credential: 'CREDENCIAL_TEMPORARIA'
  }
]
```

> Gere credenciais TURN **temporárias** (time-limited) no backend, não hardcode credenciais no frontend.

---

## 8. Regras de negócio a preservar/adaptar

- **Gating por plano**: no atual, iniciar chamada exige `allow_video_chat`. Definir a regra equivalente
  para o sistema novo (quem pode **criar** uma sala em grupo? Quem pode **entrar**? Há limite por plano?).
- **Limite de participantes**: você pediu 5, 10, 15. Sugiro um campo configurável (`max_participants`)
  com um teto de segurança (ex.: 15 ou 20) para não estourar CPU/banda dos clientes no SFU.
- **Autorização de entrada**: no 1:1 é "ser amigo aceito". No grupo, definir: convite? Link? Membros de
  uma comunidade? Apenas quem o host permitir? Isso precisa ser decidido com o produto.
- **Papel de host**: quem cria a sala pode encerrá-la para todos, remover participantes, mutar alguém.
- **Encerramento**: distinguir "sair da chamada" (só o participante sai) de "encerrar a sala" (host encerra
  para todos).

---

## 9. Checklist de implementação (grupo)

1. [ ] Decidir a topologia — **recomendado SFU** para 5–15 pessoas (mediasoup/LiveKit/Janus).
2. [ ] Provisionar servidor de mídia (SFU) e servidor TURN (coturn ou gerenciado).
3. [ ] Criar as tabelas `group_call_rooms` e `group_call_participants` (e `group_call_signals` só se for mesh).
4. [ ] Implementar signaling via WebSocket (estender `realtime/server.js` ou usar o do SFU).
5. [ ] Reaproveitar o token HMAC (`SocialSocketController@token`) para autenticar no WebSocket, incluindo `room_id`.
6. [ ] Frontend: grade de vídeos dinâmica (1 tile por participante), entrada/saída dinâmica.
7. [ ] Portar controles de mic/câmera (mesma lógica `track.enabled`) + sincronização de estado via evento `media-state`.
8. [ ] Implementar **compartilhamento de tela** com `getDisplayMedia` (novo; publicar como track adicional).
9. [ ] Definir e aplicar regras de negócio: quem cria/entra, limite de participantes, papel de host, gating por plano.
10. [ ] Configurar STUN + **TURN** (credenciais temporárias geradas no backend).
11. [ ] Testar com 5, 10 e 15 participantes reais em redes diferentes (Wi-Fi, 4G, rede corporativa).

---

## 10. Diferenças rápidas: 1:1 atual → grupo alvo

| Aspecto | Atual (1:1) | Grupo (alvo) |
|---------|-------------|--------------|
| Participantes | 2 | 5–15+ |
| Conexões por cliente | 1 `RTCPeerConnection` | 1 (SFU) ou N-1 (mesh) |
| Topologia | P2P direto | **SFU** recomendado |
| Signaling | HTTP long-polling + MySQL | WebSocket (SFU ou Socket.IO estendido) |
| Vídeo remoto | 1 elemento `<video>` fixo | Grade dinâmica (1 por participante) |
| Modelo de dados | Conversa 1:1 (`user1_id`/`user2_id`) | Sala + participantes (`group_call_rooms`/`participants`) |
| Roteamento de sinal | `to_user_id` único | Por sala e/ou por par de participantes |
| STUN/TURN | Só STUN | STUN + **TURN** |
| Microfone | ✅ `track.enabled` | ✅ igual |
| Câmera | ✅ `track.enabled` | ✅ igual |
| Compartilhamento de tela | ❌ não existe | ✅ **implementar** (`getDisplayMedia`) |
| Host / moderação | não há | host encerra sala, remove/muta participantes |

---

### Observação final para o desenvolvedor

O sistema atual é um bom ponto de partida para entender **os controles de mídia** (mic/câmera via
`track.enabled` + sincronização de estado) e o **ciclo de vida de uma chamada** (offer/answer/ICE/end),
mas a **topologia 1:1 e o signaling por HTTP-polling não devem ser copiados diretamente** para o grupo.
Para 5–15 participantes, o esforço se concentra em: escolher um SFU, montar a sala/presença, a grade de
vídeos dinâmica e adicionar TURN e compartilhamento de tela. A parte de ligar/desligar mic e câmera é a
que mais se aproveita como está.
