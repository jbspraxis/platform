@echo off
rem Makes the JBS Praxis backup key in Documents\JBS Praxis backup key (docs/BACKUPS.md, section 3)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0New-BackupKey.ps1"
echo.
pause
