# Guia — PHPUnit + Testes de Cenário no Kiro (projeto helpdeskON)

Este guia é específico para **este** projeto. Ele já considera o que existe hoje:

- **PHP 8.5.1** (CLI) instalado. Compatível com PHPUnit 11/12.
- **Composer NÃO instalado** ainda (não está no PATH).
- Arquitetura: **PHP puro, MVC próprio** (sem Laravel/Symfony).
  - Entrada web: `public/index.php` (define `BASE_PATH`, `APP_PATH`, autoload próprio, sessão).
  - Autoload: `spl_autoload_register` procura em `app/core`, `app/controllers`, `app/models`.
  - Banco: classe `Database` (singleton, PDO MySQL) lendo `config/database.php`.
  - Roteamento: `app/core/Router.php` mapeia `url` → `XxxController::metodo`.
- Ambientes de banco já configurados em `config/database.php`: **local**, **beta**, **produção**.
- Fluxo de "card" existe de verdade em `app/models/PlanningCard.php`, com os status:
  `open → in_progress → em_revisao_interna → waiting_client → em_homologacao → aprovado_producao → completed` (ou `denied`, `archived`).

O guia está em 3 níveis: **unitário (PHPUnit)**, **integração (PHPUnit + banco de teste)** e **cenário/E2E (Playwright)**. E mostra como usar Kiro (Steering, Hooks, #terminal) para automatizar tudo.

---

## Sumário

1. Pré-requisitos (instalar Composer OU usar o phpunit.phar)
2. Instalar e configurar o PHPUnit
3. Estrutura de pastas de teste
4. Nível 1 — Testes unitários
5. Nível 2 — Testes de integração (com banco de teste)
6. Nível 3 — Testes de cenário / E2E com Playwright
7. Matriz de cenários (modelo)
8. Automação no Kiro (Steering + Hooks + #terminal)
9. Prompts prontos para colar no chat do Kiro
10. Critério de conclusão ("definição de pronto")

---

## 1. Pré-requisitos

O projeto ainda não tem Composer. Você tem dois caminhos. **Escolha um.**

### Opção A — Instalar o Composer (recomendado)

No Windows, o mais simples é o instalador oficial `Composer-Setup.exe` do site getcomposer.org. Depois, feche e reabra o terminal e confirme:

```
composer -V
```

Se preferir sem instalador, dá para baixar o `composer.phar` na raiz do projeto e chamar via `php composer.phar ...`.

### Opção B — Usar só o phpunit.phar (sem Composer)

Se você não quer Composer agora, dá para baixar apenas o binário do PHPUnit. Baixe o `phpunit.phar` compatível com PHP 8.5 e coloque em uma pasta `tools/` do projeto. A execução passa a ser:

```
php tools/phpunit.phar --version
```

> Recomendo a **Opção A**. Composer facilita autoload dos testes, versionamento da ferramenta e a instalação futura de bibliotecas de mock/faker.

---

## 2. Instalar e configurar o PHPUnit

### 2.1. Instalar (com Composer)

Na raiz do projeto (`d:\Projects\GitHub\helpdeskON`):

```
composer require --dev phpunit/phpunit
```

Confirme:

```
vendor\bin\phpunit --version
```

### 2.2. `composer.json` — autoload dos testes

Como o projeto usa autoload próprio (sem namespaces/PSR-4 nas classes de `app/`), o mais prático é:

- deixar as classes de produção sendo carregadas por um **bootstrap de teste** (que reaproveita o autoload do projeto), e
- registrar só a pasta de testes no autoload do Composer.

Exemplo de trecho a adicionar no `composer.json`:

```json
{
  "autoload-dev": {
    "psr-4": {
      "Tests\\": "tests/"
    }
  }
}
```

Depois rode:

```
composer dump-autoload
```

### 2.3. `phpunit.xml` na raiz

Crie `phpunit.xml` na raiz do projeto:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <!-- Marca o ambiente como teste para o bootstrap/config -->
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

### 2.4. `tests/bootstrap.php`

Este arquivo reaproveita o autoload e as constantes do projeto, sem subir o roteador. É o ponto-chave para testar código PHP puro sem framework.

```php
<?php
// tests/bootstrap.php

// Constantes que o código de produção espera (ver public/index.php)
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

date_default_timezone_set('America/Sao_Paulo');

// Mesmo autoload do public/index.php, para carregar core/controllers/models
spl_autoload_register(function ($class) {
    $paths = [
        APP_PATH . '/core/',
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
    ];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Autoload do Composer (para as classes em tests/ e libs de dev)
require BASE_PATH . '/vendor/autoload.php';
```

### 2.5. Teste "fumaça" para validar a instalação

`tests/Unit/SmokeTest.php`:

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testAmbienteDeTesteFunciona(): void
    {
        $this->assertTrue(defined('BASE_PATH'));
        $this->assertSame(5, 2 + 3);
    }
}
```

Rode:

```
vendor\bin\phpunit --testsuite unit
```

Se aparecer verde, o PHPUnit está configurado.

---

## 3. Estrutura de pastas de teste

```
helpdeskON/
├── app/
│   ├── core/        (Database, Router, helpers, ...)
│   ├── controllers/
│   └── models/      (PlanningCard, Ticket, User, ...)
├── config/
│   └── database.php (local / beta / produção)
├── tests/
│   ├── bootstrap.php
│   ├── Unit/            <- regras isoladas, sem banco
│   ├── Integration/     <- com banco de teste (MySQL local)
│   └── E2E/             <- Playwright (fluxos pela tela)
├── phpunit.xml
└── composer.json
```

---

## 4. Nível 1 — Testes unitários

**O que testar aqui:** lógica que **não** depende de banco/rede. No projeto atual, boa parte da regra está acoplada ao PDO (os models chamam `Database::getInstance()` direto no construtor), então o candidato mais limpo a teste unitário puro são:

- funções em `app/core/helpers.php`;
- transformações/validações puras (formatação, cálculo de datas, montagem de strings);
- regras de transição de status (ver observação abaixo).

> **Observação sobre testabilidade:** como os models instanciam `Database` no construtor, testá-los "de verdade" cai no Nível 2 (integração). Para transformar regra de status em teste unitário puro, o ideal é extrair a regra "quais transições de status são válidas" para um método/serviço sem dependência de banco. Isso é uma melhoria opcional — não altere a regra de negócio só para passar teste; se decidir extrair, faça como refactor consciente.

Exemplo de teste unitário para um helper (ajuste o nome da função ao que existir em `helpers.php`):

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testFuncaoDeHelperRetornaValorEsperado(): void
    {
        // Exemplo: se existir uma função e() de escape, ou formatação de data.
        // Substitua pela função real de app/core/helpers.php
        $this->assertSame('abc', trim('  abc  '));
    }
}
```

Exemplo de teste unitário de **regra de transição** (assumindo que você extraia uma classe `CardStatusRules` com o método estático `canTransition($de, $para): bool`):

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CardStatusRulesTest extends TestCase
{
    public function testFluxoDeHomologacaoValido(): void
    {
        $this->assertTrue(CardStatusRules::canTransition('em_homologacao', 'aprovado_producao'));
    }

    public function testNaoPermitePularDeAbertoParaConcluido(): void
    {
        $this->assertFalse(CardStatusRules::canTransition('open', 'completed'));
    }
}
```

---

## 5. Nível 2 — Testes de integração (com banco de teste)

Aqui validamos os models de verdade contra um MySQL. **Nunca** rode contra o banco local de trabalho: use um banco dedicado de teste.

### 5.1. Banco de teste separado

Crie um banco só para testes, por exemplo `helpdesk_on_test`, e aplique as migrations de `migrations/` nele. Assim os testes podem criar/apagar dados sem sujar o `helpdesk_on_local`.

### 5.2. Como apontar o `Database` para o banco de teste

O `Database` lê `config/database.php`. Para testes, o mais seguro é fazer o `config/database.php` reconhecer o ambiente de teste **antes** de decidir por host/branch. Sugestão de ajuste no topo da função de config (ele já detecta local/beta/produção):

```php
// Em config/database.php, logo no início da função:
if (getenv('APP_ENV') === 'testing' || (defined('APP_ENV') && APP_ENV === 'testing')) {
    return [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'database' => 'helpdesk_on_test',
        'username' => 'root',
        'password' => '',
    ];
}
```

O `phpunit.xml` já define `APP_ENV=testing`, então ao rodar via PHPUnit a conexão vai para o banco de teste automaticamente.

### 5.3. Exemplo de teste de integração do PlanningCard

```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PlanningCardTest extends TestCase
{
    private PlanningCard $cards;

    protected function setUp(): void
    {
        // Confirma que estamos no banco de teste
        $cfg = require BASE_PATH . '/config/database.php';
        if ($cfg['database'] !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste não configurado (esperado helpdesk_on_test).');
        }
        $this->cards = new PlanningCard();
    }

    public function testCriarCardGravaNoBancoComStatusInicial(): void
    {
        $id = $this->cards->create([
            'title'      => 'Card de teste',
            'status'     => 'open',
            'priority'   => 'medium',
            'created_by' => 1,
            'position'   => 0,
        ]);

        $this->assertNotEmpty($id);

        $card = $this->cards->findById($id);
        $this->assertSame('Card de teste', $card['title']);
        $this->assertSame('open', $card['status']);

        // limpeza
        $this->cards->delete($id);
    }

    public function testAlterarStatusParaHomologacao(): void
    {
        $id = $this->cards->create([
            'title' => 'Card homolog', 'status' => 'open',
            'priority' => 'high', 'created_by' => 1, 'position' => 0,
        ]);

        $this->cards->updateStatus($id, 'em_homologacao');
        $card = $this->cards->findById($id);
        $this->assertSame('em_homologacao', $card['status']);

        $this->cards->delete($id);
    }
}
```

Rode a suíte de integração:

```
vendor\bin\phpunit --testsuite integration
```

> Dica: use `setUp()`/`tearDown()` para criar e limpar dados, ou envolva cada teste numa transação e faça rollback ao final, para não deixar lixo no banco de teste.

---

## 6. Nível 3 — Testes de cenário / E2E com Playwright

PHPUnit valida a lógica e o banco, mas **não** simula um usuário clicando na tela ("criar card → homologar → concluir"). Para isso, use **Playwright**.

### 6.1. Por que Playwright aqui

Playwright abre o navegador de verdade, faz login, clica, preenche formulário, salva e verifica o que aparece na tela. É o que mais se aproxima de "homologação automatizada" do fluxo real.

### 6.2. Instalação

Playwright é Node.js. Numa pasta `tests/E2E/` (ou na raiz), inicialize:

```
npm init -y
npm install -D @playwright/test
npx playwright install
```

### 6.3. Configuração básica (`playwright.config.ts`)

```ts
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/E2E',
  use: {
    // URL do ambiente LOCAL do projeto (ajuste porta/host do seu servidor PHP)
    baseURL: 'http://localhost',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
  },
});
```

### 6.4. Exemplo de cenário: criar e homologar um card

```ts
import { test, expect } from '@playwright/test';

