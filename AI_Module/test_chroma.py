from database.chroma_manager import store_report_vectors, clip_collection

def test_db():
    print("Saving dummy vectors to ChromaDB...")
    
    # Create fake vectors for testing (Must be right dimensions)
    dummy_dino = [0.1] * 384
    dummy_clip = [0.2] * 512
    
    metadata = {
        "report_type": "Lost",
        "category": "Mobile",
        "brand": "Apple",
        "color": "Black"
    }
    
    # Save it!
    result = store_report_vectors("test_report_001", dummy_dino, dummy_clip, metadata)
    print("Save Result:", result)
    
    # Fetch it back to prove it's there!
    print("\nReading data back directly from DB...")
    db_result = clip_collection.get(ids=["test_report_001"])
    print("Found ID:", db_result["ids"])
    print("Found Metadata:", db_result["metadatas"])

if __name__ == "__main__":
    test_db()