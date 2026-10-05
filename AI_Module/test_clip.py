from analyzers.clip_extractor import analyze_clip_text

def main():
    # Sample Digital DNA string from your AI Normalizer
    test_dna = "Lost Mobile Vivo Y31 5G black color with a scratch near the camera."
    
    print(f"\n--- Starting CLIP Text Test ---")
    print(f"Target DNA: '{test_dna}'")
    
    result = analyze_clip_text(test_dna)
    
    print("\n--- Results ---")
    if result["success"]:
        print(f"Status: {result['status']}")
        print(f"Dimensions: {result['dimensions']} (Must be 512 for CLIP)")
        
        # L2 Norm check
        sum_of_squares = sum([x**2 for x in result['vector']])
        print(f"L2 Norm check (Sum of squares): {sum_of_squares:.4f}")
        
        print(f"First 5 vector values: {result['vector'][:5]}")
    else:
        print(f"FAILED: {result['error']}")
        if "traceback" in result:
            print(result["traceback"])

if __name__ == "__main__":
    main()