test('fluxo completo: criar card e enviar para homologação', async ({ page }) => {
  // 1. Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'usuario@exemplo.com');
  await page.fill('input[name="password"]', 'senha');
  await page.click('button[type="submit"]');

  // 2. Ir ao planejamento (ajuste a rota real: /planning)
  await page.goto('/planning');

  // 3. Criar card
  await page.click('text=Novo Card');
  await page.fill('input[name="title"]', 'Card E2E');
  await page.click('text=Salvar');

  // 4. Validar que apareceu
  await expect(page.locator('text=Card E2E')).toBeVisible();

  // 5. Abrir e mudar status para homologação
  await page.click('text=Card E2E');
  await page.selectOption('select[name="status"]', 'em_homologacao');
  await page.click('text=Salvar');

  // 6. Validar status na tela
  await expect(page.locator('text=Em Homologação')).toBeVisible();
});
```

> Os seletores acima são exemplos. Ajuste-os aos `name`/textos reais das views (`app/views/planning/...`). Kiro pode inspecionar as views e gerar os seletores corretos.

Rode os E2E:

```
npx playwright test
```

### 6.5. Playwright MCP no Kiro (opcional, mas poderoso)

O Kiro pode operar o navegador via um servidor MCP do Playwright. Isso permite pedir ao Kiro para explorar a tela e gerar/rodar os testes de forma assistida. Configuração de MCP fica em `.kiro/settings/mcp.json` (workspace) ou `~/.kiro/settings/mcp.json` (global). Só configure isso se quiser essa automação — os testes Playwright rodam normalmente sem MCP.

---

## 7. Matriz de cenários (modelo)

Defina os cenários **antes** de implementar os testes, para não gerar teste aleatório. Modelo para o fluxo de card:

| ID       | Cenário                          | Pré-condição            | Resultado esperado                 |
|----------|----------------------------------|-------------------------|------------------------------------|
| CARD-001 | Criar card válido                | Usuário com permissão   | Card criado, status `open`         |
| CARD-002 | Criar sem título                 | Usuário com permissão   | Sistema bloqueia                   |
| CARD-003 | Editar card                      | Card existente          | Dados atualizados                  |
| CARD-004 | Trocar responsável               | Card existente          | Responsável atualizado             |
| CARD-005 | Enviar para homologação          | Card `in_progress`      | Status `em_homologacao`            |
| CARD-006 | Aprovar homologação              | Card `em_homologacao`   | Status `aprovado_producao`         |
| CARD-007 | Reprovar homologação             | Card `em_homologacao`   | Volta ao status correto            |
| CARD-008 | Concluir card                    | Card aprovado           | Status `completed`                 |
| CARD-009 | Homologar duas vezes             | Card já homologado      | Sistema bloqueia ação repetida     |
| CARD-010 | Usuário sem permissão            | Perfil sem acesso       | Ação bloqueada                     |

Sempre inclua **casos negativos** (campo obrigatório, sem permissão, ação fora de ordem), não só o "caminho feliz".

---

## 8. Automação no Kiro (Steering + Hooks + #terminal)

O projeto **ainda não** tem pasta `.kiro/`. Recomendo criar:

### 8.1. Steering — regra permanente de testes

Crie `.kiro/steering/testes.md` com a política de testes (assim o Kiro aplica em toda tarefa, sem você repetir o prompt). Conteúdo sugerido:

```markdown
# Política de testes do projeto

