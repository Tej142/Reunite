import os
import ast
import chromadb
from datetime import datetime

# 1. Setup Physical Storage (Idi mee folder lo 'chroma_data' ane folder create chesthundi)
DB_PATH = os.path.join(os.getcwd(), "chroma_data")
chroma_client = chromadb.PersistentClient(path=DB_PATH)

# 2. Create or Get Collections (Tables)
# 'cosine' space vadutunnam endukante DINOv2 and CLIP ki ide best mathematical match
dinov2_collection = chroma_client.get_or_create_collection(
    name="dinov2_reports",
    metadata={"hnsw:space": "cosine"} 
)

clip_collection = chroma_client.get_or_create_collection(
    name="clip_reports",
    metadata={"hnsw:space": "cosine"}
)


def get_image_vector(report_id):
    """
    Fetches the existing DINOv2 image vector for a given report_id.
    """
    try:
        result = dinov2_collection.get(ids=[report_id], include=["embeddings"])
        if result and result['embeddings'] and len(result['embeddings']) > 0:
            return result['embeddings'][0]
    except Exception as e:
        print(f"Error fetching image vector: {e}")
    return None


def store_report_vectors(report_id: str, dinov2_vector: list = None, clip_vector: list = None, metadata: dict = None):
    """
    Saves the report's vectors and metadata into the local ChromaDB database.
    """
    # ChromaDB metadata expects flat values (strings, numbers, booleans). 
    # Nested lists or dictionaries need to be converted to strings.
    flat_metadata = {}
    if metadata:
        for k, v in metadata.items():
            if isinstance(v, (dict, list)):
                flat_metadata[k] = str(v)
            elif v is not None:
                flat_metadata[k] = v

    try:
        # Save DINOv2 (Image) Vector
        if dinov2_vector and len(dinov2_vector) == 384:
            dinov2_collection.upsert(
                ids=[report_id],
                embeddings=[dinov2_vector],
                metadatas=[flat_metadata]
            )

        # Save CLIP (Text) Vector
        if clip_vector and len(clip_vector) == 512:
            clip_collection.upsert(
                ids=[report_id],
                embeddings=[clip_vector],
                metadatas=[flat_metadata]
            )
            
        return {"success": True, "message": f"Successfully stored vectors for {report_id} in ChromaDB"}
        
    except Exception as e:
        return {"success": False, "error": f"ChromaDB Error: {str(e)}"}


def calculate_dynamic_weights(photo_date_str, lost_date_str, found_date_str):
    """
    Calculates dynamic weights for Image (Max 35%) and Text (Min 65%)
    based on the time gap between when the photo was taken and when the item was found.
    """
    # Base Limits (As per your brilliant logic)
    MAX_IMAGE_WEIGHT = 0.35 
    MIN_TEXT_WEIGHT = 0.65
    
    try:
        # Dates ni parse chesthunnam (Assuming YYYY-MM-DD format)
        fmt = "%Y-%m-%d"
        photo_date = datetime.strptime(photo_date_str, fmt)
        # lost_date = datetime.strptime(lost_date_str, fmt) # Future use ki
        found_date = datetime.strptime(found_date_str, fmt)
        
        # Photo theesina time nundi, dorikina time ki madhya enni rojulu gap undi?
        days_gap = (found_date - photo_date).days
        
        if days_gap < 0:
            days_gap = 0 # Failsafe
            
        # Decay Formula: Okkosari 6 months (180 days) ki image weight 0 aipoyela chestham.
        # Ante roju roju ki image weightage thaggipothundi.
        DECAY_PERIOD = 180.0 
        
        decay_factor = min(days_gap / DECAY_PERIOD, 1.0) # Maximum 1.0 (100% loss of weight)
        
        # Calculate dynamic image weight
        dynamic_img_weight = MAX_IMAGE_WEIGHT * (1.0 - decay_factor)
        
        # Text gets whatever is left from 100%
        dynamic_text_weight = 1.0 - dynamic_img_weight
        
        return round(dynamic_img_weight, 2), round(dynamic_text_weight, 2)

    except Exception as e:
        # Okavela dates sarigga lekapothe leda error vasthe (Safe Fallback)
        print("Date processing error, falling back to safe limits:", e)
        return 0.10, 0.90 # 10% Image, 90% Text if we are unsure


