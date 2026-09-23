import json

from questions.question_schema import (
    Question,
    QuestionSet,
)


ALLOWED_QUESTION_TYPES = {
    "text",
    "choice",
    "dropdown",
    "yes_no",
}

ALLOWED_IMPORTANCE_LEVELS = {
    "highest",
    "high",
    "medium",
    "low",
}


def validate_question_response(response_text: str) -> dict:

    try:
        data = json.loads(response_text)

    except json.JSONDecodeError as e:
        return {
            "success": False,
            "error": "Invalid JSON returned by Gemini.",
            "details": str(e),
        }

    if not isinstance(data, dict):
        return {
            "success": False,
            "error": "Question response must be a JSON object.",
        }

    required_set_fields = [
        "category",
        "report_type",
        "version",
        "questions",
    ]

    for field in required_set_fields:
        if field not in data:
            return {
                "success": False,
                "error": f"Missing required field: {field}",
            }

    if not isinstance(data["category"], str):
        return {
            "success": False,
            "error": "Category must be a string.",
        }

    if not isinstance(data["report_type"], str):
        return {
            "success": False,
            "error": "Report type must be a string.",
        }

    if not isinstance(data["version"], int):
        return {
            "success": False,
            "error": "Version must be an integer.",
        }

    if not isinstance(data["questions"], list):
        return {
            "success": False,
            "error": "Questions must be a list.",
        }

    validated_questions = []

    for index, question_data in enumerate(data["questions"]):

        if not isinstance(question_data, dict):
            return {
                "success": False,
                "error": f"Question at index {index} must be an object.",
            }

        required_question_fields = [
            "id",
            "question",
            "type",
            "choices",
            "required",
            "private",
            "importance",
        ]

        for field in required_question_fields:
            if field not in question_data:
                return {
                    "success": False,
                    "error": (
                        f"Question at index {index} "
                        f"is missing required field: {field}"
                    ),
                }

        if question_data["type"] not in ALLOWED_QUESTION_TYPES:
            return {
                "success": False,
                "error": (
                    f"Invalid question type: "
                    f"{question_data['type']}"
                ),
            }

        if question_data["importance"] not in ALLOWED_IMPORTANCE_LEVELS:
            return {
                "success": False,
                "error": (
                    f"Invalid importance level: "
                    f"{question_data['importance']}"
                ),
            }

        if not isinstance(question_data["id"], str):
            return {
                "success": False,
                "error": f"Question ID at index {index} must be a string.",
            }

        if not isinstance(question_data["question"], str):
            return {
                "success": False,
                "error": (
                    f"Question text at index {index} "
                    f"must be a string."
                ),
            }

        if not isinstance(question_data["choices"], list):
            return {
                "success": False,
                "error": (
                    f"Choices at index {index} "
                    f"must be a list."
                ),
            }

        if not isinstance(question_data["required"], bool):
            return {
                "success": False,
                "error": (
                    f"'required' at index {index} "
                    f"must be a boolean."
                ),
            }

        if not isinstance(question_data["private"], bool):
            return {
                "success": False,
                "error": (
                    f"'private' at index {index} "
                    f"must be a boolean."
                ),
            }

        validated_questions.append(
            Question(
                id=question_data["id"],
                question=question_data["question"],
                type=question_data["type"],
                choices=question_data["choices"],
                required=question_data["required"],
                private=question_data["private"],
                importance=question_data["importance"],
            )
        )

    question_set = QuestionSet(
        category=data["category"],
        report_type=data["report_type"],
        version=data["version"],
        questions=validated_questions,
    )

    return {
        "success": True,
        "data": question_set,
    }