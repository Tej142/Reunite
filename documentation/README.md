# Reunite Platform — Technical Documentation & Architectural Visualizations

Welcome to the engineering documentation suite for the **Reunite Multi-Tenant Campus Lost & Found Platform**. This directory brings together production code excerpts, relational database schemas, academic thesis chapters, and standalone interactive **Archify 3.2.0** diagrams covering all facets of system design.

---

## 🧭 Master Documentation Index

```
documentation/
├── 🌐 Interactive Architectural Visualizations (Archify 3.2.0)
│   ├── 06_ARCHIFY_SYSTEM_ARCHITECTURE.html    # Production Cloud & AI Microservice Topology
│   ├── 07_ARCHIFY_CLAIM_DESK_LIFECYCLE.html   # Campus Claim Desk Custody & 2FA OTP Lifecycle
│   ├── 08_ARCHIFY_DATABASE_RELATIONSHIPS.html # Relational Database Schema & ERD
│   └── 09_ARCHIFY_AI_MATCHING_DATAFLOW.html   # Multi-Modal CLIP + ChromaDB Vector Dataflow
│
├── 💻 Production Code Reference Modules
│   ├── 01_DATABASE_AND_CONFIG_CODE.md         # Database Connection, Pool & Core Tables
│   ├── 02_AUTHENTICATION_AND_SECURITY_CODE.md # Multi-Tenant Auth, Session & RBAC Scoping
│   ├── 03_AI_MATCHING_AND_CHROMADB_CODE.md    # ChromaDB Vector Store & ViT-B/32 Similarity API
│   ├── 04_CLAIM_VERIFICATION_AND_OTP_CODE.md  # Desk Approval, Ownership Proof & OTP Handshake
│   └── 05_ADMIN_COMMAND_CENTER_AND_TELEMETRY_CODE.md # Real-Time Analytics & Ops Controls
│
├── 📋 Formal Thesis Database Chapter (Section 8.5)
│   ├── 8.5_DATABASE_STRUCTURE.md              # Academic Thesis Report with Table Definitions
│   └── 8.5_DATABASE_STRUCTURE.sql             # Executable DDL Schema Script
│
└── 🛠️ Archify Source Specifications & Generators
    └── archify_models/
        ├── reunite_database.erd.json          # Raw ERD JSON Model
        ├── reunite_ai_matching.dataflow.json  # Raw Dataflow JSON Model
        ├── build_erd.py                       # ERD Generator Script
        └── build_dataflow.py                  # Dataflow Generator Script
```

---

## 🌐 1. Interactive Architectural Visualizations (Archify 3.2.0)

All visual architecture diagrams are compiled with **Archify 3.2.0** as zero-dependency, self-contained HTML files with embedded vectors, dark/light theme switching, and real-time interaction. They can be double-clicked to open in any web browser or embedded directly into documentation portals via `<iframe>`.

| Document | Diagram Type | Visual Preset | Highlights & System Scope |
| :--- | :--- | :--- | :--- |
| [**`06_ARCHIFY_SYSTEM_ARCHITECTURE.html`**](06_ARCHIFY_SYSTEM_ARCHITECTURE.html) | `architecture` | `blueprint` | Web PWA, Android native shell (Capacitor), NGINX reverse proxy, PHP REST API Gateway, Flask AI Microservice, CLIP ViT-B/32, DINOv2 visual backbone, and MySQL 8.0 cluster. |
| [**`07_ARCHIFY_CLAIM_DESK_LIFECYCLE.html`**](07_ARCHIFY_CLAIM_DESK_LIFECYCLE.html) | `lifecycle` | `classic` | 8-phase state machine spanning item discovery, custody intake, multi-modal vector indexing, candidate match alert, claim submission, 2FA OTP handshake, and campus handover. |
| [**`08_ARCHIFY_DATABASE_RELATIONSHIPS.html`**](08_ARCHIFY_DATABASE_RELATIONSHIPS.html) | `erd` | `blueprint` | Complete 9-entity relational schema with multi-tenant campus boundaries, foreign key references, cardinality constraints, and specialized views (`multi_tenant`, `reports_dna`, `claims_handover`). |
| [**`09_ARCHIFY_AI_MATCHING_DATAFLOW.html`**](09_ARCHIFY_AI_MATCHING_DATAFLOW.html) | `dataflow` | `signal-flow` | End-to-end data pipelines from camera ingestion and EXIF stripping through CLIP multi-modal 512-dim fusion (60% image / 40% text) to ChromaDB HNSW cosine similarity search and push notifications. |