def search_vectors(dinov2_vector=None, clip_vector=None, candidate_ids=None, photo_date_str=None, found_date_str=None, query_metadata=None, top_k=5):
    """
    1. Fetches raw cosine distances from ChromaDB.
    2. Calculates Dynamic Weights based on item lost/found dates.
    3. Applies Late Fusion to generate the final match percentage.
    4. Generates Privacy-Preserving Explainable AI (XAI) reasons using Digital DNA.
    """
    results_dict = {}
    
    # Optional SQL Filter (Search only in these IDs)
    where_filter = None
    if candidate_ids and len(candidate_ids) > 0:
        if len(candidate_ids) == 1:
            where_filter = {"report_id": candidate_ids[0]}
        else:
            where_filter = {"report_id": {"$in": candidate_ids}}

    # Step 1: Get raw distances from ChromaDB for Image (DINOv2)
    if dinov2_vector and len(dinov2_vector) == 384:
        dino_results = dinov2_collection.query(
            query_embeddings=[dinov2_vector], n_results=top_k, where=where_filter
        )
        if dino_results['ids'] and len(dino_results['ids'][0]) > 0:
            for i, doc_id in enumerate(dino_results['ids'][0]):
                similarity = 1.0 - dino_results['distances'][0][i] 
                metadata = dino_results['metadatas'][0][i]
                results_dict[doc_id] = {"metadata": metadata, "dino_sim": similarity, "clip_sim": 0}

    # Step 2: Get raw distances from ChromaDB for Text (CLIP)
    if clip_vector and len(clip_vector) == 512:
        clip_results = clip_collection.query(
            query_embeddings=[clip_vector], n_results=top_k, where=where_filter
        )
        if clip_results['ids'] and len(clip_results['ids'][0]) > 0:
            for i, doc_id in enumerate(clip_results['ids'][0]):
                similarity = 1.0 - clip_results['distances'][0][i]
                metadata = clip_results['metadatas'][0][i]
                if doc_id not in results_dict:
                    results_dict[doc_id] = {"metadata": metadata, "dino_sim": 0, "clip_sim": 0}
                results_dict[doc_id]["clip_sim"] = similarity

    # Step 3: Get Dynamic Weights from Decay Logic
    img_weight, txt_weight = 0, 1.0 
    if photo_date_str and found_date_str:
        img_weight, txt_weight = calculate_dynamic_weights(photo_date_str, photo_date_str, found_date_str)
    
    # Step 4: Final Calculations & XAI
    final_matches = []
    
    # Safe fallback
    if query_metadata is None:
        query_metadata = {}

    for doc_id, data in results_dict.items():
        d_sim = data["dino_sim"]
        c_sim = data["clip_sim"]
        db_meta = data["metadata"]
        
        # ==========================================
        # ADVANCED DIGITAL DNA MATCHING (XAI)
        # ==========================================
        category_matched = False
        location_matched = False
        matched_attributes = []
        
        # 1. Object Type (Category) Match
        q_obj = str(query_metadata.get('object_type', '')).strip().lower()
        db_obj = str(db_meta.get('object_type', '')).strip().lower()
        if q_obj and db_obj and q_obj == db_obj:
            category_matched = True

        # 2. Location Match
        q_loc = str(query_metadata.get('location', '')).strip().lower()
        db_loc = str(db_meta.get('location', '')).strip().lower()
        if q_loc and db_loc and q_loc == db_loc:
            location_matched = True

        # 3. Dynamic Attributes Match (Brand, Color, Material, etc.)
        try:
            # Safely parse Query attributes
            q_attrs = query_metadata.get('attributes', {})
            if isinstance(q_attrs, str):
                q_attrs = ast.literal_eval(q_attrs)
            
            # Safely parse DB attributes
            db_attrs_str = db_meta.get('attributes', '{}')
            db_attrs = ast.literal_eval(db_attrs_str) if isinstance(db_attrs_str, str) else db_attrs_str
            
            # Compare each attribute
            if isinstance(q_attrs, dict) and isinstance(db_attrs, dict):
                for key, q_val in q_attrs.items():
                    db_val = db_attrs.get(key)
                    if q_val and db_val and str(q_val).strip().lower() == str(db_val).strip().lower():
                        # Exclude highly sensitive details from XAI output to prevent fraud
                        if key.lower() not in ['condition', 'distinguishing marks', 'date / time']:
                            matched_attributes.append(key)
        except Exception as e:
            # Ignore parsing errors and continue safely
            pass

        # Naming accurately based on architecture discussion
        visible_similarity = round(d_sim * 100, 2) 
        textual_and_private_similarity = round(c_sim * 100, 2) 
        # ==========================================

        # Apply Dynamic Weights (Late Fusion)
        if dinov2_vector and clip_vector:
            final_score = (d_sim * img_weight) + (c_sim * txt_weight)
        elif clip_vector:
            final_score = c_sim
        elif dinov2_vector:
            final_score = d_sim
        else:
            final_score = 0
            
        final_matches.append({
            "report_id": doc_id,
            "match_score": round(final_score * 100, 2), 
            "ai_reasons": {
                "category_matched": category_matched,
                "location_matched": location_matched,
                "matched_attributes": matched_attributes, 
                "visual_match_confidence": f"{visible_similarity}%",       
                "textual_and_private_match": f"{textual_and_private_similarity}%" 
            },
            "metadata": db_meta,
            "breakdown": {
                "dino_score_raw": visible_similarity,
                "clip_score_raw": textual_and_private_similarity,
                "applied_image_weight": f"{img_weight*100}%",
                "applied_text_weight": f"{txt_weight*100}%"
            }
        })
        
    # Sort from highest percentage to lowest
    final_matches = sorted(final_matches, key=lambda x: x["match_score"], reverse=True)
    return final_matches[:top_k]