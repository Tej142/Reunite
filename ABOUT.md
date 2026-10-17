# Reunite — Complete Platform Documentation

**Version:** 2.4.0
**Live URL:** https://reunite.site.je
**Stack:** PHP + MySQL | Python Flask | DINOv2 + CLIP | ChromaDB | InfinityFree Cloud

---

## TABLE OF CONTENTS

1. What Is Reunite?
2. The Two Core Roles
3. User Journey — Step by Step (User's POV)
4. The 3 Recovery Modes (M1 / M2 / M3)
5. Internal Architecture — How It Actually Works
6. Database Schema (13 Tables)
7. AI Engine — Deep Dive
8. Security & Encryption
9. Admin Panel
10. Full System Flow Diagram

---

## 1. WHAT IS REUNITE?

Reunite is a smart, AI-powered campus Lost & Found platform. It replaces
the traditional physical notice board with a digital system that:

- Lets users report lost or found items online
- Automatically matches them using neural-network vision AI
- Guides both parties through a verified, secure handover process
- Tracks everything end-to-end: from report submission to "Reunited" status

---

## 2. THE TWO CORE ROLES

| Role             | What they do                                              |
|------------------|-----------------------------------------------------------|
| Person who LOST  | Reports their lost item with photo + description          |
| Person who FOUND | Reports what they found with photo + location             |

Both roles use the same platform. The AI engine automatically tries to
cross-match them.

---

## 3. USER JOURNEY — STEP BY STEP

### Step 1 — Sign Up / Log In

- Visit the site
- Create an account: full name, campus email, password (bcrypt hashed)
- All features are gated behind login for trust and accountability
- Users get a default trust_score of 100 (tracked per account)

### Step 2 — Home Dashboard (home.php)

After login, user sees the Home page with live statistics:
- Items Reunited (from recovery_cases + verified matches)
- Active Searches (open lost + found reports not yet matched)
- Reports This Week (based on date_lost / date_found in last 7 days)
- AI Match Accuracy (avg similarity_score across verified matches)

Quick action buttons: "Report Lost Item" and "Report Found Item"

### Step 3 — Report a Lost Item (report-lost-item.php)

User fills out:
- Item category (Electronics, Bag, Keys, ID Card, etc.)
- Title and description
- Date + time lost
- Location where last seen
- Photo upload (optional but strongly recommended for AI matching)

Two input modes available:
- Instant Choice: fill out a structured form
- Talk to AI: chat naturally with the AI, which extracts all fields conversationally

On submit, PHP backend (reports.php) saves the entry to lost_reports table.
The Flask AI server is then called to generate a Digital DNA fingerprint
and store vector embeddings into ChromaDB.

### Step 4 — Report a Found Item (report-found-item.php)

User fills out:
- Item name, category, description
- Where they found it
- Date found
- Photo of the item

On submit, same pipeline runs as above but for found_reports table.
AI immediately runs vector search to find potential matches with existing lost reports.

### Step 5 — AI Smart Matching (automatic)

This is the core engine. After any report submission:

1. Flask AI server extracts a DINOv2 visual vector from the image (384 dimensions)
2. Flask AI server extracts a CLIP text vector from description + category (512 dims)
3. Both vectors are stored in ChromaDB (two separate vector collections)
4. A "Late Fusion" similarity search runs against existing reports in ChromaDB
5. Visual similarity (DINOv2) + Text similarity (CLIP) are blended with dynamic weights
6. Time decay is applied: older reports get lower weight (180-day decay window)
7. Matches above a threshold are saved to the matches table in MySQL
8. Notifications are created in the notifications table for both users

### Step 6 — Notification Received

- Bell icon in navbar polls notifications.php (PHP API) every few seconds
- Unread count badge shows on the bell icon
- Clicking a notification marks it read and navigates to item.php (Item Detail)

### Step 7 — Item Detail Page (item.php)

Shows:
- Full item photo + description + location + date
- AI match score (e.g. "87% visual match")
- Finder's profile info
- Three action buttons for recovery (M1 / M2 / M3)

### Step 8 — Search Page (search.php)

- Browse all active reports
- Filter by: category, date range, location, keywords
- Each result shows item thumbnail, title, status badge, "View" button
- Useful if you don't want to wait for AI to ping you

### Step 9 — Profile Page (profile.php)

- All your reports with live status: Active / Matched / Claimed / Reunited
- Account settings: name, email, password change

### Step 10 — Notification Center (nav.php component)

- Shows all past notifications with human-readable timestamps
  ("5m ago", "Yesterday", etc.)
- One-click mark all as read
- Deep links to the relevant item or claim

---

## 4. THE 3 RECOVERY MODES

### Mode 1 — Direct Ownership Claim (M1)

Flow:
1. Claimant clicks "Claim This Item" on the found item page
2. System displays dynamic verification questions (from question_sets table),
   generated per category (e.g. for Electronics: "What is the brand?",
   "Describe any scratches or damage", "What was the last app open?")
3. Claimant submits their answers as proof_details
4. A record is created in the recovery_cases table with status = 'pending_verification'
5. Admin reviews the claim in the admin panel
6. If approved: status -> 'approved', item status -> 'claimed'
7. Handover is coordinated offline or via M2 OTP

### Mode 2 — OTP Handover (M2)

Flow:
1. System generates a 6-digit OTP stored in matches.otp with an expiry timestamp
2. OTP is shared with the item owner (claimant)
3. During physical meetup, the owner gives the OTP to the finder
4. Finder enters the OTP on the platform to confirm physical handover
5. System validates OTP + expiry, sets status to 'handed_off' / 'reunited'
6. Both records (lost_reports + found_reports) are closed

### Mode 3 — Admin-Mediated Return (M3)

Flow:
1. User requests admin help from the item detail page
2. A recovery case is created with return_type = 'admin_office'
3. Both parties bring the item to the campus lost & found office
4. Admin verifies identity and item, manually resolves the case
5. Status set to 'approved' -> 'handed_off' by admin

---

## 5. INTERNAL ARCHITECTURE — HOW IT ACTUALLY WORKS

### 5.1 Request Flow (PHP Side)

    Browser (JS/AJAX)
          |
          v
    PHP Frontend (Frontend/*.php)
          |
          v
    PHP Backend APIs (Backend/*.php)
      - login.php          --> Auth + session
      - registration.php   --> User creation + bcrypt
      - reports.php        --> CRUD for lost/found reports
      - claims.php         --> Ownership claim lifecycle
      - notifications.php  --> Notification fetch/mark-read
      - admin_api.php      --> Admin panel operations
      - profile.php        --> User profile read/update
          |
          v
    MySQL / MariaDB (InfinityFree cloud DB)
      - 13 tables (see Section 6)

### 5.2 AI Request Flow (Flask Side)

    PHP Backend calls Flask AI server via HTTP (curl / file_get_contents)
          |
          v
    Python Flask Server (flask_server.py)
          |
          +-- /report/start              --> Question engine (per-category forms)
          +-- /report/talk_to_ai/chat    --> Multi-turn AI intake (conversational)
          +-- /new-report                --> Digital DNA generation
          +-- /report/embed-and-store    --> DINOv2 + CLIP vector extraction + ChromaDB store
          +-- /report/search-matches     --> Late Fusion similarity search
          +-- /report/normalize          --> Normalize + clean report text
          +-- /status                    --> Health check (ChromaDB collection counts)
          |
          v
    AI_Module/ (Python modules)
      - analyzers/dinov2_extractor.py   --> DINOv2 visual embedding (384-dim)
      - analyzers/clip_extractor.py     --> CLIP text embedding (512-dim)
      - analyzers/image_analyzer.py     --> Image attribute extraction (color, shape, brand)
      - database/chroma_manager.py      --> ChromaDB collections + Late Fusion search
      - questions/question_engine.py    --> Dynamic per-category question generation
      - TALKAI/                         --> Conversational AI (Talk to AI mode)
      - normalizers/normalizer.py       --> Text normalization + cleanup
      - utils/time_parser.py            --> Temporal expression parser ("2 days ago")
          |
          v
    ChromaDB (local vector store)
      - dinov2_collection               --> Visual vectors from item photos
      - clip_collection                 --> Text vectors from descriptions

### 5.3 Matching Score Calculation (Late Fusion)

    For each candidate match:

    visual_score = cosine_similarity(query_dinov2_vector, candidate_dinov2_vector)
    text_score   = cosine_similarity(query_clip_vector,   candidate_clip_vector)

    Dynamic weights (based on image availability + report date):
      if image exists:  image_weight = 35%, text_weight = 65%
      if no image:      image_weight =  0%, text_weight = 100%

    time_decay_factor = e^(-days_since_report / 180)

    final_score = (visual_score * image_weight + text_score * text_weight) * time_decay_factor

    Match stored in MySQL matches table if final_score >= threshold.
    Notifications dispatched to both users.

### 5.4 Digital DNA

When a report is submitted, the AI generates a "Digital DNA" object:

    {
      "object_type": "Electronics",
      "shape": "rectangular",
      "attributes": {
        "Brand": "Apple",
        "Color": "Space Grey",
        "Model": "iPhone 14",
        "Condition": "Slightly scratched",
        "Date / Time": "Yesterday afternoon"
      },
      "visible_features": ["cracked screen corner", "red case"],
      "location": "Library Block B",
      "resolved_date": "2026-10-16",
      "resolved_time": "14:00"
    }

This JSON is serialized and stored in the digital_dna table (encrypted_dna column)
and linked to the report via dna_id foreign key.

### 5.5 Image Handling Pipeline

    User uploads image (JPG / PNG / WebP, max 8MB)
          |
          v
    PHP (functions.php: optimizeAndStoreReportImage)
      - EXIF auto-rotation
      - Resize to max 1200px bounding box
      - Strip GPS metadata
      - Compress to WebP at 82% or JPEG at 88%
      - Save to uploads/ directory
          |
          v
    Flask (save_and_strip_exif_image)
      - PIL EXIF transpose
      - Format-aware save (JPEG 88%, WebP 90%, PNG with alpha)
      - Save to media_vault/lost_reports/ or media_vault/found_reports/
          |
          v
    DINOv2 extracts visual vector -> stored in ChromaDB

### 5.6 Authentication & Session

    Registration:
      - password bcrypt-hashed (PHP password_hash)
      - stored in users.password_hash
      - PIN field available for quick access

    Login:
      - password_verify() check
      - PHP session created: $_SESSION['user_id']
      - Admin uses separate session: $_SESSION['reunite_admin_auth']

    Password Reset:
      - Secure token generated (128-char)
      - Stored in password_resets table with 10-minute expiry
      - Token link emailed to user

---

## 6. DATABASE SCHEMA (13 TABLES)

| #  | Table                | Purpose                                                        |
|----|----------------------|----------------------------------------------------------------|
| 1  | users                | Student/staff accounts (trust_score, role, status)            |
| 2  | digital_dna          | AI-generated visual+semantic fingerprints per report          |
| 3  | lost_reports         | All lost item submissions                                      |
| 4  | found_reports        | All found item submissions                                     |
| 5  | matches              | AI match results (similarity_score, OTP, meet_location)       |
| 6  | recovery_cases       | Ownership claims lifecycle (proof, owner_verified, status)    |
| 7  | notifications        | In-app notifications per user (is_read, link, type)           |
| 8  | password_resets      | Secure password reset tokens (10-min expiry)                  |
| 9  | maintenance_settings | Site maintenance mode toggle + ETA + message                  |
| 10 | admins               | Admin accounts (superadmin / admin / moderator roles)         |
| 11 | logs                 | Full audit log (category, action, IP, device_info)            |
| 12 | question_sets        | Per-category verification question banks (JSON)               |
| 13 | access_logs          | Real-time Flask AI engine activity log                        |

Key relationships:
  lost_reports.dna_id   -> digital_dna.dna_id
  found_reports.dna_id  -> digital_dna.dna_id
  matches.lost_report_id  -> lost_reports.id
  matches.found_report_id -> found_reports.id
  recovery_cases.claimant_id -> users.user_id
  notifications.user_id -> users.user_id

---

## 7. AI ENGINE — DEEP DIVE

### Models Used

| Model   | Type            | Dimensions | Purpose                            |
|---------|-----------------|------------|------------------------------------|
| DINOv2  | Vision (ViT-S)  | 384-dim    | Visual fingerprint from item photo |
| CLIP    | Text encoder    | 512-dim    | Semantic text embedding            |

### ChromaDB Collections

  dinov2_collection  -- stores visual vectors from all report photos
  clip_collection    -- stores text vectors from all report descriptions

### Talk to AI Mode

  Instead of a static form, users can chat with the AI:
  - AI asks questions conversationally ("What color was your bag?")
  - DynamicTalkAIService extracts structured fields from natural language
  - Multi-turn session maintains conversation history
  - Optionally integrates a live voice session via ephemeral token

### Temporal Expression Parser

  Converts natural language dates:
  "yesterday afternoon" -> "2026-10-16", "14:00"
  "2 days ago"          -> "2026-10-15"
  "this morning"        -> "2026-10-17", "09:00"

---

## 8. SECURITY & ENCRYPTION

- Passwords: bcrypt via PHP password_hash (cost factor default)
- Sensitive DNA data: AES-256-CBC encryption in PHP (functions.php)
  Key derivation: SHA-256 of ENCRYPTION_KEY env variable
  IV from ENCRYPTION_IV env variable
- EXIF metadata stripped from all uploaded photos (GPS location removed)
- File uploads: extension whitelist (jpg, png, webp), max 8MB
- SQL injection: all queries use prepared statements with bind_param
- Session security: PHP sessions with admin-specific auth tokens
- Dynamic schema detection: SHOW COLUMNS used before queries to handle
  cloud vs local schema differences safely
- All AI engine activity logged to access_logs table in real time

---

## 9. ADMIN PANEL (admin.php)

Accessible only to admin-role users.
Features:
- Dashboard with live stats
- View and filter all lost + found reports
- Approve or reject ownership claims (recovery_cases)
- Verify matches and coordinate handovers
- Manage user accounts (block/unblock, trust_score)
- Toggle site maintenance mode (maintenance_settings table)
- Full audit log viewer (logs table)
- AI match performance statistics

---

## 10. FULL SYSTEM FLOW DIAGRAM

    USER SUBMITS REPORT
          |
          v
    PHP reports.php
      - Validates input
      - Compresses + stores image (GD library, WebP/JPEG)
      - Inserts into lost_reports or found_reports (MySQL)
      - Calls Flask AI server
          |
          v
    FLASK AI SERVER
      [/new-report]
        - process_report() -> generates Digital DNA JSON
        - parse_temporal_expression() -> resolves date/time
        - Returns digital_dna object to PHP
          |
          v
      [/report/embed-and-store]
        - analyze_dinov2(image) -> 384-dim visual vector
        - analyze_clip_text(description) -> 512-dim text vector
        - store_report_vectors() -> saved to ChromaDB
          |
          v
      [/report/search-matches]
        - search_vectors() -> Late Fusion cosine search
        - Dynamic weight blend (DINOv2 35% + CLIP 65%)
        - Time decay applied (180-day window)
        - Returns top-K matches with scores
          |
          v
    PHP receives matches
      - Inserts into matches table (MySQL)
      - Creates notifications for both users
          |
          v
    USER RECEIVES NOTIFICATION (bell icon)
          |
          v
    USER VIEWS ITEM DETAIL (item.php)
          |
          +--- MODE 1: Claim ------------------> recovery_cases created
          |                                      Admin reviews + approves
          |
          +--- MODE 2: OTP Handover -----------> 6-digit OTP generated
          |                                      Physical meetup confirmed
          |
          +--- MODE 3: Admin Mediated ---------> Admin office handles return
          |
          v
    STATUS UPDATED: "Reunited"
    Both report records closed.
    Final notification sent to both users.

---

> Reunite is not just a notice board. It is a full-stack AI-powered recovery
> platform with neural vision matching, conversational AI intake, encrypted
> data storage, real-time notifications, and a verified handover lifecycle
> — all running on a lightweight cloud deployment with zero native-app overhead.
