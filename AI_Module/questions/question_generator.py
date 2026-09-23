import json
import time_log

from google.genai import types

from config import client, GEMINI_MODEL
from questions.question_prompt import build_question_prompt
from questions.question_validator import validate_question_response


def generate_questions(category: str, report_type: str) -> dict:

    if not category.strip():
        return {
            "success": False,
            "error": "Category cannot be empty."
        }

    if not report_type.strip():
        return {
            "success": False,
            "error": "Report type cannot be empty."
        }

    try:

        prompt = build_question_prompt(
            category,
            report_type
        )

        time_log.start("Question Generation (Gemini)")

        response = client.models.generate_content(
            model=GEMINI_MODEL,
            contents=prompt,
            config=types.GenerateContentConfig(
                temperature=0,
                response_mime_type="application/json"
            )
        )

        time_log.stop("Question Generation (Gemini)")

        return validate_question_response(response.text)

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