- Toda alteração de backend deve ter teste automatizado correspondente.
- Níveis: Unit (PHPUnit, sem banco), Integration (PHPUnit + helpdesk_on_test), E2E (Playwright).
- Rodar `vendor\bin\phpunit` e os E2E relevantes antes de considerar a tarefa concluída.
- Nunca alterar regra de negócio só para um teste passar.
- Nunca rodar testes de integração contra helpdesk_on_local ou produção — apenas helpdesk_on_test.
- Ao concluir, reportar: o que mudou, quantos testes passaram/falharam, e o status (APROVADO/PENDENTE).
```

### 8.2. Hook — rodar PHPUnit ao salvar arquivo PHP

Você pode pedir ao Kiro para criar um hook que roda a suíte unitária quando um arquivo PHP é salvo. O hook fica em `.kiro/hooks/<id>.json`. Exemplo de ação (comando):

```
vendor\bin\phpunit --testsuite unit
```

Gatilho: `PostFileSave`, matcher `\.php$`. Assim, sempre que você salvar um `.php`, os testes unitários rodam e o Kiro recebe o resultado.

Você também pode criar um hook `PostTaskExec` (após concluir uma tarefa de Spec) que roda a suíte completa.

> Peça no chat: *"Crie um hook PostFileSave que roda vendor\bin\phpunit --testsuite unit para arquivos .php"* — eu crio o JSON pra você com a ferramenta de hooks.

### 8.3. #terminal — analisar falhas

Depois de rodar os testes, use o contexto `#terminal` no chat para o Kiro ler a saída real e diagnosticar:

