@echo off
REM (Re)cria o banco de teste helpdesk_on_test a partir do schema local.
"C:\php\php.exe" "tests\setup_test_db.php" > setup_result.txt 2>&1
echo EXITCODE=%errorlevel% >> setup_result.txt
