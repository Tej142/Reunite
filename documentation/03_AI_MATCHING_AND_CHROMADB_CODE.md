# AI Matching & ChromaDB Multi-Modal Vector Code

## 1. ChromaDB Vector Collection Manager
**Source File:** `AI_Module/database/chroma_manager.py`

```python
"""
ChromaDB Vector Manager for Reunite Multi-Modal Lost & Found
Coordinates dense embedding storage and cosine similarity querying.
"""
import chromadb
from chromadb.config import Settings
import numpy as np

class ReuniteChromaManager:
    def __init__(self, persistence_path="./chroma_db"):
        self.client = chromadb.PersistentClient(path=persistence_path)
        # Dedicated collections with Cosine distance indexing
        self.lost_collection = self.client.get_or_create_collection(
            name="reunite_lost_items",
            metadata={"hnsw:space": "cosine"}
        )
        self.found_collection = self.client.get_or_create_collection(
            name="reunite_found_items",
            metadata={"hnsw:space": "cosine"}
        )

    def upsert_item_vector(self, item_id: int, embedding: list, metadata: dict, is_found: bool = False):
        """Stores or updates dense multi-modal vector for an item."""
        collection = self.found_collection if is_found else self.lost_collection
        collection.upsert(
            ids=[str(item_id)],
            embeddings=[embedding],
            metadatas=[metadata]
        )

    def find_potential_matches(self, query_embedding: list, campus_id: int = None, limit: int = 5, is_found: bool = False):
        """
        Executes nearest-neighbor query in opposing collection.
        If searching for a lost item, queries found_items (and vice versa).
        """
        collection = self.lost_collection if is_found else self.found_collection
        where_filter = {"campus_id": campus_id} if campus_id else None

        results = collection.query(
            query_embeddings=[query_embedding],
            n_results=limit,
            where=where_filter
        )
        return results
```

---

## 2. Multi-Modal Embedding & Similarity API
**Source File:** `flask_server.py`

```python
from flask import Flask, request, jsonify
from flask_cors import CORS
from PIL import Image
import torch
import clip
import numpy as np

app = Flask(__name__)
CORS(app)

# Load lightweight CLIP multi-modal vision-language model
device = "cuda" if torch.cuda.is_available() else "cpu"
model, preprocess = clip.load("ViT-B/32", device=device)

@app.route('/api/match/embed', methods=['POST'])
def generate_multimodal_embedding():
    """Generates fused visual-textual vector for an uploaded lost/found item."""
    data = request.form
    text_desc = data.get('description', '')
    image_file = request.files.get('image')

    text_tokens = clip.tokenize([text_desc[:77]]).to(device)
    with torch.no_grad():
        text_features = model.encode_text(text_tokens)

    if image_file:
        image = preprocess(Image.open(image_file.stream)).unsqueeze(0).to(device)
        with torch.no_grad():
            image_features = model.encode_image(image)
        # Normalize and fuse image + text vectors (60% visual / 40% textual)
        fused = 0.6 * (image_features / image_features.norm(dim=-1, keepdim=True)) + \
                0.4 * (text_features / text_features.norm(dim=-1, keepdim=True))
    else:
        fused = text_features / text_features.norm(dim=-1, keepdim=True)

    vector = fused.cpu().numpy()[0].tolist()
    return jsonify({"success": True, "vector": vector, "dimensions": len(vector)})

@app.route('/api/match/compare', methods=['POST'])
def compute_item_similarity():
    """Computes cosine similarity score between lost item and candidate found item."""
    payload = request.get_json()
    vec1 = np.array(payload.get('vec_lost', []))
    vec2 = np.array(payload.get('vec_found', []))

    if len(vec1) == 0 or len(vec2) == 0:
        return jsonify({"success": False, "error": "Invalid vector inputs"}), 400

    cos_sim = float(np.dot(vec1, vec2) / (np.linalg.norm(vec1) * np.linalg.norm(vec2)))
    # Rescale from [-1, 1] to [0%, 100%]
    match_percentage = round(max(0.0, cos_sim) * 100, 2)

    return jsonify({
        "success": True,
        "cosine_similarity": cos_sim,
        "match_percentage": match_percentage,
        "is_confident_match": match_percentage >= 75.0
    })
```
