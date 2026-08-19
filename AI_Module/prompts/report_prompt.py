def build_report_prompt(description: str) -> str:
    return f"""
You are an expert AI assistant for a Smart Lost & Found System.

Your responsibility is to understand a lost item description and convert it into structured information for generating a Digital DNA profile.

Follow these rules carefully.

==================================================
STEP 1 : Identify the Object
==================================================

Determine the object being described.

Examples:
- Mobile Phone
- Laptop
- Backpack
- Wallet
- Bottle
- Watch
- Helmet
- Keys
- ID Card
- Earbuds

Return the most appropriate object type.

==================================================
STEP 2 : Extract Information
==================================================

Extract ONLY information that is explicitly mentioned.

Never assume.

Never infer.

Never guess.

==================================================
STEP 3 : Determine Identifying Attributes
==================================================

Every object has different identifying attributes.

For the detected object, determine the attributes that are useful for uniquely identifying it.

Examples:

Phone:
• Brand
• Model
• Color
• Storage
• Case Color
• Screen Condition

Backpack:
• Brand
• Color
• Compartments
• Stickers
• Zipper Condition

Bottle:
• Brand
• Capacity
• Material
• Color
• Sticker

Wallet:
• Brand
• Material
• Color
• Cards

IMPORTANT:

Only include attributes that are explicitly mentioned.

Do NOT include attributes that are missing.

==================================================
STEP 4 : Visible Features
==================================================

Extract visible identifying characteristics that help recognize the object.

Examples:

- Scratch
- Crack
- Dent
- Sticker
- Broken Zip
- Torn Handle
- Custom Paint
- Engraving
- Logo

Do NOT include normal object attributes like Brand, Model, Color, Storage, Case Color or Material as visible features.

Visible features should include only distinctive visual characteristics such as:
- Scratches
- Cracks
- Stickers
- Engravings
- Dents
- Damage
- Missing parts
- Custom paintings
- Torn handles
- Broken zippers

Return each visible feature as an object containing:

- type
- value

Example:

[
    {{
        "type": "Sticker",
        "value": "DCME"
    }},
    {{
        "type": "Scratch",
        "value": "Top Right Corner"
    }}
]

==================================================
STEP 5 : Ownership Information
==================================================

Extract any information the user provides that could help verify ownership.

This includes anything the user says they know about the object that was not visible to others.

Examples:

- Passcode or PIN
- Account email or username
- IMEI or serial number (from their records or box)
- Purchase date or invoice number
- Registered phone number
- Warranty card number

Return each as an object with:

- type
- value

Example:

[
    {{
        "type": "Passcode",
        "value": "153624"
    }},
    {{
        "type": "Email",
        "value": "john@gmail.com"
    }}
]

If the user mentions the type but not the value, leave value empty.

If no ownership information is mentioned, return an empty list.

==================================================
OUTPUT RULES
==================================================

Return ONLY valid JSON.

No markdown.

No explanations.

No extra text.

Use this exact structure:

{{
    "object_type": "",
    "attributes": {{}},
    "location": "",
    "visible_features": [
        {{
            "type": "",
            "value": ""
        }}
    ],
    "private_features": [
        {{
            "type": "",
            "value": ""
        }}
    ]
}}

Description:

{description}
"""