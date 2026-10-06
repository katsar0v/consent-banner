const path = require('path');
const { test, expect } = require('@playwright/test');

const pluginRoot = path.resolve(__dirname, '../..');
const origin = 'https://consent.example.test';
const privacyPolicyUrl = `${origin}/privacy/`;

// Serves a tall page at a real URL so the privacy-page check and scrolling behave as on a site.
async function mountBanner(page, options = {}) {
  await page.route(`${origin}/**`, (route) => route.fulfill({
    contentType: 'text/html',
    body: `<!doctype html><html><head></head><body>
      <header><a id="page-link" href="#top">Page link</a></header>
      <main style="height: 3000px"><button id="page-action" type="button">Page action</button></main>
      <div id="kdconsent-banner-root"></div>
    </body></html>`
  }));
  await page.goto(`${origin}${options.path || '/'}`);
  await page.addStyleTag({ path: path.join(pluginRoot, 'assets/css/banner.css') });
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/consent-storage.js') });
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/banner-ui.js') });
  await page.evaluate(({ blockUntilChoice, privacyUrl }) => {
    window.pageActions = 0;
    document.getElementById('page-action').addEventListener('click', () => { window.pageActions += 1; });
    window.kdconsentInitBanner(
      {
        consentVersion: 1,
        restRoot: '/wp-json/kdconsent/v1/',
        texts: {
          bannerTitle: 'Privacy choices',
          bannerBody: 'Choose optional purposes.',
          acceptAllLabel: 'Accept all',
          rejectAllLabel: 'Reject all',
          customizeLabel: 'Customize',
          saveLabel: 'Save',
          closeLabel: 'Close',
          preferencesTitle: 'Preferences',
          privacyLabel: 'Privacy policy'
        },
        categories: [
          { id: 'essential', label: 'Essential', required: true, enabledByDefault: true },
          { id: 'analytics', label: 'Analytics', required: false, enabledByDefault: false }
        ],
        behavior: { showRejectButton: true, showDelayMs: 0, position: 'bottom', blockUntilChoice, privacyPolicyUrl: privacyUrl }
      },
      { listeners: [], getConsent: () => null, setConsent: () => {} }
    );
  }, { blockUntilChoice: options.blockUntilChoice === true, privacyUrl: options.privacyPolicyUrl ?? privacyPolicyUrl });
  await expect(page.locator('.kdconsent-banner')).toBeVisible();
}

async function pageIsLocked(page) {
  return page.evaluate(() => ({
    scrollLocked: document.documentElement.classList.contains('kdconsent-scroll-locked'),
    overflow: getComputedStyle(document.documentElement).overflow,
    headerInert: document.querySelector('header').inert,
    mainInert: document.querySelector('main').inert,
    rootInert: document.getElementById('kdconsent-banner-root').inert
  }));
}

test('blocking banner locks the page until the visitor chooses, then releases it', async ({ page }) => {
  await mountBanner(page, { blockUntilChoice: true });

  expect(await pageIsLocked(page)).toEqual({
    scrollLocked: true,
    overflow: 'hidden',
    headerInert: true,
    mainInert: true,
    rootInert: false
  });

  // Keyboard focus starts in the banner and never reaches the inert page.
  await expect(page.locator('.kdconsent-banner')).toBeFocused();
  for (let index = 0; index < 6; index += 1) {
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => !document.activeElement || !document.activeElement.closest('header, main'))).toBe(true);
  }

  // Clicks land on the backdrop, not on the page behind it, and the page does not scroll.
  const action = await page.locator('#page-action').boundingBox();
  await page.mouse.click(action.x + action.width / 2, action.y + action.height / 2);
  expect(await page.evaluate(() => window.pageActions)).toBe(0);
  expect(await page.evaluate(() => document.elementFromPoint(20, 20).className)).toBe('kdconsent-banner-overlay');
  await page.mouse.wheel(0, 800);
  expect(await page.evaluate(() => window.scrollY)).toBe(0);

  // Preferences can be opened and closed without releasing the page.
  await page.getByRole('button', { name: 'Customize' }).click();
  await expect(page.locator('#kdconsent-purpose-analytics')).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('.kdconsent-modal-overlay')).toHaveAttribute('aria-hidden', 'true');
  expect((await pageIsLocked(page)).mainInert).toBe(true);

  await page.getByRole('button', { name: 'Reject all' }).click();
  await expect(page.locator('.kdconsent-banner')).toBeHidden();
  expect(await pageIsLocked(page)).toEqual({
    scrollLocked: false,
    overflow: 'visible',
    headerInert: false,
    mainInert: false,
    rootInert: false
  });
  await page.locator('#page-action').click();
  expect(await page.evaluate(() => window.pageActions)).toBe(1);
});

test('the privacy policy page is never blocked and the banner links to it', async ({ page }) => {
  await mountBanner(page, { blockUntilChoice: true, path: '/privacy' });

  const lock = await pageIsLocked(page);
  expect(lock.scrollLocked).toBe(false);
  expect(lock.mainInert).toBe(false);
  await expect(page.locator('.kdconsent-banner-privacy')).toHaveAttribute('href', privacyPolicyUrl);
  await expect(page.locator('.kdconsent-banner-privacy')).toHaveText('Privacy policy');
  await page.locator('#page-action').click();
  expect(await page.evaluate(() => window.pageActions)).toBe(1);
});

test('the default banner does not block and omits a missing privacy link', async ({ page }) => {
  await mountBanner(page, { privacyPolicyUrl: '' });

  const lock = await pageIsLocked(page);
  expect(lock.scrollLocked).toBe(false);
  expect(lock.mainInert).toBe(false);
  await expect(page.locator('.kdconsent-banner-privacy')).toHaveCount(0);
  await page.locator('#page-action').click();
  expect(await page.evaluate(() => window.pageActions)).toBe(1);
});
