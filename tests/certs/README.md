# tests/certs — bundle de CA para o ambiente local

Este diretório contém `cacert.pem`, um bundle de certificados usado **apenas no
ambiente local de desenvolvimento/testes desta máquina**. Não é código da
aplicação.

## Por que existe

O antivírus **AVG** desta máquina intercepta conexões TLS (SSL scanning):
ele termina a conexão HTTPS e reassina os certificados com sua própria CA raiz
("AVG Web/Mail Shield Root"). Essa raiz existe no Certificate Store do Windows,
mas **não** nos bundles de CA públicos usados por padrão pelo PHP/curl e pelo
Node/npm. Resultado: downloads falham com `curl error 60` (PHP/Composer) e
`UNABLE_TO_VERIFY_LEAF_SIGNATURE` (Node).

## O que é o cacert.pem

Bundle público padrão (do ca-bundle do Composer que acompanha o phpMyAdmin do
WAMP) **mais** a CA raiz do AVG exportada do store do Windows
(`Cert:\LocalMachine\Root`). Com ele, PHP/Composer e Node/npm validam as
conexões interceptadas pelo AVG.

## Como usar (apenas nesta máquina)

- Composer / PHP:
  `php -d curl.cainfo="...\tests\certs\cacert.pem" -d openssl.cafile="...\tests\certs\cacert.pem" composer.phar install`
  (ou `set COMPOSER_CAINFO=...\tests\certs\cacert.pem`)
- Node / npm / Playwright:
  `set NODE_EXTRA_CA_CERTS=...\tests\certs\cacert.pem` antes de `npm install`
  ou `npx playwright install`.

Em runtime os testes (PHPUnit e Playwright) não baixam nada da rede, então o
bundle só é necessário na etapa de instalação de dependências/navegadores.

Observação: este arquivo é específico desta máquina (depende da CA do AVG local).
Em outra máquina sem o AVG, o bundle público padrão já basta.
