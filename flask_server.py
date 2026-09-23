from flask import Flask, request, jsonify
from flask_cors import CORS

import os
import sys
import traceback
from pathlib import Path

# Ensure AI_Module directory is in Python module search path
AI_MODULE_DIR = Path(__file__).parent / "AI_Module"
if str(AI_MODULE_DIR) not in sys.path:
    sys.path.insert(0, str(AI_MODULE_DIR))

from ai_controller import process_report
from matches import compare_reports
from questions.question_engine import get_questions
from TALKAI.talkai_prompt import build_talkai_prompt
from TALKAI.live_session import LiveSessionConfig
from TALKAI.dynamic_chat import DynamicTalkAIService
from normalizers.normalizer import normalize_report

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}}, supports_credentials=True)

@app.before_request
def log_incoming_request():
    print(f">> [FLASK RECEIVED] {request.method} {request.path} from {request.remote_addr}")

current_reports = {}

UPLOAD_FOLDER = Path(__file__).parent / "AI_Module" / "temp_uploads"
UPLOAD_FOLDER.mkdir(parents=True, exist_ok=True)



UPLOAD_FOLDER = Path(__file__).parent / "AI_Module" / "temp_uploads"
UPLOAD_FOLDER.mkdir(parents=True, exist_ok=True)


# =========================================================
# HOME
# =========================================================

@app.route("/", methods=["GET"])
def home():
    return "LostConnect AI Server Running"


# =========================================================
# START REPORT (Instant Choice & Talk to AI)
# =========================================================

@app.route("/report/start", methods=["POST"])
def start_report():
    try:
        data = request.get_json()

        if not data:
            return jsonify({
                "success": False,
                "error": "Request body is required."
            }), 400

        category = data.get("category", "")
        report_type = data.get("report_type", "")
        input_mode = data.get("input_mode", "")

        if not isinstance(category, str) or not category.strip():
            return jsonify({
                "success": False,
                "error": "Category is required."
            }), 400

        if not isinstance(report_type, str) or not report_type.strip():
            return jsonify({
                "success": False,
                "error": "Report type is required."
            }), 400

        if not isinstance(input_mode, str) or not input_mode.strip():
            return jsonify({
                "success": False,
                "error": "Input mode is required."
            }), 400

        category = category.strip().lower()
        report_type = report_type.strip().lower()
        input_mode = input_mode.strip().lower()

        allowed_input_modes = {
            "instant_choice",
            "talk_to_ai"
        }

        if input_mode not in allowed_input_modes:
            return jsonify({
                "success": False,
                "error": (
                    "Invalid input_mode. "
                    "Allowed values: instant_choice, talk_to_ai."
                )
            }), 400

        # Run Question Engine
        question_result = get_questions(
            category,
            report_type
        )

        if not question_result.get("success"):
            return jsonify({
                "success": False,
                "error": question_result.get(
                    "error",
                    "Failed to generate questions."
                )
            }), 500

        question_set = question_result["data"]

        # Convert Question objects to JSON
        questions_list = [
            {
                "id": q.id,
                "question": q.question,
                "type": q.type,
                "choices": q.choices,
                "required": q.required,
                "private": q.private,
                "importance": q.importance
            }
            for q in question_set.questions
        ]

        # INSTANT CHOICE
        if input_mode == "instant_choice":
            return jsonify({
                "success": True,
                "input_mode": "instant_choice",
                "category": category,
                "report_type": report_type,
                "version": question_set.version,
                "questions": questions_list
            }), 200

        # TALK TO AI
        if input_mode == "talk_to_ai":
            talkai_prompt = build_talkai_prompt(
                category,
                report_type,
                questions_list
            )

            live_session = LiveSessionConfig()
            token_result = live_session.create_ephemeral_token(
                talkai_prompt
            )

            if not token_result.get("success"):
                return jsonify({
                    "success": False,
                    "error": "Failed to create Live session."
                }), 500

            return jsonify({
                "success": True,
                "input_mode": "talk_to_ai",
                "category": category,
                "report_type": report_type,
                "version": question_set.version,
                "live_session": {
                    "token": token_result["token"],
                    "model": token_result["model"]
                }
            }), 200

    except Exception as e:
        traceback.print_exc()
        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# =========================================================
# TALK TO AI CONVERSATIONAL CHAT & DYNAMIC EXTRACTION
# =========================================================

