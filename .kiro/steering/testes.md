# Política de testes (PHPUnit) — helpdeskON

Regras permanentes para qualquer trabalho de backend neste projeto.

## Ambiente

- PHP: `C:\php\php.exe` (8.5.1). O `php` pode não estar no PATH; use o caminho completo.
- Composer: `composer.phar` na raiz. Uso: `C:\php\php.exe composer.phar <comando>`.
- PHPUnit: instalado em `vendor/` (require-dev). Binário: `vendor\phpunit\phpunit\phpunit`.
- Rodar a suíte: executar `run_tests.bat` (grava saída em `phpunit_result.txt`) ou
  `C:\php\php.exe vendor\phpunit\phpunit\phpunit`.

## Níveis de teste

1. **Unit** (`tests/Unit`): regras isoladas, sem banco/rede.
2. **Integration** (`tests/Integration`): usam o banco `helpdesk_on_test`.
3. **E2E** (`tests/E2E`): fluxos pela interface (Playwright), quando aplicável.

## Banco de teste

- Os testes SEMPRE rodam contra `helpdesk_on_test`, nunca contra `helpdesk_on`
  (local), beta ou produção. Isso é garantido pelo bootstrap (`tests/bootstrap.php`
  define `APP_ENV=testing`) e pelo `config/database.php` (camada 0 = testing).
- Para (re)criar o schema do banco de teste a partir do local já migrado:
  `C:\php\php.exe tests/setup_test_db.php`. Esse script recria `helpdesk_on_test`
  copiando a estrutura de `helpdesk_on` e restaurando AUTO_INCREMENT nas PKs.
- Testes de integração devem criar seus próprios dados e limpá-los (setUp/tearDown).
  Ao depender de `created_by`/FKs, crie os registros pai no setUp.

## Regras de conduta

- Nunca alterar regra de negócio só para um teste passar.
- Nunca enfraquecer/remover uma validação existente para obter aprovação de teste.
- Nunca alterar um teste existente para esconder uma regressão.
- Se o comportamento esperado for ambíguo ou parecer incorreto, interromper e
  apresentar o conflito antes de mudar o código.

## Critério de conclusão (definição de pronto)

Uma tarefa de backend só é considerada concluída quando:

- [ ] Testes unitários relacionados passam.
- [ ] Testes de integração relacionados passam (contra `helpdesk_on_test`).
- [ ] Suítes existentes continuam passando (sem regressão).
- [ ] Resultado dos testes reportado (quantos passaram/falharam).

Não considerar algo "homologado" apenas porque o código rodou ou uma API respondeu 200.
