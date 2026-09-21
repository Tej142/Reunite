from question_engine.question_generator import generate_questions
from question_engine.question_repository import (
    get_saved_questions,
    save_question_set
)


def get_questions(category: str, report_type: str) -> dict:
    """
    Main controller of the Advanced Question Engine.

    Flow:
    1. Validate category and report type.
    2. Check database for an existing question set.
    3. If found, reuse it.
    4. If not found, generate questions using AI.
    5. Save the generated question set.
    6. Return the question set.
    """

    # --------------------------------------------------------
    # 1. Validate input
    # --------------------------------------------------------

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

        # ----------------------------------------------------
        # 2. Check database for saved questions
        # ----------------------------------------------------

        saved_questions = get_saved_questions(
            category,
            report_type
        )

        # ----------------------------------------------------
        # 3. Reuse existing question set
        # ----------------------------------------------------

        if saved_questions is not None:

            return {
                "success": True,
                "source": "saved",
                "data": saved_questions
            }

        # ----------------------------------------------------
        # 4. Generate new questions using AI
        # ----------------------------------------------------

        result = generate_questions(
            category,
            report_type
        )

        if not result.get("success"):
            return result

        question_set = result["data"]

        # ----------------------------------------------------
        # 5. Save generated question set
        # ----------------------------------------------------

        save_question_set(
            category,
            report_type,
            question_set
        )

        # ----------------------------------------------------
        # 6. Return generated question set
        # ----------------------------------------------------

        return {
            "success": True,
            "source": "generated",
            "data": question_set
        }

    except Exception as e:

        return {
            "success": False,
            "error": str(e)
        }