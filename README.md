# Trading Bot v3 Distribution Setup Guide

This folder contains deployable artifacts for Trading Bot v3.

## Get the Repository

```bash
git clone https://github.com/jonipwi/mimic-clawbot.git
cd mimic-clawbot
```

## Folder Layout

- `windows/trading-bot-v3.exe` -> Windows binary
- `raspi/trading-bot-v3` -> Linux/Raspberry Pi binary
- `web/index.php` -> optional simple web UI/view
- `.env.example` -> environment template for bot runtime

## Quick Setup (Build + Run)

These scripts build all binaries, start the Web UI, and launch the Mimic Bridge in one step.

### Windows (PowerShell)

```powershell
# Basic setup (build + start web UI + bridge)
.\trading-setup.ps1

# Auto-start the bot too
.\trading-setup.ps1 -StartBot

# Custom bridge token
.\trading-setup.ps1 -BridgeToken "your-secret-token"

# Stop everything
.\trading-setup.ps1 -StopAll
```

### Linux / Raspberry Pi (Bash)

```bash
chmod +x trading-setup.sh

# Basic setup (build + start web UI + bridge)
./trading-setup.sh

# Auto-start the bot too
./trading-setup.sh --start-bot

# Custom bridge token and ports
./trading-setup.sh --bridge-token "your-secret-token" --web-port 8088 --bridge-port 8099

# Stop everything
./trading-setup.sh --stop-all
```

After setup:
- Web UI: `http://127.0.0.1:8088/`
- Bridge health: `http://127.0.0.1:8099/healthz`
- Run bot manually (if not auto-started): `.\mimic-clawbot.exe` (Windows) or `./mimic-clawbot` (Linux)

---

## 1) Prepare Environment File

Create a `.env` file in the same working directory where you run the bot binary.

### Windows (PowerShell)

```powershell
Copy-Item .env.example .env
```

### Linux / Raspberry Pi

```bash
cp .env.example .env
```

Then edit `.env` and fill secret values:

- `INDODAX_API_KEY`
- `INDODAX_API_SECRET`
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_CHAT_ID`
- `TRADING_TELEGRAM_BOT_SECRET`

## 2) Database Setup

The bot expects MySQL and a DSN in `TRADING_DB_DSN`, for example:

```env
TRADING_DB_DSN=root:root@tcp(localhost:3306)/trading_db_v3?parseTime=true&charset=utf8mb4
```

Ensure:

- MySQL is running
- Database `trading_db_v3` exists
- User credentials in DSN are valid

## 3) Run on Windows

```powershell
cd windows
./trading-bot-v3.exe
```

## 4) Run on Raspberry Pi / Linux

```bash
cd raspi
chmod +x trading-bot-v3
./trading-bot-v3
```

## 5) Optional Web File

`web/index.php` can be served with your PHP/Nginx/Apache stack if needed.

## 6) Safety Notes

- Keep `LIVE_TRADING_ENABLED=false` for paper mode.
- Enable live mode only after API keys and risk limits are verified.
- Never commit real `.env` values to git.

## 7) Quick Checklist

- `.env` created from `.env.example`
- MySQL reachable by `TRADING_DB_DSN`
- Pair list and allocation values adjusted
- Telegram settings verified (optional)
- Bot starts without config errors
