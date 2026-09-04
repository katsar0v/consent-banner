const path = require('path');
const fs = require('fs');
const { test, expect } = require('@playwright/test');

const pluginRoot = path.resolve(__dirname, '../..');
const commerceSource = fs.readFileSync(path.join(pluginRoot, 'assets/js/commerce.js'), 'utf8');

test('auto-start waits for dependent listeners before initial events', async ({ page }) => {
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  await page.setContent('<!doctype html><html><body></body></html>');

  const events = await page.evaluate(async (source) => {
    window.kdconsentCommerceConfig = {
      schemaVersion: 1,
      debug: false,
      autoStart: true,
      services: [],
      page: { type: 'content', currency: 'EUR', currencyDecimals: 2, cartItems: [], items: [] }
    };
    window.kdconsent = { getConsent: () => null, onChange: () => () => {} };
    const script = document.createElement('script');
    script.textContent = source;
    document.head.appendChild(script);

    const captured = [];
    document.addEventListener('kdconsent:commerce', (event) => captured.push(event.detail));
    await new Promise((resolve) => window.setTimeout(resolve, 20));
    return captured;
  }, commerceSource);

  expect(events.map((event) => event.event_name)).toEqual(['page_view']);
  expect(pageErrors).toEqual([]);
});

test('opt-in browser commerce emits redacted local events for real actions only', async ({ page }) => {
  const externalRequests = [];
  const pageErrors = [];
  const events = [];

  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('console', async (message) => {
    if (message.type() === 'log' && message.text().startsWith('[kdconsent-commerce]')) {
      events.push(await message.args()[1].jsonValue());
    }
  });
  await page.route(/^https?:\/\//, async (route) => {
    const url = new URL(route.request().url());
    if (url.hostname === 'localhost' || url.hostname === '127.0.0.1' || url.hostname.endsWith('.localhost')) {
      await route.continue();
      return;
    }
    externalRequests.push(url.href);
    await route.abort('blockedbyclient');
  });

  await page.setContent(`<!doctype html><html><body>
    <div id="featured" role="list" data-kdconsent-commerce-list-id="featured">
      <article role="listitem" data-kdconsent-commerce-item="1"
        data-kdconsent-commerce-item-id="42" data-kdconsent-commerce-item-sku="SKU-42"
        data-kdconsent-commerce-item-name="Test product" data-kdconsent-commerce-item-price="12.5"
        data-kdconsent-commerce-index="1" data-kdconsent-commerce-list-id="featured"
        data-kdconsent-commerce-list-name="Featured">
        <a class="product-link" href="#product">Test product</a>
        <button class="add_to_cart_button ajax_add_to_cart"
          data-product_id="42" data-quantity="3">Add with AJAX</button>
        <button class="add_to_cart_button" data-product_id="42" data-quantity="1">Add normally</button>
      </article>
    </div>
  </body></html>`);
  await page.evaluate(() => {
    window.kdconsentCommerceConfig = {
      schemaVersion: 1,
      debug: true,
      autoStart: false,
      services: [
        { id: 'local_analytics', purpose: 'analytics' },
        { id: 'local_marketing', purpose: 'marketing' }
      ],
      page: {
        type: 'product_archive',
        currency: 'EUR',
        items: [{ item_id: '42', sku: 'SKU-42', item_name: 'Test product', price: 12.5, quantity: 1 }]
      }
    };
    window.kdconsent = {
      getConsent: () => ({ c: { essential: true, analytics: true, marketing: false } }),
      onChange: () => () => {}
    };
    window.wooHandlers = {};
    window.jQuery = () => ({
      on(name, callback) {
        window.wooHandlers[name] = callback;
      }
    });
  });
  await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/commerce.js') });
  await page.evaluate(() => {
    window.commerceDomEvents = [];
    document.addEventListener('kdconsent:commerce', (event) => {
      window.commerceDomEvents.push(event.detail);
    });
    window.kdconsentCommerce.init();
    window.kdconsentCommerce.init();
  });

  await expect.poll(() => events.filter((event) => event.event_name === 'view_item_list').length).toBe(1);
  expect(events.filter((event) => event.event_name === 'page_view')).toHaveLength(1);
  expect(events[0].planned_destinations).toEqual(['local_analytics']);

  await page.locator('.product-link').click();
  await page.locator('.ajax_add_to_cart').click();
  expect(events.filter((event) => event.event_name === 'select_item')).toHaveLength(1);
  expect(events.filter((event) => event.event_name === 'add_to_cart')).toHaveLength(0);

  await page.evaluate(() => {
    const button = document.querySelector('.ajax_add_to_cart');
    const fragments = {
      'div.widget_shopping_cart_content': '<div><a class="remove_from_cart_button" data-kdconsent-commerce-item-id="42" data-kdconsent-commerce-item-sku="SKU-42" data-kdconsent-commerce-item-name="Test product" data-kdconsent-commerce-item-price="3.3333333333333335" data-kdconsent-commerce-quantity="6" data-kdconsent-commerce-cart-key="private-cart-key">Remove</a></div>'
    };
    window.wooHandlers.added_to_cart({}, fragments, 'cart-hash-1', { 0: button });
    window.wooHandlers.added_to_cart({}, fragments, 'cart-hash-1', { 0: button });
  });
  await expect.poll(() => events.filter((event) => event.event_name === 'add_to_cart').length).toBe(1);
  const ajaxEvent = events.find((event) => event.event_name === 'add_to_cart');
  expect(ajaxEvent.ecommerce.items[0].price).toBeCloseTo(10 / 3, 12);
  expect(ajaxEvent.ecommerce.items[0].quantity).toBe(3);
  expect(ajaxEvent.ecommerce.value).toBe(10);
  expect(JSON.stringify(ajaxEvent)).not.toContain('private-cart-key');

  await page.locator('.add_to_cart_button:not(.ajax_add_to_cart)').click();
  await page.locator('.add_to_cart_button:not(.ajax_add_to_cart)').click();
  await expect.poll(() => events.filter((event) => event.event_name === 'add_to_cart').length).toBe(3);

  await page.evaluate(() => {
    const item = document.createElement('article');
    item.setAttribute('role', 'listitem');
    item.dataset.kdconsentCommerceItem = '1';
    item.dataset.kdconsentCommerceItemId = '43';
    item.dataset.kdconsentCommerceItemSku = 'SKU-43';
    item.dataset.kdconsentCommerceItemName = 'Dynamic product';
    item.dataset.kdconsentCommerceItemPrice = '8';
    item.dataset.kdconsentCommerceIndex = '2';
    item.dataset.kdconsentCommerceListId = 'featured';
    item.dataset.kdconsentCommerceListName = 'Featured';
    item.style.height = '20px';
    document.querySelector('#featured').appendChild(item);
  });
  await expect.poll(() => events.filter((event) => event.event_name === 'view_item_list').length).toBe(2);

  const domEvents = await page.evaluate(() => window.commerceDomEvents);
  expect(domEvents).toEqual(events);
  expect(JSON.stringify(events)).not.toContain('search_term');
  expect(JSON.stringify(events)).not.toContain('gclid');
  expect(JSON.stringify(events)).not.toContain('email');
  expect(externalRequests).toEqual([]);
  expect(pageErrors).toEqual([]);
});
