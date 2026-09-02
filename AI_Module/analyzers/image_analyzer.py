import io
import json
import time_log

from PIL import Image
from google import genai
from google.genai import types

from config import client, GEMINI_MODEL
from prompts.image_prompt import build_image_prompt
from utils.json_validator import validate_response

MAX_SIZE = 512
JPEG_QUALITY = 60


def _compress_image(image_path: str) -> bytes:
    """Resize and compress image to reduce size."""
    img = Image.open(image_path)
    img.thumbnail((MAX_SIZE, MAX_SIZE))

    if img.mode in ("RGBA", "P"):
        img = img.convert("RGB")

    buffer = io.BytesIO()
    img.save(buffer, format="JPEG", quality=JPEG_QUALITY)
    return buffer.getvalue()


def analyze_image(image_path: str) -> dict:

    if not image_path:
        return {
            "success": False,
            "error": "Image path cannot be empty."
        }

    try:

        prompt = build_image_prompt()

        time_log.start("Image Compress")
        image_bytes = _compress_image(image_path)
        time_log.stop("Image Compress")

        time_log.start("Image Analysis (Gemini)")
        response = client.models.generate_content(
            model=GEMINI_MODEL,
            contents=[
                types.Part.from_bytes(
                    data=image_bytes,
                    mime_type="image/jpeg"
                ),
                prompt
            ],
            config=types.GenerateContentConfig(
                temperature=0,
                response_mime_type="application/json"
            )
        )
        time_log.stop("Image Analysis (Gemini)")

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