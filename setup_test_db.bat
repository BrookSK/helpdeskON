@echo off
REM (Re)cria o banco de teste helpdesk_on_test a partir do helpdesk_on local.
REM Grava o progresso em tests/setup_test_db.log (ignorado pelo git).
"C:\php\php.exe" "tests\setup_test_db.php" > tests\setup_test_db.log 2>&1
echo EXITCODE=%errorlevel% >> tests\setup_test_db.log
