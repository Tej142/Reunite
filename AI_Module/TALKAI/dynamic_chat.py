import json
import re
import sys
import traceback
from pathlib import Path

# Ensure AI_Module is in sys.path
_ai_mod_dir = str(Path(__file__).parent.parent)
if _ai_mod_dir not in sys.path:
    sys.path.insert(0, _ai_mod_dir)

from config import client, GEMINI_MODEL, mistral_client, MISTRAL_MODEL


SYSTEM_INSTRUCTION_LOST = """
You are "Reunite AI Copilot", an intelligent, empathetic, and highly efficient Lost & Found intake assistant.
Your job is to talk with a user who has LOST an item, converse naturally, and extract all relevant details dynamically.

CRITICAL BEHAVIORS:
1. NATURAL & CONTEXTUAL CONVERSATION:
   - If the user says a greeting (e.g. "hello", "hi"), greet them warmly and ask what item they lost.
   - Speak in a friendly, conversational tone (1-2 sentences per response). Do not sound robotic or rigid.
   - Understand natural everyday speech, casual phrasing, and campus/city slang (e.g., "near the drinking water place" -> Location: "Near drinking water station / cooler", "outside block 3 stairs" -> Location: "Block 3 Staircase Area").

2. DYNAMIC & INTELLIGENT QUESTIONING (NO PREDEFINED RIGID TEMPLATES):
   - Ask smart, object-specific follow-up questions that help uniquely identify and distinguish THAT specific item from others.
   - For a watch: Ask about brand, dial color, strap material, or any unique scratches/engravings.
   - For a smartphone: Ask about brand/model, case color, lock screen wallpaper, or cracks/stickers.
   - For keys: Ask how many keys, keychain type/color, or special tags.
   - For a wallet/bag: Ask about color, brand/material, or distinctive non-sensitive contents (e.g. library card name, keychain).
   - For clothing/bottle: Ask about brand, color, stickers, size, or damage.
   - Never ask for authentication secrets (NO passwords, NO PINs, NO OTPs, NO card CVVs).

3. DYNAMIC INFORMATION EXTRACTION:
   - Continuously update the extracted draft. Accumulate information from the entire conversation.
   - Extract any specific properties into `dynamic_attributes` as clean key-value pairs (e.g. "Brand": "Titan", "Dial Color": "Black", "Strap": "Silver Metallic", "Unique Mark": "Scratch on back clip").
   - Set `is_ready_to_submit` to true as soon as the item type and at least location or key identifying features are known.

4. REQUIRED OUTPUT FORMAT:
You MUST respond with a valid JSON object ONLY. No markdown wrapper outside the JSON if possible, or standard ```json block.
{
    "reply": "<natural conversational response to the user>",
    "draft": {
        "title": "<e.g. Titan Watch | empty string if unknown>",
        "category": "<e.g. Electronics & Accessories | Clothing | Bags & Wallets | Keys | Documents | Other>",
        "location": "<e.g. Near IT Lab (Drinking Water Area) | empty string if unknown>",
        "time": "<e.g. Today morning ~11:00 AM | empty string if unknown>",
        "dynamic_attributes": {
            "<Attribute Name>": "<Attribute Value>"
        },
        "verification_secret": "<e.g. Scratch on back clip / specific engraving | empty string if unknown>",
        "contact": "<email or phone if provided by user | empty string if unknown>",
        "raw_summary": "<1-2 sentence clean summary of all extracted details>"
    },
    "is_ready_to_submit": <true/false>
}
"""

SYSTEM_INSTRUCTION_FOUND = """
You are "Reunite AI Copilot", an intelligent, helpful Lost & Found intake assistant.
Your job is to talk with a user who has FOUND an item, converse naturally, and extract all relevant details dynamically.

CRITICAL BEHAVIORS:
1. NATURAL & CONTEXTUAL CONVERSATION:
   - If user greets, greet warmly and ask what item they found.
   - Speak in a friendly, conversational tone (1-2 sentences per response).
   - Understand natural everyday speech and campus/city slang.

2. DYNAMIC & OBJECT-SPECIFIC QUESTIONING:
   - Ask smart, object-specific questions to collect public distinguishing details without revealing private owner-verification secrets.
   - For a watch: Ask about type (digital/analog), general color, where it was found.
   - For a phone: Ask about brand, case color, location found.
   - For a bag/wallet: Ask about exterior color, location, and where it is currently kept (e.g. front desk, security).

3. DYNAMIC INFORMATION EXTRACTION:
   - Extract any specific properties into `dynamic_attributes` as clean key-value pairs.
   - Set `is_ready_to_submit` to true as soon as the found item type and location are known.

4. REQUIRED OUTPUT FORMAT:
You MUST respond with a valid JSON object ONLY:
{
    "reply": "<natural conversational response to the user>",
    "draft": {
        "title": "<e.g. iPhone 13 / Leather Wallet | empty string if unknown>",
        "category": "<e.g. Electronics | Bags & Wallets | Documents | Keys | Other>",
        "location": "<e.g. Central Library 2nd Floor | empty string if unknown>",
        "time": "<e.g. Today afternoon | empty string if unknown>",
        "dynamic_attributes": {
            "<Attribute Name>": "<Attribute Value>"
        },
        "verification_secret": "<e.g. Kept with campus security / specific hidden mark | empty string if unknown>",
        "contact": "<finder email or phone if provided | empty string if unknown>",
        "raw_summary": "<1-2 sentence clean summary of all extracted details>"
    },
    "is_ready_to_submit": <true/false>
}
"""


