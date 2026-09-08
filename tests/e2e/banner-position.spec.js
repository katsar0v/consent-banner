const path = require('path');
const { test, expect } = require('@playwright/test');

const pluginRoot = path.resolve(__dirname, '../..');
const fixtureCss = `
  #kdconsent-banner-root .kdconsent-banner {
    width: min(75vw, 1200px);
    max-width: none;
    margin: 0 auto 20px;
  }

  @media (max-width: 768px) {
    #kdconsent-banner-root .kdconsent-banner {
      width: calc(100vw - 24px);
      margin: 0 auto 12px;
    }
  }
`;

async function mountBanner(page, options = {}) {
  const position = options.position || 'center';
  const body = options.body || 'Choose optional purposes.';
  const animation = options.animation || 'fade-in';

  await page.setContent(`<!doctype html><html><head></head><body>
    <button id="underlying-action" type="button">Underlying action</button>
    <div id="kdconsent-banner-root"></div>
  </body></html>`);
  await page.addStyleTag({ path: path.join(pluginRoot, 'assets/css/banner.css') });
  if (options.fixture !== false) {
    await page.addStyleTag({ content: fixtureCss });
  }
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/consent-storage.js') });
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/banner-ui.js') });
  await page.evaluate(({ position: selectedPosition, body: bannerBody, animation: selectedAnimation }) => {
    window.kdconsentInitBanner(
      {
        consentVersion: 1,
        restRoot: '/wp-json/kdconsent/v1/',
        texts: {
          bannerTitle: 'Privacy choices',
          bannerBody,
          acceptAllLabel: 'Accept all',
          rejectAllLabel: 'Reject all',
          customizeLabel: 'Customize',
          saveLabel: 'Save',
          closeLabel: 'Close',
          preferencesTitle: 'Preferences'
        },
        categories: [
          { id: 'essential', label: 'Essential', required: true, enabledByDefault: true },
          { id: 'analytics', label: 'Analytics', required: false, enabledByDefault: false }
        ],
        behavior: {
          showRejectButton: true,
          showDelayMs: 0,
          position: selectedPosition,
          animation: selectedAnimation
        }
      },
      { listeners: [], getConsent: () => null, setConsent: () => {} }
    );
  }, { position, body, animation });

  await expect(page.getByRole('button', { name: 'Accept all' })).toBeVisible();
}

async function assertCentered(page, gutter) {
  const viewport = page.viewportSize();
  const banner = page.locator('#kdconsent-banner-root .kdconsent-banner');
  const box = await banner.boundingBox();
  expect(box).not.toBeNull();
  expect(Math.abs(box.x + box.width / 2 - viewport.width / 2)).toBeLessThanOrEqual(2);
  expect(Math.abs(box.y + box.height / 2 - viewport.height / 2)).toBeLessThanOrEqual(2);
  expect(box.x).toBeGreaterThanOrEqual(gutter - 2);
  expect(box.x + box.width).toBeLessThanOrEqual(viewport.width - gutter + 2);
  expect(box.y).toBeGreaterThanOrEqual(gutter - 2);
  expect(box.y + box.height).toBeLessThanOrEqual(viewport.height - gutter + 2);
}

test('center position uses the viewport center, keeps preferences functional, and releases page hits after consent', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await mountBanner(page, { animation: 'slide-in-up' });

  await page.locator('.kdconsent-banner').evaluate((element) => Promise.all(
    element.getAnimations().map((animation) => animation.finished)
  ));
  await assertCentered(page, 20);
  await expect(page.locator('#kdconsent-banner-root')).toHaveClass(/kdconsent-position-center/);
  await expect(page.locator('.kdconsent-banner')).toHaveClass(/kdconsent-position-center/);
  await expect(page.locator('.kdconsent-banner')).not.toHaveClass(/kdconsent-anim-enter/);

  await page.getByRole('button', { name: 'Customize' }).click();
  await expect(page.locator('.kdconsent-modal-overlay')).toHaveAttribute('aria-hidden', 'false');
  // Preferences transfers focus asynchronously before it can handle keyboard input.
  await expect(page.locator('#kdconsent-purpose-analytics')).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('.kdconsent-modal-overlay')).toHaveAttribute('aria-hidden', 'true');

  await page.getByRole('button', { name: 'Accept all' }).click();
  await expect(page.locator('.kdconsent-banner')).toBeHidden();
  await page.locator('#underlying-action').click();
  await expect(page.locator('#underlying-action')).toBeFocused();
});

test('mobile center position remains centered with normal content', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await mountBanner(page, { animation: 'fade-in' });

  await page.locator('.kdconsent-banner').evaluate((element) => Promise.all(
    element.getAnimations().map((animation) => animation.finished)
  ));
  await assertCentered(page, 12);
});

test('mobile center position keeps long content within the viewport and actions reachable', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  const body = Array(140).fill('Long consent copy remains readable while the actions stay reachable.').join(' ');
  await mountBanner(page, { body, animation: 'slide-in-down' });

  await assertCentered(page, 12);
  const metrics = await page.locator('.kdconsent-banner').evaluate((element) => ({
    clientHeight: element.clientHeight,
    scrollHeight: element.scrollHeight,
    animationName: getComputedStyle(element).animationName
  }));
  expect(metrics.clientHeight).toBeLessThanOrEqual(820);
  expect(metrics.scrollHeight).toBeGreaterThan(metrics.clientHeight);
  expect(metrics.animationName).toBe('none');

  const accept = page.getByRole('button', { name: 'Accept all' });
  await accept.scrollIntoViewIfNeeded();
  await expect(accept).toBeVisible();
  await accept.click();
  await expect(page.locator('.kdconsent-banner')).toBeHidden();
});

test('bottom position remains anchored to the bottom edge', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await mountBanner(page, { position: 'bottom', fixture: false });

  const viewport = page.viewportSize();
  const box = await page.locator('.kdconsent-banner').boundingBox();
  expect(box).not.toBeNull();
  expect(box.y + box.height).toBeCloseTo(viewport.height - 20, 0);
  expect(box.x).toBeGreaterThanOrEqual(0);
  expect(box.x + box.width).toBeLessThanOrEqual(viewport.width);
  await expect(page.locator('#kdconsent-banner-root')).toHaveClass(/kdconsent-position-bottom/);
});
