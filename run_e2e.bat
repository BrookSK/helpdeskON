@echo off
REM Roda a suíte de testes E2E (Playwright). Grava a saída em e2e_result.txt.
REM O Playwright sobe o servidor PHP embutido automaticamente (ver playwright.config.ts).
call npx playwright test %* > e2e_result.txt 2>&1
echo EXITCODE=%errorlevel% >> e2e_result.txt
