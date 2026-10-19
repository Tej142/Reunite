# Reunite Matching Engine v2 Specification
**Structured Attribute-Aware Verification & Explainable Matchmaking**

- **Document Version:** 2.0.0
- **Status:** Proposed (Awaiting Approval)
- **Author:** Senior ML / Backend Engineer & QA Lead
- **Target Systems:** 
  - PHP Backend (`Backend/functions.php`, `Backend/reports.php`, `Backend/sync_matches.php`) on InfinityFree
  - Flask AI Service (`flask_server.py`, `AI_Module/database/chroma_manager.py`) on Render (512MB RAM constraint)
  - Frontend Intake & UI (`Frontend/js/reporting-modes.js`, `Frontend/js/flask-ai-service.js`, `Frontend/item.php`, `Frontend/profile.php`, `Frontend/admin.php`)

---

## 1. Executive Summary & Problem Definition

### 1.1 The Core Problem
In Matching Engine v1, matches were generated primarily based on:
1. Category and title text equality (which in standard workflows defaults to `"Electronics / Laptop"` == `"Electronics / Laptop"`, awarding 90+ points in the PHP fallback matcher).
2. CLIP multimodal text embedding similarity and DINOv2 visual cosine similarity.

Because sentence embeddings for items in the same general class (e.g., "Dell laptop" vs "Lenovo laptop") occupy contiguous geometric regions in embedding space (cosine similarity $> 0.80$), and because **no negative constraints (vetoes or penalties) existed anywhere in the pipeline**, any two items with similar category descriptions were scored at **98.00% similarity**, triggering persistent match records, push notifications, and external Brevo emails to both users.

### 1.2 The Solution: Matching Engine v2
Matching Engine v2 introduces a **two-stage hybrid verification architecture**:
- **Stage 1 (Candidate Retrieval):** Fast multimodal vector search (ChromaDB DINOv2 + CLIP) and category-scoped SQL candidate retrieval to retrieve candidate pairs.
- **Stage 2 (Attribute-Aware Rule Engine):** Deterministic verification layer that enforces **Hard Vetoes**, applies graduated **Penalties** and **Boosts**, assigns **Confidence Tiers** (`STRONG`, `POSSIBLE`, `DISCARD`), and generates privacy-preserving **Explainable AI (XAI) Reasons** stored in database.

---

## 2. End-to-End Pipeline Architecture

```
[New Report Submitted (Lost or Found)]
                 │
                 ▼
[Stage 1: Attribute Extraction & Normalization]
  • Extract attributes via Gemini LLM (with Mistral fallback) or structured intake form.
  • Pass attributes through Shared Normalizer (canonical brand, color family, series).
  • Persist normalized JSON into `normalized_attributes` and `digital_dna`.
                 │
                 ▼
[Stage 2: Candidate Retrieval]
  • Filter: Opposite report type (`lost` vs `found`), active status (`active`/`Pending`), `user_id != candidate_user_id`.
  • Vector Search: ChromaDB top-K ($K=15$) multimodal embeddings (CLIP text + DINOv2 image).
  • Database Index: Category family candidate retrieval.
                 │
                 ▼
[Stage 3: Deterministic Rule Verification Engine]
  ├─► Check HARD VETOES:
  │     • Brand mismatch (e.g. Dell vs Lenovo) ──────────────────┐
  │     • Category / Subtype conflict (Laptop vs Smartphone)    │
  │     • Private identifier conflict (Serial/IMEI/ID mismatch) │──► [VETO TRIGGERED]
  │     • Temporal impossibility (Found date < Lost date - 24h) │         │
  │     • Self-match (Same user)                                │         ▼
  │                                                              │    [DISCARD / 0%]
  ├─► Calculate PENALTIES:                                       │    No match, No alert
  │     • Model/series contradiction (e.g. Latitude vs XPS)     │
  │     • Color family contradiction                            │
  │     • Capacity/size conflict (13" vs 16")                   │
  │     • Physical distance & time elapsed                      │
  │                                                              │
  ├─► Calculate BOOSTS:                                          │
  │     • Canonical Brand + Model match                         │
  │     • Private identifier verified match (Near-Certain)      │
  │     • Matching distinctive marks (stickers, scratches)      │
  │     • Spatial-temporal proximity (<24h, same building)      │
  │     • High visual cosine similarity                         │
  │                                                              │
  └─► Handle UNKNOWN Attributes:                                 │
        • Neutral impact on score                               │
        • Capping rule: Missing brand caps confidence at POSSIBLE│
                 │                                               │
                 ▼                                               │
[Stage 4: Scoring & Confidence Tier Assignment]                 │
  • Final Score = clamp(0..100, Base_Similarity + Boosts - Penalties)
  • Determine Tier:                                              │
      ├── STRONG (Score >= 75%, confirmed key attributes)        │
      ├── POSSIBLE (Score 50% - 74%, or unconfirmed brand)       │
      └── DISCARD (Score < 50% or Hard Veto) ◄───────────────────┘
                 │
                 ▼
[Stage 5: Policy-Based Decision Gate]
  ├── STRONG: Insert match record, create in-app notifications for both users, send email alerts.
  ├── POSSIBLE: Insert match record (status 'possible'), display on Owner's Dashboard ONLY. NO push/email.
  └── DISCARD: Do not insert active match; record in audit log with rule hit.
```

