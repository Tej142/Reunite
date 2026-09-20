from datetime import datetime, timedelta, timezone

from google import genai

from config import GEMINI_API_KEY


# =========================================================
# Gemini Live Configuration
# =========================================================

LIVE_MODEL = "gemini-3.1-flash-live-preview"

TOKEN_USES = 1
TOKEN_EXPIRE_MINUTES = 30


class LiveSessionConfig:

    def __init__(self):

        self.client = genai.Client(
            api_key=GEMINI_API_KEY
        )
    # =====================================================
    # Live Session Configuration
    # =====================================================

    def build_config(
        self,
        system_instruction: str
    ) -> dict:
        """
        Build the configuration used when the
        frontend starts a Gemini Live session.
        """

        return {
            "response_modalities": ["AUDIO"],
            "system_instruction": system_instruction,
            "input_audio_transcription": {},
            "output_audio_transcription": {}
        }

    # =====================================================
    # Ephemeral Authentication
    # =====================================================

    def create_ephemeral_token(
        self,
        system_instruction: str
    ):
        """
        Create a short-lived Gemini Live authentication token.

        The permanent API key remains on the backend.

        The TalkAI system instruction is attached to the
        Live session constraints.
        """

        if not isinstance(system_instruction, str):
            raise ValueError(
                "system_instruction must be a string."
            )

        if not system_instruction.strip():
            raise ValueError(
                "system_instruction cannot be empty."
            )

        now = datetime.now(timezone.utc)

        expire_time = (
            now + timedelta(
                minutes=TOKEN_EXPIRE_MINUTES
            )
        )

        token = self.client.auth_tokens.create(
            config={
                "uses": TOKEN_USES,
                "expire_time": expire_time,

                "live_connect_constraints": {
                    "model": LIVE_MODEL,

                    "config": {
                        "response_modalities": ["AUDIO"],

                        "system_instruction": system_instruction,

                        "input_audio_transcription": {},
                        "output_audio_transcription": {}
                    }
                }
            }
        )

        return {
            "success": True,
            "token": token.name,
            "model": LIVE_MODEL
        }