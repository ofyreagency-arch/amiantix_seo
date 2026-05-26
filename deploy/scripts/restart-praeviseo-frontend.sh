#!/usr/bin/env bash

set -euo pipefail

APP_NAME="praeviseo-frontend"
PORT="3000"

echo "[1/5] Stopping ${APP_NAME}..."
supervisorctl stop "${APP_NAME}" >/dev/null 2>&1 || true

if ss -ltnp | grep -q ":${PORT} "; then
    echo "[2/5] Cleaning stale listener on port ${PORT}..."

    if command -v fuser >/dev/null 2>&1; then
        fuser -k "${PORT}/tcp" >/dev/null 2>&1 || true
    else
        pkill -f "next start --hostname 127.0.0.1 --port ${PORT}" >/dev/null 2>&1 || true
    fi

    sleep 2
fi

if ss -ltnp | grep -q ":${PORT} "; then
    echo "Port ${PORT} is still in use after cleanup." >&2
    ss -ltnp | grep ":${PORT} " >&2 || true
    exit 1
fi

echo "[3/5] Reloading Supervisor config..."
supervisorctl reread >/dev/null
supervisorctl update >/dev/null

echo "[4/5] Starting ${APP_NAME}..."
supervisorctl start "${APP_NAME}"

echo "[5/5] Verifying frontend..."
supervisorctl status "${APP_NAME}"
curl -I "http://127.0.0.1:${PORT}"
