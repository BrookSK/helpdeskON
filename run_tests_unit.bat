@echo off
REM Roda apenas a suíte unitária (rápida, sem banco). Usado pelo hook PostFileSave.
"C:\php\php.exe" "vendor\phpunit\phpunit\phpunit" --testsuite unit
