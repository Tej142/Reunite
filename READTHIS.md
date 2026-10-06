# 🎯 REUNITE — AI-Powered Campus Lost & Found Platform

> **Reunite** is an intelligent, full-stack campus Lost & Found ecosystem designed to replace chaotic noticeboards and unorganized chat groups. It leverages **Multimodal AI (DINOv2 + CLIP)**, **Persistent Vector Databases (ChromaDB)**, and **Real-Time Matching Engines** to instantly connect people who lost items with people who found them.

---

## 🌟 Key Highlights & Innovations

1. **Multimodal Late Fusion Matching Engine**:
   - **Visual DNA (DINOv2)**: Extracts 384-dimensional visual feature vectors from uploaded photos using local ONNX inference.
   - **Semantic Text DNA (CLIP)**: Extracts 512-dimensional semantic embeddings from item descriptions and attributes.
   - **ChromaDB Vector Store**: Persistently indexes items and calculates similarity scores with a **180-day dynamic time-decay curve**.

2. **Smart Temporal AI (Server Timestamp Aware)**:
   - Understands natural language relative dates (*"yesterday at 3 PM"*, *"found today morning"*, *"lost 2 days ago"*, *"on 3rd Oct"*).
   - Automatically references the server clock and resolves exact dates (`YYYY-MM-DD`) and times (`HH:MM:SS`) into the MySQL database.

3. **Three Interactive Reporting Modes**:
   - **📸 Multimodal Image + Description**: Upload a photo + type a quick description for instant Digital DNA synthesis.
   - **⚡ Instant Choice**: Dynamic questionnaire tailored specifically to the selected category.
   - **🎙️ Talk to AI Copilot**: Dynamic, human-like conversational intake that asks smart follow-ups without rigid forms.

4. **Real-Time Notifications & Microinteractions**:
   - Instant notifications created the second a matching item is reported.
   - Background polling (every 3.5s), animated bell indicators, Web Audio chimes, and popup toasts.

5. **Security & Ownership Protection**:
   - Student emails and phone numbers are encrypted with **AES-256-CBC**.
   - Private verification secrets (lock-screen wallpapers, serial numbers, engravings) remain hidden from public search to verify rightful owners.
   - Dedicated **Admin Terminal** with Maintenance Mode toggle, ChromaDB synchronizer, system health monitors, and audit access logs.

---

## 🏗️ System Architecture & Tech Stack

```
   ┌─────────────────────────────────────────────────────────┐
   │                  REUNITE PLATFORM                       │
   └──────────────────────────┬──────────────────────────────┘
                              │
         ┌────────────────────┴────────────────────┐
         ▼                                         ▼
┌──────────────────────────────┐        ┌──────────────────────────────┐
│       Frontend (Web UI)      │        │       Backend (PHP API)      │
│  - Vanilla HTML5 / CSS3 / JS │        │  - PHP 8.x Native Core       │
│  - Dark / Light Theme Engine │◄──────►│  - MySQL (lost_connect_db)   │
│  - Real-Time Notif Polling   │        │  - AES-256-CBC Encryption    │
│  - Live Audio Chime Engine   │        │  - Password Reset & OTP Auth │
└──────────────────────────────┘        └──────────────┬───────────────┘
                                                       │
                                                       ▼
                                        ┌──────────────────────────────┐
                                        │    AI Engine (Flask :5000)   │
                                        │  - Gemini / Mistral LLM      │
                                        │  - DINOv2 Visual Vectors     │
                                        │  - CLIP Text Vectors         │
                                        │  - ChromaDB Vector DB        │
                                        │  - Temporal Date Resolver    │
                                        └──────────────────────────────┘
```

---

## 📂 Project Structure

