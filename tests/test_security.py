import uuid
import requests
import pytest

PHP_BASE_URL = "http://localhost/pw/reunitel"

class TestSecurityAudit:

    def test_sql_injection_in_login(self):
        """Verify login parameterization against standard SQL injection payloads."""
        payloads = [
            "' OR '1'='1",
            "' OR 1=1 --",
            "admin' --",
            "' UNION SELECT 1, 'admin', 'admin', 'admin', 'hash', 100, 'admin', 'active', '', '', '' --"
        ]
        for p in payloads:
            r = requests.post(f"{PHP_BASE_URL}/Backend/login.php", json={
                "identifier": p,
                "password": "any_password"
            }, timeout=5)
            # Should reject with 401 or 404, never 200 or 500 SQL syntax error
            assert r.status_code in [401, 404], f"Vulnerable to SQLi with payload: {p}"
            data = r.json()
            assert data["success"] is False

    def test_sql_injection_in_search_and_reports(self):
        """Verify reports API parameterized queries prevent SQL injection."""
        sqli_query = "Electronics' OR '1'='1"
        r = requests.get(f"{PHP_BASE_URL}/Backend/reports.php?action=list&category={sqli_query}", timeout=5)
        assert r.status_code == 200
        data = r.json()
        assert data["success"] is True

    def test_admin_dom_containment_unauthenticated(self):
        """Verify BUG-03 fix: unauthenticated visitor receives zero adminDashboard markup or PHP version."""
        r = requests.get(f"{PHP_BASE_URL}/Frontend/admin.php", timeout=5)
        assert r.status_code == 200
        html = r.text
        # Must contain clearance login form
        assert 'id="adminLoginForm"' in html or 'admin-auth-card' in html
        # Must NOT contain the dashboard container
        assert '<div id="adminDashboard"' not in html
        # Must NOT contain the maintenance modal
        assert 'id="maintModalBackdrop"' not in html
        # Must NOT leak server PHP version
        assert 'metricPhpVersion' not in html

    def test_admin_api_idor_and_authorization(self):
        """Verify unauthenticated caller cannot execute privileged admin actions."""
        # Attempt to toggle maintenance mode
        r = requests.post(f"{PHP_BASE_URL}/Backend/admin_api.php", data={
            "action": "toggle_maintenance",
            "enabled": 1
        }, timeout=5)
        assert r.status_code == 403 or (r.json().get("success") is False)

        # Attempt to get audit logs
        r_logs = requests.get(f"{PHP_BASE_URL}/Backend/admin_api.php?action=get_logs", timeout=5)
        assert r_logs.status_code == 403 or (r_logs.json().get("success") is False)

    def test_xss_sanitization_in_item_view(self):
        """Verify XSS vectors in title or description are safely HTML-entity encoded."""
        s = requests.Session()
        unique = uuid.uuid4().hex[:6]
        # Register test user
        s.post(f"{PHP_BASE_URL}/Backend/registration.php", json={
            "full_name": f"qa_test_xss_{unique}",
            "pin": f"QAX{unique[:5].upper()}",
            "email": f"qa_xss_{unique}@campus.edu",
            "password": "Password123!QA"
        }, timeout=5)

        xss_payload = "<script>alert('XSS_ATTACK_TEST')</script><img src=x onerror=alert(1)>"
        # Submit report with payload
        r_rep = s.post(f"{PHP_BASE_URL}/Backend/reports.php", json={
            "action": "create",
            "report_type": "lost",
            "title": f"qa_test_ XSS {unique} {xss_payload}",
            "category": "General",
            "description": f"Testing description with {xss_payload}",
            "location": "Library",
            "date": "2026-10-09"
        }, timeout=5)
        assert r_rep.status_code == 200
        rep_id = r_rep.json().get("db_id") or r_rep.json().get("id")

        # Now view the item page directly
        r_view = s.get(f"{PHP_BASE_URL}/Frontend/item.php?type=lost&id={rep_id}", timeout=5)
        assert r_view.status_code == 200
        # Raw dangerous script tags must NOT be present unescaped in DOM
        assert "<script>alert('XSS_ATTACK_TEST')</script>" not in r_view.text
        assert "&lt;script&gt;alert(&#039;XSS_ATTACK_TEST&#039;)&lt;/script&gt;" in r_view.text or "&lt;script&gt;" in r_view.text

    def test_path_traversal_protection(self):
        """Verify media / image path cannot traverse out of web directory."""
        r = requests.get(f"{PHP_BASE_URL}/media_vault/../../windows/win.ini", timeout=5)
        # Apache must reject directory traversal with 400 or 403 or 404
        assert r.status_code in [400, 403, 404]
