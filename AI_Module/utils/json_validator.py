import json


def validate_response(response_text: str) -> dict:

    try:

        data = json.loads(response_text)

        return {
            "object_type": data.get("object_type", ""),
            "attributes": data.get("attributes", {}),
            "location": data.get("location", ""),
            "visible_features": data.get("visible_features", []),
            "private_features": data.get("private_features", [])
        }

    except json.JSONDecodeError:

        return {
            "success": False,
            "error": "Invalid JSON returned by AI.",
            "raw_response": response_text
        }

    except Exception as e:

        return {
            "success": False,
            "error": str(e)
        }