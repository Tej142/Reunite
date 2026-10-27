# Reunite Platform — Technical Documentation

Welcome to the comprehensive technical documentation for the **Reunite Platform** — an AI-powered lost and found ecosystem engineered for educational institutions and multi-campus universities.

---

## 📚 Documentation Index

| Module | Description | Core Files Covered |
| :--- | :--- | :--- |
| [**01. System Architecture**](01_SYSTEM_ARCHITECTURE.md) | High-level topology, multi-tier flow, directory structure, and technology stack. | `index.php`, `Backend/config/config.php`, `flask_server.py` |
| [**02. Authentication & Security**](02_AUTHENTICATION_AND_SECURITY.md) | Unified login portal, role hierarchy, session-only security policy (zero cookies), and IDOR guards. | `Frontend/login.php`, `Backend/admin_auth.php`, `Backend/admin_scope.php`, `Frontend/includes/auth.php` |
| [**03. AI Matching & Multi-Modal Engine**](03_AI_MATCHING_AND_MULTIMODAL_ENGINE.md) | ChromaDB vector engine, multi-modal embeddings, Digital DNA synthesis, and adaptive questionnaire. | `flask_server.py`, `AI_Module/database/chroma_manager.py`, `matches.py` |
| [**04. Lost, Found & Claim Lifecycle**](04_LOST_FOUND_AND_CLAIM_LIFECYCLE.md) | End-to-end report pipeline, founder verification desk, OTP handshake, and physical handover protocols. | `Frontend/report-lost-item.php`, `Frontend/api/founder_approval.php`, `Frontend/api/verify_otp.php` |
| [**05. Admin Command Center & API**](05_ADMIN_COMMAND_CENTER_AND_API.md) | Master & Campus Admin portals, live telemetry streaming, real-time campus synchronization, and API specs. | `Frontend/admin/index.php`, `Backend/admin_api.php`, `Frontend/admin/js/admin.js` |

---

## ⚡ Quick Architecture Summary

```
                       ┌─────────────────────────┐
                       │   Client Web Browser    │
                       │ (Desktop / Tablet / App)│
                       └────────────┬────────────┘
                                    │ HTTPS (Port 443)
                                    ▼
                       ┌─────────────────────────┐
                       │    Apache Web Server    │
                       │   (PHP 8.2 Execution)   │
                       └────────────┬────────────┘
                                    │
           ┌────────────────────────┴────────────────────────┐
           │                                                 │
           ▼                                                 ▼
┌─────────────────────────┐                       ┌─────────────────────────┐
│   Backend PHP Engine    │                       │  Python AI Microservice │
│ - Unified Auth          │   REST / Webhooks     │ - Flask / WSGI          │
│ - Admin Command Center  │◄─────────────────────►│ - ChromaDB Vectors      │
│ - MySQL Relational DB   │                       │ - Multi-Modal Embeddings│
│ - Telemetry Polling     │                       │ - Digital DNA Synthesis │
└──────────┬──────────────┘                       └─────────────────────────┘
           │
           ▼
┌─────────────────────────┐
│     MySQL Database      │
│  `lost_connect_db`      │
│ - Admins, Users, Campuses│
│ - Items, Matches, Claims│
│ - Immutable Audit Logs  │
└─────────────────────────┘
```

---

## 🚀 Environment & Deployment
- **Live Production URL:** `https://reunite.site.je`
- **AI Microservice (Render):** `https://reunite-ai-backend.onrender.com`
- **Database Engine:** MySQL 8.x (`lost_connect_db`)
- **PHP Version:** PHP 8.1+ / 8.2
- **Vector Database:** ChromaDB 0.4+ / Python 3.10+
