import os
import math
import time
import traceback
import numpy as np
from PIL import Image
import onnxruntime as ort

os.environ["HF_HUB_DISABLE_SYMLINKS_WARNING"] = "1"

# 1. Resolve Model Path: Check local directory first, then fallback to cached HuggingFace model
LOCAL_MODEL_PATH = os.path.join(os.path.dirname(__file__), "..", "..", "models", "model.onnx")
REPO_ID = "Xenova/dinov2-small"
FILENAME = "onnx/model.onnx"

session = None

def _get_dinov2_session():
    global session
    if session is not None:
        return session

    model_path = None
    if os.path.exists(LOCAL_MODEL_PATH):
        print(f"[DINOv2] Using local ONNX model from: {LOCAL_MODEL_PATH}")
        model_path = LOCAL_MODEL_PATH
    else:
        try:
            from huggingface_hub import hf_hub_download
            print(f"[DINOv2] Loading cached ONNX model from HuggingFace ({REPO_ID})...")
            model_path = hf_hub_download(repo_id=REPO_ID, filename=FILENAME)
        except Exception as hf_err:
            print(f"[DINOv2] Error retrieving model from HuggingFace Hub: {hf_err}")

    if model_path and os.path.exists(model_path):
        try:
            # Force CPUExecutionProvider for local execution
            session = ort.InferenceSession(model_path, providers=['CPUExecutionProvider'])
            print("[DINOv2] ONNX model loaded successfully on CPU.")
        except Exception as ort_err:
            print(f"[DINOv2] Error creating ONNX InferenceSession: {ort_err}")
            session = None
    else:
        print("[DINOv2] Model path could not be resolved.")

    return session


def preprocess_image(image_path: str) -> np.ndarray:
    """Manually prepares the image for DINOv2 (bypassing PyTorch/Transformers)."""
    image = Image.open(image_path).convert("RGB")

    # 1. Resize shorter edge to 256
    w, h = image.size
    if w < h:
        new_w, new_h = 256, int(256 * h / w)
    else:
        new_w, new_h = int(256 * w / h), 256
    image = image.resize((new_w, new_h), Image.Resampling.BICUBIC)

    # 2. Center crop to exactly 224x224
    left = (new_w - 224) / 2
    top = (new_h - 224) / 2
    right = (new_w + 224) / 2
    bottom = (new_h + 224) / 2
    image = image.crop((left, top, right, bottom))

    # 3. Convert to numpy float32 array and scale colors to [0, 1]
    img_array = np.array(image, dtype=np.float32) / 255.0

    # 4. Normalize with standard ImageNet mean and std distribution
    mean = np.array([0.485, 0.456, 0.406], dtype=np.float32)
    std = np.array([0.229, 0.224, 0.225], dtype=np.float32)
    img_array = (img_array - mean) / std

    # 5. Transpose to Channels-First format: (Height, Width, Channels) -> (Channels, Height, Width)
    img_array = np.transpose(img_array, (2, 0, 1))

    # 6. Add batch dimension: (CHW) -> (1, C, H, W)
    img_array = np.expand_dims(img_array, axis=0)

    return img_array


def analyze_dinov2(image_path: str) -> dict:
    """
    Takes an image path, runs it through ONNX DINOv2 locally, and returns a normalized 384-d vector.
    """
    sess = _get_dinov2_session()
    if sess is None:
        return {
            "success": False,
            "error": "DINOv2 ONNX model failed to load."
        }

    try:
        start_time = time.time()

        if not os.path.exists(image_path):
            return {
                "success": False,
                "error": f"Image file not found: {image_path}"
            }

        # 1. Preprocess image using pure NumPy
        input_tensor = preprocess_image(image_path)

        # 2. Run ONNX Inference locally
        input_name = sess.get_inputs()[0].name
        outputs = sess.run(None, {input_name: input_tensor})

        # 3. Extract the CLS token (DINOv2 outputs shape [1, 257, 384]; index 0 is [CLS])
        last_hidden_state = outputs[0]
        cls_token = last_hidden_state[0, 0, :]

        # 4. L2 Normalization for Cosine Similarity / Late Fusion weighting
        cls_token_list = [float(x) for x in cls_token]
        norm = math.sqrt(sum(x**2 for x in cls_token_list))
        vector = [x / norm for x in cls_token_list] if norm > 0 else cls_token_list

        duration = round(time.time() - start_time, 3)
        return {
            "success": True,
            "status": f"Visual vector extracted locally via DINOv2 in {duration}s",
            "image_path": image_path,
            "dimensions": len(vector),
            "vector": vector
        }

    except Exception as e:
        return {
            "success": False,
            "error": f"DINOv2 processing failed: {str(e)}",
            "traceback": traceback.format_exc()
        }