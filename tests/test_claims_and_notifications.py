import time
import uuid
import requests
import pytest

PHP_BASE_URL = "http://localhost/pw/reunitel"

@pytest.fixture(scope="module")
def qa_context():
    """Create two test users (finder & claimant) with qa_test_ prefix."""
    unique_suffix = uuid.uuid4().hex[:6]
    
    finder_data = {
        "full_name": f"qa_test_finder_{unique_suffix}",
        "pin": f"QAF{unique_suffix[:5].upper()}",
        "email": f"qa_test_finder_{unique_suffix}@campus.edu",
        "password": "Password123!QA",
        "college": "SV Polytechnic",
        "branch": "CSE"
    }
    
    claimant_data = {
        "full_name": f"qa_test_claimant_{unique_suffix}",
        "pin": f"QAC{unique_suffix[:5].upper()}",
        "email": f"qa_test_claimant_{unique_suffix}@campus.edu",
        "password": "Password123!QA",
        "college": "SV Polytechnic",
        "branch": "ECE"
    }
    
    # Finder session
    s_finder = requests.Session()
    rf = s_finder.post(f"{PHP_BASE_URL}/Backend/registration.php", json=finder_data, timeout=5)
    assert rf.status_code == 200, f"Finder reg failed: {rf.text}"
    finder_user_id = rf.json().get("user", {}).get("user_id")
    
    # Claimant session
    s_claimant = requests.Session()
    rc = s_claimant.post(f"{PHP_BASE_URL}/Backend/registration.php", json=claimant_data, timeout=5)
    assert rc.status_code == 200, f"Claimant reg failed: {rc.text}"
    claimant_user_id = rc.json().get("user", {}).get("user_id")

    context = {
        "s_finder": s_finder,
        "s_claimant": s_claimant,
        "finder_data": finder_data,
        "claimant_data": claimant_data,
        "finder_id": finder_user_id,
        "claimant_id": claimant_user_id,
        "report_id": None,
        "claim_id": None
    }
    
    yield context

class TestClaimsAndNotificationsLifecycle:

    def test_finder_creates_found_report(self, qa_context):
        s_finder = qa_context["s_finder"]
        payload = {
            "action": "create",
            "report_type": "found",
            "title": f"qa_test_ Blue HP Pavilion Laptop {time.time()}",
            "category": "Electronics",
            "description": "Found inside library 2nd floor reading hall with black sleeve.",
            "location": "Central Library",
            "date": "2026-10-09"
        }
        r = s_finder.post(f"{PHP_BASE_URL}/Backend/reports.php", json=payload, timeout=5)
        assert r.status_code == 200, f"Failed creating found report: {r.text}"
        data = r.json()
        assert data["success"] is True
        qa_context["report_id"] = data.get("db_id")
        assert qa_context["report_id"] and qa_context["report_id"] > 0

    def test_unauthenticated_claim_rejected(self, qa_context):
        rep_id = qa_context["report_id"]
        # Blank session
        s_anon = requests.Session()
        r = s_anon.post(f"{PHP_BASE_URL}/Backend/claims.php", json={
            "action": "submit",
            "report_id": rep_id,
            "proof_details": "My laptop serial number is SN-987654"
        }, timeout=5)
        assert r.status_code == 401
        data = r.json()
        assert data["success"] is False
        assert data.get("code") == "UNAUTHORIZED"

    def test_self_claim_prevented(self, qa_context):
        s_finder = qa_context["s_finder"]
        rep_id = qa_context["report_id"]
        # Finder tries to claim their own found report
        r = s_finder.post(f"{PHP_BASE_URL}/Backend/claims.php", json={
            "action": "submit",
            "report_id": rep_id,
            "proof_details": "I am claiming this myself"
        }, timeout=5)
        assert r.status_code == 400
        data = r.json()
        assert data["success"] is False
        assert "cannot claim an item that you reported" in data["error"].lower()

    def test_claimant_submits_valid_claim(self, qa_context):
        s_claimant = qa_context["s_claimant"]
        rep_id = qa_context["report_id"]
        r = s_claimant.post(f"{PHP_BASE_URL}/Backend/claims.php", json={
            "action": "submit",
            "report_id": rep_id,
            "proof_details": "Lock screen wallpaper is an anime girl and bottom right corner has a scratch.",
            "contact_info": "Alex Rivera (21CS042)"
        }, timeout=5)
        assert r.status_code == 200, f"Claim failed: {r.text}"
        data = r.json()
        assert data["success"] is True
        assert "claim_id" in data
        qa_context["claim_id"] = data["claim_id"]

    def test_duplicate_pending_claim_prevented(self, qa_context):
        s_claimant = qa_context["s_claimant"]
        rep_id = qa_context["report_id"]
        r = s_claimant.post(f"{PHP_BASE_URL}/Backend/claims.php", json={
            "action": "submit",
            "report_id": rep_id,
            "proof_details": "Another attempt to claim the exact same item."
        }, timeout=5)
        assert r.status_code == 400
        data = r.json()
        assert data["success"] is False
        assert "already have a pending verification claim" in data["error"].lower()

    def test_finder_receives_claim_notification(self, qa_context):
        s_finder = qa_context["s_finder"]
        r = s_finder.get(f"{PHP_BASE_URL}/Backend/notifications.php?action=get", timeout=5)
        assert r.status_code == 200
        data = r.json()
        assert data["success"] is True
        notifs = data.get("notifications", [])
        claim_notifs = [n for n in notifs if n.get("type") == "claim"]
        assert len(claim_notifs) > 0, "Finder did not receive claim notification"

    def test_unauthorized_user_cannot_resolve_claim(self, qa_context):
        # A 3rd unrelated user attempts to approve or reject the claim
        s_intruder = requests.Session()
        s_intruder.post(f"{PHP_BASE_URL}/Backend/registration.php", json={
            "full_name": "qa_test_intruder",
            "pin": f"QAI{uuid.uuid4().hex[:4].upper()}",
            "email": f"qa_intruder_{uuid.uuid4().hex[:6]}@campus.edu",
            "password": "Password123!QA"
        }, timeout=5)
        
        r = s_intruder.post(f"{PHP_BASE_URL}/Backend/claims.php", json={
            "action": "resolve",
            "claim_id": qa_context["claim_id"],
            "status": "approved"
        }, timeout=5)
        assert r.status_code == 403
        data = r.json()
        assert data["success"] is False

    def test_finder_approves_claim_and_updates_status(self, qa_context):
        s_finder = qa_context["s_finder"]
        claim_id = qa_context["claim_id"]
        rep_id = qa_context["report_id"]
        
        r = s_finder.post(f"{PHP_BASE_URL}/Backend/claims.php", json={
            "action": "resolve",
            "claim_id": claim_id,
            "status": "approved"
        }, timeout=5)
        assert r.status_code == 200
        data = r.json()
        assert data["success"] is True
        assert data.get("new_status") == "approved"

        # Claimant checks notification
        s_claimant = qa_context["s_claimant"]
        rc = s_claimant.get(f"{PHP_BASE_URL}/Backend/notifications.php?action=get", timeout=5)
        assert rc.status_code == 200
        c_notifs = rc.json().get("notifications", [])
        approved_notifs = [n for n in c_notifs if "approved" in n.get("title", "").lower() or "approved" in n.get("message", "").lower()]
        assert len(approved_notifs) > 0, "Claimant did not receive approval notification"
