# Gate de testes antes de produção — helpdeskON

Regra **permanente e obrigatória**. Toda alteração de código deste projeto —
e, com rigor máximo, qualquer subida para **produção** (merge/deploy de uma
branch para a branch de produção) — TEM que passar por toda a fase de testes
descrita aqui antes de ser considerada pronta.

Esta regra complementa (não substitui) as políticas detalhadas em:
- Testes de backend: `.kiro/steering/testes.md` (PHPUnit).
- Testes de interface: `.kiro/steering/e2e-playwright.md` (Playwright/Chromium).

## Quando este gate se aplica

- SEMPRE que houver mudança em código PHP, views, migrations, regras de negócio
  ou configuração que afete comportamento.
- OBRIGATORIAMENTE antes de: abrir PR para a branch de produção, fazer merge
  para produção, ou publicar/deployar.
- Também ao concluir qualquer demanda/tarefa: a demanda só é "pronta" depois de
  verde (ver "Definição de pronto").

Não pule o gate "porque a mudança é pequena". Mudança pequena também quebra.

## As duas suítes que rodamos

1. **PHPUnit** (backend: regras de negócio + persistência).
   - Unit (`tests/Unit`): regras puras, sem banco.
   - Integration (`tests/Integration`): contra o banco `helpdesk_on_test`.
2. **Playwright / Chromium** (E2E: comportamento pela interface).
   - Specs em `tests-e2e/*.spec.ts`, servidor PHP embutido na porta 8199.

Detalhes de ambiente, caminhos e banco estão nas duas steering referenciadas
acima. Resumo operacional abaixo.

## Passo a passo do gate (na ordem)

Execute do diretório raiz do projeto (`d:\Projects\GitHub\helpdeskON`).

1. **Sintaxe** de todo arquivo PHP alterado:
   `C:\php\php.exe -l caminho\do\arquivo.php` (não deve haver erro).

2. **(Re)criar o banco de teste** quando o schema mudou (nova migration, coluna,
   tabela) — senão os testes de integração rodam contra um schema velho:
   `C:\php\php.exe tests/setup_test_db.php`
   (recria `helpdesk_on_test` a partir do `helpdesk_on` já migrado).
   Se você criou uma migration nova, aplique-a antes no banco local `helpdesk_on`.

3. **PHPUnit — suíte completa**:
   `run_tests.bat` (grava `phpunit_result.txt`) ou
   `C:\php\php.exe vendor\phpunit\phpunit\phpunit --testdox`.

4. **Playwright — suíte completa**:
   `run_e2e.bat` (grava `e2e_result.txt`) ou `npx playwright test`.

5. **Ler os resultados** e reportar quantos passaram/falharam em cada suíte.

Se qualquer suíte falhar, o gate está **reprovado**: corrija e rode de novo.
Não prossiga para produção com suíte vermelha.

## Como testar o mais fiel possível à realidade

O objetivo é que o teste reproduza o uso real, não uma versão de laboratório
que "passa" mas não representa o que o usuário faz.

- **Cubra o cenário de verdade, não o caminho feliz só.** Para cada mudança,
  teste: o caso normal, os limites (vazio, nulo, valores iguais, duplicados),
  os erros esperados (permissão negada, dado inválido) e a regressão que a
  mudança poderia causar em quem já funcionava.
- **Backend com dados próprios.** Testes de integração criam seus próprios
  registros no `setUp` (inclusive os pais para FKs) e limpam no `tearDown`.
  Nunca dependa de dados pré-existentes no banco.
- **Escolha o nível certo.** Regra de negócio pura → Unit. Persistência /
  query / model → Integration. Comportamento visível na tela (estado de botão,
  navegação, fluxo do usuário) → E2E.
- **Extraia regra pura quando fizer sentido.** Lógica de decisão (status,
  permissão, cálculo, validação) deve virar classe pura testável (padrão já
  usado: `AgendaRules`, `CrmRules`, `RdoRules`, `Permissions`, `TicketAccess`,
  `ImageUploadRules`). Fica fácil de testar e vira fonte única de verdade.
- **E2E fiel:** use o mesmo caminho do usuário (mesmas rotas, mesmos cliques).
  Fluxos com câmera/microfone já usam mídia falsa configurada. E2E cria os
  próprios dados (ex.: `tests-e2e/make-room.php`) e não deixa lixo.
- **Um 200/HTTP OK não é homologação.** Só considere validado o que um teste
  confirmou de fato (valor gravado, tela refletindo o estado, permissão barrada).
- **O que não dá para testar aqui, diga explicitamente.** Integrações externas
  (envio real de WhatsApp/e-mail, APIs de terceiros, transcrição) dependem de
  credenciais/serviços que não existem no ambiente de teste. Não finja que
  testou: registre que ficou de fora e por quê, e valide manualmente no
  ambiente com as integrações ativas.

## Ao mexer, ajuste as demandas para ficar "certinho"

- Se a mudança alterou o comportamento esperado, **atualize/adicione o teste**
  correspondente na mesma leva — não deixe teste desatualizado.
- Se um teste começou a falhar, **descubra a causa raiz** antes de tocar no
  teste. Corrija o código se foi regressão; só ajuste o teste se a expectativa
  dele estava incorreta (e explique por quê).
- Limpe artefatos temporários criados para validar (arquivos soltos, dados de
  teste no banco local).

## Regras de conduta (invioláveis)

- Nunca alterar regra de negócio só para um teste passar.
- Nunca enfraquecer/remover uma validação existente para obter aprovação.
- Nunca alterar um teste existente para esconder uma regressão.
- Se o comportamento esperado for ambíguo ou parecer errado, **pare e alinhe**
  antes de mudar o código.

## Definição de pronto (checklist do gate)

Uma alteração só está pronta para produção quando TODOS os itens abaixo valem:

- [ ] `php -l` sem erros nos arquivos alterados.
- [ ] Banco de teste recriado se o schema mudou.
- [ ] Testes unitários relacionados passam.
- [ ] Testes de integração relacionados passam (contra `helpdesk_on_test`).
- [ ] Suíte PHPUnit completa verde (sem regressão).
- [ ] Suíte Playwright/E2E completa verde (sem regressão).
- [ ] Resultado reportado (quantos passaram/falharam em cada suíte).
- [ ] Itens não testáveis no ambiente (integrações externas) explicitamente
      listados para validação manual.
