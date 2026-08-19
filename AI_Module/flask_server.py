from flask import Flask, request, jsonify
from flask_cors import CORS

import traceback

from ai_controller import process_report
from matches import comparing_reports

app = Flask(__name__)
CORS(app)

current_reports = {}


@app.route("/", methods=["GET"])
def home():
    return "LostConnect AI Server Running"


@app.route("/new-report", methods=["POST"])
def new_report():

    try:

        report_id = f"R{len(current_reports) + 1:04d}"

        data = request.get_json()
        description = data.get("description", "").strip()
        image_path = data.get("image_path", "").strip() or None

        if not description:
            return jsonify({
                "success": False,
                "message": "Description is required."
            }), 400

        digital_dna = process_report(
            description,
            image_path
        )

        current_reports[report_id] = digital_dna


        return jsonify({
            "success": True,
            "report_id": report_id,
             **digital_dna
        })

    except Exception as e:

        traceback.print_exc()

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


@app.route("/compare-report", methods=["POST"])
def compare_report():

    data = request.get_json()

    report_id = data.get("report_id")
    existing_dnas = data.get("digital_dnas")

    if report_id not in current_reports:
        return jsonify({
            "success": False,
            "message": "Current Digital DNA Not Found"
        }), 404

    current_dna = current_reports[report_id]

    matches = comparing_reports(
        current_dna,
        existing_dnas
    )

    del current_reports[report_id]

    return jsonify({
        "success": True,
        "report_id": report_id,
        "matches": matches
    })


if __name__ == "__main__":
    app.run(
        host="0.0.0.0",
        port=5000,
        debug=True
    )