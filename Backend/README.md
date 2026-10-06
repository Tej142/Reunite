# 🛠️ Reunite PHP Backend Module

The **`Backend/`** folder connects the frontend to the MySQL database (`lost_connect_db`), handling user authentication, profile updates, session management, and storing lost/found item reports.

---

## 📁 Directory Structure

```
reunitel/
├── media_vault/
│   ├── lost_reports/       # User uploaded photos for lost item reports
│   └── found_reports/      # User uploaded photos for found item reports
├── Backend/
│   ├── config/
│   │   └── config.php      # Database connection (lost_connect_db) & session initialization
│   ├── functions.php       # Security (AES-256), JSON response formatters, & access logging
│   ├── login.php           # Student login handler (supports College PIN, Email, & Phone)
│   ├── registration.php    # Student registration handler with password hashing
│   ├── logout.php          # Session clearing and redirect
│   ├── profile.php         # Profile data retrieval, user updates, and password changes
│   ├── reports.php         # Saves lost & found item reports directly into the database
│   └── schema.sql          # MySQL database schema for lost_connect_db
```

---

## 🗄️ Database Connection (`config/config.php`)

Connects to MySQL / MariaDB via `mysqli`:
* **Host**: `localhost`
* **User**: `root`
* **Password**: `""` (Empty default)
* **Database**: `lost_connect_db`
* **Port**: `3306`

---

## 🌐 Endpoints

* **`POST Backend/login.php`**: Authenticate via PIN, Email, or Phone.
* **`POST Backend/registration.php`**: Create new student account.
* **`GET / POST Backend/logout.php`**: Destroy session.
* **`GET / POST Backend/profile.php`**: Retrieve and update profile info & change password.
* **`POST Backend/reports.php`**: Save lost or found reports to `lost_reports` / `found_reports`.
* **`GET Backend/reports.php`**: List reports from database.
