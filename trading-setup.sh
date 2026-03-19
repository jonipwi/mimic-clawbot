#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

BRIDGE_TOKEN="bridge-shared-token-8099"
START_BOT=0
STOP_ALL=0
WEB_HOST="0.0.0.0"
WEB_PORT="8088"
BRIDGE_PORT="8099"

usage() {
	cat <<'USAGE'
Usage: ./trading-setup.sh [options]

Options:
	--bridge-token <token>  Bridge bearer token (default: bridge-shared-token-8099)
	--start-bot             Start mimic-clawbot after build
	--stop-all              Stop web ui, bridge, and bot processes then exit
	--web-host <host>       PHP web UI host (default: 0.0.0.0)
	--web-port <port>       PHP web UI port (default: 8088)
	--bridge-port <port>    Mimic bridge port (default: 8099)
	-h, --help              Show this help
USAGE
}

while [[ $# -gt 0 ]]; do
	case "$1" in
		--bridge-token)
			BRIDGE_TOKEN="${2:-}"
			shift 2
			;;
		--start-bot)
			START_BOT=1
			shift
			;;
		--stop-all)
			STOP_ALL=1
			shift
			;;
		--web-host)
			WEB_HOST="${2:-}"
			shift 2
			;;
		--web-port)
			WEB_PORT="${2:-}"
			shift 2
			;;
		--bridge-port)
			BRIDGE_PORT="${2:-}"
			shift 2
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "Unknown option: $1" >&2
			usage
			exit 1
			;;
	esac
done

stop_port_listener() {
	local port="$1"

	if command -v lsof >/dev/null 2>&1; then
		local pids
		pids="$(lsof -t -iTCP:"$port" -sTCP:LISTEN 2>/dev/null || true)"
		if [[ -n "$pids" ]]; then
			# shellcheck disable=SC2086
			kill -9 $pids 2>/dev/null || true
			echo "Stopped listeners on port $port."
			return
		fi
	fi

	if command -v fuser >/dev/null 2>&1; then
		if fuser -k "${port}/tcp" >/dev/null 2>&1; then
			echo "Stopped listeners on port $port."
		fi
	fi
}

stop_trading_stack() {
	echo "Stopping running trading-related processes (if any)..."
	pkill -f 'trading-bot' 2>/dev/null || true
	pkill -f 'trading-bot-v3' 2>/dev/null || true
	pkill -f 'mimic-clawbot' 2>/dev/null || true
	pkill -f 'mimic-bridge' 2>/dev/null || true
	pkill -f "php -S ${WEB_HOST}:${WEB_PORT}" 2>/dev/null || true

	stop_port_listener "$WEB_PORT"
	stop_port_listener "$BRIDGE_PORT"
}

if [[ "$STOP_ALL" -eq 1 ]]; then
	stop_trading_stack
	echo "All trading stack services were stopped."
	exit 0
fi

echo "Checking Go installation..."
go version

echo "Tidying go modules..."
go mod tidy

stop_trading_stack

mkdir -p "$SCRIPT_DIR/linux" "$SCRIPT_DIR/logs"

HAS_BRIDGE_SOURCE=0
if [[ -d "$SCRIPT_DIR/cmd/mimic-bridge" ]]; then
	HAS_BRIDGE_SOURCE=1
fi

echo "Building ./mimic-clawbot.exe (GOOS=windows GOARCH=amd64)..."
GOOS=windows GOARCH=amd64 go build -o ./mimic-clawbot.exe .

if [[ "$HAS_BRIDGE_SOURCE" -eq 1 ]]; then
	echo "Building ./mimic-bridge.exe (GOOS=windows GOARCH=amd64)..."
	GOOS=windows GOARCH=amd64 go build -o ./mimic-bridge.exe ./cmd/mimic-bridge
else
	echo "Skipping bridge build: ./cmd/mimic-bridge not found in this project."
fi

echo "Building ./mimic-clawbot (GOOS=linux GOARCH=arm64)..."
GOOS=linux GOARCH=arm64 go build -o ./mimic-clawbot .

if [[ "$HAS_BRIDGE_SOURCE" -eq 1 ]]; then
	echo "Building ./linux/mimic-bridge (GOOS=linux GOARCH=arm64)..."
	GOOS=linux GOARCH=arm64 go build -o ./linux/mimic-bridge ./cmd/mimic-bridge
fi

echo
echo "Build completed successfully."

echo "Starting Trading Web UI..."
if command -v php >/dev/null 2>&1; then
	nohup php -S "${WEB_HOST}:${WEB_PORT}" -t web > "$SCRIPT_DIR/logs/web-ui.log" 2>&1 &
	echo "Web UI started: http://${WEB_HOST}:${WEB_PORT}/"
	echo "Local check: http://127.0.0.1:${WEB_PORT}/"
	if [[ "$WEB_HOST" == "0.0.0.0" ]]; then
		LAN_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
		if [[ -n "$LAN_IP" ]]; then
			echo "LAN URL: http://${LAN_IP}:${WEB_PORT}/"
		fi
	fi
else
	echo "PHP not found in PATH. Skipping Web UI startup."
fi

BRIDGE_CMD="${MIMIC_BRIDGE_COMMAND:-}"
if [[ -z "$BRIDGE_CMD" ]]; then
	if [[ -x "$SCRIPT_DIR/raspi/trading-bot-v3" ]]; then
		BRIDGE_CMD="$SCRIPT_DIR/raspi/trading-bot-v3"
	elif [[ -x "$SCRIPT_DIR/mimic-clawbot" ]]; then
		BRIDGE_CMD="$SCRIPT_DIR/mimic-clawbot"
	fi
fi

echo "Starting Mimic Bridge in background..."
BRIDGE_BIN=""
if [[ -x "$SCRIPT_DIR/linux/mimic-bridge" ]]; then
	BRIDGE_BIN="$SCRIPT_DIR/linux/mimic-bridge"
elif [[ -x "$SCRIPT_DIR/mimic-bridge" ]]; then
	BRIDGE_BIN="$SCRIPT_DIR/mimic-bridge"
fi

if [[ -n "$BRIDGE_BIN" ]]; then
	MIMIC_WEB_ENDPOINT_TOKEN="$BRIDGE_TOKEN" \
	MIMIC_BRIDGE_ADDR="0.0.0.0" \
	MIMIC_BRIDGE_PORT="$BRIDGE_PORT" \
	MIMIC_BRIDGE_COMMAND="$BRIDGE_CMD" \
	nohup "$BRIDGE_BIN" > "$SCRIPT_DIR/logs/mimic-bridge.log" 2>&1 &

	echo "Mimic Bridge started."
	echo "Health check: http://127.0.0.1:${BRIDGE_PORT}/healthz"
else
	echo "Skipping bridge start: mimic-bridge binary not found."
	echo "Bridge source/binary missing in this project."
	echo "To build bridge, run this script from the mimic-clawbot repository that contains ./cmd/mimic-bridge."
	echo "If PHP exec/proc_open is disabled, HTTP bridge is required."
fi

if [[ "$START_BOT" -eq 1 ]]; then
	echo "Starting Mimic Bot in background..."
	nohup "$SCRIPT_DIR/mimic-clawbot" > "$SCRIPT_DIR/logs/mimic-clawbot.log" 2>&1 &
	echo "Mimic Bot started."
else
	echo "Run Mimic Bot manually: ./mimic-clawbot"
	echo "Tip: use --start-bot to auto-start bot after build."
fi