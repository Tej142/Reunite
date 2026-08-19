from compare.compare import compare_reports 

MATCH_THRESHOLD = 85


def comparing_reports(current_dna, existing_dnas):

    matched_reports = []

    for dna in existing_dnas:

        result=compare_reports(current_dna,dna)
        if result.get("success") is False:
            continue

        match_percentage = result.get("similarity_score",0)

        if match_percentage >= MATCH_THRESHOLD:

            matched_reports.append({

                "report_id": dna["report_id"],

                "match_percentage": match_percentage

            })

    return matched_reports