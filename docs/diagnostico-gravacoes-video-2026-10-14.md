# Gravações de videochamada: diagnóstico, correção e instruções

Atualizado em 14/10/2026 após implementar e validar a correção localmente.

**Estado atual: implementação local concluída; 56/56 cenários passaram em ambiente isolado. Não houve deploy nem alteração em produção.** O resultado cobre os cenários descritos abaixo; não é uma garantia absoluta para qualquer navegador, infraestrutura ou interrupção física.

## Incidente analisado

Origem: `drive-download-20261014T202116Z-1-001.zip`. Os originais foram preservados.

| Arquivo | Bytes | Evidência |
| --- | ---: | --- |
| `rec_85_6a367da6c8f004e0046565989363769b.webm` | 629444992 | VP9 1920×1080 e Opus, cabeçalho presente, duração ausente e término prematuro |
| `rec_85_7c3a29c94fa89769028ed1a7ea53695e.webm` | 220841775 | Sem cabeçalho EBML; ffprobe falha ao abrir |

O maior passou do limite antigo de 600 MiB em 299392 bytes. Seu último pacote identificável está em 57:21.194. O primeiro Cluster identificável do menor está em 59:39.589; sua mídia chega a aproximadamente 1:21:47.212. A diferença entre esses pontos é cerca de 138,4 segundos. Existem bytes anteriores ao primeiro Cluster do menor, portanto esses pontos não medem exatamente todo o intervalo perdido.

A sequência mais provável é: teto de tamanho atingido → HTTP 413 ignorado pelo navegador → arquivo sem receber dados por mais de 120 segundos → recuperação por `mtime` move o arquivo durante a gravação → novo envio recria uma continuação sem cabeçalho. Os arquivos e o código sustentam essa sequência; confirmar o evento histórico exige os logs e registros do servidor, que não foram acessados.

Também foram reproduzidas as seguintes falhas: descarte de remux válido por leitura incorreta da duração; finalização antiga apagando backup da sessão nova; confirmação de sucesso após falha de escrita; e arquivo fora da listagem após falha no insert do banco.

Concatenar todos os pedaços de uma gravação na ordem correta não é, por si só, o defeito. A especificação permite que cada Blob isolado não seja reproduzível e exige que sua combinação completa seja reproduzível: [MediaStream Recording](https://www.w3.org/TR/mediastream-recording/#mediarecorder-methods).

## Correção implementada

| Área | Comportamento atual |
| --- | --- |
| Captura e estado do cliente | Cada `MediaRecorder` mantém sua própria sessão e seus próprios callbacks. Uma resposta antiga não apaga dados de uma gravação nova. |
| Cópia local | Dados são gravados no IndexedDB antes do envio. A confirmação aguarda o evento `complete` da transação, não apenas o sucesso de um request individual. Não é mantido um array com a reunião inteira em RAM. |
| Transporte | Eventos grandes do MediaRecorder são divididos em pedaços de até 1 MiB. Cada pedaço leva sequência, tamanho e SHA-256. HTTP/JSON e ACK são conferidos; falhas transitórias têm retry limitado. |
| Persistência do servidor | Pedaços imutáveis, escrita verificada, flush/fsync, rename e journal atômico. Um lock estável por sessão serializa envio, confirmação e finalização. |
| Reenvio | Mesma sequência e mesmos bytes não duplicam conteúdo. Conteúdo divergente, checksum incorreto e ordem inválida são recusados. |
| Finalização | Só ocorre após o último `dataavailable`, persistência e confirmação da fila. O manifesto final vincula quantidade, bytes e hashes de todos os pedaços. |
| Arquivo e banco | Token estável, journal antes da publicação e consulta por token permitem retomar após falha no insert ou perda de resposta. Finalização repetida retorna a mesma gravação. |
| Validação de mídia | Remux exige FFmpeg/exec, áudio e vídeo, duração positiva e ausência dos erros de integridade verificados. A saída de diagnóstico do FFmpeg é capturada corretamente. Não há fallback que publique um vídeo inválido como sucesso. |
| Temporários | Tentativas usam arquivos exclusivos, inclusive após crash. Depois de confirmar arquivo, banco e journal, os chunks brutos são removidos. O journal pequeno permanece para idempotência. |
| Pausas e recuperação | Abrir a listagem não move mais arquivos por inatividade. Pausas não dividem gravações. Partes antigas são preservadas para recuperação explícita. |
| Encerramento da sala | Uma sessão já autorizada pode concluir os envios por até 24 horas desde o início. Sala encerrada/expirada não aceita uma gravação nova. A sessão gravadora continua sendo validada. |
| Fechamento abrupto | Não há beacon declarando sucesso sem o último pedaço. Ao retornar à sala, uma gravação completamente capturada pode ser reenviada; um trecho sem confirmação de fim oferece download identificado como parcial. |

A verificação do manifesto é necessária: os testes removeram um Cluster intermediário e o vídeo ainda passou pelo remux, manteve a duração e foi decodificado sem erro. Validade do contêiner, sozinha, não prova que todos os pedaços chegaram.

## Arquivos de implementação

- `app/core/RecordingSessionStore.php`: journal, lock, checksums, sequência, manifesto, montagem e limpeza.
- `app/core/RecordingMedia.php`: remux e validação da mídia.
- `app/controllers/VideocallController.php`: endpoints, associação da sessão ao gravador, publicação e reconciliação arquivo/banco.
- `public/assets/js/recording-upload.js`: IndexedDB, fila persistente, retry, ACK, manifesto, retomada e download.
- `app/views/videocall/room.php`: captura e lifecycle por sessão, finalização após `onstop` e controles de pendências.
- `.htaccess`: bloqueio de acesso HTTP ao diretório `storage/` quando a raiz do projeto é servida pelo Apache.
- `.gitignore`: exclusão dos journals e chunks gerados em `storage/video-recordings/`.

Não foi necessária alteração de schema. O model e as queries de gravação existentes foram mantidos.

## Contrato dos endpoints

Todos usam POST e o token da sala no caminho. `sess` identifica uma sessão de gravação com 16–64 caracteres alfanuméricos, hífen ou underscore; o cliente usa UUID aleatório.

| Endpoint | Campos relevantes | Resultado |
| --- | --- | --- |
| `/videocall/recStart/{roomToken}` | `sess`, `recorded_by_name` | Valida sala/permissão e capacidade de finalização; cria sessão com token final estável. |
| `/videocall/recChunk/{roomToken}` | `sess`, `seq`, `sha256`, arquivo `chunk` | Confirma `success`, `seq`, `size`, `sha256` somente após persistência. |
| `/videocall/recStatus/{roomToken}` | `sess` | Retorna estado, próxima sequência e bytes confirmados. |
| `/videocall/recHeartbeat/{roomToken}` | `sess`, `paused` | Atualiza presença/pausa, sem finalizar por inatividade. |
| `/videocall/recFinalize/{roomToken}` | `sess`, `ended=1`, `count`, `bytes`, `manifest` | Confere manifesto e mídia; publica ou retoma publicação com o mesmo token. |

`manifest` é SHA-256, em hexadecimal, do texto UTF-8 composto por uma linha por pedaço: `sequencia:tamanho:sha256\n`, em ordem. O tamanho é em bytes. A quebra de linha é LF real.

Erros 409/422 indicam conflito/integridade; 413 indica limite; 503/507 indicam indisponibilidade ou falha de persistência. O cliente mantém sua cópia diante de falha ou resposta ambígua. O upload integral legado responde 409 pedindo atualização da página: ele não substitui um envio verificável.

## Requisitos para colocar a correção em uso

1. **Publicar os arquivos PHP e o JS juntos**, com a view atualizada. Planejar a troca quando não houver gravações antigas em andamento; o cliente anterior não usa o protocolo novo. Recarregar as páginas de chamada após a publicação.
2. Disponibilizar FFmpeg e permitir `exec` no PHP. O binário pode vir de `ffmpeg_path` já usado pela aplicação ou do PATH. Sem esses recursos, uma nova gravação é recusada antes de iniciar a captura.
3. Garantir escrita pelo usuário do PHP em `storage/video-recordings/` e `public/uploads/recordings/`. O armazenamento precisa ser persistente entre releases e reinicializações. `flock` e rename precisam funcionar entre os processos que atendem a mesma sessão; múltiplas instâncias não podem usar diretórios independentes para uma mesma gravação.
4. Servir preferencialmente `public/` como document root. Se a raiz do projeto for servida pelo Apache, manter a regra que bloqueia `storage/`. Em outro servidor com raiz no projeto, configurar bloqueio equivalente; journals e chunks não devem ser públicos.
5. Cada request envia no máximo 1 MiB de vídeo mais multipart. Limites efetivos de PHP/proxy devem aceitar isso; `upload_max_filesize=2M` e `post_max_size=4M` já acomodam o protocolo. Os limites maiores existentes podem ser mantidos. Não é necessário um POST único de centenas de MB.
6. O teto acumulado padrão é **2 GiB por sessão**. A variável de ambiente **`VIDEO_RECORDING_MAX_BYTES`**, em bytes, permite ajustá-lo. Reservar espaço também para a montagem e o remux: no pior caso, cerca de três vezes o tamanho de uma gravação em finalização, além dos demais arquivos.
7. O navegador precisa de HTTPS ou localhost, MediaRecorder, Web Crypto e IndexedDB funcionais. A cópia local depende da quota do navegador e deve ser preservada até a confirmação. Quando o armazenamento local falha, a aplicação não declara captura completa.
8. Antes de liberar para usuários, executar a bateria abaixo e conferir os requisitos efetivos no ambiente de destino. Os testes locais não consultaram nem modificaram produção.

## Instruções para o usuário da chamada

- Parar normalmente a gravação e aguardar a confirmação de salvamento.
- Se houver pendência, usar **Reenviar** ou **Baixar cópia local**. Não limpar os dados do site antes de resolver a pendência.
- Após recarregar a mesma sala no mesmo perfil do navegador, uma captura com fim confirmado pode ser reenviada a partir do IndexedDB.
- Após fechamento abrupto, o sistema pode oferecer somente um **trecho parcial**. O final que não chegou a ser capturado/persistido não pode ser reconstruído pelo protocolo.
- Ao atingir o limite do servidor, a captura é parada de forma controlada e a cópia local é preservada. Aumentar o limite ou dividir reuniões precisa respeitar a capacidade de armazenamento; não se devem publicar cortes arbitrários de bytes como vídeos independentes.

## Testes executados e reprodução

**56/56 cenários passaram em uma execução nova do runner, com zero falhas, cancelamentos ou skips.**

| Bateria | Casos | Escopo |
| --- | ---: | --- |
| PHP 8.3 / SQLite / FFmpeg no Docker | 28 | Controller e model reais, escrita, hashes, manifesto, permissões, falhas no banco, recuperação, teto, arquivo corrompido e erro de escrita imposto pelo SO. |
| Cliente JavaScript | 13 | Código real do transportador com fronteiras controladas: HTTP/JSON, ACK perdido, quota, corrupção local, manifesto e isolamento entre sessões. |
| HTTP com quatro workers PHP | 6 | Multipart real, uploads simultâneos, payload divergente, finalizações simultâneas, corrida append/finalize, ownership e HTTP Range. |
| Chromium / Playwright local | 8 | MediaRecorder real, IndexedDB real, remux/seek, respostas perdidas, reload, fechamento abrupto, 413, transação abortada e handlers reais de stop/start de `room.php`. |
| Arquivo acima do limite antigo | 1 | WebM válido com 681774040 bytes, 651 chunks, finalização correta e pico de 6291456 bytes de memória PHP sob limite de 128 MiB. |

O teste grande usa um elemento EBML Void válido para aumentar o contêiner, mantendo seis segundos de áudio/vídeo. Ele prova travessia do limite e consumo de memória, **não** uma gravação de várias horas nem continuidade da captura em segundo plano.

Para reproduzir, a partir da raiz do projeto, com Docker acessível, Node, dependências locais de Playwright e Chromium instalados:

```sh
npm install --ignore-scripts --no-audit --no-fund
npx playwright install chromium
bash tests/diagnostics/video-recording/run.sh
```

Se precisar de cache de navegador em outro diretório, usar a mesma variável na instalação e na execução:

```sh
PLAYWRIGHT_BROWSERS_PATH=/tmp/helpdesk-playwright-browsers npx playwright install chromium
PLAYWRIGHT_BROWSERS_PATH=/tmp/helpdesk-playwright-browsers bash tests/diagnostics/video-recording/run.sh
```

O runner cria nome e imagem exclusivos, usa banco SQLite próprio, publica apenas uma porta aleatória em `127.0.0.1`, guarda os resultados em `tests/diagnostics/video-recording/results/current/` e remove seu contêiner e sua imagem ao terminar, inclusive em erro. Ele não faz prune global nem remove imagens anteriores.

O relatório técnico fica em `tests/diagnostics/video-recording/README.md`. Resultados de reprodução das falhas anteriores foram arquivados em `results/baseline/`.

## Limites da validação e operação dos dados

- O model real foi testado com SQLite por um adapter de teste. O driver/configuração MySQL da produção e o servidor web de destino não foram homologados nesta execução. Não houve teste de endurance de horas, múltiplos navegadores ou falha física de armazenamento.
- Checksums e manifesto verificam transporte e persistência. Eles não provam que câmera, microfone e canvas capturaram conteúdo continuamente durante suspensão do navegador.
- Sem fim confirmado, o servidor não transforma uma sessão interrompida em gravação completa. Partes antigas e sessões interrompidas não são apagadas automaticamente por idade; definir retenção operacional depois de preservar o que precisar ser recuperado.
- Journals pequenos concluídos permanecem para reconhecer retries. Os chunks concluídos são removidos somente após arquivo, banco e journal confirmados. O prazo de retomada de uma sessão não concluída é de 24 horas desde o início; depois disso, a cópia local ainda pode ser baixada.

## Vídeos defeituosos já existentes e rollback

A correção evita a publicação de novas gravações incompletas nos cenários testados; ela não reconstrói bytes ausentes dos arquivos do ZIP.

Preservar o ZIP e as fontes antes de reparar. O maior foi reempacotado localmente e recebeu duração aproximada de 57:22, mas continua representando apenas o trecho disponível. A cauda menor permitiu inspeção parcial após reconstrução assistida de cabeçalho; isso não é restauração integral garantida. Não juntar os dois e declarar a reunião completa.

Se uma publicação futura precisar de rollback, interromper a entrada de novas gravações, preservar journals/chunks e as cópias locais e reverter o conjunto de PHP/JS/view de maneira coordenada. O cliente antigo não entende o journal v2. Não apagar dados nem reinstalar a recuperação automática por `mtime` como medida de reparo.