```
pw/
├── reunitel/                       # Main Project Directory
│   ├── AI_Module/                  # Python AI Engine
│   │   ├── analyzers/              # DINOv2, CLIP, Image & Report Analyzers
│   │   ├── database/               # ChromaDB Persistent Client & Vector Search
│   │   ├── prompts/                # Structured LLM Prompts & Entity Extractors
│   │   ├── question_engine/        # Dynamic Question Generation Engine
│   │   ├── TALKAI/                 # Conversational Intake & Dynamic Chat Copilot
│   │   ├── utils/                  # Temporal Time Resolver & JSON Validators
│   │   └── sync_mysql_to_chroma.py # DB-to-Vector Synchronization Script
│   ├── Backend/                    # PHP Server & Core Logic
│   │   ├── config/                 # DB Credentials & Encryption Keys
│   │   ├── admin_api.php           # Admin Controls & Diagnostics Endpoints
│   │   ├── functions.php           # Core Utilities, Matchmaker & Auth Helpers
│   │   ├── login.php / logout.php  # Authentication Endpoints
│   │   ├── notifications.php       # Live Notifications API
│   │   ├── profile.php             # Profile & User Activity Handler
│   │   ├── reports.php             # Report Creation & DB Persistence
│   │   └── schema.sql              # MySQL Database Schema
│   ├── Frontend/                   # User Interface Pages
│   │   ├── components/             # Reusable Navbars, Footers & Modals
│   │   ├── css/                    # Variables, Themes, Typography & Styles
│   │   ├── js/                     # Search, AI Chat, Notifications & UI Scripts
│   │   ├── home.php                # Landing & Community Board
│   │   ├── search.php              # Search & Filter Catalog (Defaults to Found Items)
│   │   ├── report-lost-item.php    # Lost Report Intake Wizard
│   │   ├── report-found-item.php   # Found Report Intake Wizard
│   │   ├── profile.php             # User Profile & Activity Dashboard
│   │   └── maintenance.php         # 503 Scheduled Maintenance Page
│   ├── chroma_data/                # Persistent Vector Database on Disk
│   ├── media_vault/                # Secure Uploaded Images Directory
│   ├── admin.php                   # Admin Portal Gateway
│   ├── flask_server.py             # Flask AI Server (Port 5000)
│   └── requirements.txt            # Python Dependencies
└── READTHIS.md                     # This Project Documentation
```

---

## 🚀 How to Run the Project Locally

### 1. Prerequisites
- **XAMPP** (Apache + MySQL running).
- **Python 3.10+** installed and added to PATH.

### 2. Database Setup
1. Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open your browser and navigate to `http://localhost/phpmyadmin/`.
3. Create a new database named **`lost_connect_db`**.
4. Import `Backend/schema.sql` into `lost_connect_db`.

### 3. Install Python Dependencies
Open a terminal in the `reunitel/` directory and run:
```bash
pip install -r requirements.txt
```

### 4. Start the AI Server
In your terminal, start the Python Flask AI engine:
```bash
python flask_server.py
```
*The AI server will launch on `http://127.0.0.1:5000`.*

### 5. Access the Platform
- **Student Web Application**:  
  👉 [`http://localhost/pw/reunitel/Frontend/home.php`](http://localhost/pw/reunitel/Frontend/home.php)
- **Search Found Items**:  
  👉 [`http://localhost/pw/reunitel/Frontend/search.php`](http://localhost/pw/reunitel/Frontend/search.php)
- **Admin Control Terminal**:  
  👉 [`http://localhost/pw/reunitel/admin.php`](http://localhost/pw/reunitel/admin.php)

---

## ⚡ Core User & Admin Workflows

### Reporting an Item
1. Click **"Report Lost Item"** or **"Report Found Item"**.
2. Select your preferred mode:
   - **Mode 1**: Upload a photo and describe the item.
   - **Mode 2**: Instant Choice category questionnaire.
   - **Mode 3**: Talk to AI Copilot naturally.
3. Submit the report. The backend automatically:
   - Encrypts and saves records in MySQL.
   - Resolves relative timestamps (e.g., *"yesterday"* -> exact date).
   - Generates DINOv2 & CLIP vector embeddings into ChromaDB.
   - Scans opposing active reports for semantic & visual matches.
   - Creates real-time notifications for both parties if a match is found.

### Viewing & Claiming Matches
1. The notification bell in the navbar rings and highlights unread match alerts.
2. Clicking the alert opens `search.php?match_id=...` with the match breakdown (visual score, text score, total AI confidence).
3. The owner can initiate a **Claim Verification** modal to recover their item safely.

### Admin Controls
1. Log in to `/admin.php`.
2. Access live diagnostics:
   - **Site Maintenance Toggle**: Gracefully puts the site into 503 maintenance mode with custom messages and ETAs.
   - **ChromaDB Re-sync**: Re-embeds all database records into vector collections with one click.
   - **Access Audit Logs**: Live stream of user actions, AI syntheses, and security events.

---

## 🛡️ Security & Privacy
- **AES-256 Data Encryption**: Sensitive PII (emails, phone numbers) are encrypted at rest.
- **Credential Protection**: Strict regex filters block passwords, PINs, card CVVs, and OTPs from being stored in public reports.
- **Role-Based Access Control (RBAC)**: Admin routes are protected by server-side session authentication.

---

*Developed for intelligent, effortless, and secure campus item recovery.*
