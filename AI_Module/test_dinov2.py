import os
from analyzers.dinov2_extractor import analyze_dinov2

def main():
    test_image = os.path.join(os.path.dirname(__file__), "sample_images", "img1.jpg")
    
    print(f"\n--- Starting DINOv2 Test ---")
    print(f"Target Image: {test_image}")
    
    result = analyze_dinov2(test_image)
    
    print("\n--- Results ---")
    if result["success"]:
        print(f"Status: {result['status']}")
        print(f"Dimensions: {result['dimensions']}")
        
        # Calculate sum of squares to prove it is normalized (should be ~1.0)
        sum_of_squares = sum([x**2 for x in result['vector']])
        print(f"L2 Norm check (Sum of squares): {sum_of_squares:.4f}")
        
        # Print just the first 5 values to keep the console clean
        print(f"First 5 vector values: {result['vector'][:5]}")
    else:
        print(f"FAILED: {result['error']}")
        if "traceback" in result:
            print(result["traceback"])

if __name__ == "__main__":
    main()