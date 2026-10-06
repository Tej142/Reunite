"""
Reunite Multimodal AI Flask Microservice
Core Neural Vision & NLP Engine for Lost & Found Matching
"""

from flask import Flask, request, jsonify
from flask_cors import CORS

import os
import sys
import traceback
from pathlib import Path
from dotenv import load_dotenv

# Load DB & linking credentials from .env.example, then overlay API keys from .env
ROOT_DIR = Path(__file__).resolve().parent
ROOT_EXAMPLE_PATH = ROOT_DIR / ".env.example"
ROOT_ENV_PATH = ROOT_DIR / ".env"

if ROOT_EXAMPLE_PATH.exists():
    load_dotenv(dotenv_path=ROOT_EXAMPLE_PATH, override=False)
if ROOT_ENV_PATH.exists():
    load_dotenv(dotenv_path=ROOT_ENV_PATH, override=True)

# Ensure AI_Module directory is in Python module search path
AI_MODULE_DIR = Path(__file__).resolve().parent / "AI_Module"
if str(AI_MODULE_DIR) not in sys.path:
    sys.path.insert(0, str(AI_MODULE_DIR))

from ai_controller import process_report
from matches import compare_reports
from questions.question_engine import get_questions
from TALKAI.talkai_prompt import build_talkai_prompt
from TALKAI.live_session import LiveSessionConfig
from TALKAI.dynamic_chat import DynamicTalkAIService
from normalizers.normalizer import normalize_report
from AI_Module.utils.time_parser import parse_temporal_expression
from analyzers.image_analyzer import analyze_image

import uuid
import time
from PIL import Image, ImageOps
import pymysql

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}}, supports_credentials=True)

def db_log_event(action: str, details: str = "", user_id: int = None, ip: str = None):
    """
    Directly writes AI Engine activity & system events into MySQL `access_logs` table.
    """
    try:
        db_host = os.getenv("DB_HOST", "localhost")
        db_user = os.getenv("DB_USER", "root")
        db_pass = os.getenv("DB_PASS", "")
        db_name = os.getenv("DB_NAME", "lost_connect_db")
        db_port = int(os.getenv("DB_PORT", 3306))

        conn = pymysql.connect(
            host=db_host,
            user=db_user,
            password=db_pass,
            database=db_name,
            port=db_port,
            autocommit=True,
            connect_timeout=2
        )
        with conn.cursor() as cursor:
            sql = "INSERT INTO access_logs (user_id, action, ip_address, device_info) VALUES (%s, %s, %s, %s)"
            cursor.execute(sql, (user_id, action, ip or "127.0.0.1", (details[:255] if details else "AI Engine (Flask)")))
        conn.close()
    except Exception as err:
        pass

@app.before_request
def log_incoming_request():
    print(f">> [FLASK RECEIVED] {request.method} {request.path} from {request.remote_addr}")

current_reports = {}

VAULT_DIR = Path(__file__).parent / "media_vault"
(VAULT_DIR / "lost_reports").mkdir(parents=True, exist_ok=True)
(VAULT_DIR / "found_reports").mkdir(parents=True, exist_ok=True)

# Temporary upload staging directory (used by /new-report for image analysis)
UPLOAD_FOLDER = VAULT_DIR / "uploads"
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

ALLOWED_IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}
MAX_FILE_SIZE_BYTES = 8 * 1024 * 1024  # 8 MB

