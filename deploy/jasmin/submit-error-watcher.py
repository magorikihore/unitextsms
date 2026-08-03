#!/usr/bin/env python3
"""
Watch Jasmin messages.log for SMSC submit failures (e.g. ESME_RINVSRCADR)
and POST them to UniNText so the panel can mark messages failed with a clear reason.

Jasmin HTTP /send returns Success when queued; SMSC reject often has no HTTP DLR.
"""

from __future__ import annotations

import os
import re
import time
import urllib.parse
import urllib.request

LOG_PATH = os.environ.get("JASMIN_MESSAGES_LOG", "/var/log/jasmin/messages.log")
ENDPOINT = os.environ.get(
    "UNITEXT_SUBMIT_ERROR_URL",
    "https://sms.triptz.net/dlr/jasmin-submit-error",
)
STATE_PATH = os.environ.get(
    "JASMIN_SUBMIT_ERROR_STATE",
    "/var/lib/jasmin-submit-error-watcher.offset",
)

PATTERN = re.compile(
    r"\[queue-msgid:([0-9a-fA-F-]{36})\].*?\[status:ERROR/CommandStatus\.([A-Z0-9_]+)\]"
)


def load_offset() -> int:
    try:
        with open(STATE_PATH, "r", encoding="utf-8") as fh:
            return int(fh.read().strip() or "0")
    except Exception:
        return 0


def save_offset(offset: int) -> None:
    tmp = STATE_PATH + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        fh.write(str(offset))
    os.replace(tmp, STATE_PATH)


def post_error(msgid: str, status: str) -> None:
    data = urllib.parse.urlencode(
        {
            "id": msgid,
            "message_status": status,
            "status": status,
        }
    ).encode()
    req = urllib.request.Request(
        ENDPOINT,
        data=data,
        method="POST",
        headers={"Content-Type": "application/x-www-form-urlencoded", "User-Agent": "jasmin-submit-error-watcher/1.0"},
    )
    with urllib.request.urlopen(req, timeout=15) as resp:
        body = resp.read().decode(errors="replace")
        if "ACK/Jasmin" not in body and resp.status != 200:
            raise RuntimeError(f"unexpected response {resp.status}: {body[:200]}")


def follow() -> None:
    offset = load_offset()
    while True:
        try:
            size = os.path.getsize(LOG_PATH)
        except FileNotFoundError:
            time.sleep(2)
            continue

        if offset > size:
            # log rotated
            offset = 0

        with open(LOG_PATH, "r", encoding="utf-8", errors="replace") as fh:
            fh.seek(offset)
            while True:
                line = fh.readline()
                if not line:
                    offset = fh.tell()
                    save_offset(offset)
                    time.sleep(0.5)
                    break

                match = PATTERN.search(line)
                if not match:
                    continue

                msgid, status = match.group(1), match.group(2)
                try:
                    post_error(msgid, status)
                    print(f"posted submit-error id={msgid} status={status}", flush=True)
                except Exception as exc:
                    print(f"failed id={msgid} status={status} err={exc}", flush=True)
                    time.sleep(2)


if __name__ == "__main__":
    follow()
