# 03 — AI Matching & Multi-Modal Vector Engine

## 1. Engine Overview
The **Reunite AI Subsystem** bridges deep visual feature extraction, optical character recognition (OCR), and dense semantic vector embeddings. It automatically identifies candidate matches between reported lost items and newly registered found items across university grounds.

```
┌─────────────────────────────────┐       ┌─────────────────────────────────┐
│     Reported Lost Item          │       │      Reported Found Item        │
│  - Image Upload                 │       │  - Image Upload                 │
│  - Text Description             │       │  - Location & Tags              │
└────────────────┬────────────────┘       └────────────────┬────────────────┘
                 │                                         │
                 ▼                                         ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                      Cloud Flask AI Microservice                          │
│                    `https://reunite-ai-backend.onrender.com`              │
│                                                                           │
│  1. Vision Processing: CLIP / ViT Multi-Modal Image Embedder             │
│  2. Text Semantic Vectorization: all-MiniLM-L6-v2 Embeddings              │
│  3. Digital DNA Synthesis: Color Histogram + OCR Text + Category Metadata │
└─────────────────────────────────────┬─────────────────────────────────────┘
                                      │
                                      ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                        ChromaDB Vector Database                           │
│  - Cosine Distance Computation                                            │
│  - Ranked Neighbor Retrieval (Top-K Matches)                              │
│  - Institutional Campus Scope Filtering                                   │
└─────────────────────────────────────┬─────────────────────────────────────┘
                                      │
                                      ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                     Instant Choice & Claim Desk Engine                    │
│  - Adaptive Verification Questionnaire (Color, Brand, Markings)           │
│  - Confidence Scoring & Founder Handshake Generation                      │
└───────────────────────────────────────────────────────────────────────────┘
```

---

## 2. ChromaDB Vector Database Manager
File: `AI_Module/database/chroma_manager.py`

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
        # Separate collections for lost vs found inventory
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

## 3. Instant Choice & Adaptive Questionnaire
When a user begins claiming a matched item, the AI dynamically synthesizes distinctive multiple-choice questions from the item's Digital DNA (e.g. unique scratches, stickers, brand variants, or case colors).

```json
{
  "question_id": "dna_brand_variant",
  "prompt": "Which specific logo or brand marking is visible on the bottom-right corner?",
  "options": [
    "Silver embossed Apple logo",
    "Matte black Dell emblem",
    "HP minimalist circle",
    "Plain unbranded surface"
  ],
  "correct_hash": "e99a18c428cb38d5f260853678922e03"
}
```

This prevents malicious students from falsely claiming valuable electronics simply by looking at blurred thumbnail listings on public university boards.
