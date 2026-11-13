## 1. Navegador

- [x] 1.1 Limitar o vídeo da gravação a 1 Mbps
- [x] 1.2 Avisar quem grava quando o vídeo atingir o limite do servidor
- [x] 1.3 Finalizar como parcial depois do limite e baixar a cópia completa
- [x] 1.4 Versionar a URL de `recording-upload.js` para o navegador não usar a versão antiga do cache

## 2. Servidor

- [x] 2.1 `recFinalizeV2` aceita `partial=1` e publica como incompleta
- [x] 2.2 Pular a injeção de duração sem FFmpeg acima de 64 MiB

## 3. Testes

- [x] 3.1 Teste de integração: arquivo grande não é lido inteiro na memória
- [x] 3.2 E2E: limite atingido publica a gravação parcial, baixa a cópia e põe o áudio na fila
- [x] 3.3 Rodar PHPUnit e Playwright completos