def save_and_strip_exif_image(file_storage, report_type: str = "lost"):
    """
    Validates file extension and size, strips all EXIF metadata using PIL,
    saves into media_vault/{lost_reports|found_reports}/, and returns
    (web_relative_path, abs_system_path).
    """
    orig_name = file_storage.filename or "upload.jpg"
    ext = Path(orig_name).suffix.lower()
    if ext not in ALLOWED_IMAGE_EXTENSIONS:
        raise ValueError(f"Unsupported file format '{ext}'. Allowed: JPG, PNG, WebP.")

    subfolder = "found_reports" if report_type.lower() == "found" else "lost_reports"
    target_dir = VAULT_DIR / subfolder
    target_dir.mkdir(parents=True, exist_ok=True)

    safe_filename = f"upload_{int(time.time())}_{uuid.uuid4().hex[:8]}{ext}"
    target_abs = target_dir / safe_filename
    web_rel = f"media_vault/{subfolder}/{safe_filename}"

    img = Image.open(file_storage.stream)
    try:
        img = ImageOps.exif_transpose(img)
    except Exception:
        pass

    # Save cleanly without EXIF metadata
    if ext == ".png" and img.mode in ("RGBA", "LA"):
        img.save(target_abs, format="PNG")
    elif ext == ".webp":
        if img.mode not in ("RGB", "RGBA"):
            img = img.convert("RGBA" if "A" in img.mode else "RGB")
        img.save(target_abs, format="WEBP", quality=90)
    else:
        if img.mode != "RGB":
            img = img.convert("RGB")
        img.save(target_abs, format="JPEG", quality=88)

    return web_rel, str(target_abs)


@app.route("/report/talk_to_ai/upload-image", methods=["POST"])
def talk_to_ai_upload_image():
    """
    Direct image upload & vision analysis endpoint for Talk to AI copilot.
    Validates file, strips EXIF metadata, saves to media_vault, and extracts visual attributes.
    """
    try:
        report_type = request.form.get("report_type", "lost").strip().lower()
        file = request.files.get("image")
        if not file or not file.filename:
            return jsonify({"success": False, "error": "No image file provided."}), 400

        file.seek(0, os.SEEK_END)
        size = file.tell()
        file.seek(0)
        if size > MAX_FILE_SIZE_BYTES:
            return jsonify({"success": False, "error": "Image exceeds 8MB maximum size limit."}), 400

        web_rel, abs_path = save_and_strip_exif_image(file, report_type)

        analysis_result = analyze_image(abs_path)
        if not analysis_result or analysis_result.get("success") is False:
            analysis_result = {
                "object_type": "",
                "attributes": {},
                "visible_features": [],
                "location": ""
            }

        return jsonify({
            "success": True,
            "image_url": web_rel,
            "image_analysis": analysis_result
        }), 200

    except ValueError as ve:
        return jsonify({"success": False, "error": str(ve)}), 400
    except Exception as e:
        traceback.print_exc()
        return jsonify({"success": False, "error": str(e)}), 500


