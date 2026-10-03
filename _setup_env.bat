@echo off
REM ============================================================
REM _setup_env.bat — Prepara o ambiente de testes helpdeskON
REM Executa: composer install, npm install, chromium install
REM ============================================================
setlocal

set PHP=C:\wamp64\bin\php\php8.5.0\php.exe
set PROJECT=D:\Github Desktop ;;;\helpdeskON

echo === [1/5] Verificando PHP ===
"%PHP%" --version
if errorlevel 1 (
    echo ERRO: PHP nao encontrado em %PHP%
    exit /b 1
)

echo.
echo === [2/5] Verificando Node.js / npm ===
where node 2>nul
if errorlevel 1 (
    echo AVISO: node.exe nao encontrado no PATH.
    echo Baixando Node.js LTS 22...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Invoke-WebRequest -Uri 'https://nodejs.org/dist/v22.11.0/node-v22.11.0-x64.msi' -OutFile 'D:\node-installer.msi'"
    echo Instalando Node.js silenciosamente (requer admin)...
    msiexec /i "D:\node-installer.msi" /qn ADDLOCAL=ALL
    echo.
    echo Node.js instalado. Por favor REABRA este terminal e rode:
    echo   _setup_env.bat
    echo Para continuar com npm install e playwright install.
    exit /b 0
)

node --version
npm --version

echo.
echo === [3/5] Composer install (PHPUnit + deps) ===
cd /d "%PROJECT%"
"%PHP%" composer.phar install --no-interaction 2>&1
if errorlevel 1 (
    echo ERRO: composer install falhou
    exit /b 1
)

echo.
echo === [4/5] npm install (Playwright) ===
npm install
if errorlevel 1 (
    echo ERRO: npm install falhou
    exit /b 1
)

echo.
echo === [5/5] Instalar Chromium do Playwright ===
npx playwright install chromium
if errorlevel 1 (
    echo ERRO: playwright install chromium falhou
    exit /b 1
)

echo.
echo === CONCLUIDO! ===
echo PHPUnit: "%PROJECT%\vendor\phpunit\phpunit\phpunit"
echo Rodar testes PHP:   run_tests.bat
echo Rodar testes E2E:   run_e2e.bat
echo.
