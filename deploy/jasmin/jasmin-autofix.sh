#!/bin/bash
# Jasmin auto-healer — SMPP bind flaps + RabbitMQ consumer loss.

CONNECTOR_NAME="tigo_route"
SUBMIT_QUEUE="submit.sm.${CONNECTOR_NAME}"
JASMIN_LOG_PATH="/var/log/jasmin"
CONNECTOR_LOG="$JASMIN_LOG_PATH/default-$CONNECTOR_NAME.log"
DLR_LOG="$JASMIN_LOG_PATH/dlrlookupd-messages.log"
SMPP_PORT="10501"

# Wait this long after a disconnect before considering restart (seconds)
FLAP_GRACE_SECONDS=180
# DLR inactivity alone is not enough; require connector unhealthy too
STALE_SECONDS=600
LOG_LINES=80

needs_restart=false
current_time=$(date +%s)
tb=no

echo "[$(date)] Running Health Check for $CONNECTOR_NAME..."

tcp_bound() {
    ss -tn state established "( dport = :$SMPP_PORT )" 2>/dev/null | grep -qE ":$SMPP_PORT\b" \
        || netstat -tn 2>/dev/null | grep -E ":$SMPP_PORT[[:space:]].*ESTABLISHED" | grep -q .
}

seconds_since_pattern() {
    local file="$1" pattern="$2"
    local line ts epoch
    line=$(grep -iE "$pattern" "$file" 2>/dev/null | tail -n 1) || true
    if [ -z "$line" ]; then
        echo 999999
        return
    fi
    ts=$(echo "$line" | awk "{print \$1\" \"\$2}")
    epoch=$(date -d "$ts" +%s 2>/dev/null || echo 0)
    if [ "$epoch" -eq 0 ]; then
        echo 999999
        return
    fi
    echo $((current_time - epoch))
}

start_connector() {
    if [ -x /usr/local/bin/jasmin-start-connector.sh ]; then
        JASMIN_CONNECTOR_NAME="$CONNECTOR_NAME" /usr/local/bin/jasmin-start-connector.sh
        return $?
    fi
    (
        sleep 1; echo "jcliadmin"
        sleep 1; echo "jclipwd"
        sleep 1; echo "smppccm -1 $CONNECTOR_NAME"
        sleep 2; echo "quit"
    ) | telnet localhost 8990 >/tmp/jasmin-healer-jcli.log 2>&1 || true
}

amqp_consumers() {
    # Returns consumer count for submit.sm.tigo_route, or -1 on error.
    local line count
    line=$(rabbitmqctl list_queues name consumers 2>/dev/null | awk -v q="$SUBMIT_QUEUE" '$1==q {print $2; exit}')
    if [ -z "$line" ]; then
        echo -1
        return
    fi
    echo "$line"
}

secs_since_fail=999999
secs_since_ok=999999

if [ -f "$CONNECTOR_LOG" ]; then
    logs=$(tail -n "$LOG_LINES" "$CONNECTOR_LOG")
    last_ok=$(echo "$logs" | grep -n -i -E "Bind succeeded|BOUND_TRX|bound_trx" | tail -n 1 | cut -d: -f1)
    last_fail=$(echo "$logs" | grep -n -i -E "Connection lost|disconnected from" | tail -n 1 | cut -d: -f1)
    last_ok=${last_ok:-0}
    last_fail=${last_fail:-0}
    echo "DEBUG: Last OK Line: $last_ok | Last Fail Line: $last_fail"

    secs_since_fail=$(seconds_since_pattern "$CONNECTOR_LOG" "Connection lost|disconnected from")
    secs_since_ok=$(seconds_since_pattern "$CONNECTOR_LOG" "Bind succeeded|BOUND_TRX")
    if tcp_bound; then tb=yes; else tb=no; fi
    echo "DEBUG: Seconds since fail=$secs_since_fail ok=$secs_since_ok tcp_bound=$tb"

    if [ "$last_fail" -gt "$last_ok" ]; then
        if [ "$secs_since_fail" -lt "$FLAP_GRACE_SECONDS" ]; then
            echo "INFO: Disconnect seen, but within ${FLAP_GRACE_SECONDS}s grace. Waiting for auto-reconnect."
        elif [ "$tb" = "yes" ]; then
            echo "INFO: Log looks down, but TCP to Tigo:$SMPP_PORT is ESTABLISHED. Skipping SMPP restart."
        else
            echo "CRITICAL: Connector down for ${secs_since_fail}s with no successful rebind."
            needs_restart=true
        fi
    else
        echo "OK: Latest connector event is a successful bind."
    fi
else
    echo "WARNING: Connector log not found. Skipping connector check."
fi

# AMQP consumer loss can leave SMPP looking fine while submits pile up.
consumers=$(amqp_consumers)
echo "DEBUG: RabbitMQ $SUBMIT_QUEUE consumers=$consumers"
if [ "$consumers" = "0" ]; then
    echo "CRITICAL: $SUBMIT_QUEUE has 0 consumers — submits will stall."
    needs_restart=true
elif [ "$consumers" = "-1" ]; then
    echo "WARNING: Could not read RabbitMQ queue consumers."
fi

if [ -f "$DLR_LOG" ]; then
    file_mtime=$(stat -c %Y "$DLR_LOG")
    diff=$((current_time - file_mtime))
    if [ "$diff" -gt "$STALE_SECONDS" ]; then
        if [ "$needs_restart" = true ] || { [ "$secs_since_ok" -gt "$FLAP_GRACE_SECONDS" ] && [ "$tb" != "yes" ]; }; then
            echo "CRITICAL: No DLR log activity for $diff seconds and connector unhealthy."
            needs_restart=true
        else
            echo "INFO: DLR quiet for $diff seconds, but connector looks OK — not restarting (idle is normal)."
        fi
    else
        echo "OK: Last DLR activity was $diff seconds ago."
    fi
fi

if [ "$needs_restart" = true ]; then
    echo "ACTION: Attempting soft recovery first (start connector)..."
    start_connector || true

    sleep 10
    secs_ok_now=$(seconds_since_pattern "$CONNECTOR_LOG" "Bind succeeded|BOUND_TRX")
    consumers_now=$(amqp_consumers)
    echo "DEBUG: after soft start consumers=$consumers_now secs_ok_now=$secs_ok_now"
    if { tcp_bound || [ "$secs_ok_now" -lt 30 ]; } && [ "$consumers_now" != "0" ]; then
        echo "RECOVERY: Soft start worked. No full Jasmin restart."
        exit 0
    fi

    echo "ACTION: Soft start failed — restarting jasmind (last resort)..."
    systemctl restart jasmind
    echo "Waiting 20 seconds for Jasmin + ExecStartPost..."
    sleep 20

    start_connector || true

    echo "RECOVERY COMPLETE (full restart)."
else
    echo "System Healthy. No action taken."
fi
