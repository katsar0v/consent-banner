const path = require('path');
const { test, expect } = require('@playwright/test');

const pluginRoot = path.resolve(__dirname, '../..');

test('consent UI stays local, optional purposes start off, and transparency is accessible', async ({ page }) => {
  const externalRequests = [];
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  await page.route(/^https?:\/\//, async (route) => {
    externalRequests.push(route.request().url());
    await route.abort('blockedbyclient');
  });

  await page.setContent('<!doctype html><html><head></head><body><button id="return-focus">Open</button><div id="kdconsent-banner-root"></div></body></html>');
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/consent-storage.js') });
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/banner-ui.js') });
  await page.evaluate(() => {
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
          servicesTitle: 'Services',
          providerLabel: 'Provider',
          purposeLabel: 'Purpose',
          dataLabel: 'Data',
          cookiesLabel: 'Cookies',
          durationLabel: 'Duration',
          recipientsLabel: 'Recipients',
          transferLabel: 'Transfer',
          privacyLabel: 'Privacy'
        },
        categories: [
          { id: 'essential', label: 'Essential', required: true, enabledByDefault: true },
          { id: 'analytics', label: 'Analytics', required: false, enabledByDefault: false }
        ],
        services: [
          {
            id: 'clarity',
            name: 'Clarity',
            purpose: 'analytics',
            purposeDescription: 'Usage analysis',
            provider: 'Microsoft',
            data: ['Clicks'],
            cookies: ['_clck'],
            duration: '13 months',
            recipients: ['Microsoft'],
            thirdCountryTransfer: 'United States (SCC)',
            privacyUrl: 'https://privacy.example.test/'
          }
        ],
        behavior: { showRejectButton: true, showDelayMs: 0, position: 'bottom' }
      },
      { listeners: [], getConsent: () => null, setConsent: () => {} }
    );
  });

  await expect(page.getByRole('button', { name: 'Reject all' })).toBeVisible();
  await page.getByRole('button', { name: 'Customize' }).click();
  await expect(page.locator('.kdconsent-modal[role="dialog"][aria-modal="true"]')).toBeVisible();

  const essential = page.locator('#kdconsent-purpose-essential');
  const analytics = page.locator('#kdconsent-purpose-analytics');
  await expect(essential).toBeChecked();
  await expect(essential).toBeDisabled();
  await expect(analytics).not.toBeChecked();

  await page.getByText('Clarity', { exact: true }).click();
  await expect(page.getByText('Microsoft', { exact: true }).first()).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('.kdconsent-modal-overlay')).toHaveAttribute('aria-hidden', 'true');

  expect(externalRequests).toEqual([]);
  expect(pageErrors).toEqual([]);
});


test('preferences dialog shows the intro and its Accept all button grants every purpose', async ({ page }) => {
  await page.setContent('<!doctype html><html><head></head><body><div id="kdconsent-banner-root"></div></body></html>');
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/consent-storage.js') });
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/banner-ui.js') });
  await page.evaluate(() => {
    window.kdconsentInitBanner(
      {
        consentVersion: 1,
        restRoot: '/wp-json/kdconsent/v1/',
        texts: {
          acceptAllLabel: 'Accept all',
          customizeLabel: 'Customize',
          saveLabel: 'Save',
          closeLabel: 'Close',
          preferencesIntro: 'Choose what you allow.'
        },
        categories: [
          { id: 'essential', label: 'Essential', required: true, enabledByDefault: true },
          { id: 'analytics', label: 'Analytics', required: false, enabledByDefault: false }
        ],
        behavior: { showRejectButton: true, showDelayMs: 0, position: 'bottom' }
      },
      { listeners: [], getConsent: () => null, setConsent: (consent) => { window.savedConsent = consent; } }
    );
  });

  await page.getByRole('button', { name: 'Customize' }).click();
  const dialog = page.locator('.kdconsent-modal');
  await expect(dialog.locator('.kdconsent-modal-intro')).toHaveText('Choose what you allow.');
  await expect(dialog.locator('.kdconsent-modal-actions button')).toHaveText(['Close', 'Save', 'Accept all']);

  await dialog.getByRole('button', { name: 'Accept all' }).click();
  await expect(page.locator('.kdconsent-modal-overlay')).toHaveAttribute('aria-hidden', 'true');
  await expect(page.locator('.kdconsent-banner')).toBeHidden();
  expect(await page.evaluate(() => window.savedConsent.c)).toEqual({ essential: true, analytics: true });
});

test('preferences dialog omits an empty intro', async ({ page }) => {
  await page.setContent('<!doctype html><html><head></head><body><div id="kdconsent-banner-root"></div></body></html>');
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/consent-storage.js') });
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/banner-ui.js') });
  await page.evaluate(() => {
    window.kdconsentInitBanner(
      {
        consentVersion: 1,
        texts: { customizeLabel: 'Customize', preferencesIntro: '' },
        categories: [{ id: 'essential', label: 'Essential', required: true, enabledByDefault: true }],
        behavior: { showDelayMs: 0 }
      },
      { listeners: [], getConsent: () => null, setConsent: () => {} }
    );
  });

  await page.getByRole('button', { name: 'Customize' }).click();
  await expect(page.locator('.kdconsent-modal-overlay')).toHaveAttribute('aria-hidden', 'false');
  await expect(page.locator('.kdconsent-modal-intro')).toHaveCount(0);
});

test('deferred dialog CSS preserves manually styled preference triggers and their focus state', async ({ page }) => {
  await page.setContent(`<!doctype html><html><head><style>
    footer { background: #999; padding: 30px; color: white; }
    #manual-link { color: white; font-size: 12px; }
    #manual-button { color: white; background: #80642f; padding: 2px; border: 0; }
  </style></head><body><footer>
    <a id="manual-link" href="#preferences" class="kdconsent-open-preferences">Cookie settings</a>
    <button id="manual-button" class="kdconsent-open-preferences">Custom settings</button>
    <button id="plugin-button" class="kdconsent-open-preferences kdconsent-preferences-button">Shortcode settings</button>
  </footer></body></html>`);
  const appearance = async (selector) => page.locator(selector).evaluate((element) => {
    const style = getComputedStyle(element);
    return {
      color: style.color, background: style.backgroundColor, padding: style.padding,
      border: style.border, borderRadius: style.borderRadius,
      width: element.getBoundingClientRect().width, height: element.getBoundingClientRect().height
    };
  });
  const linkBefore = await appearance('#manual-link');
  const buttonBefore = await appearance('#manual-button');
  // Returning visitors load this stylesheet for the first time when reopening preferences.
  await page.addStyleTag({ path: path.join(pluginRoot, 'assets/css/banner.css') });
  await page.locator('#manual-link').focus();
  expect(await appearance('#manual-link')).toEqual(linkBefore);
  await page.locator('#manual-link').hover();
  expect(await appearance('#manual-link')).toEqual(linkBefore);
  await page.locator('#manual-button').focus();
  expect(await appearance('#manual-button')).toEqual(buttonBefore);
  expect(await appearance('#plugin-button')).toMatchObject({ color: 'rgb(31, 35, 40)', background: 'rgb(255, 255, 255)' });
});
