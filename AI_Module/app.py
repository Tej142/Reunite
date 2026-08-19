from analyzers.report_analyzer import analyze_report


def main():

    print("\n===== Smart Lost & Found AI =====\n")

    description = input("Enter lost item description:\n\n").strip()

    if not description:
        print("\n❌ Description cannot be empty.")
        return

    result = analyze_report(description)

    print("\n===== Analysis Result =====\n")
    print(result)


if __name__ == "__main__":
    main()