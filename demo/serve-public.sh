#!/usr/bin/env bash
# Serve the Opayo Pi demo over a public HTTPS URL via ngrok (macOS / Linux).
#
#   From the repo root, just run:   ./demo/serve-public.sh
#   Stop everything with:           Ctrl-C
#
# Starts PHP's built-in web server on a local port, then opens an ngrok tunnel
# from your reserved public domain down to that port. The public HTTPS URL is
# what you open in a browser to test Apple Pay. Windows users: use the
# PowerShell twin, demo/serve-public.ps1. See demo/README.md.
#
# The only setting is the domain, read from .env (OPAYO_APPLE_PAY_DOMAIN), so
# there is a single source of truth and nothing to retype.

set -euo pipefail

PORT=8000
ROOT="$(cd "$(dirname "$0")/.." && pwd)"   # repo root (the folder above demo/)
cd "$ROOT"

# --- Read the public domain from .env (OPAYO_APPLE_PAY_DOMAIN) ---------------
ENV_FILE="$ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "No .env found at $ENV_FILE. Copy .env.example to .env first." >&2
  exit 1
fi
raw="$(grep -E '^[[:space:]]*OPAYO_APPLE_PAY_DOMAIN[[:space:]]*=' "$ENV_FILE" | head -n1 || true)"
DOMAIN="${raw#*=}"
DOMAIN="$(printf '%s' "$DOMAIN" | tr -d '[:space:]')"   # trim whitespace
DOMAIN="${DOMAIN%\"}"; DOMAIN="${DOMAIN#\"}"            # strip surrounding " "
DOMAIN="${DOMAIN%\'}"; DOMAIN="${DOMAIN#\'}"            # strip surrounding ' '
if [ -z "${DOMAIN:-}" ]; then
  echo "OPAYO_APPLE_PAY_DOMAIN is not set in .env. Add your reserved ngrok domain, e.g. foo.ngrok-free.dev" >&2
  exit 1
fi

# --- Check the tools are present --------------------------------------------
for tool in php ngrok; do
  command -v "$tool" >/dev/null 2>&1 || { echo "'$tool' is not on your PATH (which $tool finds nothing)." >&2; exit 1; }
done

# --- Pick the domain flag this ngrok build understands ----------------------
# Current ngrok uses --url (value includes the scheme); older builds use
# --domain (bare hostname). Detect it so this works across versions.
help="$(ngrok http --help 2>&1 || true)"
if printf '%s' "$help" | grep -q -- '--url'; then
  FLAG=--url;    VALUE="https://$DOMAIN"
elif printf '%s' "$help" | grep -q -- '--domain'; then
  FLAG=--domain; VALUE="$DOMAIN"
else
  FLAG=--url;    VALUE="https://$DOMAIN"
fi

echo
echo "  PHP server : http://127.0.0.1:$PORT   (serving demo/)"
echo "  Public URL : https://$DOMAIN"
echo "  Open that public URL in your browser. Ctrl-C stops both."
echo

# --- Start PHP in the background; stop it when ngrok exits -------------------
php -S "127.0.0.1:$PORT" -t demo >/dev/null 2>&1 &
PHP_PID=$!
trap 'kill "$PHP_PID" 2>/dev/null || true' EXIT INT TERM

ngrok http "$PORT" "$FLAG" "$VALUE"
