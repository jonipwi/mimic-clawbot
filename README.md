# Trading Bot v3 Distribution Setup Guide

This folder contains deployable artifacts for Trading Bot v3.

---

## 🚀 Step-by-Step Setup Installation

### Step 1 — Get the Code

**Option A: Using Git CLI**

If you have Git installed, open PowerShell and navigate to a folder you know (e.g. Desktop or Documents) **before** running `git clone`. The command will create a new `mimic-clawbot/` folder there.

```powershell
# 1. Go to a folder you know
cd C:\Users\YourName\Desktop

# 2. Clone — this creates a mimic-clawbot folder in that location
git clone https://github.com/jonipwi/mimic-clawbot.git

# 3. Enter the folder
cd mimic-clawbot
```

> `git clone` saves the folder **wherever your terminal is currently at** when you run the command.
> If unsure, navigate to Desktop or Documents first, then clone.

**Option B: Manual Download ZIP (if git clone fails or Git is not installed)**

1. Go to https://github.com/jonipwi/mimic-clawbot
2. Click **Code** → **Download ZIP**
3. Extract the ZIP to a folder you know (e.g. Desktop)
4. Open that extracted folder — it contains everything you need

> Don't have Git? Download Git CLI from https://git-scm.com/downloads or use GitHub Desktop.

---

### Step 2 — Install MySQL + PHP CLI

Follow the full guide here:
👉 [INSTALL.md](./INSTALL.md) — MySQL + PHP CLI Setup (Windows & Linux)

**Quickest option for Windows: XAMPP**

1. Download XAMPP: https://www.apachefriends.org/index.html
2. Install with **Apache**, **MySQL**, and **PHP** enabled
3. Open XAMPP Control Panel:
   - Click **Start** next to **Apache** (Apache running = PHP is running)
   - Click **Start** next to **MySQL**
4. Optional — check **"Start with Windows"** (run on startup) for both Apache and MySQL

> Apache running = PHP is active. No separate PHP install needed with XAMPP.

---

### Step 3 — Set Up the Web UI

After cloning or extracting the repository, you will have a `web/` folder inside the project:

```
mimic-clawbot/
└── web/
    └── index.php   ← this is the Web UI file
```

Copy `web/index.php` from the project folder into your XAMPP web root (`C:\xampp\htdocs\`):

```powershell
# Windows (PowerShell) — run from inside the mimic-clawbot folder
Copy-Item web\index.php C:\xampp\htdocs\web\index.php
```

```bash
# Linux — run from inside the mimic-clawbot folder
cp web/index.php /var/www/html/web/index.php
```

Then test the Web UI in your browser:

```
http://localhost/web/index.php
```

---

### Step 4 — Configure Environment

```powershell
# Windows
Copy-Item .env.example .env
```

```bash
# Linux
cp .env.example .env
```

Edit `.env` and fill in your credentials:

- `INDODAX_API_KEY` / `INDODAX_API_SECRET`
- `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID`
- `TRADING_DB_DSN` (MySQL connection string)

---

### Step 4b — Set Up Telegram Bot Token & Channel

#### 1. Create a Telegram Bot

1. Open Telegram and search for **@BotFather**
2. Start a chat and send `/newbot`
3. Follow the prompts:
   - Enter a **display name** (e.g. `My Trading Bot`)
   - Enter a **username** ending in `bot` (e.g. `mytrading_bot`)
4. BotFather will reply with your **bot token**, which looks like:
   ```
   123456789:AAFxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   ```
5. Copy this token — this is your `TELEGRAM_BOT_TOKEN`

#### 2. Get Your Chat ID

**Option A — Personal chat (simplest)**

1. Search for **@userinfobot** in Telegram
2. Start a chat and send `/start`
3. It will reply with your **user ID** (a number like `987654321`)
4. Use that number as your `TELEGRAM_CHAT_ID`

**Option B — Group or channel**

1. Add your bot to the group/channel as an **admin**
2. Send a message in that group/channel
3. Open a browser and visit:
   ```
   https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
   ```
4. Find `"chat":{"id":` in the response — the value is your `TELEGRAM_CHAT_ID`
   - Group/channel IDs are negative numbers (e.g. `-100123456789`)

#### 3. Set the values in `.env`

```env
TELEGRAM_BOT_TOKEN=123456789:AAFxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TELEGRAM_CHAT_ID=987654321
```

> The bot must be **started** (user sent `/start`) or **added as admin** to the target chat before it can send messages.

---

### Step 5 — Run the Bot

**Windows:**

```powershell
.\trading-setup.ps1 -StartBot
```

**Linux / Raspberry Pi:**

```bash
./trading-setup.sh --start-bot
```

---

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

---

## 8) Troubleshooting

### ❌ Fatal error: Access denied for user 'root'@'localhost' (using password: YES)

**Error example:**
```
Fatal error: Uncaught mysqli_sql_exception: Access denied for user 'root'@'localhost' (using password: YES)
in C:\xampp\htdocs\index.php on line 404
```

**Cause:** MySQL root user password is not set or doesn't match what's configured in the app.

**Fix — Set MySQL root password to `root` via XAMPP:**

1. Open **XAMPP Control Panel**
2. Make sure **MySQL is running** (green status)
3. Click **Shell** (or open a terminal and go to `C:\xampp\mysql\bin\`)
4. Run the following commands:

```bash
# Enter MySQL as root (no password yet)
mysql -u root

# Inside MySQL shell — set the password to "root"
ALTER USER 'root'@'localhost' IDENTIFIED BY 'root';
FLUSH PRIVILEGES;
EXIT;
```

> If `mysql -u root` is denied, try:
> ```bash
> mysql -u root -p
> ```
> Then just press **Enter** (blank password) when prompted.

5. After setting the password, verify your `.env` or DSN matches:

```env
TRADING_DB_DSN=root:root@tcp(localhost:3306)/trading_db_v3?parseTime=true&charset=utf8mb4
```

6. Also make sure the database exists:

```bash
mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS trading_db_v3;"
```

7. Restart Apache and MySQL from XAMPP Control Panel, then reload the dashboard.

---

### ❌ Dashboard not loading / blank page

- Confirm Apache is **Started** (green) in XAMPP Control Panel
- Confirm `index.php` is placed at `C:\xampp\htdocs\index.php` (or `web\index.php` → `C:\xampp\htdocs\web\index.php`)
- Check PHP error log: `C:\xampp\php\logs\php_error_log`
- Try accessing directly: `http://localhost/index.php` or `http://localhost/web/index.php`