@app.route("/report/talk_to_ai/chat", methods=["POST"])
def talk_to_ai_chat():
    """
    Multi-turn conversational chat & dynamic intake endpoint.
    Supports both JSON payloads (text) and multipart/form-data (text + image attachments).
    """
    try:
        user_message = ""
        history = []
        report_type = "lost"
        current_draft = {}
        image_analysis = None
        image_urls = []

        if request.is_json:
            data = request.get_json() or {}
            user_message = data.get("message", "").strip()
            history = data.get("history", [])
            report_type = data.get("report_type", "lost").strip().lower()
            current_draft = data.get("current_draft", {})
            image_analysis = data.get("image_analysis")
            image_urls = data.get("image_urls", [])
        else:
            # Handle multipart/form-data
            user_message = request.form.get("message", "").strip()
            report_type = request.form.get("report_type", "lost").strip().lower()
            try:
                history = json.loads(request.form.get("history") or "[]")
            except Exception:
                history = []
            try:
                current_draft = json.loads(request.form.get("current_draft") or "{}")
            except Exception:
                current_draft = {}

            # Handle existing image URLs passed in form
            raw_img_urls = request.form.get("image_urls")
            if raw_img_urls:
                try:
                    image_urls = json.loads(raw_img_urls)
                except Exception:
                    pass

            raw_analysis = request.form.get("image_analysis")
            if raw_analysis:
                try:
                    image_analysis = json.loads(raw_analysis)
                except Exception:
                    pass

            # Handle uploaded image files if attached directly
            files = request.files.getlist("image") or ([request.files.get("image")] if request.files.get("image") else [])
            for file in files:
                if file and file.filename:
                    file.seek(0, os.SEEK_END)
                    size = file.tell()
                    file.seek(0)
                    if size > MAX_FILE_SIZE_BYTES:
                        return jsonify({"success": False, "error": "Attached photo exceeds 8MB maximum size limit."}), 400

                    web_rel, abs_path = save_and_strip_exif_image(file, report_type)
                    image_urls.append(web_rel)

                    try:
                        analysis_res = analyze_image(abs_path)
                        if analysis_res and analysis_res.get("success") is not False:
                            if not image_analysis:
                                image_analysis = analysis_res
                            else:
                                for k, v in analysis_res.get("attributes", {}).items():
                                    image_analysis.setdefault("attributes", {})[k] = v
                                if analysis_res.get("visible_features"):
                                    image_analysis.setdefault("visible_features", []).extend(analysis_res["visible_features"])
                    except Exception as img_err:
                        print(f"[TalkToAI] Image analysis error notice: {img_err}")

        # If user uploaded photo without text, provide helpful default text
        if not user_message and (image_urls or image_analysis):
            user_message = "I've shared a photo of the item."

        if not user_message and not image_urls:
            return jsonify({
                "success": False,
                "error": "Message or photo cannot be empty."
            }), 400

        result = DynamicTalkAIService.handle_turn(
            user_message=user_message,
            history=history,
            report_type=report_type,
            current_draft=current_draft,
            image_analysis=image_analysis,
            image_urls=image_urls
        )

        return jsonify(result), 200

    except ValueError as ve:
        return jsonify({"success": False, "error": str(ve)}), 400
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

        # Resolve Temporal Details (e.g. "yesterday", "today morning", "2 days ago")
        temporal_input = f"{when_val} {description}".strip()
        time_info = parse_temporal_expression(temporal_input)

        # Ensure location and time are populated in digital DNA
        if isinstance(digital_dna, dict):
            if not digital_dna.get("location") and where_val:
                digital_dna["location"] = where_val

            if not digital_dna.get("resolved_date") and time_info.get("resolved_date"):
                digital_dna["resolved_date"] = time_info["resolved_date"]
            if not digital_dna.get("resolved_time") and time_info.get("resolved_time"):
                digital_dna["resolved_time"] = time_info["resolved_time"]
            digital_dna["time_display"] = time_info.get("formatted_display", "")

            if "attributes" in digital_dna and isinstance(digital_dna["attributes"], dict):
                if not digital_dna["attributes"].get("Date / Time") or time_info.get("is_relative"):
                    digital_dna["attributes"]["Date / Time"] = time_info.get("formatted_display", when_val or "Today")

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

        db_log_event("AI Report Synthesized", f"Report: {report_id}, Category: {digital_dna.get('object_type', 'General')}, Date: {digital_dna.get('resolved_date')}", ip=request.remote_addr)

        return jsonify({
            "success": True,
            "report_id": report_id,
            "resolved_date": digital_dna.get("resolved_date") or time_info["resolved_date"],
            "resolved_time": digital_dna.get("resolved_time") or time_info.get("resolved_time", ""),
            "time_display": time_info.get("formatted_display", ""),
            "digital_dna": digital_dna
        })

    except Exception as e:
        traceback.print_exc()
        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# =========================================================
# CHROMADB VECTOR DATABASE & LATE FUSION MATCHMAKING
# =========================================================

try:
    from AI_Module.database.chroma_manager import (
        store_report_vectors, search_vectors, calculate_dynamic_weights,
        dinov2_collection, clip_collection
    )
    from AI_Module.analyzers.clip_extractor import analyze_clip_text
    from AI_Module.analyzers.dinov2_extractor import analyze_dinov2
    CHROMA_AVAILABLE = True
except Exception as chroma_import_err:
    print(f"[WARN] ChromaDB or extractor imports notice: {chroma_import_err}")
    CHROMA_AVAILABLE = False


