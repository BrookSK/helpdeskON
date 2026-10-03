@echo off
REM (Re)cria o banco de teste helpdesk_on_test a partir do schema local.
set PHP=C:\wamp64\bin\php\php8.5.0\php.exe
if exist "C:\php\php.exe" set PHP=C:\php\php.exe
"%PHP%" tests\setup_test_db.php > setup_result.txt 2>&1
echo EXITCODE=%errorlevel% >> setup_result.txt