### ⌨️ Archify Viewer Hotkeys & Navigation
When viewing any Archify HTML diagram:
- `+` / `-` / `0` — Zoom in, zoom out, or reset to 100% viewport fit.
- `F` — Toggle full-screen presentation mode.
- `M` — Toggle the radar mini-map navigator.
- `L` — Activate the Semantic Lens focus probe.
- `R` — Activate the Route Probe to inspect connection dependencies between nodes.
- `Escape` — Clear active highlights and dismiss modals.

---

## 💻 2. Production Code Reference Index

Production code excerpts extracted directly from active controllers, models, and workers:

| File | Domain | Key Source Files Documented |
| :--- | :--- | :--- |
| [**`01_DATABASE_AND_CONFIG_CODE.md`**](01_DATABASE_AND_CONFIG_CODE.md) | Database Connection & Relational Schema | `Backend/config/config.php`, `campuses`, `admins`, `users`, `claims`, `reports` DDL |
| [**`02_AUTHENTICATION_AND_SECURITY_CODE.md`**](02_AUTHENTICATION_AND_SECURITY_CODE.md) | Unified Auth & Multi-Tenant Scoping | `Frontend/api/auth.php`, `Backend/admin_auth.php`, `Backend/admin_scope.php` |
| [**`03_AI_MATCHING_AND_CHROMADB_CODE.md`**](03_AI_MATCHING_AND_CHROMADB_CODE.md) | ChromaDB Vectors & Multi-Modal Similarity | `AI_Module/database/chroma_manager.py`, `flask_server.py` |
| [**`04_CLAIM_VERIFICATION_AND_OTP_CODE.md`**](04_CLAIM_VERIFICATION_AND_OTP_CODE.md) | Founder Approval & Handover OTP Handshake | `Frontend/api/founder_approval.php`, `Frontend/api/verify_otp.php` |
| [**`05_ADMIN_COMMAND_CENTER_AND_TELEMETRY_CODE.md`**](05_ADMIN_COMMAND_CENTER_AND_TELEMETRY_CODE.md) | Real-Time Telemetry & Theme Engine | `Backend/admin_api.php`, `Frontend/admin/js/admin.js`, `Frontend/admin/css/admin.css` |

---

## 📋 3. Formal Thesis Database Structure (Section 8.5)

Formatted specifically for academic project reports, capstone thesis presentations, and technical documentation audits:

| File | Type | Description |
| :--- | :--- | :--- |
| [**`8.5_DATABASE_STRUCTURE.md`**](8.5_DATABASE_STRUCTURE.md) | Markdown Report | Formatted thesis chapter with numbered sub-sections (8.5.1 to 8.5.8), comprehensive field specification tables (Type, Nullable, Default, Key, Purpose), and relationship cardinality matrices. |
| [**`8.5_DATABASE_STRUCTURE.sql`**](8.5_DATABASE_STRUCTURE.sql) | DDL SQL Script | Production SQL DDL script creating all multi-tenant tables (`campuses`, `admins`, `users`, `lost_reports`, `found_reports`, `digital_dna`, `matches`, `recovery_cases`, `notifications`) with complete indexes and constraints. |

---

## 🛠️ 4. Archify Source Models & CLI Compilation

All interactive HTML diagrams are built deterministically from pure JSON declarative specifications located in [`archify_models/`](archify_models/).

### Rebuilding Diagrams via Archify CLI
If models are updated or customized, re-render them using the bundled Archify CLI:

```powershell
# 1. Render Database Schema ERD
node ../../archify-main/archify/bin/archify.mjs render erd `
  archify_models/reunite_database.erd.json `
  08_ARCHIFY_DATABASE_RELATIONSHIPS.html --quality standard

# 2. Render AI Matching & Vector Dataflow
node ../../archify-main/archify/bin/archify.mjs render dataflow `
  archify_models/reunite_ai_matching.dataflow.json `
  09_ARCHIFY_AI_MATCHING_DATAFLOW.html --quality standard
```

Or execute the Python helper scripts:
```powershell
python archify_models/build_erd.py
python archify_models/build_dataflow.py
```