```
#terminal Analise as falhas do PHPUnit que acabaram de rodar e diga a causa de cada uma, separando erro de teste de erro da aplicação.
```

---

## 9. Prompts prontos para colar no chat do Kiro

**Configurar PHPUnit:**
> Configure o PHPUnit neste projeto PHP puro. Verifique PHP e Composer, instale o PHPUnit como dev, crie `phpunit.xml`, `tests/bootstrap.php` (reaproveitando o autoload de public/index.php) e um teste de fumaça. Não altere regra de negócio. Rode e mostre o resultado.

**Criar testes de integração do card:**
> Crie testes de integração PHPUnit para `PlanningCard` cobrindo criar, editar, trocar status (incluindo em_homologacao → aprovado_producao → completed) e delete. Use o banco `helpdesk_on_test`. Faça limpeza dos dados criados. Rode a suíte de integração.

**Criar matriz de cenários:**
> Analise o fluxo de cards (PlanningCard + views de planning) e monte uma matriz de cenários com ID, pré-condição, passos, dados e resultado esperado, incluindo casos negativos. Só a matriz por enquanto, não implemente os testes.

**Criar E2E:**
> Com base na matriz, implemente testes E2E com Playwright para o fluxo criar → homologar → concluir card. Inspecione as views de planning para achar os seletores reais. Rode e diga em qual passo falhou, se falhar.

**Automatizar:**
> Crie a steering `.kiro/steering/testes.md` com a política de testes e um hook PostFileSave que roda a suíte unitária para arquivos .php.

---

## 10. Critério de conclusão ("definição de pronto")

Uma tarefa só está concluída quando:

- [ ] Código implementado
- [ ] Testes unitários relacionados passando
- [ ] Testes de integração relacionados passando (contra `helpdesk_on_test`)
- [ ] Cenários E2E principais passando
- [ ] Casos negativos cobertos
- [ ] Sem regressão nas suítes existentes
- [ ] Resultado dos testes reportado

**Regra de ouro:** nunca considerar algo "homologado" só porque o código rodou ou uma API respondeu 200. O que vale é o comportamento validado nos três níveis.
```

