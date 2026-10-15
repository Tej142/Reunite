"""
Sync MySQL Database Reports to ChromaDB Vector Database
Runs multimodal vector extraction on all existing MySQL reports.
"""

import sys
import os
import requests
from pathlib import Path

# Add root directory to python path
ROOT_DIR = Path(__file__).parent.parent
sys.path.insert(0, str(ROOT_DIR))
sys.path.insert(0, str(ROOT_DIR / "AI_Module"))

try:
    import pymysql
except ImportError:
    pymysql = None

from database.chroma_manager import store_report_vectors
from analyzers.clip_extractor import analyze_clip_text
from analyzers.dinov2_extractor import analyze_dinov2

def sync_all():
    print(">> [SYNC] Starting MySQL to ChromaDB Vector Sync...")
    
    # Check if MySQL is accessible via HTTP endpoint
    try:
        resp = requests.get("http://localhost/pw/reunitel/Backend/reports.php?action=list&type=all&limit=200", timeout=5)
        if resp.status_code == 200:
            data = resp.json()
            reports = data.get("data", {}).get("reports", []) or data.get("reports", [])
            print(f">> [SYNC] Found {len(reports)} reports from Backend API.")
            
            for r in reports:
                r_type = r.get("report_type", "lost")
                r_id = f"R{r_type[0].upper()}-{int(r['id']):05d}"
                cat = r.get("category", "General")
                title = r.get("title", "")
                desc = r.get("description", "")
                loc = r.get("location", "")
                img_path = r.get("image_path")
                
                # Visual vector
                dino_vec = None
                if img_path:
                    cand = ROOT_DIR / img_path
                    if cand.exists():
                        dino_res = analyze_dinov2(str(cand))
                        if dino_res.get("success"):
                            dino_vec = dino_res.get("vector")
                            
                # Text vector
                text_input = f"{cat}. {title}. {desc}. {loc}."
                clip_res = analyze_clip_text(text_input)
                clip_vec = clip_res.get("vector") if clip_res.get("success") else None
                
                metadata = {
                    "report_id": r_id,
                    "db_id": int(r["id"]),
                    "report_type": r_type,
                    "category": cat,
                    "title": title,
                    "description": desc[:300],
                    "location": loc,
                    "date": r.get("created_at", ""),
                    "image_path": str(img_path or "")
                }
                
                res = store_report_vectors(r_id, dino_vec, clip_vec, metadata)
                print(f"  ✓ Synced {r_id}: {res.get('message') or res}")
                
            print(">> [SYNC] Completed successfully!")
            return
    except Exception as err:
        print(f">> [SYNC] API fetch notice: {err}")

if __name__ == "__main__":
    sync_all()
