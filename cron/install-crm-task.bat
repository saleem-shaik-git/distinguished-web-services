@echo off
set TASK_NAME=Distinguished Web Services - CRM Automation
set SCRIPT=C:\xampp\htdocs\distinguished-web-services\cron\run-crm-automation.bat

if not exist "%SCRIPT%" (
  echo ERROR: %SCRIPT% not found.
  exit /b 1
)

schtasks /Create /TN "%TASK_NAME%" /TR "\"%SCRIPT%\"" /SC MINUTE /MO 15 /F
if errorlevel 1 (
  echo ERROR: Could not create scheduled task. Run this file as Administrator.
  exit /b 1
)

echo CRM automation task installed successfully.
schtasks /Query /TN "%TASK_NAME%" /FO LIST
