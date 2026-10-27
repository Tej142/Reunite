# Admin Command Center & Real-Time Telemetry Code

## 1. Real-Time Telemetry Stream Endpoint
**Source File:** `Backend/admin_api.php`

```php
// ── Telemetry Stream Pipeline ──────────────────────────────────
if ($action === 'telemetry_stream') {
    $scopeSql = apply_campus_scope_sql("SELECT COUNT(*) AS c FROM claims WHERE status = 'pending_founder_approval'", 'claims', $adminSession);
    $qClaims = $conn->query($scopeSql);
    $pendingClaims = $qClaims ? (int)$qClaims->fetch_assoc()['c'] : 0;

    $scopeLost = apply_campus_scope_sql("SELECT COUNT(*) AS c FROM lost_reports WHERE status = 'active'", 'lost_reports', $adminSession);
    $qLost = $conn->query($scopeLost);
    $totalLost = $qLost ? (int)$qLost->fetch_assoc()['c'] : 0;

    $scopeFound = apply_campus_scope_sql("SELECT COUNT(*) AS c FROM found_reports WHERE status = 'available'", 'found_reports', $adminSession);
    $qFound = $conn->query($scopeFound);
    $totalFound = $qFound ? (int)$qFound->fetch_assoc()['c'] : 0;

    // Fetch latest audit event for live ticker
    $qEvent = $conn->query("SELECT action, details, created_at FROM access_logs ORDER BY log_id DESC LIMIT 1");
    $latestEvent = $qEvent ? $qEvent->fetch_assoc() : null;

    echo json_encode([
        'success' => true,
        'data'    => [
            'pending_claims'  => $pendingClaims,
            'total_lost'      => $totalLost,
            'total_found'     => $totalFound,
            'campus_id'       => $adminSession['campus_id'],
            'campus_name'     => $adminSession['campus_name'],
            'is_master'       => $adminSession['is_master'],
            'latest_event'    => $latestEvent,
            'timestamp'       => time()
        ]
    ]);
    exit;
}

// ── Dynamic Campus Sync Endpoint ──────────────────────────────
if ($action === 'add_campus') {
    require_master_admin($adminSession);
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $name = trim($input['name'] ?? '');
    $code = strtoupper(trim($input['code'] ?? ''));
    $city = trim($input['city'] ?? '');

    if (empty($name) || empty($code)) {
        echo json_encode(['success' => false, 'error' => 'Campus Name and Unique Code are required.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO campuses (name, code, city, is_active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $name, $code, $city);
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        logAccess(0, 'Campus Created', "Added campus node: {$name} [{$code}]");
        echo json_encode(['success' => true, 'campus_id' => $newId, 'message' => "Campus '{$name}' created and synchronized across the platform."]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error or duplicate campus code.']);
    }
    exit;
}
```

---

## 2. Frontend 8-Second Telemetry Sync Loop
**Source File:** `Frontend/admin/js/admin.js`

```javascript
/**
 * 8-Second Telemetry Sync Engine
 * Synchronizes operational stats and triggers audio notifications
 */
let isTelemetryPaused = false;
let previousPendingClaims = 0;

function pollTelemetryStream() {
    if (isTelemetryPaused) return;

    fetch('../../Backend/admin_api.php?action=telemetry_stream')
        .then(res => res.json())
        .then(response => {
            if (!response.success) return;
            const data = response.data;

            // Audio alert on new incoming claim
            if (previousPendingClaims !== null && data.pending_claims > previousPendingClaims) {
                playAudioNotification();
            }
            previousPendingClaims = data.pending_claims;

            // Synchronize UI Badges
            document.getElementById('badgeLostCount').textContent = data.total_lost;
            document.getElementById('badgeFoundCount').textContent = data.total_found;
            document.getElementById('badgeClaimCount').textContent = data.pending_claims;

            // Prepend new audit event into live ticker
            if (data.latest_event) {
                const ticker = document.getElementById('liveTickerText');
                if (ticker) {
                    ticker.innerHTML = `<span style="color:var(--accent); font-weight:600;">${escapeHtml(data.latest_event.action)}:</span> ${escapeHtml(data.latest_event.details)}`;
                }
            }
        })
        .catch(err => console.error('[Telemetry] Network polling error:', err));
}

// Start recurring 8s telemetry loop
setInterval(pollTelemetryStream, 8000);
```

---

## 3. Dual-Theme Engine (Default White Theme + Dark Switcher)
**Source File:** `Frontend/admin/js/admin.js` & `Frontend/admin/css/admin.css`

```javascript
// Instant theme initialization before paint
function initThemeEngine() {
    const savedTheme = localStorage.getItem('reunite_admin_theme') || 'light';
    applyTheme(savedTheme);
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('reunite_admin_theme', theme);
    const icon = document.getElementById('themeToggleIcon');
    if (icon) {
        icon.className = (theme === 'dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = (current === 'dark') ? 'light' : 'dark';
    applyTheme(next);
}
```

```css
/* Core Dual-Theme CSS Variables */
:root,
[data-theme="light"] {
  --bg-body: #F8FAFC;
  --bg-surface: #FFFFFF;
  --bg-card: #FFFFFF;
  --border-color: #E2E8F0;
  --ink: #0F172A;
  --muted: #64748B;
  --accent: #E06D38;
  --accent-hover: #C45524;
  --danger: #EF4444;
}

[data-theme="dark"] {
  --bg-body: #0F0D0B;
  --bg-surface: #181411;
  --bg-card: #1E1915;
  --border-color: rgba(255, 255, 255, 0.08);
  --ink: #F7F3EE;
  --muted: #A89D94;
  --accent: #E06D38;
}
```
