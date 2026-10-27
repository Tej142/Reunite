# Reunite Platform — Core Code Modules for Documentation

This directory contains the primary production source code excerpts from across the **Reunite Platform**, organized by functional domain so they can be directly embedded into documentation and project reports.

---

## 💻 Code Reference Index

| File | Component | Primary Source Files Extracted |
| :--- | :--- | :--- |
| [**`01_DATABASE_AND_CONFIG_CODE.md`**](01_DATABASE_AND_CONFIG_CODE.md) | Database Connection & Relational Schema | `Backend/config/config.php`, `lost_connect_db` tables (`admins`, `campuses`, `users`, `claims`, `reports`) |
| [**`02_AUTHENTICATION_AND_SECURITY_CODE.md`**](02_AUTHENTICATION_AND_SECURITY_CODE.md) | Unified Auth & Multi-Tenant Scoping | `Frontend/api/auth.php`, `Backend/admin_auth.php`, `Backend/admin_scope.php` |
| [**`03_AI_MATCHING_AND_CHROMADB_CODE.md`**](03_AI_MATCHING_AND_CHROMADB_CODE.md) | ChromaDB Vectors & Multi-Modal Similarity | `AI_Module/database/chroma_manager.py`, `flask_server.py` |
| [**`04_CLAIM_VERIFICATION_AND_OTP_CODE.md`**](04_CLAIM_VERIFICATION_AND_OTP_CODE.md) | Founder Approval & Handover OTP Handshake | `Frontend/api/founder_approval.php`, `Frontend/api/verify_otp.php` |
| [**`05_ADMIN_COMMAND_CENTER_AND_TELEMETRY_CODE.md`**](05_ADMIN_COMMAND_CENTER_AND_TELEMETRY_CODE.md) | Real-Time Telemetry & Theme Engine | `Backend/admin_api.php`, `Frontend/admin/js/admin.js`, `Frontend/admin/css/admin.css` |
