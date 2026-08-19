import json

from google import genai
from google.genai import types

from config import client, GEMINI_MODEL
from prompts.compare_prompt import COMPARE_PROMPT
from utils.compare_validator import validate_compare_response


def compare_reports(current_dna: dict, existing_dna: dict) -> dict:

    if not current_dna:
        return {
            "success": False,
            "error": "Current Digital DNA cannot be empty."
        }

    if not existing_dna:
        return {
            "success": False,
            "error": "Existing Digital DNA cannot be empty."
        }

    try:

        prompt = f"""
{COMPARE_PROMPT}

Current Digital DNA:
{json.dumps(current_dna, indent=4)}

Existing Digital DNA:
{json.dumps(existing_dna, indent=4)}
"""

        response = client.models.generate_content(
            model=GEMINI_MODEL,
            contents=prompt,
            config=types.GenerateContentConfig(
                temperature=0,
                response_mime_type="application/json"
            )
        )

        return validate_compare_response(response.text)

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