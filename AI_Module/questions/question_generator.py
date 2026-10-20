import json
import time_log

from google.genai import types

from config import client, GEMINI_MODEL
from questions.question_prompt import build_question_prompt
from questions.question_validator import validate_question_response


from questions.question_schema import Question, QuestionSet

SMART_CATEGORY_FALLBACKS = {
    "electronics": [
        Question(id="device_type", question="What type of electronic device is this?", type="choice", choices=["Smartphone", "Laptop", "Wireless Earbuds", "Smartwatch", "Tablet", "Power Bank", "Headphones", "Scientific Calculator", "Other Device"], required=True, importance="highest"),
        Question(id="brand", question="What is the brand / manufacturer?", type="choice", choices=["Apple", "Samsung", "Dell", "HP", "Lenovo", "Asus", "Acer", "Boat", "Noise", "Sony", "OnePlus", "Xiaomi / Redmi", "Realme", "Casio", "Other Brand", "I don't know / Not sure"], required=True, importance="high"),
        Question(id="model", question="Specific model name or series (if known)?", type="text", choices=[], required=False, importance="high"),
        Question(id="color", question="What is the primary color of the device?", type="choice", choices=["Black", "Space Grey / Silver", "White", "Midnight Blue", "Gold", "Red", "Green", "Other"], required=True, importance="medium"),
        Question(id="screen_condition", question="Physical condition of the item?", type="choice", choices=["Like New / Mint", "Good with minor scuffs", "Worn / Scratched", "Cracked screen / Damaged"], required=False, importance="medium"),
        Question(id="case_cover", question="Was the device in a protective case, sleeve, or pouch?", type="text", choices=[], required=False, importance="medium"),
        Question(id="lock_screen_wallpaper", question="Private Verification: Lock screen wallpaper, stickers, or unique markings?", type="text", choices=[], required=False, private=True, importance="highest")
    ],
    "wallets": [
        Question(id="wallet_type", question="What type of wallet, purse, or bag is this?", type="choice", choices=["Bifold Wallet", "Trifold Wallet", "Cardholder", "Purse / Handbag", "College Backpack", "Sling / Crossbody Pouch"], required=True, importance="highest"),
        Question(id="brand", question="What brand is it?", type="choice", choices=["Wildcraft", "Woodland", "Fastrack", "Tommy Hilfiger", "Titan", "American Tourister", "Skybags", "Puma", "Local / Unbranded", "Other"], required=False, importance="high"),
        Question(id="material", question="What material is it made of?", type="choice", choices=["Genuine Leather", "Synthetic / Faux Leather", "Canvas / Fabric", "Nylon / Polyester", "Metal"], required=True, importance="medium"),
        Question(id="color", question="Primary color of the wallet/bag?", type="choice", choices=["Black", "Dark Brown", "Tan / Light Brown", "Navy Blue", "Grey", "Red", "Olive Green", "Patterned"], required=True, importance="high"),
        Question(id="distinguishing_marks", question="Any unique exterior markings, zippers, or logos?", type="text", choices=[], required=False, importance="medium"),
        Question(id="private_contents_hint", question="Private Verification: Non-sensitive card name initial or unique item inside?", type="text", choices=[], required=False, private=True, importance="highest")
    ],
    "keys": [
        Question(id="key_type", question="What kind of key or key set is this?", type="choice", choices=["Bike / Scooter Key", "Car Key with Remote Fob", "Hostel / Room Key", "House / Padlock Key", "Almirah / Drawer Key", "Key Bunch"], required=True, importance="highest"),
        Question(id="key_count", question="How many keys are on the ring?", type="choice", choices=["Single key (1)", "2 to 3 keys", "4 to 6 keys", "Large bunch (7+ keys)"], required=True, importance="high"),
        Question(id="key_brand", question="Brand or emblem on the key / fob?", type="choice", choices=["Honda", "Hero", "Yamaha", "TVS", "Suzuki", "Royal Enfield", "Hyundai", "Maruti Suzuki", "Godrej", "Link", "Yale", "Unmarked / Standard"], required=False, importance="high"),
        Question(id="keychain_design", question="Keychain, lanyard, or accessory attached?", type="choice", choices=["Metal ring only (No keychain)", "Fabric / Campus Lanyard", "Rubber / Figurine Keychain", "Leather Fob", "Carabiner Clip", "Bottle Opener"], required=True, importance="medium"),
        Question(id="unique_key_mark", question="Private Verification: Unique scratch, colored tape, or ribbon tag?", type="text", choices=[], required=False, private=True, importance="highest")
    ],
    "clothing": [
        Question(id="clothing_type", question="What type of clothing or wearable item?", type="choice", choices=["Hoodie / Sweatshirt", "Jacket / Windbreaker", "Cap / Beanie", "T-Shirt / Shirt", "Shoes / Sneakers", "Wristwatch", "Glasses / Sunglasses", "Scarf"], required=True, importance="highest"),
        Question(id="brand", question="What brand is it?", type="choice", choices=["Nike", "Adidas", "Puma", "Zara", "Decathlon", "Levi's", "H&M", "Fastrack", "Casio", "Other Brand", "Unbranded"], required=False, importance="high"),
        Question(id="color", question="Primary color or pattern?", type="choice", choices=["Black", "Navy Blue", "Grey", "White", "Red", "Green", "Maroon", "Striped / Checkered"], required=True, importance="high"),
        Question(id="clothing_size", question="Approximate size?", type="choice", choices=["S", "M", "L", "XL", "XXL", "Free Size / Adjustable", "Shoe Size"], required=False, importance="medium"),
        Question(id="unique_wear_mark", question="Private Verification: Interior tag name, micro-tear, or unique badge?", type="text", choices=[], required=False, private=True, importance="highest")
    ],
    "documents": [
        Question(id="document_type", question="What type of document or academic record?", type="choice", choices=["College ID Card", "Hall Ticket / Exam Pass", "Library Card", "Govt ID (Aadhaar / DL)", "Marks Memo / Certificate", "Lab Manual / Project Notebook", "Textbook"], required=True, importance="highest"),
        Question(id="issuing_institution", question="Issuing institution or college?", type="choice", choices=["Sri Venkateswara Govt Polytechnic, Tirupati", "Govt Polytechnic Pillaripattu, Puttur", "Venkata Perumal", "State Board of Technical Education", "Other Institution"], required=True, importance="high"),
        Question(id="department", question="Academic branch or department?", type="choice", choices=["Computer Engineering (CME)", "Electronics & Comm (ECE)", "Electrical & Electronics (EEE)", "Mechanical (ME)", "Civil (CE)", "General Studies"], required=False, importance="high"),
        Question(id="cover_color", question="Color of holder, lanyard, or notebook cover?", type="choice", choices=["Blue", "Red", "Transparent / Clear", "Black", "Green", "Yellow", "No cover"], required=False, importance="medium"),
        Question(id="holder_name_initial", question="Private Verification: Student name initials or roll number prefix?", type="text", choices=[], required=False, private=True, importance="highest")
    ],
    "other": [
        Question(id="item_title", question="What is the general name of this item?", type="text", choices=[], required=True, importance="highest"),
        Question(id="brand", question="Brand or manufacturer (if visible)?", type="choice", choices=["Cello", "Milton", "Fastrack", "Casio", "Lenskart", "Quechua", "Wildcraft", "Unknown / Unbranded", "Other"], required=False, importance="high"),
        Question(id="color", question="Primary color of the item?", type="choice", choices=["Black", "Blue", "Silver / Grey", "Red", "Green", "White", "Transparent", "Multi-Color"], required=True, importance="high"),
        Question(id="material", question="What material is it primarily made of?", type="choice", choices=["Plastic", "Stainless Steel / Metal", "Glass", "Fabric / Canvas", "Rubber / Silicone", "Wood", "Other"], required=False, importance="medium"),
        Question(id="condition", question="Physical condition?", type="choice", choices=["Brand New / Mint", "Gently Used", "Worn / Scratched", "Damaged"], required=False, importance="medium"),
        Question(id="distinguishing_marks", question="Private Verification: Distinguishing scratches, engravings, or stickers?", type="text", choices=[], required=False, private=True, importance="highest")
    ]
}

