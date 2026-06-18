#!/usr/bin/env bash
#
# Smoke-test the public query API (POST /api/queries).
#
# Submits a sample query (with a generated test image attachment) to any
# JobCardOnline instance, so you can exercise the endpoint locally or against
# a deployed host without touching the Revamp landing page.
#
# Usage:
#   scripts/test-query-api.sh                       # uses defaults below
#   BASE_URL=http://127.0.0.1:8001 scripts/test-query-api.sh
#   BASE_URL=https://jco.example.com API_KEY=xxxxx scripts/test-query-api.sh
#   NO_ATTACHMENT=1 scripts/test-query-api.sh       # skip the file upload
#
# Env vars:
#   BASE_URL       Target host (default http://127.0.0.1:8001 — JCO's local port)
#   API_KEY        X-Api-Key value (default: QUERY_API_KEY from this repo's .env)
#   COMPANY_ID     Optional company_id to attach the query to
#   NO_ATTACHMENT  Set to any value to skip the attachment upload
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

BASE_URL="${BASE_URL:-http://127.0.0.1:8001}"
BASE_URL="${BASE_URL%/}"

# Default the key to QUERY_API_KEY from the repo's .env if not supplied.
if [[ -z "${API_KEY:-}" && -f "$REPO_ROOT/.env" ]]; then
  API_KEY="$(grep -E '^QUERY_API_KEY=' "$REPO_ROOT/.env" | head -n1 | cut -d= -f2- | tr -d '\r')"
fi
API_KEY="${API_KEY:-}"

if [[ -z "$API_KEY" ]]; then
  echo "ERROR: No API key. Set API_KEY=... or QUERY_API_KEY in .env." >&2
  exit 1
fi

ENDPOINT="$BASE_URL/api/queries"
echo "POST $ENDPOINT"
echo "X-Api-Key: ${API_KEY:0:6}…(${#API_KEY} chars)"

CURL_ARGS=(
  -sS -w '\n--- HTTP %{http_code} (%{time_total}s) ---\n'
  -X POST "$ENDPOINT"
  -H "X-Api-Key: $API_KEY"
  -H 'Accept: application/json'
  -F 'name=Test'
  -F 'surname=Submission'
  -F 'email=test@example.com'
  -F 'cell=+27123456789'
  -F "description=Automated smoke test from scripts/test-query-api.sh at $(date)"
)

[[ -n "${COMPANY_ID:-}" ]] && CURL_ARGS+=(-F "company_id=$COMPANY_ID")

TMP_IMG=""
if [[ -z "${NO_ATTACHMENT:-}" ]]; then
  # Decode a valid 1x1 PNG so the mimes:png rule passes without a real asset.
  TMP_IMG="$(mktemp).png"
  base64 -d > "$TMP_IMG" <<'PNG'
iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC
PNG
  # mingw/Git-Bash curl needs a native Windows path for the @file argument.
  CURL_PATH="$TMP_IMG"
  command -v cygpath >/dev/null 2>&1 && CURL_PATH="$(cygpath -w "$TMP_IMG")"
  CURL_ARGS+=(-F "attachments[0]=@$CURL_PATH;type=image/png;filename=test.png")
  echo "Attachment: test.png (generated 1x1 PNG)"
fi

echo
curl "${CURL_ARGS[@]}"

[[ -n "$TMP_IMG" && -f "$TMP_IMG" ]] && rm -f "$TMP_IMG"
