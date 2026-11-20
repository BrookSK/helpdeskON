## ADDED Requirements

### Requirement: Gravação continua com a aba oculta
A gravação da reunião SHALL continuar atualizando a imagem gravada quando a aba de quem grava fica oculta (outra aba, janela minimizada ou coberta). O redesenho do mosaico MUST NOT depender de `requestAnimationFrame`.

#### Scenario: Quem grava troca de aba durante a reunião
- **WHEN** a gravação está em andamento e o navegador deixa de chamar `requestAnimationFrame` na aba da reunião
- **THEN** o mosaico da gravação continua mudando conforme as câmeras mudam
