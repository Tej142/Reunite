import sys
from pathlib import Path

# Ensure AI_Module directory is in Python module search path
AI_MODULE_DIR = Path(__file__).parent / "AI_Module"
if str(AI_MODULE_DIR) not in sys.path:
    sys.path.insert(0, str(AI_MODULE_DIR))

from compare.compare import compare_reports as ai_compare_reports

MATCH_THRESHOLD = 85


def compare_reports(current_dna, existing_dnas):

    matched_reports = []

    for dna in existing_dnas:

        result = ai_compare_reports(current_dna, dna)

        if not result or result.get("success") is False:
            continue

        match_percentage = result.get("similarity_score", 0)

        if match_percentage >= MATCH_THRESHOLD:

            matched_reports.append({
                "report_id": dna.get("report_id"),
                "match_percentage": match_percentage
            })

    return matched_reports