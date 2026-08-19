from flask import Flask, request, jsonify
from flask_cors import CORS

import os
import traceback
from pathlib import Path

from ai_controller import process_report
from matches import compare_reports

app = Flask(__name__)
CORS(app)

current_reports = {}

UPLOAD_FOLDER = Path(__file__).parent / "AI_Module" / "temp_uploads"
UPLOAD_FOLDER.mkdir(parents=True, exist_ok=True)


@app.route("/", methods=["GET"])
def home():
    return "LostConnect AI Server Running"


@app.route("/new-report", methods=["POST"])
def new_report():

    try:

        report_id = f"R{len(current_reports) + 1:04d}"

        description = request.form.get("description", "").strip()
        image = request.files.get("image")

        if not description:
            return jsonify({
                "success": False,
                "message": "Description is required."
            }), 400

        if image is None:
            return jsonify({
                "success": False,
                "message": "Image is required."
            }), 400

        existing = list(UPLOAD_FOLDER.glob("img*.*"))
        image_number = len(existing) + 1

        extension = os.path.splitext(image.filename)[1] or ".jpg"

        image_path = UPLOAD_FOLDER / f"img{image_number}{extension}"

        image.save(str(image_path))

        result = process_report(
            description,
            str(image_path)
        )

        if not result["success"]:
            return jsonify(result), 400

        digital_dna = result["digital_dna"]

        current_reports[report_id] = digital_dna

        if image_path.exists():
            image_path.unlink()

        return jsonify({
            "success": True,
            "report_id": report_id,
            "digital_dna": digital_dna
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

    matches = compare_reports(
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