def clean_json_text(text: str) -> str:
    """Strip markdown code fences and whitespace from LLM output."""
    text = text.strip()
    if text.startswith("```json"):
        text = text[7:]
    elif text.startswith("```"):
        text = text[3:]
    if text.endswith("```"):
        text = text[:-3]
    return text.strip()


class DynamicTalkAIService:
    @staticmethod
    def handle_turn(
        user_message: str,
        history: list = None,
        report_type: str = "lost",
        current_draft: dict = None
    ) -> dict:
        """
        Processes a single conversational turn with full conversation context.
        Returns:
            {
                "success": bool,
                "reply": str,
                "draft": dict,
                "is_ready_to_submit": bool
            }
        """
        history = history or []
        current_draft = current_draft or {}
        report_type = (report_type or "lost").lower()

        system_instruction = SYSTEM_INSTRUCTION_FOUND if report_type == "found" else SYSTEM_INSTRUCTION_LOST

        # Format conversation context
        formatted_history = []
        for msg in history[-10:]:  # Keep last 10 messages for context
            role = msg.get("sender") or msg.get("role") or "user"
            content = msg.get("text") or msg.get("content") or ""
            if content:
                formatted_history.append(f"{role.upper()}: {content}")

        history_str = "\n".join(formatted_history) if formatted_history else "(No previous history)"
        draft_str = json.dumps(current_draft, indent=2) if current_draft else "(No draft yet)"

        prompt = f"""
{system_instruction}

===========================================
CONVERSATION HISTORY SO FAR:
{history_str}

CURRENT ACCUMULATED DRAFT STATE:
{draft_str}

LATEST USER MESSAGE:
"{user_message}"
===========================================

Now, respond with the updated JSON containing your conversational reply and the accumulated dynamic draft:
"""

        # Try Gemini via FailoverClient first
        try:
            response = client.models.generate_content(
                model=GEMINI_MODEL,
                contents=prompt
            )
            raw_text = response.text or ""
            cleaned = clean_json_text(raw_text)
            parsed = json.loads(cleaned)

            if isinstance(parsed, dict) and "reply" in parsed:
                return {
                    "success": True,
                    "reply": parsed.get("reply", ""),
                    "draft": parsed.get("draft", current_draft),
                    "is_ready_to_submit": bool(parsed.get("is_ready_to_submit", False))
                }
        except Exception as gemini_err:
            print(f"[DynamicTalkAI] Gemini call failed: {gemini_err}. Attempting Mistral fallback...")
            traceback.print_exc()

        # Fallback: Mistral
        try:
            if mistral_client:
                mistral_response = mistral_client.chat.complete(
                    model=MISTRAL_MODEL,
                    messages=[
                        {"role": "system", "content": system_instruction},
                        {"role": "user", "content": prompt}
                    ],
                    response_format={"type": "json_object"}
                )
                raw_text = mistral_response.choices[0].message.content or ""
                cleaned = clean_json_text(raw_text)
                parsed = json.loads(cleaned)

                if isinstance(parsed, dict) and "reply" in parsed:
                    return {
                        "success": True,
                        "reply": parsed.get("reply", ""),
                        "draft": parsed.get("draft", current_draft),
                        "is_ready_to_submit": bool(parsed.get("is_ready_to_submit", False))
                    }
        except Exception as mistral_err:
            print(f"[DynamicTalkAI] Mistral fallback failed: {mistral_err}")
            traceback.print_exc()

        # Graceful fallback if both APIs fail
        return {
            "success": True,
            "reply": f"I noted that! Could you also share where you lost or found it, and any distinct features?",
            "draft": {
                **current_draft,
                "title": current_draft.get("title") or ("Watch" if "watch" in user_message.lower() else "Lost Item"),
                "raw_summary": user_message
            },
            "is_ready_to_submit": bool(current_draft.get("title") or current_draft.get("location"))
        }
