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
