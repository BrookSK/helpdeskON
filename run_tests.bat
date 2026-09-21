@echo off
"C:\php\php.exe" "vendor\phpunit\phpunit\phpunit" --testdox > phpunit_result.txt 2>&1
echo EXITCODE=%errorlevel% >> phpunit_result.txt
