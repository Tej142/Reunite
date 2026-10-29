"""
build_sequence.py  — Generator for Reunite Internal Workings Sequence (Archify 3.2.0)
Produces:  specs/reunite_internal_sequence.sequence.json
Renders:   06_END_TO_END_INTERNAL_SEQUENCE.html
"""
import json, subprocess, os

BASE_DIR    = os.path.dirname(os.path.abspath(__file__))
ARCHIFY_DIR = os.path.dirname(BASE_DIR)
ARCHIFY_CLI = os.path.abspath(os.path.join(ARCHIFY_DIR, "..", "..", "..", "archify-main", "archify", "bin", "archify.mjs"))

sequence_data = {
  "schema_version": 1,
  "diagram_type": "sequence",
  "meta": {
    "title": "Reunite - User Interaction & Internal Workings Sequence",
    "output": "06_END_TO_END_INTERNAL_SEQUENCE.html",
    "viewBox": [1080, 1300],
    "animation": "trace",
    "visual_preset": "signal-flow",
    "quality_profile": "showcase"
  },
  "participants": [
    { "id": "client",   "type": "frontend",   "label": "Student Client", "sublabel": "Browser / PWA" },
    { "id": "gateway",  "type": "backend",    "label": "PHP API Gateway","sublabel": "REST & Auth" },
    { "id": "guard",    "type": "security",   "label": "Session Guard",  "sublabel": "Anti-IDOR Scope" },
    { "id": "mysql",    "type": "database",   "label": "MySQL 8.0",      "sublabel": "lost_connect_db" },
    { "id": "flask_ai", "type": "backend",    "label": "Flask AI Engine","sublabel": "CLIP ViT-B/32" },
    { "id": "chromadb", "type": "database",   "label": "ChromaDB Store", "sublabel": "HNSW Cosine" },
    { "id": "notifier", "type": "messagebus", "label": "Alert Notifier", "sublabel": "Push / Bell Event" },
    { "id": "desk",     "type": "security",   "label": "Campus Desk",    "sublabel": "Admin 2FA Gate" }
  ],
  "segments": [
    { "from": 140, "to": 340, "label": "1. Student Onboarding & Session Issuance" },
    { "from": 355, "to": 590, "label": "2. Multi-Modal Item Intake & Security Gating" },
    { "from": 605, "to": 850, "label": "3. Flask AI CLIP Vectorization & ChromaDB k-NN Search" },
    { "from": 865, "to": 1120, "label": "4. Match Alert, OTP Verification & Physical Handover" }
  ],
  "messages": [
    { "from": "client",   "to": "gateway",  "y": 170, "label": "POST /api/auth.php (Roll No + PIN)",                  "variant": "emphasis" },
    { "from": "gateway",  "to": "mysql",    "y": 210, "label": "SELECT credentials & campus_id WHERE status=active",  "variant": "default"  },
    { "from": "mysql",    "to": "gateway",  "y": 248, "label": "User record verified (campus_id: 1)",                 "variant": "return"   },
    { "from": "gateway",  "to": "guard",    "y": 286, "label": "Bind session token & campus boundary scope",          "variant": "security" },
    { "from": "guard",    "to": "client",   "y": 324, "label": "200 OK + session active (home.php redirect)",         "variant": "return"   },

    { "from": "client",   "to": "gateway",  "y": 380, "label": "POST /api/report_item.php (image + form data)",       "variant": "emphasis" },
    { "from": "gateway",  "to": "guard",    "y": 416, "label": "Validate CSRF token, MIME type & campus scope",       "variant": "security" },
    { "from": "guard",    "to": "gateway",  "y": 454, "label": "Sanitized & EXIF-stripped file stream approved",      "variant": "return"   },
    { "from": "gateway",  "to": "mysql",    "y": 494, "label": "INSERT INTO lost_reports / found_reports",            "variant": "emphasis" },
    { "from": "mysql",    "to": "gateway",  "y": 530, "label": "Return report_id & timestamp",                        "variant": "return"   },
    { "from": "gateway",  "to": "client",   "y": 568, "label": "201 Created: Report live on Community Board",         "variant": "return"   },

    { "from": "gateway",  "to": "flask_ai", "y": 628, "label": "POST /api/match/embed (image + description text)",   "variant": "emphasis" },
    { "from": "flask_ai", "to": "chromadb", "y": 668, "label": "Upsert 512-d CLIP fused vector (60% vis / 40% txt)", "variant": "default"  },
    { "from": "flask_ai", "to": "chromadb", "y": 710, "label": "HNSW k-NN cosine scan opposing collection",          "variant": "emphasis" },
    { "from": "chromadb", "to": "flask_ai", "y": 750, "label": "Candidates with cosine distance <= 0.30 returned",   "variant": "return"   },
    { "from": "flask_ai", "to": "gateway",  "y": 790, "label": "Return match candidates & confidence scores",         "variant": "return"   },
    { "from": "gateway",  "to": "mysql",    "y": 830, "label": "INSERT INTO matches (lost_id, found_id, score)",      "variant": "emphasis" },

    { "from": "gateway",  "to": "notifier", "y": 882, "label": "Emit match event: similarity >= 75%",                "variant": "emphasis" },
    { "from": "notifier", "to": "client",   "y": 922, "label": "Push notification + navbar bell badge update",        "variant": "dashed"   },
    { "from": "client",   "to": "gateway",  "y": 964, "label": "POST /api/claim_item.php (proof + identity)",         "variant": "default"  },
    { "from": "gateway",  "to": "desk",     "y": 1006,"label": "Queue custody case for campus desk admin",            "variant": "security" },
    { "from": "desk",     "to": "gateway",  "y": 1048,"label": "Admin verifies & enters 6-digit OTP",                "variant": "security" },
    { "from": "gateway",  "to": "mysql",    "y": 1086,"label": "Verify OTP hash & UPDATE status=reunited",           "variant": "emphasis" },
    { "from": "mysql",    "to": "client",   "y": 1124,"label": "Handover confirmed: Belonging returned!",             "variant": "return"   }
  ],
  "activations": [
    { "participant": "gateway",  "from": 165, "to": 328, "type": "backend"    },
    { "participant": "mysql",    "from": 205, "to": 252, "type": "database"   },
    { "participant": "guard",    "from": 280, "to": 328, "type": "security"   },
    { "participant": "gateway",  "from": 375, "to": 572, "type": "backend"    },
    { "participant": "guard",    "from": 410, "to": 458, "type": "security"   },
    { "participant": "mysql",    "from": 488, "to": 534, "type": "database"   },
    { "participant": "gateway",  "from": 622, "to": 834, "type": "backend"    },
    { "participant": "flask_ai", "from": 624, "to": 794, "type": "backend"    },
    { "participant": "chromadb", "from": 662, "to": 754, "type": "database"   },
    { "participant": "notifier", "from": 876, "to": 926, "type": "messagebus" },
    { "participant": "gateway",  "from": 958, "to": 1050,"type": "backend"    },
    { "participant": "desk",     "from": 1000,"to": 1052,"type": "security"   }
  ],
  "cards": [
    {
      "dot": "emerald",
      "title": "Zero-Trust Campus Scoping",
      "items": [
        "Student credentials enforce strict campus_id boundary scoping throughout the entire session.",
        "Anti-IDOR session guard prevents cross-campus data access at every API endpoint.",
        "EXIF metadata is stripped and MIME types validated at the gateway before writing to persistent storage."
      ]
    },
    {
      "dot": "cyan",
      "title": "Sub-Second CLIP Vector Search",
      "items": [
        "Flask AI microservice generates 512-dimensional CLIP ViT-B/32 multi-modal fused embeddings.",
        "ChromaDB persistent HNSW collections execute approximate nearest-neighbor search in sub-millisecond time.",
        "Heuristic filters validate category alignment and plausible 14-day discovery time windows."
      ]
    },
    {
      "dot": "orange",
      "title": "Cryptographic Handover Security",
      "items": [
        "Matches with similarity >= 75% trigger instant real-time push notifications to claimant and founder.",
        "Campus Desk Admin physically verifies ownership proof and claimant student identity in person.",
        "6-digit HMAC-SHA256 OTP must be validated at the physical desk before status updates to Reunited."
      ]
    }
  ]
}

def main():
    json_path = os.path.join(BASE_DIR, "reunite_internal_sequence.sequence.json")
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(sequence_data, f, indent=2)
    print("Saved:", json_path)

    html_path = os.path.join(ARCHIFY_DIR, "06_END_TO_END_INTERNAL_SEQUENCE.html")
    result = subprocess.run(
        ["node", ARCHIFY_CLI, "render", "sequence", json_path, html_path, "--quality", "standard"],
        capture_output=True, text=True
    )
    print("Render:", result.stdout or result.stderr)
    print("Exit code:", result.returncode)

if __name__ == "__main__":
    main()

