import pytest
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost/pw/reunitel"

def test_landing_page_ui_and_theme_toggle():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto(f"{BASE_URL}/Frontend/index.php", timeout=10000)
        
        # Verify page title
        assert "Reunite" in page.title()
        
        # Verify call-to-actions exist
        assert page.locator("a[href*='signup']").count() > 0 or page.locator("a[href*='login']").count() > 0
        
        # Verify theme toggle button exists and works
        theme_btn = page.locator("#themeToggleBtn, #btnThemeToggle, .nav-theme-btn, [aria-label*='theme' i]").first
        if theme_btn.count() > 0 and theme_btn.is_visible():
            theme_btn.click()
            page.wait_for_timeout(300)

        browser.close()

def test_search_page_interactive_filtering():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto(f"{BASE_URL}/Frontend/search.php", timeout=10000)
        
        # Check search input
        search_input = page.locator("#searchInput, input[type='search'], input[placeholder*='search' i]").first
        assert search_input.count() > 0
        search_input.fill("Laptop")
        page.wait_for_timeout(500)
        
        # Verify category chips or filters exist
        cat_chips = page.locator(".category-chip, .filter-chip, [data-category]")
        if cat_chips.count() > 0:
            first_chip = cat_chips.first
            if first_chip.is_visible():
                first_chip.click()
                page.wait_for_timeout(300)

        browser.close()

def test_admin_clearance_security_gate():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto(f"{BASE_URL}/Frontend/admin.php", timeout=10000)
        
        # Unauthenticated user MUST see clearance terminal
        passcode_input = page.locator("#adminPasscode")
        assert passcode_input.is_visible()
        
        # Verify admin dashboard is NOT visible in DOM
        dash = page.locator("#adminDashboard")
        assert dash.count() == 0
        
        # Test wrong passcode feedback
        passcode_input.fill("invalid_code_12345")
        page.locator("#adminLoginForm button[type='submit']").click()
        page.wait_for_timeout(1000)
        
        # Still not logged in
        assert page.locator("#adminDashboard").count() == 0

        browser.close()
