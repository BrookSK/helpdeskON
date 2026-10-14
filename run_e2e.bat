@echo off
REM Roda a suíte de testes E2E (Playwright). Grava a saída em e2e_result.txt.
REM O Playwright sobe o servidor PHP embutido automaticamente (ver playwright.config.ts).
set NODE=C:\Program Files\nodejs\node.exe
set PHP=C:\wamp64\bin\php\php8.5.0\php.exe
if exist "C:\php\php.exe" set PHP=C:\php\php.exe
set PHP_BIN=%PHP%
"%NODE%" node_modules\@playwright\test\cli.js test %* > e2e_result.txt 2>&1
echo EXITCODE=%errorlevel% >> e2e_result.txt
