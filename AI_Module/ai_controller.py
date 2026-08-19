from concurrent.futures import ThreadPoolExecutor
import time_log

from analyzers.report_analyzer import analyze_report
from analyzers.image_analyzer import analyze_image
from analyzers.digital_dna_generator import generate_digital_dna


def process_report(description: str, image: str = None) -> dict:

    # If no image provided, run only description analysis
    if not image:
        report_result = analyze_report(description)

        if report_result.get("success") is False:
            time_log.print_logs()
            return report_result

        # Wrap report analysis as the digital DNA directly
        result = {
            "success": True,
            "same_object": True,
            "digital_dna": report_result.get("digital_dna", report_result)
        }
        time_log.print_logs()
        return result

    # Run description and image analysis in parallel using threads
    with ThreadPoolExecutor(max_workers=2) as executor:
        future_report = executor.submit(analyze_report, description)
        future_image = executor.submit(analyze_image, image)

        report_result = future_report.result()
        image_result = future_image.result()

    if report_result.get("success") is False:
        time_log.print_logs()
        return report_result

    if image_result.get("success") is False:
        time_log.print_logs()
        return image_result

    digital_dna = generate_digital_dna(
        report_result,
        image_result
    )

    time_log.print_logs()
    return digital_dna