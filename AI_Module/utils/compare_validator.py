import json


def validate_compare_response(response_text: str) -> dict:

    try:

        data = json.loads(response_text)

        required_fields = [
            "similarity_score",
            "confidence",
            "matched_features",
            "mismatched_features",
            "reason"
        ]

        for field in required_fields:

            if field not in data:

                return {
                    "success": False,
                    "error": f"Missing required field: {field}"
                }

        return {
            "success":True,
            "similarity_score": float(data["similarity_score"]),
            "confidence": data["confidence"],
            "matched_features": data["matched_features"],
            "mismatched_features": data["mismatched_features"],
            "reason": data["reason"]
        }

    except json.JSONDecodeError:

        return {
            "success": False,
            "error": "Invalid JSON returned by Gemini.",
            "raw_response": response_text
        }

    except Exception as e:

        return {
            "success": False,
            "error": str(e)
        }