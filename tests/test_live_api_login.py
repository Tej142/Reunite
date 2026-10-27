import urllib.request
import json

def test_login(identifier, password, role, expected_success, label):
    url = "https://reunite.site.je/Frontend/api/auth.php?action=login"
    payload = json.dumps({
        "identifier": identifier,
        "password": password,
        "role": role
    }).encode("utf-8")
    
    req = urllib.request.Request(
        url,
        data=payload,
        headers={
            "Content-Type": "application/json",
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"
        }
    )
    
    try:
        with urllib.request.urlopen(req) as resp:
            raw = resp.read().decode("utf-8")
            try:
                data = json.loads(raw)
            except Exception:
                print(f"[RAW RESPONSE] ({resp.status}): {raw[:300]}")
                raise
            success = data.get("success", False)
            print(f"[{'PASS' if success == expected_success else 'FAIL'}] {label}")
            print(f"  Response: {json.dumps(data)}")
            return data
    except Exception as e:
        print(f"[ERROR] {label}: {e}")
        return None

print("=== TESTING UNIFIED LOGIN API OVER HTTP ===")
# 1. Campus Admin Explicit Login
test_login("ramesh_svgp", "password123", "campus_admin", True, "Campus admin login with correct password")

# 2. Campus Admin Invalid Password
test_login("ramesh_svgp", "wrong_pass_test", "campus_admin", False, "Campus admin login with incorrect password")

# 3. Smart Fallback: Campus admin credentials on student tab
test_login("ramesh_svgp", "password123", "student", True, "Campus admin credentials submitted on student tab (smart fallback)")

# 4. Unknown account
test_login("unknown_fake_user_12345", "some_password", "campus_admin", False, "Non-existent campus admin account")