---

## 3. Normalized Attribute Schema & Dictionaries

### 3.1 Category-Specific Attribute Schemas

Each attribute is stored with:
- `canonical`: Normalized string from standard dictionary.
- `source`: `'user_text'` | `'structured_question'` | `'image_analysis'` | `'ai_extracted'`.
- `confidence`: Float between `0.0` and `1.0`.

#### Schema Definitions

| Category Family | Subtypes | Key Attributes | Private Verification Identifiers (Server-Side Only) |
| :--- | :--- | :--- | :--- |
| **Electronics - Laptops** | `laptop`, `chromebook`, `macbook`, `2-in-1` | `brand`, `series_model`, `color_family`, `screen_size`, `chassis_material`, `stickers_marks` | `serial_number`, `asset_tag`, `login_username` |
| **Electronics - Phones & Tablets** | `smartphone`, `tablet`, `feature_phone` | `brand`, `series_model`, `color_family`, `storage_capacity`, `case_color`, `screen_condition` | `imei_last4`, `phone_number_last4`, `lockscreen_wallpaper` |
| **Electronics - Audio & Wearables** | `wireless_earbuds`, `headphones`, `smartwatch`, `smartband` | `brand`, `model`, `color_family`, `case_included`, `band_material` | `engraving`, `mac_address_last4` |
| **Wallets & Small Leather Goods** | `bifold`, `trifold`, `cardholder`, `clutch`, `coin_pouch` | `brand`, `color_family`, `material`, `distinctive_marks` | `id_cards_names`, `card_issuer_names` (never full numbers) |
| **Bags & Luggage** | `backpack`, `tote_bag`, `duffel`, `handbag`, `laptop_bag` | `brand`, `color_family`, `size_tier`, `pattern`, `keychains` | `interior_contents_summary` |
| **Keys & Access** | `single_key`, `keychain_bunch`, `vehicle_fob`, `access_card` | `key_count`, `fob_brand`, `keychain_figure`, `lanyard_color` | `room_key_stamped_number`, `card_printed_id` |
| **IDs & Documents** | `college_id`, `driver_license`, `aadhaar_govt_id`, `bank_card`, `notebook` | `document_type`, `issuing_authority` | `full_name_on_card`, `id_number_hash` |
| **Watches & Jewelry** | `analog_watch`, `ring`, `necklace`, `bracelet` | `brand`, `metal_color`, `dial_shape`, `gem_type` | `rear_engraving`, `hallmark_code` |
| **Clothing & Eyewear** | `jacket`, `hoodie`, `spectacles`, `sunglasses` | `brand`, `color_family`, `size_label`, `frame_shape` | `internal_tag_name`, `prescription_type` |
| **Bottles & Tumblers** | `steel_flask`, `plastic_bottle`, `glass_bottle` | `brand`, `color_family`, `capacity_volume`, `stickers` | `bottom_marking`, `engraved_initials` |

---

### 3.2 Canonical Brand Dictionaries & Synonyms

A deterministic brand normalizer maps user variations, misspellings, and sub-brands to the canonical parent brand:

```json
{
  "dell": {
    "canonical": "Dell",
    "aliases": ["dell", "del", "alienware", "latitude", "inspiron", "xps", "vostro", "precision", "g-series"],
    "sub_brands": {
      "alienware": "Alienware",
      "latitude": "Latitude",
      "inspiron": "Inspiron",
      "xps": "XPS",
      "vostro": "Vostro"
    }
  },
  "lenovo": {
    "canonical": "Lenovo",
    "aliases": ["lenovo", "thinkpad", "ideapad", "legion", "yoga", "loq", "thinkbook"],
    "sub_brands": {
      "thinkpad": "ThinkPad",
      "ideapad": "IdeaPad",
      "legion": "Legion",
      "yoga": "Yoga"
    }
  },
  "hp": {
    "canonical": "HP",
    "aliases": ["hp", "hewlett packard", "hewlett-packard", "pavilion", "envy", "omen", "victus", "spectre", "elitebook", "probook"],
    "sub_brands": {
      "pavilion": "Pavilion",
      "omen": "Omen",
      "spectre": "Spectre",
      "envy": "Envy"
    }
  },
  "apple": {
    "canonical": "Apple",
    "aliases": ["apple", "macbook", "macbook pro", "macbook air", "iphone", "ipad", "airpods", "airpod", "iwatch", "apple watch"],
    "sub_brands": {
      "macbook": "MacBook",
      "iphone": "iPhone",
      "ipad": "iPad",
      "airpods": "AirPods"
    }
  },
  "samsung": {
    "canonical": "Samsung",
    "aliases": ["samsung", "galaxy", "galaxy book", "galaxy buds"],
    "sub_brands": {
      "galaxy": "Galaxy"
    }
  },
  "asus": {
    "canonical": "Asus",
    "aliases": ["asus", "rog", "republic of gamers", "tuf", "zenbook", "vivobook"]
  },
  "acer": {
    "canonical": "Acer",
    "aliases": ["acer", "predator", "nitro", "aspire", "swift"]
  },
  "zebronics": {
    "canonical": "Zebronics",
    "aliases": ["zebronics", "zeb", "zeb-"]
  },
  "boat": {
    "canonical": "boAt",
    "aliases": ["boat", "bo-at", "airdopes", "bassheads"]
  },
  "wildcraft": {
    "canonical": "Wildcraft",
    "aliases": ["wildcraft", "wild craft"]
  },
  "milton": {
    "canonical": "Milton",
    "aliases": ["milton", "thermosteel"]
  }
}
```

---

### 3.3 Color Family Compatibility Matrix

Color perception fluctuates due to lighting, camera white balance, and terminology. Colors are grouped into canonical color families with defined compatibility neighbors:

```json
{
  "black": { "family": "BLACK", "compatible_neighbors": ["GREY", "DARK_BLUE"] },
  "dark grey": { "family": "GREY", "compatible_neighbors": ["BLACK", "SILVER"] },
  "space grey": { "family": "GREY", "compatible_neighbors": ["BLACK", "SILVER", "GREY"] },
  "silver": { "family": "SILVER", "compatible_neighbors": ["GREY", "WHITE"] },
  "white": { "family": "WHITE", "compatible_neighbors": ["SILVER", "CREAM"] },
  "dark blue": { "family": "BLUE", "compatible_neighbors": ["BLACK", "BLUE"] },
  "blue": { "family": "BLUE", "compatible_neighbors": ["DARK_BLUE", "LIGHT_BLUE"] },
  "red": { "family": "RED", "compatible_neighbors": ["MAROON", "ORANGE"] },
  "brown": { "family": "BROWN", "compatible_neighbors": ["TAN", "BEIGE", "BLACK"] }
}
```

- **Compatible:** Exact family match or neighbor under poor lighting (no penalty).
- **Contradictory:** Distant families (e.g. `WHITE` vs `BLACK`, `RED` vs `GREEN`, `BLUE` vs `YELLOW`) $\rightarrow$ triggers **Strong Penalty (-30%)**.

---

## 4. Rule Engine Specification (Configurable Rules)

All rules and thresholds are governed by a single configuration object (`matching_rules.json` / `Backend/config/matching_rules.php`):

### 4.1 Hard Vetoes (Zero Match, Immediate Rejection)
If ANY hard veto condition evaluates to true, the match score is set to `0.0`, the tier is set to `DISCARD`, no database match record is activated, and zero notifications/emails are dispatched.

1. **`VETO_BRAND_MISMATCH`**: Both reports have a known canonical brand, and `canonical_brand_A != canonical_brand_B`.
   - *Example:* Lost `Dell` vs Found `Lenovo` $\rightarrow$ **VETOED**.
2. **`VETO_CATEGORY_INCOMPATIBLE`**: Both reports belong to incompatible category families or subtypes.
   - *Example:* Lost `Laptop` vs Found `Smartphone` $\rightarrow$ **VETOED**.
   - *Example:* Lost `Wireless Mouse` vs Found `Laptop` $\rightarrow$ **VETOED**.
