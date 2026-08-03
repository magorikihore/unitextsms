#!/bin/bash
set -euo pipefail

install -o root -g root -m 755 /tmp/jasmin-start-connector.sh /usr/local/bin/jasmin-start-connector.sh
install -o root -g root -m 644 /tmp/jasmin-connector-autostart.service /etc/systemd/system/jasmin-connector-autostart.service
mkdir -p /etc/systemd/system/jasmind.service.d
install -o root -g root -m 644 /tmp/jasmind-autostart-connector.conf /etc/systemd/system/jasmind.service.d/autostart-connector.conf
if [ -f /tmp/jasmin-autofix.sh ]; then
  install -o root -g root -m 755 /tmp/jasmin-autofix.sh /usr/local/bin/jasmin-autofix.sh
fi

systemctl daemon-reload
systemctl enable jasmin-connector-autostart.service
systemctl reset-failed jasmind.service jasmin-connector-autostart.service || true
systemctl restart jasmind.service
# Give jasmind + async oneshot time
sleep 20
echo "jasmind=$(systemctl is-active jasmind)"
echo "connector_unit=$(systemctl is-active jasmin-connector-autostart || true)"
systemctl status jasmin-connector-autostart --no-pager | head -20 || true
journalctl -u jasmin-connector-autostart --since "3 min ago" --no-pager | tail -30 || true

python3 - <<'PY'
import telnetlib, time
tn = telnetlib.Telnet("127.0.0.1", 8990, timeout=10)
tn.read_until(b"Username:", timeout=5); tn.write(b"jcliadmin\n")
tn.read_until(b"Password:", timeout=5); tn.write(b"jclipwd\n")
tn.read_until(b"jcli :", timeout=5)
tn.write(b"smppccm -l\n"); time.sleep(1.2)
print(tn.read_very_eager().decode(errors="replace"))
tn.write(b"quit\n"); tn.close()
PY

rabbitmqctl list_queues name messages consumers 2>/dev/null | grep -E 'submit|name' || true
echo DONE
