from questions.question_generator import generate_questions


def get_questions(category: str, report_type: str) -> dict:

    if not isinstance(category, str) or not category.strip():
        return {
            "success": False,
            "error": "Category cannot be empty."
        }

    if not isinstance(report_type, str) or not report_type.strip():
        return {
            "success": False,
            "error": "Report type cannot be empty."
        }

    category = category.strip().lower()
    report_type = report_type.strip().lower()

    try:
        return generate_questions(category, report_type)

    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }