import os
from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeoutError

from dotenv import load_dotenv
from google import genai
from mistralai import Mistral

load_dotenv(override=True)

# ==========================================
# Gemini
# ==========================================

# Support multiple API keys: comma-separated or numbered env variables
GEMINI_API_KEYS = []
primary_key = os.getenv("GEMINI_API_KEY", "").strip()
if primary_key:
    if "," in primary_key:
        GEMINI_API_KEYS.extend([k.strip() for k in primary_key.split(",") if k.strip()])
    else:
        GEMINI_API_KEYS.append(primary_key)

# Also check GEMINI_API_KEY_2, GEMINI_API_KEY_3, etc.
i = 2
while True:
    k = os.getenv(f"GEMINI_API_KEY_{i}")
    if not k:
        break
    k = k.strip()
    if k and k not in GEMINI_API_KEYS:
        GEMINI_API_KEYS.append(k)
    i += 1

if not GEMINI_API_KEYS:
    raise ValueError(
        "GEMINI_API_KEY not found. Please add it to your .env file."
    )

GEMINI_API_KEY = GEMINI_API_KEYS[0]
GEMINI_MODEL = "gemini-2.5-flash"

STANDARD_MODELS = [
    "gemini-2.5-flash",
    "gemini-flash-latest",
    "gemini-2.5-pro",
    "gemini-3.5-flash",
    "gemini-3.7-flash"
]

LITE_MODELS = [
    "gemini-2.5-flash-lite",
    "gemini-3.5-flash-lite",
    "gemini-flash-lite-latest"
]

def is_quota_or_transient_error(e: Exception) -> bool:
    err_str = str(e).lower()
    code = getattr(e, "code", getattr(e, "status_code", None))
    if code in (429, 503, 504, 500):
        return True
    
    keywords = [
        "quota", "exhausted", "limit", "demand", "rate", "unavailable", 
        "overloaded", "resource_exhausted", "capacity", "temporary", "spikes"
    ]
    if any(keyword in err_str for keyword in keywords):
        return True
    return False

# Max seconds to wait for a single API call before abandoning and trying next option
CALL_TIMEOUT_SECONDS = 25

# Initialize the genai clients for each key
clients = [genai.Client(api_key=key) for key in GEMINI_API_KEYS]

class FailoverModels:
    def generate_content(self, model, contents, config=None, **kwargs):
        # Build priority lists
        standard_list = list(STANDARD_MODELS)
        lite_list = list(LITE_MODELS)

        if model:
            # If the requested model is a lite model, put it at start of lite list
            if any(lite in model for lite in LITE_MODELS):
                if model not in lite_list:
                    lite_list.insert(0, model)
            else:
                # Otherwise, it's a standard/accurate model
                if model not in standard_list:
                    standard_list.insert(0, model)

        last_error = None
        
        # Tier 1: Try standard (accurate) models across all API keys first (loop models first)
        for model_name in standard_list:
            for client_idx, genai_client in enumerate(clients):
                try:
                    print(f"[FAILOVER] Trying standard model '{model_name}' using API Key {client_idx + 1}...")
                    with ThreadPoolExecutor(max_workers=1) as ex:
                        future = ex.submit(
                            genai_client.models.generate_content,
                            model=model_name,
                            contents=contents,
                            config=config,
                            **kwargs
                        )
                        response = future.result(timeout=CALL_TIMEOUT_SECONDS)
                    return response
                except FuturesTimeoutError:
                    print(f"[FAILOVER] Standard model '{model_name}' on Key {client_idx + 1} timed out after {CALL_TIMEOUT_SECONDS}s. Trying next option...")
                    last_error = Exception(f"Timeout after {CALL_TIMEOUT_SECONDS}s")
                    continue
                except Exception as e:
                    last_error = e
                    if is_quota_or_transient_error(e):
                        print(f"[FAILOVER] Standard model '{model_name}' hit rate limit/quota limit on Key {client_idx + 1}. Trying next option...")
                        continue
                    else:
                        raise e

        # Tier 2: Try lite models across all API keys as a last resort
        print("[FAILOVER] All standard models exhausted across all keys. Falling back to Lite models...")
        for model_name in lite_list:
            for client_idx, genai_client in enumerate(clients):
                try:
                    print(f"[FAILOVER] Trying lite model '{model_name}' using API Key {client_idx + 1}...")
                    with ThreadPoolExecutor(max_workers=1) as ex:
                        future = ex.submit(
                            genai_client.models.generate_content,
                            model=model_name,
                            contents=contents,
                            config=config,
                            **kwargs
                        )
                        response = future.result(timeout=CALL_TIMEOUT_SECONDS)
                    return response
                except FuturesTimeoutError:
                    print(f"[FAILOVER] Lite model '{model_name}' on Key {client_idx + 1} timed out after {CALL_TIMEOUT_SECONDS}s. Trying next option...")
                    last_error = Exception(f"Timeout after {CALL_TIMEOUT_SECONDS}s")
                    continue
                except Exception as e:
                    last_error = e
                    if is_quota_or_transient_error(e):
                        print(f"[FAILOVER] Lite model '{model_name}' hit rate limit/quota limit on Key {client_idx + 1}. Trying next option...")
                        continue
                    else:
                        raise e
        
        if last_error:
            raise last_error

class FailoverClient:
    def __init__(self):
        self.models = FailoverModels()

    def __getattr__(self, name):
        return getattr(clients[0], name)

client = FailoverClient()

# ==========================================
# Mistral
# ==========================================

MISTRAL_API_KEY = os.getenv("MISTRAL_API_KEY")
MISTRAL_MODEL = "mistral-small-latest"
mistral_client = Mistral(api_key=MISTRAL_API_KEY)

if not MISTRAL_API_KEY:
    raise ValueError(
        "MISTRAL_API_KEY not found. Please add it to your .env file."
    )