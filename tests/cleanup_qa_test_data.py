import subprocess
import requests

PHP_CLI_PATH = r"c:\xampp\php\php.exe"
CLEANUP_SCRIPT = r"c:\xampp\htdocs\pw\reunitel\tests\cleanup_qa.php"

def run_cleanup():
    print("[CLEANUP] Purging qa_test_ test data...")
    res = subprocess.run([PHP_CLI_PATH, CLEANUP_SCRIPT], capture_output=True, text=True)
    print(res.stdout)
    if res.stderr:
        print("[STDERR]", res.stderr)

if __name__ == "__main__":
    run_cleanup()
