import os
import random
import chromadb
import ai_controller
from questions.question_engine import get_questions
from database.chroma_manager import search_vectors

CHROMA_DB_PATH = os.path.join(os.getcwd(), "chroma_data")
COLLECTION_NAME = "clip_reports"
report_session={}

def run_test(num_reports=3):
    print("🚀 STARTING ULTIMATE BLEND & STRICT METADATA TEST 🚀\n")
    
    chroma_client = chromadb.PersistentClient(path=CHROMA_DB_PATH)
    try:
        collection = chroma_client.get_collection(name=COLLECTION_NAME)
        all_data = collection.get(include=["metadatas"])
    except Exception as e:
        print(f"❌ Database error: {e}")
        return

    total_items = len(all_data['ids'])
    if total_items == 0:
        print("❌ Database is empty! Please run stress_test.py first to add items.")
        return

    test_size = min(num_reports, total_items)
    test_indices = random.sample(range(total_items), test_size)

    TP = FP = FN = 0

    for i, idx in enumerate(test_indices):
        target_id = all_data['ids'][idx]
        metadata = all_data['metadatas'][idx]

        cat = metadata.get("category", metadata.get("object_type", "item"))
        loc = metadata.get("location", "Unknown")
        
        # Safely parse ALL attributes
        if isinstance(metadata.get("attributes"), str):
            import ast
            try:
                attrs = ast.literal_eval(metadata.get("attributes", "{}"))
            except:
                attrs = {}
        else:
            attrs = metadata.get("attributes", {})

        # Extract ALL available fields
        ground_truth = {k.lower(): str(v) for k, v in attrs.items()}
        ground_truth["location"] = loc

        print("=" * 60)
        print(f"🎯 Target Report ID: {target_id}")
        
        # ---> FIX 1: session_id ni mundhe create chesthunnam <---
        session_id = f"session_user_{i}"
        
        # THE ULTIMATE BLEND (Select 1 of 3 modes)
        input_source = random.choice(["description", "instant_choice", "talk_to_ai"])
        print(f"   [>] User Input Mode: {input_source.upper()}")

        report_data = {}
        q_list_pure = [] # We will ensure this is a STRICT list

        if input_source == "description":
            report_data = {
                "object_type": cat,
                "location": loc,
                "attributes": ground_truth,
                "visible_features": [],
                "private_features": []
            }
        else:
            print("   [>] Fetching dynamic questions from Gemini...")
            real_questions_meta = get_questions(cat, "lost")
            
            # ----------------------------------------------------
            # STRICTLY EXTRACTING AS A PYTHON LIST
            # ----------------------------------------------------
            if isinstance(real_questions_meta, dict):
                q_list_pure = real_questions_meta.get("questions", [])
            elif hasattr(real_questions_meta, "questions"):
                q_list_pure = real_questions_meta.questions
            elif isinstance(real_questions_meta, str):
                import json
                try:
                    q_list_pure = json.loads(real_questions_meta).get("questions", [])
                except:
                    q_list_pure = []
            else:
                q_list_pure = real_questions_meta if isinstance(real_questions_meta, list) else []

            # ---> FIX 2: Gemini empty isthe e item ni skip chesi next dhaaniki veltham <---
            if not q_list_pure:
                print("   [❌] Gemini API failed or returned empty. Skipping to next report...")
                continue
                
            # ---> FIX 3: Pure list ni session lo petti save chesthunnam <---
            report_session[session_id] = q_list_pure

            # Dynamic Mapping
            user_answers = {}
            for q in q_list_pure:
                q_id = q.get("id", "")
                q_target = q.get("target", "")
                q_text = q.get("question_text", "").lower()

                matched_value = "Not sure"
                for field_name, field_value in ground_truth.items():
                    if field_name in q_text or field_name in q_id.lower() or (field_name == "material" and "made of" in q_text):
                        matched_value = field_value
                        break
                
                if matched_value == "Not sure" and q_target == "location":
                    matched_value = loc
                    
                user_answers[q_id] = matched_value

            # Build Payload based on mode
            if input_source == "instant_choice":
                report_data = {
                    "questions": q_list_pure, # Injecting raw list
                    "answers": user_answers
                }
            elif input_source == "talk_to_ai":
                report_data = {
                    "category": cat,
                    "report_type": "lost",
                    "questions": q_list_pure, # Injecting raw list
                    "answers": user_answers
                }
                
        # ---> FIX 4: Description kakapothe matrame session nunchi pop chesthunnam <---
        retrieved_questions = None
        if input_source != "description":
            retrieved_questions = report_session.pop(session_id, None)
            
        # Send to AI Controller
        controller_result = ai_controller.process_report(
            input_data=report_data,
            input_type=input_source, 
            category=cat,
            report_type="lost",
            questions=retrieved_questions, # Passing the raw list parameter directly
            image_path=None
        )

        if not controller_result.get("success"):
            print(f"❌ AI Module Error: {controller_result.get('error')}")
            continue

        # Matching Logic
        clip_vector = controller_result.get("clip", {}).get("vector")
        matches = search_vectors(clip_vector=clip_vector, top_k=3)

        if not matches:
            print("❌ No matches found in DB.")
            FN += 1
            continue

        top_match = matches[0]
        matched_id = top_match["report_id"]
        match_score = top_match["match_score"]

        print(f"🔗 Best Match Found: {matched_id}")
        print(f"📊 Matching percentage: {match_score}%")

        if matched_id == target_id:
            print("✅ Status: TRUE POSITIVE (Correctly matched!)")
            TP += 1
        elif match_score == 100.0:
            print("⚠️ Status: TRUE POSITIVE (Duplicate vector in DB matched!)")
            TP += 1
        else:
            print("❌ Status: FALSE POSITIVE (Matched wrong item!)")
            FP += 1
            FN += 1

    # Confusion Matrix
    print("\n" + "=" * 60)
    print(" 📈 CONFUSION MATRIX & FINAL RESULTS ")
    print("=" * 60)
    print(f"Total Reports Tested : {test_size}")
    print(f"True Positives (TP)  : {TP}")
    print(f"False Positives (FP) : {FP}")
    print(f"False Negatives (FN) : {FN}")
    
    if (TP + FP) > 0:
        precision = (TP / (TP + FP)) * 100
        print(f"Precision            : {precision:.2f}%")
    if (TP + FN) > 0:
        recall = (TP / (TP + FN)) * 100
        print(f"Recall               : {recall:.2f}%")
        
    if test_size > 0:
        accuracy = (TP / test_size) * 100
        print(f"Overall Accuracy     : {accuracy:.2f}%")
    print("=" * 60)

if __name__ == "__main__":
    run_test(num_reports=3)