# 05 — Admin Command Center & REST API Reference

## 1. Command Center Architecture
The **Command Center** ([`Frontend/admin/index.php`](file:///c:/xampp/htdocs/pw/Reunite/Frontend/admin/index.php)) is a single-pane-of-glass administrative operational hub. It automatically adapts based on whether the logged-in administrator is a **Master Administrator** (Global) or a **Campus Administrator** (Institutional Scope).

### Core Capabilities:
- **Live Event Telemetry:** Background sync loop polling every 8 seconds with audio alerts and visual ticker banner.
- **Claims Verification Desk:** Real-time review of disputed items, approval handshakes, and owner verification photos.
- **Dynamic Campus Provisioning:** Master Admin can add, update, and manage campus nodes, instantly propagating new institutions to the public registration dropdown.
- **Dual-Theme Engine:** High-contrast crisp White theme by default, with instant local persistence for Dark theme.

---

## 2. Real-Time Telemetry Polling Pipeline
File: `Frontend/admin/js/admin.js`

```javascript
/**
 * 8-Second Telemetry Sync Engine
 * Fetches real-time counts, claims, and audit events scoped to admin campus
 */
function pollTelemetryStream() {
    if (isTelemetryPaused) return;

    fetch('../../Backend/admin_api.php?action=telemetry_stream')
        .then(response => response.json())
        .then(res => {
            if (!res.success) return;
            const data = res.data;

            // 1. Detect new claims requiring administrative action
            if (data.pending_claims > previousClaimCount) {
                triggerAudioAlert('claim');
                showNotificationBanner(`New claim submitted on ${data.campus_name || 'campus'}`);
            }
            previousClaimCount = data.pending_claims;

            // 2. Update dashboard count badges
            updateBadge('badgeLostCount', data.total_lost);
            updateBadge('badgeFoundCount', data.total_found);
            updateBadge('badgeMatchCount', data.pending_matches);
            updateBadge('badgeClaimCount', data.pending_claims);

            // 3. Prepend latest audit log into live ticker
            if (data.latest_event) {
                updateLiveTicker(data.latest_event);
            }
        })
        .catch(err => console.error('[Telemetry] Sync error:', err));
}

// Automatically start polling loop
setInterval(pollTelemetryStream, 8000);
```

---

## 3. Core REST API Reference

| Endpoint | Method | Authorization | Parameters | Description |
| :--- | :--- | :--- | :--- | :--- |
| `Backend/admin_api.php?action=telemetry_stream` | `GET` | Campus / Master Admin | None | Returns real-time counts, unreviewed claims, and latest audit events. |
| `Backend/admin_api.php?action=campuses` | `GET` | Master Admin | None | Lists all institutional campus nodes with admin assignments. |
| `Backend/admin_api.php?action=add_campus` | `POST` | Master Admin | `name`, `code`, `city` | Creates a new university node; updates registration dropdown instantly. |
| `Backend/admin_api.php?action=claims` | `GET` | Campus / Master Admin | `status`, `page` | Returns paginated claim records scoped by campus isolation rules. |
| `Backend/admin_api.php?action=audit_logs` | `GET` | Campus / Master Admin | `limit` | Returns cryptographic audit trail of logins, handovers, and claims. |
| `Frontend/api/auth.php?action=login` | `POST` | Public | `identifier`, `password`, `role` | Centralized authentication endpoint for Students and Administrators. |
| `Frontend/api/founder_approval.php` | `POST` | Authenticated User | `claim_id`, `decision` | Authorizes or rejects handover request for a found item. |
| `Backend/admin_auth.php?action=logout` | `GET` | Authenticated Admin | None | Terminates session in server memory and wipes fallback cookies. |

---

## 4. Theme System Architecture
File: `Frontend/admin/css/admin.css`

Themes are controlled via the `data-theme` attribute on the root `<html>` element:
```css
:root,
[data-theme="light"] {
  --bg-body: #F4F6F9;
  --bg-surface: #FFFFFF;
  --bg-card: #FFFFFF;
  --border-color: #E2E8F0;
  --ink: #0F172A;
  --muted: #64748B;
  --accent: #E06D38;
  --danger: #EF4444;
}

[data-theme="dark"] {
  --bg-body: #0E0C0A;
  --bg-surface: #171412;
  --bg-card: #1D1916;
  --border-color: rgba(255, 255, 255, 0.08);
  --ink: #F7F3EE;
  --muted: #A89D94;
  --accent: #E06D38;
}
```

A flash-prevention script in `<head>` applies the user's stored preference from `localStorage.getItem('reunite_admin_theme')` synchronously before browser layout and paint.
