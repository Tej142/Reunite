# 📋 Comprehensive QA Test Plan — Reunite Platform (Local Environment: `reunitel`)

> **Target Environment**: Local XAMPP (Apache Port 80, MySQL Port 3306 `lost_connect_db`) and Flask AI server (`http://127.0.0.1:5000`).  
> **Safety Guarantees**: All test records use the `qa_test_` prefix; real user data is untouched; pre-QA database and vector backups created in `reunitel/backups/`.

---

## 🎯 Test Execution Matrix

| Test ID | Area | Test Case / Scenario | Execution Type | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **ENV-01** | Env & Smoke | Verify MySQL connection & table schema match | Automated (Python) | All required tables exist & UTF8mb4 charset | PASS |
| **ENV-02** | Env & Smoke | Verify Flask server health & endpoints | Automated (Python) | Healthcheck returns 200 OK | PASS |
| **ENV-03** | Env & Smoke | Verify Apache serves core frontend & backend endpoints | Automated (Python) | Protected routes redirect, public load 200 | PASS |
| **AUTH-01** | Auth | Register new user with valid data | Automated (Python) | User inserted, password hashed, encrypted fields | PASS |
| **AUTH-02** | Auth | Register with duplicate email | Automated (Python) | Rejected with friendly validation error | PASS |
| **AUTH-03** | Auth | Register with duplicate PIN/roll number | Automated (Python) | Rejected with friendly validation error | PASS |
| **AUTH-04** | Auth | Register with short password or missing fields | Automated (Python) | Blocked with 400 validation error | PASS |
| **AUTH-05** | Auth | Register with XSS payload in full_name | Automated (Python) | Sanitized/escaped, no raw script execution | PASS |
| **AUTH-06** | Auth | Login with valid credentials | Automated (Python) | Session cookie issued, authenticated session active | PASS |
| **AUTH-07** | Auth | Login with incorrect password | Automated (Python) | Rejected with 401/error, session not created | PASS |
| **AUTH-08** | Auth | Login with unknown email | Automated (Python) | Rejected with generic error message | PASS |
| **AUTH-09** | Auth | Logout flow & session invalidation | Automated (Python) | Session destroyed, subsequent auth check returns false | PASS |
| **AUTH-10** | Auth | Unauthenticated access to protected page (`profile.php`, `home.php`) | Automated (Python) | 302 Redirect to `login.php` with redirect query param | PASS |
| **AUTH-11** | Auth | Regular user attempts access to `admin.php` & `admin_api.php` | Automated (Python) | Clearance denied; non-admin blocked | PASS |
| **REP-01** | Reporting M1 | Submit valid lost report via Mode 1 (Photo + Description) | Automated (Python) | Report in MySQL, safe media file, vectors in Chroma | PASS |
| **REP-02** | Reporting M1 | Submit valid found report via Mode 1 | Automated (Python) | Stored in `found_reports` with correct category | PASS |
| **REP-03** | Reporting M1 | Mode 1 submission without description or title | Automated (Python) | Rejected with validation error | PASS |
| **REP-04** | Reporting M1 | File upload edge cases: fake image (.php renamed .jpg), huge image | Automated (Python) | Blocked with invalid MIME/image error | PASS |
| **REP-05** | Reporting M1 | Path traversal attempt in filename (`../../hack.jpg`) | Automated (Python) | Sanitized to safe random/basename in `media_vault` | PASS |
| **REP-06** | Reporting M2 | Submit report via Mode 2 Guided Choice | Automated (Python) | Successfully synthesizes DNA and saves report | PASS |
| **REP-07** | Reporting M3 | Talk to AI conversational turn via Flask | Automated (Python) | Dynamic attributes & draft returned cleanly | PASS |
| **REP-08** | Reporting M3 | Temporal date parsing ("yesterday", "2 days ago") | Automated (Python) | Resolved to exact valid YYYY-MM-DD | PASS |
| **REP-09** | Reporting M3 | Private verification secrets not exposed publicly | Automated (Python) | Kept in `verification_secret` / private fields | PASS |
| **AI-01** | Matching | Seed matching lost & found pair | Automated (Python) | Vector search finds high similarity match | PASS |
| **AI-02** | Matching | Verify no self-match between same user's reports | Automated (Python) | Matchmaker ignores reports with same `user_id` | PASS |
| **AI-03** | Matching | Verify resolved/closed reports excluded from match pool | Automated (Python) | Status != 'active' reports not matched | PASS |
| **AI-04** | Matching | ChromaDB vector storage consistency | Automated (Python) | Vectors match report records | PASS |
| **SCH-01** | Search | Search items by keyword & category | Automated (Python) | Filtered results returned without errors | PASS |
| **SCH-02** | Search | Item detail API (`item.php` / `reports.php?action=get`) | Automated (Python) | Item details load; private fields omitted from public JSON | PASS |
| **CLM-01** | Claims | Submit valid claim on found item | Automated (Python) | Claim row in `recovery_cases` with pending status | PASS |
| **CLM-02** | Claims | Prevent user from claiming their own found item | Automated (Python) | Blocked with validation error | PASS |
| **CLM-03** | Claims | Non-admin user attempts claim approval via API | Automated (Python) | Blocked with 403 Forbidden | PASS |
| **NOTIF-01** | Notifications| Match generates notifications for both users | Automated (Python) | Notifications row created in DB with unread status | PASS |
| **NOTIF-02** | Notifications| Notification polling endpoint returns user-specific notifications | Automated (Python) | Only recipient user sees their notifications | PASS |
| **NOTIF-03** | Notifications| Mark notification as read | Automated (Python) | `is_read` flag set to 1 | PASS |
| **ADM-01** | Admin | Admin clearance passcode authentication | Automated (Python) | `reunite_admin_auth` session established | PASS |
| **ADM-02** | Admin | Admin HUD diagnostics & stats endpoint | Automated (Python) | Accurate counts of users, reports, and system specs | PASS |
| **ADM-03** | Admin | Toggle Maintenance Mode (Enable / Disable) | Automated (Python) | Setting persists in `maintenance_settings` | PASS |
| **ADM-04** | Admin | Maintenance Mode enforcement for normal users | Automated (Python) | Normal users redirected to maintenance page / 503 | PASS |
| **SEC-01** | Security | SQL Injection on login & search fields | Automated (Python) | Blocked by PDO/MySQLi prepared statements | PASS |
| **SEC-02** | Security | XSS reflection in search and report responses | Automated (Python) | Escaped with `htmlspecialchars` | PASS |
| **SEC-03** | Security | AES-256-CBC encryption of sensitive personal data | Automated (Python) | `phone` and `email` stored encrypted in DB | PASS |
| **SEC-04** | Security | IDOR check on user profile and claim details | Automated (Python) | User A cannot fetch User B's private claim | PASS |
| **UI-01** | UI / Browser | Browser flow: Register -> Login -> Report -> Search | Automated (Playwright) | Seamless page navigation without console exceptions | PASS |
| **UI-02** | UI / Browser | Dark / Light theme persistence & responsive layout | Automated (Playwright) | Theme attributes applied to root element | PASS |
| **CLEAN-01**| Cleanup | Cleanup all `qa_test_` data | Automated (Python) | Database, media_vault, ChromaDB left clean | PASS |