3. **`VETO_IDENTIFIER_MISMATCH`**: Both reports have a non-empty private unique identifier (e.g., serial number, student ID number, IMEI), and normalized values do not match.
   - *Example:* Serial `DL12345` vs Serial `LN99887` $\rightarrow$ **VETOED**.
4. **`VETO_TEMPORAL_IMPOSSIBILITY`**: Found date is earlier than lost date by more than `MAX_TEMPORAL_TOLERANCE_HOURS` (Default: 24 hours to accommodate timezone or human memory jitter). An item cannot be found days before it was lost.
   - *Example:* Lost on `Oct 19` vs Found on `Oct 10` $\rightarrow$ **VETOED**.
5. **`VETO_SAME_USER`**: Both reports were filed by the same authenticated user (`lost_user_id == found_user_id`).
6. **`VETO_INACTIVE_REPORT`**: Either report is marked `closed`, `reunited`, or `rejected`.
7. **`VETO_SAME_REPORT_TYPE`**: Comparing `lost` to `lost`, or `found` to `found`.

---

### 4.2 Strong Penalties
Penalties subtract percentage points from the composite similarity score:

| Rule Name | Condition | Penalty |
| :--- | :--- | :--- |
| **`PENALTY_MODEL_CONTRADICTION`** | Same canonical brand, but both reports have distinct known sub-brands or models (e.g. `Latitude 5320` vs `XPS 15`). | **-35%** |
| **`PENALTY_COLOR_CONTRADICTION`** | Both reports have known, non-compatible color families (e.g. `Red` vs `White`). | **-30%** |
| **`PENALTY_SIZE_MISMATCH`** | Both reports have known differing physical sizes or storage (e.g. `13-inch` vs `16-inch`, `64GB` vs `512GB`). | **-25%** |
| **`PENALTY_LOCATION_DIVERGENCE`** | Locations specified are distinct non-overlapping buildings/zones with no shared keywords. | **-20%** |
| **`PENALTY_TEMPORAL_STALE`** | Found date is $> 45$ days after lost date. | **-15%** |

---

### 4.3 Boosts
Boosts add percentage points to the composite similarity score:

| Rule Name | Condition | Boost |
| :--- | :--- | :--- |
| **`BOOST_IDENTIFIER_MATCH`** | Private identifiers (serial number, student roll number, IMEI) match exactly. | **+45%** *(Auto-promotes to STRONG)* |
| **`BOOST_BRAND_AND_MODEL_MATCH`** | Both canonical brand and model/series match. | **+25%** |
| **`BOOST_DISTINCTIVE_MARKS_MATCH`** | Matching unique stickers, engravings, or custom skin description tokens. | **+15%** |
| **`BOOST_PROXIMITY_TIME_SPACE`** | Found within 6 hours and in the exact same room/hall as lost report. | **+10%** |
| **`BOOST_HIGH_VISUAL_SIMILARITY`** | DINOv2 cosine visual similarity $> 0.88$. | **+10%** |

---

### 4.4 Handling Unknown Attributes & Confidence Capping
- If an attribute is unknown on either side (e.g., finder reported "Laptop, brand unknown", or lost report did not specify model):
  - **No Penalty** is applied (neutral impact).
  - **No Boost** is awarded.
  - **Tier Capping Rule:** If `brand` is `unknown` on either report, the match **cannot reach the `STRONG` tier** regardless of embedding score. It is capped at `POSSIBLE` to prevent false push notifications, unless a private unique identifier matches.

### 4.5 Multimodal Conflict Flagging
- If a user's text description says `"Dell"` but image analysis (e.g. Gemini Vision or OCR) detects a `"Lenovo"` badge on the photo:
  - Do NOT trigger a hard veto immediately (user could have made a typo or photo may be illustrative).
  - Reduce `brand.confidence` to `0.4` and flag `sensor_conflict: true`.
  - Treat the brand as unconfirmed/unknown for tier calculation.

---

## 5. Confidence Tiers & Decision Gates

| Tier | Score Range | Criteria | System Actions |
| :--- | :--- | :--- | :--- |
| **`STRONG`** | $\ge 75\%$ | • Zero Hard Vetoes.<br>• Confirmed Brand match.<br>• No unresolved strong penalties.<br>• Score $\ge 75\%$. | 1. Persistent match inserted (`status: 'pending'`, `tier: 'STRONG'`).<br>2. In-app notifications dispatched to both users.<br>3. Brevo email alerts sent.<br>4. Prominently featured on user dashboards. |
| **`POSSIBLE`** | $50\% - 74\%$ | • Zero Hard Vetoes.<br>• Compatible category.<br>• Brand matching or unknown on one side.<br>• Score between $50\%$ and $74\%$. | 1. Match inserted with `confidence_tier: 'POSSIBLE'`.<br>2. Appears on Owner Dashboard in **"Possible Matches (Unverified)"** section.<br>3. **NO push notification, NO email** (prevents alert fatigue). |
| **`DISCARD`** | $< 50\%$ | • Any Hard Veto triggered, or composite score $< 50\%$. | 1. No active match created.<br>2. Discard logged in `matching_audit_log` with the triggered veto rule for telemetry. |

---

## 6. Stored Explainable AI Schema (`match_reasons`)

Every match record in the database will persist an auditable JSON object in `matches.match_reasons`:

```json
{
  "engine_version": "2.0.0",
  "confidence_tier": "STRONG",
  "final_score": 86.5,
  "component_scores": {
    "vector_clip_text": 84.0,
    "vector_dino_visual": 79.5,
    "fused_base_similarity": 82.4,
    "rule_adjustment": 4.1
  },
  "verified_attributes": {
    "category": { "lost": "Electronics / Laptop", "found": "Electronics / Laptop", "status": "MATCH" },
    "brand": { "lost": "Dell", "found": "Dell", "status": "MATCH" },
    "model": { "lost": "Latitude 5320", "found": "Latitude Series", "status": "COMPATIBLE" },
    "color": { "lost": "Dark Grey / Multi-skin", "found": "Dark Grey / Black", "status": "COMPATIBLE" }
  },
  "unknown_attributes": ["serial_number"],
  "rules_hit": [
    { "rule": "BOOST_BRAND_AND_MODEL_MATCH", "delta": "+25.0", "reason": "Brand (Dell) and series (Latitude) match" },
    { "rule": "BOOST_DISTINCTIVE_MARKS", "delta": "+15.0", "reason": "Distinctive nautical skin tokens matched" },
    { "rule": "PENALTY_LOCATION_DIVERGENCE", "delta": "-20.0", "reason": "CME 3rd year room vs Main Block corridor" }
  ],
  "why_matched_summary": "Confirmed Dell Latitude laptop with matching dark grey chassis and distinctive skin marks. Compatible location on campus."
}
```

### Privacy Guarantee
> [!CAUTION]
> **Zero Private Data Leakage**: Private identifiers (`serial_number`, `imei`, `student_id_number`, `wallet_contents`) are compared **strictly server-side**. They are NEVER written to `match_reasons`, notifications, emails, or public APIs. Only a boolean flag (`"private_identifier_verified": true`) is recorded.

---

## 7. Intake Workflow Improvements

To supply the Rule Engine with clean canonical data, all 3 reporting modes will be upgraded to prompt and capture key attributes:

### 7.1 Instant Choice (Guided Wizard - Mode 2)
- When "Electronics / Laptop" or "Smartphone" is selected:
  - Step 2 dynamically renders structured pill choices:
    - **Brand**: `Dell`, `Lenovo`, `HP`, `Apple`, `Samsung`, `Asus`, `Acer`, `Other (specify)`, and **"I don't know / Not sure"**.
    - **Color**: Standard palette pills.
    - **Model / Series**: Text field with smart suggestions.
    - **Identifiers**: Optional confidential field marked with a privacy shield: *"Private verification for claim approval only"*.

### 7.2 Image + Description (Mode 1)
- The existing Gemini Vision analyzer outputs structured JSON with detected brand, series, and colors.
- If the user description contains contradictory text, the UI displays an inline badge: *"AI detected Dell; your text says Lenovo. Please verify before submitting."*

### 7.3 Talk to AI Copilot (Mode 3)
- If the user provides a transcript like *"I found a laptop in the CME lab"*, the copilot prompt is updated to ask:
  > *"Got it! Could you tell me what brand or model the laptop is (like Dell, Lenovo, HP), or if it has any stickers or case?"*
- If the user replies *"I'm not sure"* or *"no idea"*, the copilot records `brand = "unknown"` with confidence `0.0`.

---

## 8. Database Migration Plan (Idempotent)