@app.route("/report/embed-and-store", methods=["POST"])
def embed_and_store_report():
    """
    Extracts multimodal vector embeddings (DINOv2 for image + CLIP for text)
    and stores them into ChromaDB vector collections.
    """
    try:
        data = request.get_json() or {}
        report_id = data.get("report_id") or f"R{len(current_reports) + 1:05d}"
        report_type = str(data.get("report_type") or "lost").lower()
        category = str(data.get("category") or "General")
        title = str(data.get("title") or "")
        description = str(data.get("description") or "")
        location = str(data.get("location") or "")
        report_date = str(data.get("date") or "")
        image_path_val = data.get("image_path")
        digital_dna = data.get("digital_dna") or {}

        # 1. Extract Visual Vector (DINOv2 - 384 dimensions)
        dinov2_vector = None
        if image_path_val:
            cand = Path(image_path_val)
            actual_img_path = None
            if cand.is_absolute() and cand.exists():
                actual_img_path = str(cand)
            elif (Path(__file__).parent / cand).exists():
                actual_img_path = str(Path(__file__).parent / cand)
            elif (Path(__file__).parent / "AI_Module" / cand).exists():
                actual_img_path = str(Path(__file__).parent / "AI_Module" / cand)

            if actual_img_path and CHROMA_AVAILABLE:
                dino_res = analyze_dinov2(actual_img_path)
                if dino_res.get("success"):
                    dinov2_vector = dino_res.get("vector")

        # 2. Extract Text Vector (CLIP - 512 dimensions)
        clip_vector = None
        text_for_embedding = f"{category}. {title}. {description}. {location}."
        if isinstance(digital_dna, dict):
            dna_features = digital_dna.get("features") or digital_dna.get("attributes") or {}
            text_for_embedding += f" {str(dna_features)}"

        if CHROMA_AVAILABLE:
            clip_res = analyze_clip_text(text_for_embedding)
            if clip_res.get("success"):
                clip_vector = clip_res.get("vector")

        # 3. Store into ChromaDB
        metadata = {
            "report_id": report_id,
            "db_id": int(data.get("db_id") or 0),
            "report_type": report_type,
            "category": category,
            "title": title,
            "description": description[:300],
            "location": location,
            "date": report_date,
            "image_path": str(image_path_val or "")
        }

        store_res = {"success": True, "message": "Vectors generated (Mock mode)"}
        if CHROMA_AVAILABLE and (dinov2_vector or clip_vector):
            store_res = store_report_vectors(
                report_id=report_id,
                dinov2_vector=dinov2_vector,
                clip_vector=clip_vector,
                metadata=metadata
            )

        db_log_event("ChromaDB Vectors Stored", f"Report ID: {report_id} ({report_type}), Vectors: DINOv2={dinov2_vector is not None}, CLIP={clip_vector is not None}", ip=request.remote_addr)

        return jsonify({
            "success": True,
            "report_id": report_id,
            "dinov2_extracted": dinov2_vector is not None,
            "clip_extracted": clip_vector is not None,
            "chroma_result": store_res
        }), 200

    except Exception as e:
        traceback.print_exc()
        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


@app.route("/report/search-matches", methods=["POST"])
def search_matches_vector():
    """
    Performs AI Vector Similarity Search using Late Fusion (DINOv2 Visual + CLIP Text)
    with dynamic 180-day time decay weighting.
    """
    try:
        data = request.get_json() or {}
        query_text = str(data.get("text") or data.get("description") or "").strip()
        source_report_id = data.get("report_id")
        target_report_type = data.get("report_type")  # 'lost' or 'found'
        image_path_val = data.get("image_path")
        photo_date = data.get("photo_date")
        found_date = data.get("found_date")
        top_k = int(data.get("top_k") or 10)

        dinov2_vector = None
        clip_vector = None

        if image_path_val and CHROMA_AVAILABLE:
            cand = Path(image_path_val)
            actual_img_path = None
            if cand.is_absolute() and cand.exists():
                actual_img_path = str(cand)
            elif (Path(__file__).parent / cand).exists():
                actual_img_path = str(Path(__file__).parent / cand)

            if actual_img_path:
                dino_res = analyze_dinov2(actual_img_path)
                if dino_res.get("success"):
                    dinov2_vector = dino_res.get("vector")

        if query_text and CHROMA_AVAILABLE:
            clip_res = analyze_clip_text(query_text)
            if clip_res.get("success"):
                clip_vector = clip_res.get("vector")

        matches = []
        if CHROMA_AVAILABLE and (dinov2_vector or clip_vector):
            matches = search_vectors(
                dinov2_vector=dinov2_vector,
                clip_vector=clip_vector,
                photo_date_str=photo_date,
                found_date_str=found_date,
                top_k=top_k
            )

        # Filter by report type if target requested
        if target_report_type:
            target_norm = target_report_type.strip().lower()
            matches = [m for m in matches if str(m.get("metadata", {}).get("report_type", "")).lower() == target_norm]

        # Format match results
        formatted_matches = []
        for m in matches:
            formatted_matches.append({
                "report_id": m.get("report_id"),
                "match_percentage": m.get("match_score"),
                "metadata": m.get("metadata"),
                "breakdown": {
                    "visual_score": m.get("breakdown", {}).get("dino_score_raw", 0),
                    "text_score": m.get("breakdown", {}).get("clip_score_raw", 0),
                    "image_weight": m.get("breakdown", {}).get("applied_image_weight", "35%"),
                    "text_weight": m.get("breakdown", {}).get("applied_text_weight", "65%")
                }
            })

        db_log_event("Vector Similarity Search", f"Query: {query_text[:50]}, Target: {target_report_type or 'all'}, Matches: {len(formatted_matches)}", ip=request.remote_addr)

        return jsonify({
            "success": True,
            "query": query_text,
            "total_matches": len(formatted_matches),
            "matches": formatted_matches
        }), 200

    except Exception as e:
        traceback.print_exc()
        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# =========================================================
