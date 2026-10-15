const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// ── Target Configuration ──────────────────────────────────────────
const BASE_URL = process.env.TARGET_URL || 'http://localhost/pw/Reunite/Frontend';
const SCREENSHOT_DIR = path.resolve(__dirname, '..', 'qa_screenshots');

if (!fs.existsSync(SCREENSHOT_DIR)) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

// ── Viewports Specification ─────────────────────────────────────────
const VIEWPORTS = [
  { name: '280x653_fold_outer', width: 280, height: 653, dpr: 1, isTouch: true },
  { name: '320x568_iphone_se', width: 320, height: 568, dpr: 2, isTouch: true },
  { name: '360x800_phone_std', width: 360, height: 800, dpr: 2, isTouch: true },
  { name: '375x812_iphone_x', width: 375, height: 812, dpr: 2, isTouch: true },
  { name: '393x873_pixel_8', width: 393, height: 873, dpr: 2, isTouch: true },
  { name: '412x915_dpr2_galaxy', width: 412, height: 915, dpr: 2, isTouch: true },
  { name: '720x1600_dpr1_native', width: 720, height: 1600, dpr: 1, isTouch: true },
  { name: '720x1600_dpr2_user_phone', width: 720, height: 1600, dpr: 2, isTouch: true },
  { name: '653x840_fold_inner', width: 653, height: 840, dpr: 2, isTouch: true },
  { name: '768x1024_ipad_port', width: 768, height: 1024, dpr: 2, isTouch: true },
  { name: '820x1180_ipad_air', width: 820, height: 1180, dpr: 2, isTouch: true },
  { name: '1024x768_ipad_land', width: 1024, height: 768, dpr: 2, isTouch: true },
  { name: '1280x720_hd_desktop', width: 1280, height: 720, dpr: 1, isTouch: false },
  { name: '1366x768_laptop', width: 1366, height: 768, dpr: 1, isTouch: false },
  { name: '1440x900_macbook', width: 1440, height: 900, dpr: 2, isTouch: false },
  { name: '1920x1080_fhd_desktop', width: 1920, height: 1080, dpr: 1, isTouch: false },
  { name: '2560x1440_qhd_2k', width: 2560, height: 1440, dpr: 1, isTouch: false },
  { name: '3440x1440_ultrawide', width: 3440, height: 1440, dpr: 1, isTouch: false },
  { name: '844x390_phone_landscape', width: 844, height: 390, dpr: 2, isTouch: true }
];

// ── Pages to Audit ─────────────────────────────────────────────────
const PAGES = [
  { id: 'landing', path: '/index.php', name: 'Landing Page' },
  { id: 'login', path: '/login.php', name: 'Student Login' },
  { id: 'register', path: '/signup.php', name: 'Student Registration' },
  { id: 'forgot_password', path: '/forgot-password.php', name: 'Forgot Password' },
  { id: 'reset_password', path: '/reset-password.php', name: 'Reset Password' },
  { id: 'home', path: '/home.php', name: 'Student Dashboard' },
  { id: 'search', path: '/search.php', name: 'Item Search & Filters' },
  { id: 'item_detail', path: '/item.php', name: 'Item Detail View' },
  { id: 'report_lost', path: '/report-lost-item.php', name: 'Report Lost Item' },
  { id: 'report_found', path: '/report-found-item.php', name: 'Report Found Item' },
  { id: 'profile', path: '/profile.php', name: 'Student Profile' },
  { id: 'maintenance', path: '/maintenance.php', name: 'Maintenance Mode' },
  { id: 'page_404', path: '/404.php', name: '404 Not Found' },
  { id: 'admin_login', path: '/admin.php', name: 'Admin Console' }
];

// In-browser inspection script
async function inspectPage(page, isTouch) {
  return await page.evaluate((isTouchViewport) => {
    const results = {
      scrollWidth: document.documentElement.scrollWidth,
      innerWidth: window.innerWidth,
      hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      overflowingElements: [],
      smallTextElements: [],
      smallTapTargets: [],
      oversizedMedia: [],
      stickyCoveringElements: []
    };

    const docWidth = window.innerWidth;

    // 1. Detect overflowing elements sticking out of the viewport
    const allElements = document.querySelectorAll('body *');
    const seenOverflowTags = new Set();

    allElements.forEach((el) => {
      // Ignore hidden or zero-size elements
      const rect = el.getBoundingClientRect();
      const style = window.getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return;
      if (rect.width === 0 || rect.height === 0) return;

      // Element sticks out past viewport width
      if (rect.right > docWidth + 2) {
        const overflowAmount = Math.round(rect.right - docWidth);
        const tagDesc = `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''}`;
        if (!seenOverflowTags.has(tagDesc) && results.overflowingElements.length < 10) {
          seenOverflowTags.add(tagDesc);
          results.overflowingElements.push({
            selector: tagDesc,
            width: Math.round(rect.width),
            right: Math.round(rect.right),
            overflowPx: overflowAmount
          });
        }
      }

      // 2. Small font size (< 12px)
      const fontSize = parseFloat(style.fontSize);
      if (fontSize > 0 && fontSize < 11.5 && el.textContent.trim().length > 0 && el.children.length === 0) {
        const snippet = el.textContent.trim().slice(0, 30);
        if (results.smallTextElements.length < 5) {
          results.smallTextElements.push({
            selector: el.tagName.toLowerCase(),
            fontSize: `${fontSize}px`,
            textSnippet: snippet
          });
        }
      }

      // 3. Touch tap targets (< 44x44px) on touch viewports
      if (isTouchViewport) {
        const isClickable = el.matches('a, button, input:not([type="hidden"]), select, textarea, [role="button"], label.cursor-pointer');
        if (isClickable) {
          if ((rect.width < 43 || rect.height < 43) && !el.closest('header nav .nav-theme-btn, .theme-toggle')) {
            const desc = `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/)[0] : ''}`;
            if (results.smallTapTargets.length < 8) {
              results.smallTapTargets.push({
                target: desc,
                size: `${Math.round(rect.width)}x${Math.round(rect.height)}px`
              });
            }
          }
        }
      }

      // 4. Oversized media/tables/pre
      if (el.matches('img, table, pre, iframe, video, canvas')) {
        if (rect.width > docWidth + 2) {
          results.oversizedMedia.push({
            tag: el.tagName.toLowerCase(),
            width: Math.round(rect.width),
            viewportWidth: docWidth
          });
        }
      }
    });

    return results;
  }, isTouch);
}

