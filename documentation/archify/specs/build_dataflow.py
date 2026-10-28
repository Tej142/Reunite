import json
import os
import subprocess

dataflow_data = {
  "schema_version": 1,
  "diagram_type": "dataflow",
  "meta": {
    "title": "Reunite - Multi-Modal AI Matching & Vector Dataflow Pipeline",
    "output": "documentation/09_ARCHIFY_AI_MATCHING_DATAFLOW.html",
    "viewBox": [1080, 540],
    "animation": "trace",
    "visual_preset": "signal-flow",
    "quality_profile": "showcase"
  },
  "stages": [
    { "label": "Client Capture" },
    { "label": "Gateway & Vault" },
    { "label": "CLIP Encoders" },
    { "label": "Fusion & ChromaDB" },
    { "label": "Match & Dispatch" }
  ],
  "nodes": [
    { "id": "web_client", "type": "frontend", "label": "Web Portal", "sublabel": "Camera & Form", "stage": 0, "row": 0, "tag": "Desktop / PWA" },
    { "id": "mobile_app", "type": "frontend", "label": "Mobile App", "sublabel": "Android Native Cam", "stage": 0, "row": 2, "tag": "Capacitor APK" },

    { "id": "api_gateway", "type": "backend", "label": "API Gateway", "sublabel": "Auth & Validation", "stage": 1, "row": 0, "tag": "PHP REST" },
    { "id": "media_vault", "type": "cloud", "label": "Media Vault", "sublabel": "EXIF Strip & Store", "stage": 1, "row": 2, "tag": "File Storage" },

    { "id": "clip_vision", "type": "backend", "label": "Vision Encoder", "sublabel": "CLIP ViT-B/32", "stage": 2, "row": 0, "tag": "512-d Visual" },
    { "id": "clip_text", "type": "backend", "label": "Text Encoder", "sublabel": "Context Transformer", "stage": 2, "row": 2, "tag": "512-d Text" },

    { "id": "vector_fusion", "type": "backend", "label": "Vector Fusion", "sublabel": "60% Vis / 40% Txt", "stage": 3, "row": 0, "tag": "L2-Normalized" },
    { "id": "chroma_index", "type": "database", "label": "ChromaDB Store", "sublabel": "HNSW Cosine Space", "stage": 3, "row": 2, "tag": "Persistent k-NN" },

    { "id": "matches_engine", "type": "database", "label": "Match Engine", "sublabel": "Cosine Sim >= 75%", "stage": 4, "row": 0, "tag": "MySQL Matches" },
    { "id": "alert_dispatcher", "type": "messagebus", "label": "Alert Dispatcher", "sublabel": "Push, Desk & 2FA", "stage": 4, "row": 2, "tag": "Notifications" }
  ],
  "flows": [
    { "from": "web_client", "to": "api_gateway", "label": "lost/found report", "classification": "multipart/form", "variant": "emphasis", "route": "straight" },
    { "from": "mobile_app", "to": "media_vault", "label": "camera raw upload", "classification": "binary image", "variant": "default", "route": "straight" },
    { "from": "mobile_app", "to": "api_gateway", "label": "report metadata", "classification": "JSON payload", "variant": "emphasis", "route": "vertical-channel" },
    { "from": "api_gateway", "to": "clip_vision", "label": "image tensor feed", "classification": "PyTorch tensor", "variant": "emphasis", "route": "straight" },
    { "from": "api_gateway", "to": "clip_text", "label": "tokenized prompt", "classification": "77 tokens", "variant": "default", "route": "vertical-channel" },
    { "from": "clip_vision", "to": "vector_fusion", "label": "visual embedding", "classification": "dense tensor", "variant": "emphasis", "route": "straight" },
    { "from": "clip_text", "to": "vector_fusion", "label": "text embedding", "classification": "dense tensor", "variant": "default", "route": "vertical-channel" },
    { "from": "vector_fusion", "to": "chroma_index", "label": "upsert item vector", "classification": "512-d vector", "variant": "emphasis", "route": "straight" },
    { "from": "chroma_index", "to": "matches_engine", "label": "k-NN nearest matches", "classification": "distance <= 0.30", "variant": "emphasis", "route": "vertical-channel" },
    { "from": "matches_engine", "to": "alert_dispatcher", "label": "broadcast candidate", "classification": "instant alert", "variant": "emphasis", "route": "straight" }
  ],
  "cards": [
    {
      "dot": "emerald",
      "title": "Multi-Modal Visual-Semantic Fusion",
      "items": [
        "CLIP ViT-B/32 encodes visual features and textual descriptions into a shared 512-dimensional vector space.",
        "Vectors are fused with 60% visual weight and 40% textual weight, normalized with L2 Euclidean norm.",
        "Ensures resilience against lighting variations, partial occlusions, and vague keyword descriptions."
      ]
    },
    {
      "dot": "amber",
      "title": "Sub-Millisecond Vector Retrieval",
      "items": [
        "ChromaDB HNSW index executes fast approximate nearest neighbor search across opposing collections.",
        "Cosine distance metric computes spatial proximity with strict campus-level scoping.",
        "Multi-tier heuristic filtering validates item category alignment and realistic 14-day timestamp windows."
      ]
    },
    {
      "dot": "rose",
      "title": "Automated Custody & Notification Dispatch",
      "items": [
        "Candidates matching cosine distance <= 0.30 (>= 75% similarity) are stored in the MySQL matches table.",
        "Real-time event dispatcher triggers instant push and web alerts with side-by-side comparison photos.",
        "Pre-populates the Campus Lost & Found Desk queue for secure 2FA OTP handover upon verification."
      ]
    }
  ]
}

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ARCHIFY_DIR = os.path.dirname(BASE_DIR)
DOCS_DIR = os.path.dirname(ARCHIFY_DIR)
ARCHIFY_CLI = os.path.abspath(os.path.join(DOCS_DIR, "..", "..", "archify-main", "archify", "bin", "archify.mjs"))

model_path = os.path.join(BASE_DIR, "reunite_ai_matching.dataflow.json")
with open(model_path, "w", encoding="utf-8") as f:
    json.dump(dataflow_data, f, indent=2)

print("Saved model to", model_path)

out_html = os.path.join(ARCHIFY_DIR, "03_AI_MATCHING_VECTOR_DATAFLOW.html")
res = subprocess.run(["node", ARCHIFY_CLI, "render", "dataflow", model_path, out_html, "--quality", "standard"], capture_output=True, text=True)
print("Render output:", res.stdout)
if res.stderr:
    print("Render stderr:", res.stderr)
print("Exit code:", res.returncode)

