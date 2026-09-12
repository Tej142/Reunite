import json
import time_log

from config import mistral_client, MISTRAL_MODEL, client, GEMINI_MODEL
from prompts.digital_dna_prompt import build_digital_dna_prompt
from google.genai import types


def _clean_json_str(raw: str) -> str:
    s = raw.strip()
    if s.startswith("```json"):
        s = s[7:]
    elif s.startswith("```"):
        s = s[3:]
    if s.endswith("```"):
        s = s[:-3]
    return s.strip()


def _post_process_dna(dna_result: dict, report_data: dict, image_data: dict) -> dict:
    if not isinstance(dna_result, dict):
        return dna_result

    dna = dna_result.get("digital_dna")
    if isinstance(dna, dict):
        # Safeguard location
        if not dna.get("location"):
            reported_loc = report_data.get("location") or image_data.get("location") or ""
            if reported_loc:
                dna["location"] = reported_loc

        # Safeguard Date / Time
        attrs = dna.get("attributes")
        if isinstance(attrs, dict):
            reported_time = report_data.get("attributes", {}).get("Date / Time") or report_data.get("attributes", {}).get("Date/Time")
            if reported_time and not attrs.get("Date / Time") and not attrs.get("Date/Time"):
                attrs["Date / Time"] = reported_time

    return dna_result


def generate_digital_dna(report_data: dict, image_data: dict) -> dict:
    prompt = build_digital_dna_prompt(report_data, image_data)

    # 1. Primary generation via Mistral
    if mistral_client:
        try:
            time_log.start("DNA Generation (Mistral)")
            response = mistral_client.chat.complete(
                model=MISTRAL_MODEL,
                messages=[{"role": "user", "content": prompt}],
                temperature=0
            )
            time_log.stop("DNA Generation (Mistral)")

            clean_text = _clean_json_str(response.choices[0].message.content)
            parsed = json.loads(clean_text)
            return _post_process_dna(parsed, report_data, image_data)
        except Exception as e:
            print(f"[DNA Generator] Mistral call failed: {e}. Falling back to Gemini...")

    # 2. Resilient fallback via Gemini
    try:
        time_log.start("DNA Generation Fallback (Gemini)")
        response = client.models.generate_content(
            model=GEMINI_MODEL,
            contents=prompt,
            config=types.GenerateContentConfig(
                temperature=0,
                response_mime_type="application/json"
            )
        )
        time_log.stop("DNA Generation Fallback (Gemini)")

        clean_text = _clean_json_str(response.text)
        parsed = json.loads(clean_text)
        return _post_process_dna(parsed, report_data, image_data)
    except Exception as e:
        fallback_dna = {
            "success": True,
            "same_object": True,
            "digital_dna": {
                "object_type": image_data.get("object_type") or report_data.get("object_type") or "Item",
                "attributes": {**report_data.get("attributes", {}), **image_data.get("attributes", {})},
                "location": report_data.get("location") or image_data.get("location") or "",
                "visible_features": image_data.get("visible_features", []) or report_data.get("visible_features", []),
                "private_features": report_data.get("private_features", [])
            }
        }
        return _post_process_dna(fallback_dna, report_data, image_data)