@echo off
rem Opens a JBS Praxis server backup downloaded from Drive (docs/BACKUPS.md, section 6)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Restore-Backup.ps1" %*
echo.
pause