@app.route("/report/talk_to_ai/chat", methods=["POST"])
def talk_to_ai_chat():
    try:
        data = request.get_json() or {}
        user_message = data.get("message", "").strip()
        history = data.get("history", [])
        report_type = data.get("report_type", "lost").strip().lower()
        current_draft = data.get("current_draft", {})

        if not user_message:
            return jsonify({
                "success": False,
                "error": "Message cannot be empty."
            }), 400

        result = DynamicTalkAIService.handle_turn(
            user_message=user_message,
            history=history,
            report_type=report_type,
            current_draft=current_draft
        )

        return jsonify(result), 200

    except Exception as e:
        traceback.print_exc()
        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# =========================================================
# NORMALIZE REPORT
# =========================================================

@app.route("/report/normalize", methods=["POST"])
def normalize_report_endpoint():
    try:
        data = request.get_json() or {}
        report_data = data.get("report_data")
        source = data.get("source", "description")
        category = data.get("category")
        report_type = data.get("report_type")

        if report_data is None:
            return jsonify({
                "success": False,
                "error": "report_data is required."
            }), 400

        result = normalize_report(
            input_data=report_data,
            source=source,
            category=category,
            report_type=report_type
        )

        return jsonify(result)

    except Exception as e:
        traceback.print_exc()
        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# =========================================================
# NEW REPORT (Multimodal Description & Image Analysis)
# =========================================================

@app.route("/new-report", methods=["POST"])
def new_report():
    try:
        report_id = f"R{len(current_reports) + 1:04d}"
        uploaded_by_file = False
        image_path = None

        where_val = ""
        when_val = ""

        if request.is_json:
            data = request.get_json() or {}
            description = str(data.get("description") or "").strip()
            where_val = str(data.get("where") or data.get("location") or "").strip()
            when_val = str(data.get("when") or data.get("time") or "").strip()
            img_val = data.get("image_path") or data.get("image")
            if img_val:
                candidate = Path(img_val)
                if candidate.is_absolute() and candidate.exists():
                    image_path = candidate
                elif (Path(__file__).parent / candidate).exists():
                    image_path = Path(__file__).parent / candidate
                elif (UPLOAD_FOLDER / candidate).exists():
                    image_path = UPLOAD_FOLDER / candidate
                elif (Path(__file__).parent / "AI_Module" / candidate).exists():
                    image_path = Path(__file__).parent / "AI_Module" / candidate
        else:
            description = request.form.get("description", "").strip()
            where_val = (request.form.get("where") or request.form.get("location") or "").strip()
            when_val = (request.form.get("when") or request.form.get("time") or "").strip()
            image = request.files.get("image")

            if image:
                existing = list(UPLOAD_FOLDER.glob("img*.*"))
                image_number = len(existing) + 1
                extension = os.path.splitext(image.filename)[1] or ".jpg"
                image_path = UPLOAD_FOLDER / f"img{image_number}{extension}"
                image.save(str(image_path))
                uploaded_by_file = True

        if not description:
            return jsonify({
                "success": False,
                "message": "Description is required."
            }), 400

        result = process_report(
            description,
            str(image_path) if image_path else None
        )

        if isinstance(result, dict) and result.get("success") is False:
            return jsonify(result), 400

        digital_dna = result.get("digital_dna", result) if isinstance(result, dict) else result

        # Ensure location is populated if provided in form/meta
        if isinstance(digital_dna, dict):
            if not digital_dna.get("location") and where_val:
                digital_dna["location"] = where_val
            
            # Ensure time/when is captured in attributes if provided
            if when_val and "attributes" in digital_dna and isinstance(digital_dna["attributes"], dict):
                if not digital_dna["attributes"].get("Date / Time") and not digital_dna["attributes"].get("Date/Time"):
                    digital_dna["attributes"]["Date / Time"] = when_val

        # ==================================================
        # [TEMP LOG]
        # ==================================================
        import json
        print("\n" + "="*50)
        print(f"[LOG] GENERATED DIGITAL DNA (Report: {report_id}):")
        print(json.dumps(digital_dna, indent=4))
        print("="*50 + "\n")
        # ==================================================

        current_reports[report_id] = digital_dna

        if uploaded_by_file and image_path and image_path.exists():
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


# =========================================================
# COMPARE REPORT (Matching Engine)
# =========================================================

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


# =========================================================
# MAIN
# =========================================================

if __name__ == "__main__":
    app.run(
        host="0.0.0.0",
        port=5000,
        debug=True
    )