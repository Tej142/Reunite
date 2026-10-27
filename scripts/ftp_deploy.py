import ftplib
import os
import sys

def load_env(env_path):
    env = {}
    if os.path.exists(env_path):
        with open(env_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    env[k.strip()] = v.strip().strip("'\"")
    return env

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
env_vars = load_env(os.path.join(base_dir, ".env"))

FTP_HOST = env_vars.get("FTP_HOST", os.getenv("FTP_HOST", "ftpupload.net"))
FTP_USER = env_vars.get("FTP_USER", os.getenv("FTP_USER", "if0_42705202"))
FTP_PASS = env_vars.get("FTP_PASS", os.getenv("FTP_PASS", "reunitePVBVC123"))
REMOTE_ROOT = "/htdocs"

FILES_TO_DEPLOY = [
    "Backend/admin_api.php",
    "Backend/admin_auth.php",
    "Backend/admin_scope.php",
    "Backend/functions.php",
    "Backend/notifications.php",
    "Backend/migrations/campus_admin_migration.sql",
    "Frontend/admin/index.php",
    "Frontend/admin/css/admin.css",
    "Frontend/admin/js/admin.js",
    "Frontend/api/verify_otp.php",
    "Frontend/includes/auth.php",
]

def ensure_remote_dir(ftp, remote_dir):
    parts = remote_dir.strip("/").split("/")
    current = ""
    for part in parts:
        current += "/" + part
        try:
            ftp.cwd(current)
        except ftplib.error_perm:
            try:
                print(f"  Creating remote directory: {current}")
                ftp.mkd(current)
                ftp.cwd(current)
            except Exception as e:
                pass

def deploy():
    print(f"Connecting to FTP server {FTP_HOST} as {FTP_USER}...")
    ftp = ftplib.FTP(FTP_HOST, timeout=30)
    ftp.login(FTP_USER, FTP_PASS)
    print("Logged in successfully.")

    for rel_path in FILES_TO_DEPLOY:
        local_path = os.path.join(base_dir, rel_path.replace("/", os.sep))
        remote_path = f"{REMOTE_ROOT}/{rel_path}"
        remote_dir = os.path.dirname(remote_path)

        if not os.path.exists(local_path):
            print(f"ERROR: Local file not found: {local_path}")
            continue

        ensure_remote_dir(ftp, remote_dir)
        ftp.cwd(remote_dir)

        filename = os.path.basename(remote_path)
        file_size = os.path.getsize(local_path)
        print(f"Uploading {rel_path} ({file_size} bytes)...", end="", flush=True)
        with open(local_path, "rb") as f:
            ftp.storbinary(f"STOR {filename}", f)
        print(" DONE")

    ftp.quit()
    print("\nAll files deployed via FTP successfully!")

if __name__ == "__main__":
    deploy()
