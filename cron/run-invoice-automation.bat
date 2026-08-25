@echo off
cd /d C:\xampp\htdocs\distinguished-web-services
if not exist storage\logs mkdir storage\logs
C:\xampp\php\php.exe cron\invoice-automation.php >> storage\logs\invoice-automation.log 2>&1
