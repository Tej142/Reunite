import pytest
import requests
import json
import os
import time

PHP_BASE_URL = "http://localhost/pw/reunitel"
FLASK_BASE_URL = "http://127.0.0.1:5000"

TEST_USER_EMAIL = f"qa_test_{int(time.time())}@campus.edu"
TEST_USER_PIN = f"QA{int(time.time()) % 100000:05d}"
TEST_USER_PASS = "ValidPassword123!"

class TestPhase0Environment:
    def test_flask_server_health(self):
        r = requests.get(f"{FLASK_BASE_URL}/", timeout=5)
        assert r.status_code == 200
        assert "LostConnect AI Server Running" in r.text

    def test_apache_serving_public_pages(self):
        r = requests.get(f"{PHP_BASE_URL}/Frontend/search.php", timeout=5)
        assert r.status_code == 200
        assert "Search" in r.text

    def test_protected_pages_redirect_unauthenticated(self):
        r = requests.get(f"{PHP_BASE_URL}/Frontend/home.php", allow_redirects=False, timeout=5)
        assert r.status_code == 302
        assert "login.php" in r.headers.get("Location", "")

        r = requests.get(f"{PHP_BASE_URL}/Frontend/profile.php", allow_redirects=False, timeout=5)
        assert r.status_code == 302
        assert "login.php" in r.headers.get("Location", "")

class TestPhase2AuthAndSessions:
    def test_register_validation_empty_fields(self):
        r = requests.post(f"{PHP_BASE_URL}/Backend/registration.php", json={}, timeout=5)
        assert r.status_code == 400
        res = r.json()
        assert res["success"] is False

    def test_register_validation_short_password(self):
        payload = {
            "full_name": "QA Tester",
            "email": "qa_short@test.com",
            "password": "123",
            "pin": "QA999"
        }
        r = requests.post(f"{PHP_BASE_URL}/Backend/registration.php", json=payload, timeout=5)
        assert r.status_code == 400
        res = r.json()
        assert res["success"] is False
        assert "at least 8" in res["message"].lower()

    def test_register_validation_invalid_email(self):
        payload = {
            "full_name": "QA Tester",
            "email": "not-an-email",
            "password": "ValidPassword123!",
            "pin": "QA998"
        }
        r = requests.post(f"{PHP_BASE_URL}/Backend/registration.php", json=payload, timeout=5)
        assert r.status_code == 400
        res = r.json()
        assert res["success"] is False

    def test_login_wrong_password(self):
        payload = {
            "action": "login",
            "identifier": "unknown_user@test.com",
            "password": "WrongPassword123!"
        }
        r = requests.post(f"{PHP_BASE_URL}/Frontend/api/auth.php", json=payload, timeout=5)
        res = r.json()
        assert res["success"] is False

    def test_admin_api_blocks_unauthorized_user(self):
        # Without session
        r = requests.get(f"{PHP_BASE_URL}/Backend/admin_api.php?action=get_reports", timeout=5)
        assert r.status_code == 403 or (r.json().get("success") is False)

    def test_admin_auth_endpoint(self):
        # Correct passcode
        r = requests.post(f"{PHP_BASE_URL}/Backend/admin_api.php", data={"action": "login", "passcode": "admin"}, timeout=5)
        res = r.json()
        assert res["success"] is True

        # Incorrect passcode
        r = requests.post(f"{PHP_BASE_URL}/Backend/admin_api.php", data={"action": "login", "passcode": "wrong_key_123"}, timeout=5)
        res = r.json()
        assert res["success"] is False

class TestPhase2ReportingAndAI:
    def test_flask_talk_to_ai_chat(self):
        payload = {
            "message": "I lost my blue backpack near library yesterday",
            "history": [],
            "report_type": "lost",
            "current_draft": {}
        }
        r = requests.post(f"{FLASK_BASE_URL}/report/talk_to_ai/chat", json=payload, timeout=15)
        assert r.status_code == 200
        res = r.json()
        assert res["success"] is True
        assert "reply" in res
        assert "draft" in res
        assert res["draft"].get("resolved_date") is not None

    def test_flask_temporal_time_parser(self):
        from AI_Module.utils.time_parser import parse_temporal_expression
        parsed = parse_temporal_expression("I lost my keys yesterday at 3 PM")
        assert parsed["resolved_date"] is not None
        assert parsed["resolved_time"] == "15:00:00"

    def test_mode1_report_creation_missing_required_fields(self):
        payload = {"action": "create", "type": "lost"}
        r = requests.post(f"{PHP_BASE_URL}/Backend/reports.php", json=payload, timeout=5)
        assert r.status_code == 400
        res = r.json()
        assert res["success"] is False
