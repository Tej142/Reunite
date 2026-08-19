import json
import time_log

from config import mistral_client, MISTRAL_MODEL
from prompts.digital_dna_prompt import build_digital_dna_prompt


def generate_digital_dna(report_data: dict, image_data: dict) -> dict:

    try:

        prompt = build_digital_dna_prompt(
            report_data,
            image_data
        )

        time_log.start("DNA Generation (Mistral)")
        response = mistral_client.chat.complete(
            model=MISTRAL_MODEL,
            messages=[
                {
                    "role": "user",
                    "content": prompt
                }
            ],
            temperature=0
        )
        time_log.stop("DNA Generation (Mistral)")

        result = json.loads(
            response.choices[0].message.content
        )

        return result

    except json.JSONDecodeError:

        return {
            "success": False,
            "same_object": False,
            "error_code": "INVALID_JSON",
            "reason": "Mistral returned invalid JSON."
        }

    except Exception as e:

        return {
            "success": False,
            "same_object": False,
            "error_code": "MISTRAL_ERROR",
            "reason": str(e)
        }