def get_smart_fallback_question_set(category: str, report_type: str) -> QuestionSet:
    clean_cat = category.strip().lower()
    matched_key = "other"
    for key in SMART_CATEGORY_FALLBACKS:
        if key in clean_cat or clean_cat in key:
            matched_key = key
            break

    questions = SMART_CATEGORY_FALLBACKS.get(matched_key, SMART_CATEGORY_FALLBACKS["other"])
    return QuestionSet(
        category=clean_cat,
        report_type=report_type.strip().lower(),
        version=1,
        questions=questions
    )


def generate_questions(category: str, report_type: str) -> dict:

    if not category.strip():
        return {
            "success": False,
            "error": "Category cannot be empty."
        }

    if not report_type.strip():
        return {
            "success": False,
            "error": "Report type cannot be empty."
        }

    try:

        prompt = build_question_prompt(
            category,
            report_type
        )

        time_log.start("Question Generation (Gemini)")

        response = client.models.generate_content(
            model=GEMINI_MODEL,
            contents=prompt,
            config=types.GenerateContentConfig(
                temperature=0,
                response_mime_type="application/json"
            )
        )

        time_log.stop("Question Generation (Gemini)")

        val_res = validate_question_response(response.text)
        if val_res.get("success"):
            return val_res
        
        # If Gemini returned invalid schema, smoothly use smart fallback
        fallback = get_smart_fallback_question_set(category, report_type)
        return {
            "success": True,
            "data": fallback,
            "source": "smart_canonical_fallback"
        }

    except Exception as e:
        print(f"[Question Generator] Gemini call notice: {e}. Activating smart category fallback.")
        fallback = get_smart_fallback_question_set(category, report_type)
        return {
            "success": True,
            "data": fallback,
            "source": "smart_canonical_fallback"
        }