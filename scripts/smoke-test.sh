#!/usr/bin/env bash
set -euo pipefail

API_URL="${API_URL:-http://localhost:18080}"
EMAIL="${SMOKE_EMAIL:-demo@monitoring.local}"
PASSWORD="${SMOKE_PASSWORD:-Demo123456}"

json_field() {
  python3 -c 'import json,sys; data=json.load(sys.stdin); key=sys.argv[1]; print(data.get(key,"") if isinstance(data,dict) else "")' "$1"
}

json_nested() {
  python3 -c 'import json,sys; data=json.load(sys.stdin); parts=sys.argv[1].split("."); cur=data
for part in parts:
    cur = cur.get(part, {}) if isinstance(cur, dict) else {}
print(cur if isinstance(cur, str) else "")' "$1"
}

hmac_sign() {
  python3 -c 'import hmac,hashlib,sys; print(hmac.new(sys.argv[3].encode(), (sys.argv[1]+"."+sys.argv[2]).encode(), hashlib.sha256).hexdigest())' "$1" "$2" "$3"
}

echo "==> Health live"
curl -sf "$API_URL/health/live" | grep -q '"status":"ok"'

echo "==> Health ready"
curl -sf "$API_URL/health/ready" | grep -q '"status"'

echo "==> Login"
LOGIN_RESPONSE="$(curl -sf -X POST "$API_URL/api/v1/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")"
TOKEN="$(printf '%s' "$LOGIN_RESPONSE" | json_field token)"
if [[ -z "$TOKEN" ]]; then
  echo "Login failed: $LOGIN_RESPONSE" >&2
  exit 1
fi

echo "==> Auth me"
curl -sf "$API_URL/api/v1/auth/me" -H "Authorization: Bearer $TOKEN" | grep -q '"email"'

echo "==> List sites"
SITES_RESPONSE="$(curl -sf "$API_URL/api/v1/sites" -H "Authorization: Bearer $TOKEN")"
SITE_ID="$(printf '%s' "$SITES_RESPONSE" | python3 -c 'import json,sys; d=json.load(sys.stdin); items=d.get("items",[]); print(items[0]["id"] if items else "")')"

if [[ -z "$SITE_ID" ]]; then
  echo "==> Create demo site for ingest smoke"
  CREATE_RESPONSE="$(curl -sf -X POST "$API_URL/api/v1/sites" \
    -H "Authorization: Bearer $TOKEN" \
    -H 'Content-Type: application/json' \
    -d '{"domain":"smoke.example.com","siteUrl":"https://smoke.example.com"}')"
  SITE_ID="$(printf '%s' "$CREATE_RESPONSE" | json_field siteId)"
  API_SECRET="$(printf '%s' "$CREATE_RESPONSE" | json_field apiSecret)"
else
  echo "==> Rotate key for existing site $SITE_ID"
  ROTATE_RESPONSE="$(curl -sf -X POST "$API_URL/api/v1/sites/$SITE_ID/rotate-key" \
    -H "Authorization: Bearer $TOKEN")"
  API_SECRET="$(printf '%s' "$ROTATE_RESPONSE" | json_field apiSecret)"
fi

if [[ -z "$SITE_ID" || -z "$API_SECRET" ]]; then
  echo "Could not obtain site credentials" >&2
  exit 1
fi

echo "==> Signed heartbeat ingest"
BODY='{"eventId":"smoke-test","collectedAt":"'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'","module":{"version":"0.1.0","mode":"saas","collectorInterval":300},"environment":{"bitrixVersion":"smoke","phpVersion":"8.2"}}'
TIMESTAMP="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
REQUEST_ID="$(uuidgen | tr '[:upper:]' '[:lower:]')"
SIGNATURE="$(hmac_sign "$TIMESTAMP" "$BODY" "$API_SECRET")"

HTTP_CODE="$(curl -s -o /tmp/smoke-heartbeat.json -w '%{http_code}' -X POST "$API_URL/api/v1/heartbeat" \
  -H 'Content-Type: application/json' \
  -H "X-Site-Id: $SITE_ID" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Signature: $SIGNATURE" \
  -H "X-Request-Id: $REQUEST_ID" \
  -H 'X-Module-Version: 0.1.0' \
  -d "$BODY")"

if [[ "$HTTP_CODE" != "202" ]]; then
  echo "Heartbeat failed with HTTP $HTTP_CODE:" >&2
  cat /tmp/smoke-heartbeat.json >&2
  exit 1
fi

grep -q '"status":"accepted"' /tmp/smoke-heartbeat.json

echo "==> Replay protection"
HTTP_CODE="$(curl -s -o /tmp/smoke-replay.json -w '%{http_code}' -X POST "$API_URL/api/v1/heartbeat" \
  -H 'Content-Type: application/json' \
  -H "X-Site-Id: $SITE_ID" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Signature: $SIGNATURE" \
  -H "X-Request-Id: $REQUEST_ID" \
  -H 'X-Module-Version: 0.1.0' \
  -d "$BODY")"

if [[ "$HTTP_CODE" != "401" ]]; then
  echo "Expected replay to be rejected, got HTTP $HTTP_CODE" >&2
  cat /tmp/smoke-replay.json >&2
  exit 1
fi

echo "Smoke test passed."
