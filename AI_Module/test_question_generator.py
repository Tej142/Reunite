from questions.question_engine import get_questions


def main():

    category = "mobile"
    report_type = "lost"

    result = get_questions(
        category,
        report_type
    )

    print("\n===== QUESTION GENERATION TEST =====\n")

    if result["success"]:
        print("SUCCESS ✅")
        print("Source: Generated")

        question_set = result["data"]

        print("\nCategory:", question_set.category)
        print("Report Type:", question_set.report_type)
        print("Version:", question_set.version)

        print("\nQuestions:\n")

        for q in question_set.questions:
            print("ID:", q.id)
            print("Question:", q.question)
            print("Type:", q.type)
            print("Choices:", q.choices)
            print("Required:", q.required)
            print("Private:", q.private)
            print("Importance:", q.importance)
            print("Target:", q.target)
            print("-" * 50)

    else:
        print("FAILED ❌")
        print("Error:", result["error"])


if __name__ == "__main__":
    main()