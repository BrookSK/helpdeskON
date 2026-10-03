@echo off
REM Roda apenas a suíte unitária (rápida, sem banco). Usado pelo hook PostFileSave.
set PHP=C:\wamp64\bin\php\php8.5.0\php.exe
if exist "C:\php\php.exe" set PHP=C:\php\php.exe
"%PHP%" vendor\phpunit\phpunit\phpunit --testsuite unit
