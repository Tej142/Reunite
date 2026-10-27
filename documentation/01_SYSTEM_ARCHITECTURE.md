# 01 — System Architecture & Core Topology

## 1. System Overview
The **Reunite Platform** is an enterprise-grade Lost and Found platform built on a distributed hybrid architecture:
- **Presentation & Application Layer (PHP 8.2 + Vanilla Modern JS/CSS):** Serves responsive client interfaces, handles user sessions, manages campus tenancy, and coordinates operational workflows.
- **Relational Data Layer (MySQL 8.x):** Relational store containing campuses, administrative accounts, student credentials, audit trails, and item tracking records.
- **AI & Vector Microservice (Python Flask + ChromaDB):** Cloud-deployed microservice hosted on Render (`https://reunite-ai-backend.onrender.com`) providing semantic vector embeddings, vision OCR attribute extraction, and multi-modal similarity matching.

---

## 2. Directory Layout
```
Reunite/
├── AI_Module/                 # Python AI modules and vector database managers
│   ├── database/
│   │   └── chroma_manager.py  # ChromaDB collection initialization and vector querying
│   └── models/               # Embedding models and CLIP wrappers
├── Backend/                   # Core PHP services & administrative API handlers
│   ├── admin_api.php          # REST endpoint for admin metrics, claims desk, and telemetry
│   ├── admin_auth.php         # Admin authentication processor and session initiator
│   ├── admin_scope.php        # Multi-tenant campus scoping and IDOR verification engine
│   ├── config/
│   │   └── config.php         # Database connection, timezone, and .env parser
│   ├── functions.php          # Encryption, hashing, access logging, and helpers
│   └── items.php              # Item lifecycle operations (lost & found listings)
├── Frontend/                  # User & Administrator interfaces
│   ├── admin/                 # Command Center workspace (SPA-like dashboard)
│   │   ├── index.php          # Main Admin dashboard interface
│   │   ├── js/admin.js        # Real-time telemetry, live feed ticker, and tab switcher
│   │   └── css/admin.css      # Dual-theme (White / Dark) administrative styling
│   ├── components/            # Reusable UI partials (navigation, Orbi assistant, footers)
│   ├── login.php              # Unified login portal (Students, Campus Admins, Master Admin)
│   ├── home.php               # Student home board and personal claims tracker
│   └── report-lost-item.php   # Multi-step lost item reporting with instant DNA extraction
├── documentation/             # Engineering and architectural documentation
└── flask_server.py            # Flask AI microservice handling ChromaDB and image analysis
```

---

## 3. Core Architectural Excerpt: Database & Environment Initialization
File: `Backend/config/config.php`

```php
<?php
/**
 * Reunite Database & Global Configuration
 * Database: lost_connect_db
 */

// Ensure consistent campus timezone (Tirupati, AP, GMT+5:30)
date_default_timezone_set('Asia/Kolkata');

// Start volatile session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Automatically load environment variables: .env is the primary source of truth
(function() {
    $rootDir = dirname(__DIR__, 2);
    $examplePath = $rootDir . '/.env.example';
    $envPath = $rootDir . '/.env';

    // Parse .env key=value pairs into getenv(), $_ENV, and $_SERVER
    if (file_exists($envPath) && is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
})();

// Establish Primary Database Connection
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'lost_connect_db';
$dbPort = (int)(getenv('DB_PORT') ?: 3306);

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
}
```

---

## 4. Multi-Tier Telemetry Flow
1. **Client Browser** issues AJAX requests to `Backend/admin_api.php?action=telemetry_stream` every 8 seconds.
2. **PHP Backend** aggregates live counts of unverified claims, pending matches, system load, and recent audit events scoped to the admin's campus ID.
3. **Frontend Telemetry Engine (`js/admin.js`)** detects count deltas, triggers real-time audio chime alerts, and dynamically prepends incoming operational events to the live ticker banner.
