@echo off
REM Roda a suíte PHPUnit completa. Grava saída em phpunit_result.txt.
set PHP=C:\wamp64\bin\php\php8.5.0\php.exe
if exist "C:\php\php.exe" set PHP=C:\php\php.exe
"%PHP%" vendor\phpunit\phpunit\phpunit --testdox > phpunit_result.txt 2>&1
echo EXITCODE=%errorlevel% >> phpunit_result.txt
