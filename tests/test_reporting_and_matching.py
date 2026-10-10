import time
import uuid
import requests
import pytest

PHP_BASE_URL = "http://localhost/pw/reunitel"
FLASK_BASE_URL = "http://127.0.0.1:5000"

@pytest.fixture(scope="module")
def user_session():
    """Create a test user session."""
    s = requests.Session()
    unique = uuid.uuid4().hex[:6]
    payload = {
        "full_name": f"qa_test_match_{unique}",
        "pin": f"QAM{unique[:5].upper()}",
        "email": f"qa_test_match_{unique}@campus.edu",
        "password": "Password123!QA"
    }
    r = s.post(f"{PHP_BASE_URL}/Backend/registration.php", json=payload, timeout=5)
    assert r.status_code == 200, f"Registration failed: {r.text}"
    return s

class TestReportingAndMatchingPipeline:

    def test_mode1_create_lost_and_found_reports(self, user_session):
        unique = uuid.uuid4().hex[:6]
        # 1. Create Found Report for a Calculator
        found_payload = {
            "action": "create",
            "report_type": "found",
            "title": f"qa_test_ Casio fx-991EX Scientific Calculator {unique}",
            "category": "Electronics",
            "description": "Found black and white scientific calculator in Room 302 desk drawer.",
            "location": "Room 302",
            "date": "2026-10-09"
        }
        r_f = user_session.post(f"{PHP_BASE_URL}/Backend/reports.php", json=found_payload, timeout=5)
        assert r_f.status_code == 200
        f_data = r_f.json()
        assert f_data["success"] is True
        found_id = f_data.get("id") or f_data.get("db_id")
        assert found_id and found_id > 0

        # 2. Create Lost Report for the same Calculator
        lost_payload = {
            "action": "create",
            "report_type": "lost",
            "title": f"qa_test_ Lost Casio fx-991EX Calculator {unique}",
            "category": "Electronics",
            "description": "Lost my Casio fx-991EX calculator during maths class in Room 302.",
            "location": "Room 302",
            "date": "2026-10-09"
        }
        r_l = user_session.post(f"{PHP_BASE_URL}/Backend/reports.php", json=lost_payload, timeout=5)
        assert r_l.status_code == 200
        l_data = r_l.json()
        assert l_data["success"] is True
        lost_id = l_data.get("id") or l_data.get("db_id")
        assert lost_id and lost_id > 0

    def test_mode2_talk_to_ai_extraction(self):
        # Conversational message containing multiple slots
        msg = "Hi, I lost my brown leather Titan wallet near the college canteen yesterday afternoon."
        payload = {
            "message": msg,
            "history": [],
            "report_type": "lost",
            "current_draft": {}
        }
        r = requests.post(f"{FLASK_BASE_URL}/report/talk_to_ai/chat", json=payload, timeout=15)
        assert r.status_code == 200
        data = r.json()
        assert data.get("success") is True
        assert "reply" in data
        draft = data.get("draft", {})
        # Verify slot extraction correctly populated category and attributes
        assert isinstance(draft, dict)
        assert draft.get("category") == "Bags & Wallets" or "wallet" in str(draft).lower()

    def test_mode3_quick_report_synthesis(self):
        payload = {
            "description": "Found a silver stainless steel water bottle in seminar hall today morning",
            "location": "Seminar Hall",
            "time": "today morning"
        }
        r = requests.post(f"{FLASK_BASE_URL}/new-report", json=payload, timeout=10)
        assert r.status_code == 200
        data = r.json()
        assert data.get("success") is True
        assert "digital_dna" in data
        dna = data["digital_dna"]
        assert "resolved_date" in data or "resolved_date" in dna

    def test_vector_search_matches_query(self):
        query_payload = {
            "text": "Casio scientific calculator black Room 302",
            "report_type": "found",
            "top_k": 5
        }
        r = requests.post(f"{FLASK_BASE_URL}/report/search-matches", json=query_payload, timeout=10)
        assert r.status_code == 200
        data = r.json()
        assert data.get("success") is True
        assert "matches" in data
        assert isinstance(data["matches"], list)

    def test_search_matches_filtering_by_target_type(self):
        # Querying specifically for lost vs found
        r_found = requests.post(f"{FLASK_BASE_URL}/report/search-matches", json={
            "text": "calculator",
            "report_type": "found",
            "top_k": 5
        }, timeout=10)
        assert r_found.status_code == 200
        for m in r_found.json().get("matches", []):
            if m.get("metadata") and "report_type" in m["metadata"]:
                assert m["metadata"]["report_type"].lower() == "found"
