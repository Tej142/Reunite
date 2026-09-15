import json
import time_log

from google import genai
from google.genai import types

from config import client, GEMINI_MODEL
from utils.json_validator import validate_response
from prompts.report_prompt import build_report_prompt

# ------------------------------------------------------------
# Report Analyzer
# ------------------------------------------------------------
def analyze_report(description: str) -> dict:

    if not description.strip():
        return {
            "success": False,
            "error": "Description cannot be empty."
        }

    try:

        prompt = build_report_prompt(description)

        time_log.start("Report Analysis (Gemini)")
        response = client.models.generate_content(
            model=GEMINI_MODEL,
            contents=prompt,
            config=types.GenerateContentConfig(
                temperature=0,
                response_mime_type="application/json"
            )
        )
        time_log.stop("Report Analysis (Gemini)")

        return validate_response(response.text)

    except json.JSONDecodeError as e:

        return {
            "success": False,
            "error": "Invalid JSON returned by Gemini.",
            "details": str(e)
        }

    except Exception as e:

        return {
            "success": False,
            "error": str(e)
        }