import time

_logs = []


def start(step_name: str):
    """Start timing a step."""
    _logs.append({
        "step": step_name,
        "start": time.time(),
        "end": None,
        "duration": None
    })


def stop(step_name: str):
    """Stop timing a step."""
    now = time.time()
    for log in _logs:
        if log["step"] == step_name and log["end"] is None:
            log["end"] = now
            log["duration"] = round(now - log["start"], 2)
            break


def get_logs():
    """Return all logs and clear them."""
    result = list(_logs)
    _logs.clear()
    return result


def print_logs():
    """Print all logs to console and clear them."""
    if not _logs:
        print("[TIMER] No timing logs recorded.")
        return

    print("\n" + "=" * 50)
    print("[TIMER] TIMING LOG")
    print("=" * 50)

    total = 0
    for log in _logs:
        duration = log["duration"] or 0
        total += duration
        status = f"{duration:.2f}s" if log["end"] else "RUNNING"
        print(f"  {log['step']:.<35} {status}")

    print("-" * 50)
    print(f"  {'TOTAL':.<35} {total:.2f}s")
    print("=" * 50 + "\n")

    _logs.clear()
