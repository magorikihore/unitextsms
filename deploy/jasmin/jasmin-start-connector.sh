#!/bin/bash
# Wait for jcli, then start the SMPP connector after jasmind boots.
# Used as ExecStartPost so tigo_route does not stay stopped after restart.

set -euo pipefail

CONNECTOR_NAME="${JASMIN_CONNECTOR_NAME:-tigo_route}"
JCLI_HOST="${JASMIN_JCLI_HOST:-127.0.0.1}"
JCLI_PORT="${JASMIN_JCLI_PORT:-8990}"
JCLI_USER="${JASMIN_JCLI_USER:-jcliadmin}"
JCLI_PASS="${JASMIN_JCLI_PASS:-jclipwd}"
MAX_WAIT="${JASMIN_START_WAIT_SECONDS:-60}"
LOG_TAG="jasmin-start-connector"

log() { echo "[$(date -Is)] $LOG_TAG: $*"; }

wait_for_jcli() {
  local i=0
  while [ "$i" -lt "$MAX_WAIT" ]; do
    if (echo >/dev/tcp/"$JCLI_HOST"/"$JCLI_PORT") >/dev/null 2>&1; then
      return 0
    fi
    i=$((i + 1))
    sleep 1
  done
  return 1
}

jcli_cmd() {
  local cmd="$1"
  python3 - "$JCLI_HOST" "$JCLI_PORT" "$JCLI_USER" "$JCLI_PASS" "$cmd" <<'PY'
import sys, telnetlib, time
host, port, user, password, cmd = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4], sys.argv[5]
tn = telnetlib.Telnet(host, port, timeout=15)
tn.read_until(b"Username:", timeout=10)
tn.write(user.encode() + b"\n")
tn.read_until(b"Password:", timeout=10)
tn.write(password.encode() + b"\n")
tn.read_until(b"jcli :", timeout=10)
tn.write(cmd.encode() + b"\n")
time.sleep(1.5)
out = tn.read_very_eager().decode(errors="replace")
print(out)
tn.write(b"quit\n")
tn.close()
PY
}

log "Waiting up to ${MAX_WAIT}s for jcli on ${JCLI_HOST}:${JCLI_PORT}..."
if ! wait_for_jcli; then
  log "ERROR: jcli not ready; connector not started"
  exit 1
fi

# Brief settle so RouterPB / SMPPClientManager are ready
sleep 3

log "Starting connector ${CONNECTOR_NAME}..."
out=$(jcli_cmd "smppccm -1 ${CONNECTOR_NAME}" || true)
echo "$out" | sed 's/^/  /'

# Already-running connectors often return "Failed starting..." — verify via list.
status=$(jcli_cmd "smppccm -l" || true)
echo "$status" | sed 's/^/  /'
if echo "$status" | grep -qiE "${CONNECTOR_NAME}[[:space:]]+started"; then
  log "Persisting configuration..."
  jcli_cmd "persist" >/dev/null || true
  log "OK: ${CONNECTOR_NAME} is started"
  exit 0
fi

# Retry once if still stopped
sleep 5
out=$(jcli_cmd "smppccm -1 ${CONNECTOR_NAME}" || true)
echo "$out" | sed 's/^/  /'
status=$(jcli_cmd "smppccm -l" || true)
echo "$status" | sed 's/^/  /'
if echo "$status" | grep -qiE "${CONNECTOR_NAME}[[:space:]]+started"; then
  log "Persisting configuration..."
  jcli_cmd "persist" >/dev/null || true
  log "OK: ${CONNECTOR_NAME} is started"
  exit 0
fi

log "ERROR: ${CONNECTOR_NAME} did not report started"
exit 1
