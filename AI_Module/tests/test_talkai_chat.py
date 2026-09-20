import json
import sys
from pathlib import Path

root_dir = Path(__file__).resolve().parent.parent.parent
if str(root_dir) not in sys.path:
    sys.path.insert(0, str(root_dir))
ai_mod = root_dir / "AI_Module"
if str(ai_mod) not in sys.path:
    sys.path.insert(0, str(ai_mod))

from AI_Module.TALKAI.dynamic_chat import DynamicTalkAIService

def run_test():
    history = []
    draft = {}

    turns = [
        "hello",
        "I have lost my Titan watch",
        "I have lost it near the IT Lab drinking water place",
        "It has a black dial, silver mesh strap, and a small scratch on the back clip"
    ]

    for turn in turns:
        print(f"\n==========================================")
        print(f"USER: {turn}")
        print(f"==========================================")
        res = DynamicTalkAIService.handle_turn(
            user_message=turn,
            history=history,
            report_type="lost",
            current_draft=draft
        )
        print("AI REPLY:", res.get("reply"))
        draft = res.get("draft", {})
        print("EXTRACTED DRAFT:")
        print(json.dumps(draft, indent=2))
        print("READY TO SUBMIT:", res.get("is_ready_to_submit"))
        
        history.append({"role": "user", "text": turn})
        history.append({"role": "assistant", "text": res.get("reply", "")})

if __name__ == "__main__":
    run_test()
