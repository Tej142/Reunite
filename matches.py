MATCH_THRESHOLD = 85


def compare_reports(current_dna, existing_dnas):

    matched_reports = []

    for dna in existing_dnas:

        # Future AI Matching Logic

        match_percentage = 0

        # match_percentage = compare(current_dna, dna)

        if match_percentage >= MATCH_THRESHOLD:

            matched_reports.append({

                "report_id": dna["report_id"],

                "match_percentage": match_percentage

            })

    return matched_reports