// ── Main Audit Runner ──────────────────────────────────────────────
(async () => {
  console.log(`\n======================================================`);
  console.log(`🚀 REUNITE FULL-SPECTRUM RESPONSIVE AUDIT RUNNER`);
  console.log(`Target Base URL: ${BASE_URL}`);
  console.log(`Total Viewports: ${VIEWPORTS.length}`);
  console.log(`Total Pages:     ${PAGES.length}`);
  console.log(`======================================================\n`);

  const browser = await chromium.launch({
    executablePath: 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors']
  });

  const fullReport = [];
  let totalOverflowsFound = 0;
  let totalSmallTapTargets = 0;
  let totalSmallTexts = 0;

  for (const pageDef of PAGES) {
    const pageUrl = `${BASE_URL}${pageDef.path}`;
    console.log(`\n📄 Auditing: ${pageDef.name} (${pageDef.path})`);

    const pageReport = {
      pageId: pageDef.id,
      name: pageDef.name,
      url: pageUrl,
      viewports: {}
    };

    for (const vp of VIEWPORTS) {
      const context = await browser.newContext({
        viewport: { width: vp.width, height: vp.height },
        deviceScaleFactor: vp.dpr,
        isMobile: vp.isTouch,
        hasTouch: vp.isTouch,
        ignoreHTTPSErrors: true
      });

      const page = await context.newPage();
      const consoleErrors = [];
      const failedRequests = [];

      page.on('console', msg => {
        if (msg.type() === 'error') consoleErrors.push(msg.text().slice(0, 100));
      });
      page.on('requestfailed', req => {
        failedRequests.push(`${req.method()} ${req.url()}`);
      });

      try {
        await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 10000 });
        // Allow brief render stabilization
        await page.waitForTimeout(300);

        const inspection = await inspectPage(page, vp.isTouch);

        if (inspection.hasHorizontalOverflow) {
          totalOverflowsFound++;
        }
        totalSmallTapTargets += inspection.smallTapTargets.length;
        totalSmallTexts += inspection.smallTextElements.length;

        // Save screenshot
        const screenshotFileName = `${pageDef.id}_${vp.name}.png`;
        const screenshotFilePath = path.join(SCREENSHOT_DIR, screenshotFileName);
        await page.screenshot({ path: screenshotFilePath, fullPage: false });

        pageReport.viewports[vp.name] = {
          viewport: `${vp.width}x${vp.height} @${vp.dpr}x`,
          isTouch: vp.isTouch,
          hasHorizontalOverflow: inspection.hasHorizontalOverflow,
          scrollWidth: inspection.scrollWidth,
          viewportWidth: vp.width,
          overflowPx: inspection.scrollWidth - vp.width,
          overflowingElements: inspection.overflowingElements,
          smallTapTargetsCount: inspection.smallTapTargets.length,
          smallTapTargets: inspection.smallTapTargets,
          smallTextCount: inspection.smallTextElements.length,
          smallTextElements: inspection.smallTextElements,
          oversizedMedia: inspection.oversizedMedia,
          consoleErrorsCount: consoleErrors.length,
          consoleErrors: consoleErrors.slice(0, 3),
          failedRequestsCount: failedRequests.length,
          screenshot: screenshotFileName
        };

        const statusSymbol = inspection.hasHorizontalOverflow ? '❌ OVERFLOW' : '✅ OK';
        process.stdout.write(`  [${vp.name.padEnd(25)}] ${statusSymbol} (scroll: ${inspection.scrollWidth}px / vp: ${vp.width}px, tap targets <44px: ${inspection.smallTapTargets.length})\n`);

      } catch (err) {
        console.error(`  ❌ Error on ${vp.name}: ${err.message}`);
        pageReport.viewports[vp.name] = { error: err.message };
      } finally {
        await page.close();
        await context.close();
      }
    }

    fullReport.push(pageReport);
  }

  await browser.close();

  // Save audit report JSON
  const reportFileName = process.env.REPORT_FILE || 'audit_report_phase1.json';
  const reportPath = path.resolve(__dirname, reportFileName);
  fs.writeFileSync(reportPath, JSON.stringify(fullReport, null, 2));

  console.log(`\n======================================================`);
  console.log(`🏁 AUDIT COMPLETE!`);
  console.log(`Total Horizontal Overflows Encountered: ${totalOverflowsFound}`);
  console.log(`Total Small Tap Target Instances:      ${totalSmallTapTargets}`);
  console.log(`Total Small Font (<12px) Instances:    ${totalSmallTexts}`);
  console.log(`Full JSON Report Saved to:             ${reportPath}`);
  console.log(`Screenshots Directory:                 ${SCREENSHOT_DIR}`);
  console.log(`======================================================\n`);
})();
