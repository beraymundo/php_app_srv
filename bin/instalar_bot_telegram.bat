@echo off
rem Cria a tarefa agendada que mantem o bot do Telegram ligado e ja liga o bot.
rem A tarefa roda a cada 5 minutos; se o bot ja estiver rodando, ela sai na hora (nao abre duas copias).
rem Para remover depois:  schtasks /delete /tn "CentralFerramentas\TelegramBot" /f

set PHPW=%~dp0..\..\..\php\php-win.exe
set BOT=%~dp0telegram_bot.php

if not exist "%PHPW%" (
  echo Nao achei o php-win.exe em %PHPW%
  pause
  exit /b 1
)

schtasks /create /tn "CentralFerramentas\TelegramBot" /tr "\"%PHPW%\" \"%BOT%\"" /sc minute /mo 5 /f
if errorlevel 1 (
  echo Nao foi possivel criar a tarefa.
  pause
  exit /b 1
)
schtasks /run /tn "CentralFerramentas\TelegramBot"
echo.
echo Pronto. O bot foi ligado e a tarefa vai mante-lo ligado.
echo Volte para a pagina Telegram da Central e confira se aparece "rodando".
pause