# SYSTEM ADMIN & DIAGNOSTICS ENDPOINTS
# =========================================================

@app.route("/status", methods=["GET"])
def server_status():
    dino_count = 0
    clip_count = 0
    if CHROMA_AVAILABLE:
        try:
            dino_count = dinov2_collection.count()
            clip_count = clip_collection.count()
        except Exception:
            pass

    return jsonify({
        "success": True,
        "status": "online",
        "service": "Reunite AI Multi-Modal Engine",
        "version": "2.4.0",
        "chroma_available": CHROMA_AVAILABLE,
        "collections": {
            "dinov2_reports": dino_count,
            "clip_reports": clip_count
        }
    }), 200


@app.route("/report/chroma-stats", methods=["GET"])
def get_chroma_stats():
    try:
        dino_count = 0
        clip_count = 0
        if CHROMA_AVAILABLE:
            dino_count = dinov2_collection.count()
            clip_count = clip_collection.count()

        return jsonify({
            "success": True,
            "chroma_available": CHROMA_AVAILABLE,
            "dinov2_count": dino_count,
            "clip_count": clip_count,
            "total_vectors": dino_count + clip_count
        }), 200
    except Exception as e:
        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


@app.route("/report/delete-vector/<report_id>", methods=["DELETE", "POST"])
def delete_report_vector(report_id):
    try:
        if not CHROMA_AVAILABLE:
            return jsonify({"success": False, "error": "ChromaDB not initialized"}), 503

        deleted_dino = False
        deleted_clip = False

        try:
            dinov2_collection.delete(ids=[report_id])
            deleted_dino = True
        except Exception:
            pass

        try:
            clip_collection.delete(ids=[report_id])
            deleted_clip = True
        except Exception:
            pass

        return jsonify({
            "success": True,
            "report_id": report_id,
            "deleted_dinov2": deleted_dino,
            "deleted_clip": deleted_clip
        }), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.route("/report/purge-all-vectors", methods=["POST"])
def purge_all_vectors():
    try:
        if not CHROMA_AVAILABLE:
            return jsonify({"success": False, "error": "ChromaDB not initialized"}), 503

        # Clear items in collections
        d_count = dinov2_collection.count()
        c_count = clip_collection.count()

        if d_count > 0:
            d_ids = dinov2_collection.get()["ids"]
            if d_ids:
                dinov2_collection.delete(ids=d_ids)

        if c_count > 0:
            c_ids = clip_collection.get()["ids"]
            if c_ids:
                clip_collection.delete(ids=c_ids)

        db_log_event("ChromaDB Vectors Purged", f"Purged DINOv2: {d_count}, CLIP: {c_count}", ip=request.remote_addr)

        return jsonify({
            "success": True,
            "message": "All vector embeddings purged successfully.",
            "purged_dinov2": d_count,
            "purged_clip": c_count
        }), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


# =========================================================
# MAIN
# =========================================================

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    debug = os.environ.get("FLASK_DEBUG", "false").lower() == "true"
    app.run(
        host="0.0.0.0",
        port=port,
        debug=debug
    )