```sql
-- Idempotent Migration: Matching v2 Schema Additions

-- 1. Upgrade matches table
ALTER TABLE `matches` 
  ADD COLUMN IF NOT EXISTS `engine_version` VARCHAR(20) NOT NULL DEFAULT '2.0.0',
  ADD COLUMN IF NOT EXISTS `confidence_tier` ENUM('STRONG', 'POSSIBLE', 'DISCARD') NOT NULL DEFAULT 'STRONG',
  ADD COLUMN IF NOT EXISTS `match_reasons` LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS `needs_admin_review` TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Add composite index for dashboard performance
CREATE INDEX IF NOT EXISTS `idx_matches_tier_status` ON `matches` (`confidence_tier`, `status`);

-- 3. Cache normalized attributes on lost_reports and found_reports for sub-millisecond evaluation
ALTER TABLE `lost_reports`
  ADD COLUMN IF NOT EXISTS `normalized_attributes` LONGTEXT NULL;

ALTER TABLE `found_reports`
  ADD COLUMN IF NOT EXISTS `normalized_attributes` LONGTEXT NULL;
```

---

## 9. Re-Scoring Tool & Legacy Data Migration Plan

### 9.1 Dry-Run First Principle
A dedicated admin CLI / web script `Backend/rescore_matches.php` will be deployed.
- By default, it runs with `--dry-run=1`.
- It evaluates all existing matches in the `matches` table against Matching Engine v2 rules and prints a comparative matrix:

| Match ID | Lost Item | Found Item | Old Score | New Score | Old Tier | New Tier | Action |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | Mouse | Mouse | 98% | 96% | STRONG | STRONG | Retain (Verified handover) |
| **2** | Mouse | Laptop | 35% | 0% | STRONG | DISCARD | Invalidate (Category Veto) |
| **17** | Dell Laptop | Dell Laptop | 98% | 88% | STRONG | STRONG | Retain (`STRONG`) |
| **18** | Dell Laptop | Lenovo Laptop | 98% | 0% | STRONG | DISCARD | **INVALIDATE (`VETO_BRAND_MISMATCH`)** |

### 9.2 Safe Transition Rules
1. **Claims in Progress Protected:** Matches with status `pending_founder_approval`, `ready_for_handover`, or `verified` are **never altered automatically**. If they violate rules, they are marked `needs_admin_review = 1`.
2. **Invalidated Matches:** Matches scoring `< 50%` or triggering Hard Vetoes are marked `status = 'rejected'`, `confidence_tier = 'DISCARD'`. **No data is ever deleted**.
3. **Stale Notifications:** Unread notifications referencing invalidated matches are updated with `is_read = 1` or archived to prevent user confusion.

---

## 10. Performance, Resource Limits & Fallback Strategy

- **Render Memory Safety (512 MB Limit):**
  - **Zero new ML models.** No additional heavy HuggingFace/PyTorch libraries will be loaded into Flask.
  - The Rule Engine is implemented in lightweight native PHP / Python using dictionary hash-maps and regex.
  - Attribute extraction relies on the existing Google Gemini API with Mistral fallback, followed by regex rule-based extraction if external APIs are unreachable.
- **Idempotency:** Re-running the matching pipeline for any report uses unique composite keys `(lost_id, found_id)` and updates existing rows rather than creating duplicates.
- **Latency Target:** $< 10\text{ms}$ rule evaluation per candidate pair in PHP.

---

## 11. Decisions Requested from Product Owner

Before we begin implementation in Phase 2, please review and approve the following design decisions:

1. **Brand Mismatch Veto:** Confirm that any confirmed brand mismatch (e.g., Dell vs Lenovo) is a **HARD VETO** (0% match, immediate discard). *(Recommended: Yes)*.
2. **Notification Policy:** Confirm that **only `STRONG` tier matches ($\ge 75\%$) trigger push notifications and Brevo emails**, while `POSSIBLE` tier matches ($50\% - 74\%$) appear exclusively on the user's web dashboard under "Possible Matches". *(Recommended: Yes)*.
3. **Required Intake Fields:** Confirm that `Brand` and `Color` will be strongly prompted (with an explicit *"I don't know / Not sure"* option) across all reporting modes for Electronics, Bags, and Wallets. *(Recommended: Yes)*.
4. **Temporal Tolerance:** Confirm that an item found $> 24$ hours before it was reported lost is treated as an impossible match (**HARD VETO**). *(Recommended: Yes, with 24h tolerance for timezone/memory offset)*.
