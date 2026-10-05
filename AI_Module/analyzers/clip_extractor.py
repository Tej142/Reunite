import os
import math
import time
import traceback
import numpy as np
import onnxruntime as ort
from tokenizers import Tokenizer

os.environ["HF_HUB_DISABLE_SYMLINKS_WARNING"] = "1"

LOCAL_TOKENIZER_PATH = os.path.join(os.path.dirname(__file__), "..", "..", "models", "clip", "tokenizer.json")
LOCAL_MODEL_PATH = os.path.join(os.path.dirname(__file__), "..", "..", "models", "clip", "text_model.onnx")
REPO_ID = "Xenova/clip-vit-base-patch32"

session = None
tokenizer = None

def _get_clip_resources():
    global session, tokenizer
    if session is not None and tokenizer is not None:
        return session, tokenizer

    tok_path = None
    model_path = None

    # Check local models directory first
    if os.path.exists(LOCAL_TOKENIZER_PATH) and os.path.exists(LOCAL_MODEL_PATH):
        print(f"[CLIP] Using local ONNX text model from: {LOCAL_MODEL_PATH}")
        tok_path = LOCAL_TOKENIZER_PATH
        model_path = LOCAL_MODEL_PATH
    else:
        try:
            from huggingface_hub import hf_hub_download
            print(f"[CLIP] Loading cached CLIP model from HuggingFace ({REPO_ID})...")
            tok_path = hf_hub_download(repo_id=REPO_ID, filename="tokenizer.json")
            model_path = hf_hub_download(repo_id=REPO_ID, filename="onnx/text_model.onnx")
        except Exception as hf_err:
            print(f"[CLIP] Error retrieving model from HuggingFace Hub: {hf_err}")

    if tok_path and os.path.exists(tok_path):
        try:
            tokenizer = Tokenizer.from_file(tok_path)
            tokenizer.enable_truncation(max_length=77)  # CLIP has a strict 77-token limit
        except Exception as tok_err:
            print(f"[CLIP] Error loading tokenizer: {tok_err}")
            tokenizer = None

    if model_path and os.path.exists(model_path):
        try:
            session = ort.InferenceSession(model_path, providers=['CPUExecutionProvider'])
            print("[CLIP] ONNX Text model loaded successfully on CPU.")
        except Exception as ort_err:
            print(f"[CLIP] Error creating ONNX InferenceSession: {ort_err}")
            session = None

    return session, tokenizer


def analyze_clip_text(text_dna: str) -> dict:
    """
    Takes the Digital DNA text, runs it through ONNX CLIP locally,
    and returns a normalized 512-d vector.
    """
    sess, tok = _get_clip_resources()
    if sess is None or tok is None:
        return {
            "success": False,
            "error": "CLIP ONNX model or tokenizer failed to load."
        }

    try:
        start_time = time.time()
        text_str = str(text_dna or "").strip()
        if not text_str:
            return {
                "success": False,
                "error": "Empty text provided for CLIP extraction."
            }

        # 1. Tokenize the Digital DNA text
        encoded = tok.encode(text_str)

        # 2. Prepare ONNX inputs
        input_ids = np.array([encoded.ids], dtype=np.int64)
        attention_mask = np.array([encoded.attention_mask], dtype=np.int64)

        expected_inputs = [inp.name for inp in sess.get_inputs()]
        inputs = {}
        if "input_ids" in expected_inputs:
            inputs["input_ids"] = input_ids
        if "attention_mask" in expected_inputs:
            inputs["attention_mask"] = attention_mask

        # 3. Run ONNX Inference locally
        outputs = sess.run(None, inputs)

        # 4. Extract pooled text embedding (512-d)
        if len(outputs) > 1:
            text_features = outputs[1][0]
        else:
            text_features = outputs[0][0]

        # 5. L2 Normalization for Cosine Similarity
        text_features_list = [float(x) for x in text_features]
        norm = math.sqrt(sum(x**2 for x in text_features_list))
        vector = [x / norm for x in text_features_list] if norm > 0 else text_features_list

        duration = round(time.time() - start_time, 3)
        return {
            "success": True,
            "status": f"Text vector extracted locally via CLIP in {duration}s",
            "dimensions": len(vector),
            "vector": vector
        }

    except Exception as e:
        return {
            "success": False,
            "error": f"CLIP processing failed: {str(e)}",
            "traceback": traceback.format_exc()
        }