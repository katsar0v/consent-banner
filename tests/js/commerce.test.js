const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const sourcePath = path.resolve(__dirname, '../../assets/js/commerce.js');
const source = fs.readFileSync(sourcePath, 'utf8');

function makeStorage(initial) {
  const values = Object.assign({}, initial || {});
  return {
    get length() {
      return Object.keys(values).length;
    },
    key(index) {
      return Object.keys(values)[index] || null;
    },
    removeItem(key) {
      delete values[key];
    },
    dump() {
      return Object.assign({}, values);
    }
  };
}

function createHarness(options = {}) {
  const handlers = {};
  const jqueryHandlers = {};
  const logs = [];
  const commerceEvents = [];
  const cookieWrites = [];
  const timers = [];
  const observed = [];
  const localStorage = makeStorage({ sbjs_current: 'current', keep: 'safe' });
  const sessionStorage = makeStorage({ sbjs_custom: 'custom' });
  let now = Date.parse('2026-01-01T00:00:00Z');
  let consent = options.consent || { c: { essential: true, analytics: true, marketing: false } };
  let consentListener = null;

  class HarnessDate extends Date {
    constructor(...args) {
      super(...(args.length ? args : [now]));
    }

    static now() {
      return now;
    }
  }

  const itemElement = {
    nodeType: 1,
    dataset: {
      kdconsentCommerceItem: '1',
      kdconsentCommerceItemId: '42',
      kdconsentCommerceItemSku: 'SKU-42',
      kdconsentCommerceItemName: 'Test product',
      kdconsentCommerceItemPrice: '12.5',
      kdconsentCommerceIndex: '1',
      kdconsentCommerceListId: 'featured',
      kdconsentCommerceListName: 'Featured'
    },
    matches(selector) {
      return selector === '[data-kdconsent-commerce-item="1"]';
    },
    querySelectorAll() {
      return [];
    }
  };

  function classList(classes) {
    return {
      contains(name) {
        return classes.indexOf(name) !== -1;
      }
    };
  }

  function makeControl(classes, productId = '42', quantity = '2', wrapper = itemElement) {
    const dataset = { product_id: productId };
    if (quantity !== null) {
      dataset.quantity = quantity;
    }
    const control = {
      nodeType: 1,
      dataset,
      classList: classList(classes),
      closest(selector) {
        if (selector === '[data-kdconsent-commerce-item="1"]') {
          return wrapper;
        }
        if (selector === '.add_to_cart_button') {
          return classes.indexOf('add_to_cart_button') !== -1 ? control : null;
        }
        if (selector === '.remove, .remove_from_cart_button') {
          return classes.indexOf('remove') !== -1 || classes.indexOf('remove_from_cart_button') !== -1
            ? control
            : null;
        }
        if (selector === 'form.cart' || selector === 'form.cart, form.variations_form') {
          return null;
        }
        if (selector === '.wc-block-components-product-button__button, [data-kdconsent-commerce-action="add"]') {
          return classes.indexOf('wc-block-components-product-button__button') !== -1 ? control : null;
        }
        if (selector === '.wc-block-cart-item__remove-link, .wc-block-components-product-button__button--remove, [data-kdconsent-commerce-action="remove"]') {
          return classes.indexOf('wc-block-cart-item__remove-link') !== -1 ? control : null;
        }
        if (selector === '[data-wc-context]' || selector.indexOf('.mini_cart_item') === 0) {
          return null;
        }
        if (selector.indexOf('.add_to_cart_button') !== -1 || selector.indexOf('button') !== -1) {
          return control;
        }
        return null;
      }
    };
    return control;
  }

  function enrichControl(control, item) {
    control.dataset.kdconsentCommerceItemId = String(item.item_id);
    control.dataset.kdconsentCommerceItemSku = String(item.sku || '');
    control.dataset.kdconsentCommerceItemName = String(item.item_name);
    control.dataset.kdconsentCommerceItemPrice = String(item.price);
    control.dataset.kdconsentCommerceQuantity = String(item.quantity);
    control.dataset.kdconsentCommerceCartKey = String(item.cart_key || '');
    return control;
  }

  const ajaxButton = makeControl(['add_to_cart_button', 'ajax_add_to_cart']);
  const normalButton = makeControl(['add_to_cart_button']);
  const unknownButton = makeControl(['add_to_cart_button', 'ajax_add_to_cart'], '999', '1', null);
  const classicRemoveButton = makeControl(['remove'], '42', '1');
  const ajaxRemoveButton = makeControl(['remove_from_cart_button'], '42', '1');
  const variationButton = makeControl(['single_add_to_cart_button'], '42', '3', null);
  const standaloneRemoveButton = makeControl(['remove_from_cart_button'], '88', '4', null);
  const configuredRemoveButton = makeControl(['remove_from_cart_button'], '42', null, null);
  const fragmentRemoveButton = enrichControl(
    makeControl(['remove_from_cart_button'], '42', null, null),
    { item_id: '77', sku: 'VAR-77', item_name: 'Fresh variation', price: 10 / 3, quantity: 3, cart_key: 'fresh-key' }
  );
  const blockAddButton = enrichControl(
    makeControl(['wc-block-components-product-button__button'], '91', null, null),
    { item_id: '91', sku: 'BLOCK-91', item_name: 'Block A', price: 5, quantity: 2 }
  );
  const blockAddButtonB = enrichControl(
    makeControl(['wc-block-components-product-button__button'], '92', null, null),
    { item_id: '92', sku: 'BLOCK-92', item_name: 'Block B', price: 7, quantity: 1 }
  );
  const blockAddButtonExpired = enrichControl(
    makeControl(['wc-block-components-product-button__button'], '93', null, null),
    { item_id: '93', sku: 'BLOCK-93', item_name: 'Block expired', price: 8, quantity: 1 }
  );
  const blockAddButtonInvalid = enrichControl(
    makeControl(['wc-block-components-product-button__button'], '94', null, null),
    { item_id: '94', sku: 'BLOCK-94', item_name: 'Block invalid', price: 'not-a-price', quantity: 1 }
  );
  const blockRemoveButton = enrichControl(
    makeControl(['wc-block-cart-item__remove-link'], '91', null, null),
    { item_id: '91', sku: 'BLOCK-91', item_name: 'Block A', price: 5, quantity: 2 }
  );
  const productLink = {
    nodeType: 1,
    href: 'https://localhost/product/test/',
    closest(selector) {
      if (selector === '[data-kdconsent-commerce-item="1"]') {
        return itemElement;
      }
      if (selector === '[data-kdconsent-commerce-item="1"] a[href]') {
        return productLink;
      }
      return null;
    }
  };

  const document = {
    readyState: options.readyState || 'complete',
    body: {},
    createElement(name) {
      if (name !== 'template') {
        return {};
      }
      let html = '';
      const template = {
        content: {
          querySelectorAll() {
            return (html.match(/<[^>]+>/g) || []).map((tag) => {
              const dataset = {};
              const attributes = tag.matchAll(/\sdata-([a-z0-9-]+)=(['"])(.*?)\2/gi);
              for (const match of attributes) {
                const key = match[1].replace(/-([a-z0-9])/g, (value, character) => character.toUpperCase());
                dataset[key] = match[3];
              }
              return { dataset };
            }).filter((element) => (
              element.dataset.kdconsentCommerceItemId &&
              element.dataset.kdconsentCommerceItemName &&
              Object.prototype.hasOwnProperty.call(element.dataset, 'kdconsentCommerceItemPrice') &&
              element.dataset.kdconsentCommerceQuantity
            ));
          }
        }
      };
      Object.defineProperty(template, 'innerHTML', {
        set(value) {
          html = String(value);
        }
      });
      return template;
    },
    querySelectorAll(selector) {
      return selector === '[data-kdconsent-commerce-item="1"]' ? [itemElement] : [];
    },
    addEventListener(name, callback) {
      handlers[name] = handlers[name] || [];
      handlers[name].push(callback);
    },
    dispatchEvent(event) {
      if (event.type === 'kdconsent:commerce') {
        commerceEvents.push(JSON.parse(JSON.stringify(event.detail)));
      }
      (handlers[event.type] || []).forEach((callback) => callback(event));
      return true;
    }
  };
  Object.defineProperty(document, 'cookie', {
    get() {
      return 'sbjs_current=current; sbjs_discovered=discovered';
    },
    set(value) {
      cookieWrites.push(value);
    }
  });

  const attributionValues = [];
  const browserWindow = {
    kdconsentCommerceConfig: {
      schemaVersion: 1,
      debug: true,
      autoStart: options.autoStart === true,
      services: [
        { id: 'warehouse_metrics', purpose: 'analytics' },
        { id: 'campaign_delivery', purpose: 'marketing' },
        { id: 'invalid service!', purpose: 'analytics' }
      ],
      page: options.page || {
        type: 'product_archive',
        currency: 'EUR',
        currencyDecimals: 2,
        cartItems: [],
        items: [{ item_id: '42', sku: 'SKU-42', item_name: 'Test product', price: 12.5, quantity: 1 }]
      }
    },
    crypto: { randomUUID: () => '123e4567-e89b-42d3-a456-426614174000' },
    console: {
      log(prefix, payload) {
        logs.push({ prefix, payload: JSON.parse(JSON.stringify(payload)) });
      }
    },
    localStorage,
    sessionStorage,
    location: { hostname: 'shop.localhost' },
    kdconsent: {
      getConsent: () => consent,
      onChange(callback) {
        consentListener = callback;
        return () => {};
      }
    },
    wc_order_attribution: {
      setOrderTracking(value) {
        attributionValues.push(value);
      }
    },
    jQuery() {
      return {
        on(name, callback) {
          jqueryHandlers[name] = callback;
        }
      };
    },
    IntersectionObserver: function (callback, observerOptions) {
      this.callback = callback;
      this.options = observerOptions;
      this.observe = (element) => observed.push(element);
      this.unobserve = () => {};
      browserWindow.intersectionObserver = this;
    },
    setTimeout(callback, delay) {
      timers.push({ callback, delay });
      return timers.length;
    }
  };

  function CustomEvent(type, eventOptions) {
    this.type = type;
    this.detail = eventOptions && eventOptions.detail;
  }

  const context = {
    window: browserWindow,
    document,
    CustomEvent,
    Date: HarnessDate,
    Math,
    Number,
    Object,
    Array,
    String,
    JSON,
    Uint8Array,
    decodeURIComponent,
    encodeURIComponent
  };
  vm.createContext(context);
  vm.runInContext(source, context, { filename: sourcePath });

  return {
    window: browserWindow,
    document,
    logs,
    events: commerceEvents,
    handlers,
    jqueryHandlers,
    cookieWrites,
    localStorage,
    sessionStorage,
    attributionValues,
    itemElement,
    ajaxButton,
    normalButton,
    unknownButton,
    classicRemoveButton,
    ajaxRemoveButton,
    variationButton,
    standaloneRemoveButton,
    configuredRemoveButton,
    fragmentRemoveButton,
    blockAddButton,
    blockAddButtonB,
    blockAddButtonExpired,
    blockAddButtonInvalid,
    blockRemoveButton,
    productLink,
    observed,
    setConsent(next) {
      consent = next;
      if (consentListener) {
        consentListener(next);
      }
    },
    advanceTime(milliseconds) {
      now += milliseconds;
    },
    runTimers(delay) {
      const matching = timers.filter((timer) => timer.delay === delay);
      for (let index = timers.length - 1; index >= 0; index -= 1) {
        if (timers[index].delay === delay) {
          timers.splice(index, 1);
        }
      }
      matching.forEach((timer) => timer.callback());
    },
    dispatch(type, detail, target) {
      document.dispatchEvent({ type, detail, target });
    }
  };
}

{
  const result = createHarness();
  const api = result.window.kdconsentCommerce;
  const event = api.buildEvent('view_item', {
    email: 'must-not-leak@example.test',
    gclid: 'must-not-leak',
    search_term: 'private search',
    items: [{ item_id: '42', sku: 'SKU-42', item_name: 'Test product', price: 12.5, quantity: 2 }]
  });

  assert.match(event.event_id, /^browser:view_item:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  assert.strictEqual(event.source, 'browser');
  assert.strictEqual(event.ecommerce.email, undefined);
  assert.strictEqual(event.ecommerce.gclid, undefined);
  assert.strictEqual(event.ecommerce.search_term, undefined);
  assert.deepStrictEqual(JSON.parse(JSON.stringify(event.planned_destinations)), ['warehouse_metrics', 'invalidservice']);
  [
    'page_view',
    'view_item_list',
    'select_item',
    'view_item',
    'add_to_cart',
    'remove_from_cart',
    'view_cart',
    'begin_checkout',
    'add_shipping_info',
    'add_payment_info',
    'search'
  ].forEach((name) => assert.strictEqual(api.buildEvent(name, {}).event_name, name));
  assert.strictEqual(api.buildEvent('purchase', {}), null);
  console.log('ok - browser envelope is UUID-based, allowlisted, and derives destinations from services');

  const emitted = api.emit('search', { search_term: 'private search', phone: '+123' });
  assert.deepStrictEqual(JSON.parse(JSON.stringify(emitted.ecommerce)), {});
  assert.deepStrictEqual(result.events[0], emitted);
  assert.deepStrictEqual(result.logs[0], { prefix: '[kdconsent-commerce]', payload: emitted });
  console.log('ok - only the redacted event is logged, dispatched, and returned');
}

{
  const result = createHarness({ autoStart: true });
  const downstreamEvents = [];
  result.document.addEventListener('kdconsent:commerce', (event) => downstreamEvents.push(event.detail));
  assert.strictEqual(downstreamEvents.length, 0);
  result.runTimers(0);
  assert.strictEqual(downstreamEvents.filter((event) => event.event_name === 'page_view').length, 1);
  console.log('ok - deferred auto-start lets immediately dependent listeners receive initial page events');
}

{
  const result = createHarness({
    page: {
      type: 'cart',
      currency: 'EUR',
      currencyDecimals: 2,
      cartItems: [],
      items: [{ item_id: '42', sku: 'SKU-42', item_name: 'Thirds', price: 10 / 3, quantity: 3 }]
    }
  });
  result.window.kdconsentCommerce.init();
  const cartEvent = result.events.find((event) => event.event_name === 'view_cart');
  assert.strictEqual(cartEvent.ecommerce.items[0].price, 10 / 3);
  assert.strictEqual(cartEvent.ecommerce.value, 10);

  const decimalResult = createHarness({
    page: {
      type: 'cart',
      currency: 'EUR',
      currencyDecimals: 2,
      cartItems: [],
      items: [{ item_id: '43', sku: '', item_name: 'Decimals', price: 0.1, quantity: 3 }]
    }
  });
  decimalResult.window.kdconsentCommerce.init();
  const decimalEvent = decimalResult.events.find((event) => event.event_name === 'view_cart');
  assert.strictEqual(decimalEvent.ecommerce.value, 0.3);
  console.log('ok - repeating unit prices preserve exact merchandise totals at currency precision');
}

{
  const result = createHarness();
  const api = result.window.kdconsentCommerce;
  api.init();
  const variation = {
    variation_id: 77,
    kdconsent_commerce: { item_id: '77', sku: 'VAR-77', item_name: 'Blue', price: 9, quantity: 1 }
  };
  result.jqueryHandlers.found_variation({}, variation);
  result.jqueryHandlers.added_to_cart({}, {}, 'variation-cart-hash', { 0: result.variationButton });
  result.jqueryHandlers.added_to_cart({}, {}, 'list-cart-hash', { 0: result.ajaxButton });
  const added = result.events.filter((event) => event.event_name === 'add_to_cart');
  assert.strictEqual(added[0].ecommerce.items[0].item_id, '77');
  assert.strictEqual(added[0].ecommerce.items[0].quantity, 3);
  assert.strictEqual(added[1].ecommerce.items[0].item_id, '42');
  console.log('ok - confirmed variation adds use the selected variation without contaminating list controls');
}

{
  const result = createHarness();
  result.ajaxButton.dataset.quantity = '3';
  result.window.kdconsentCommerce.init();
  const fragments = {
    'div.widget_shopping_cart_content': '<div><a class="remove_from_cart_button" data-kdconsent-commerce-item-id="42" data-kdconsent-commerce-item-sku="SKU-42" data-kdconsent-commerce-item-name="Test product" data-kdconsent-commerce-item-price="3.3333333333333335" data-kdconsent-commerce-quantity="6" data-kdconsent-commerce-cart-key="server-only">Remove</a></div>'
  };
  result.jqueryHandlers.added_to_cart({}, fragments, 'effective-price-hash', { 0: result.ajaxButton });
  const added = result.events.find((event) => event.event_name === 'add_to_cart');
  assert.strictEqual(added.ecommerce.items[0].price, 10 / 3);
  assert.strictEqual(added.ecommerce.items[0].quantity, 3);
  assert.strictEqual(added.ecommerce.value, 10);
  assert.strictEqual(JSON.stringify(added).includes('server-only'), false);

  const ambiguousFragments = {
    'div.widget_shopping_cart_content': '<div>' +
      '<a data-kdconsent-commerce-item-id="42" data-kdconsent-commerce-item-sku="SKU-42" data-kdconsent-commerce-item-name="Test product" data-kdconsent-commerce-item-price="3" data-kdconsent-commerce-quantity="3" data-kdconsent-commerce-cart-key="line-a"></a>' +
      '<a data-kdconsent-commerce-item-id="42" data-kdconsent-commerce-item-sku="SKU-42" data-kdconsent-commerce-item-name="Test product" data-kdconsent-commerce-item-price="4" data-kdconsent-commerce-quantity="3" data-kdconsent-commerce-cart-key="line-b"></a>' +
      '</div>'
  };
  const beforeAmbiguous = result.events.filter((event) => event.event_name === 'add_to_cart').length;
  result.jqueryHandlers.added_to_cart({}, ambiguousFragments, 'ambiguous-price-hash', { 0: result.ajaxButton });
  assert.strictEqual(
    result.events.filter((event) => event.event_name === 'add_to_cart').length,
    beforeAmbiguous
  );
  console.log('ok - confirmed AJAX adds use unique fragment pricing and suppress ambiguous cart lines');
}

{
  const contentResult = createHarness({
    page: {
      type: 'content',
      currency: 'EUR',
      currencyDecimals: 2,
      items: [],
      cartItems: [{
        cart_key: 'stale-key',
        product_id: 42,
        variation_id: 0,
        item: { item_id: '42', sku: 'STALE', item_name: 'Stale product', price: 99, quantity: 1 }
      }]
    }
  });
  contentResult.window.kdconsentCommerce.init();
  contentResult.standaloneRemoveButton.dataset.product_id = '42';
  contentResult.standaloneRemoveButton.dataset.cart_item_key = 'stale-key';
  contentResult.dispatch('click', null, contentResult.standaloneRemoveButton);
  contentResult.jqueryHandlers.removed_from_cart({}, {}, 'standalone-remove', { 0: contentResult.standaloneRemoveButton });
  assert.strictEqual(contentResult.events.filter((event) => event.event_name === 'remove_from_cart').length, 0);
  contentResult.dispatch('click', null, contentResult.fragmentRemoveButton);
  contentResult.jqueryHandlers.removed_from_cart({}, {}, 'fragment-remove', { 0: contentResult.fragmentRemoveButton });
  const fragment = contentResult.events.find((event) => event.event_name === 'remove_from_cart');
  assert.deepStrictEqual(JSON.parse(JSON.stringify(fragment.ecommerce.items[0])), {
    item_id: '77',
    sku: 'VAR-77',
    item_name: 'Fresh variation',
    price: 10 / 3,
    quantity: 3
  });
  assert.strictEqual(fragment.ecommerce.value, 10);

  const cartResult = createHarness({
    page: {
      type: 'cart',
      currency: 'EUR',
      currencyDecimals: 2,
      items: [],
      cartItems: [{
        cart_key: 'cart-line-42',
        product_id: 42,
        variation_id: 0,
        item: { item_id: '42', sku: 'SKU-42', item_name: 'Cart product', price: 4, quantity: 3 }
      }]
    }
  });
  cartResult.configuredRemoveButton.dataset.cart_item_key = 'cart-line-42';
  cartResult.window.kdconsentCommerce.init();
  cartResult.dispatch('click', null, cartResult.configuredRemoveButton);
  cartResult.jqueryHandlers.removed_from_cart({}, {}, 'configured-remove', { 0: cartResult.configuredRemoveButton });
  const configured = cartResult.events.find((event) => event.event_name === 'remove_from_cart');
  assert.strictEqual(configured.ecommerce.items[0].quantity, 3);
  assert.strictEqual(configured.ecommerce.value, 12);
  assert.strictEqual(JSON.stringify(configured).includes('cart-line-42'), false);
  console.log('ok - fresh fragments and keyed mini-cart removals retain exact data while ID-only controls are dropped');
}

{
  const result = createHarness({
    page: { type: 'content', currency: 'EUR', currencyDecimals: 2, cartItems: [], items: [] }
  });
  result.window.kdconsentCommerce.init();
  result.dispatch('click', null, result.blockAddButton);
  result.dispatch('click', null, result.blockAddButtonB);
  result.dispatch('wc-blocks_added_to_cart', { preserveCartData: true });
  result.dispatch('click', null, result.blockRemoveButton);
  result.dispatch('wc-blocks_removed_from_cart', { preserveCartData: true });
  assert.strictEqual(result.events.find((event) => event.event_name === 'add_to_cart').ecommerce.items[0].item_id, '92');
  assert.strictEqual(result.events.find((event) => event.event_name === 'remove_from_cart').ecommerce.items[0].quantity, 2);
  const before = result.events.length;
  result.dispatch('click', null, result.blockAddButtonExpired);
  result.advanceTime(2001);
  result.dispatch('wc-blocks_added_to_cart', { preserveCartData: true });
  assert.strictEqual(result.events.length, before);
  result.dispatch('click', null, result.blockAddButtonInvalid);
  result.dispatch('wc-blocks_added_to_cart', { preserveCartData: true });
  assert.strictEqual(result.events.length, before);
  result.dispatch('wc-blocks_added_to_cart', { preserveCartData: true });
  assert.strictEqual(result.events.length, before);
  console.log('ok - sparse Woo Blocks confirmations use only the latest unexpired exact intent');
}

{
  const result = createHarness();
  result.window.kdconsentCommerce.init();
  for (let index = 0; index < 201; index += 1) {
    result.jqueryHandlers.added_to_cart({}, {}, `cart-hash-${index}`, { 0: result.ajaxButton });
  }
  result.advanceTime(10000);
  result.jqueryHandlers.added_to_cart({}, {}, 'cart-hash-0', { 0: result.ajaxButton });
  assert.strictEqual(result.events.filter((event) => event.event_name === 'add_to_cart').length, 201);
  console.log('ok - confirmed cart hashes remain deduplicated for the full page lifetime beyond 200 signals');
}

{
  const result = createHarness();
  const api = result.window.kdconsentCommerce;
  api.init();
  api.init();

  assert.strictEqual(result.events.filter((event) => event.event_name === 'page_view').length, 1);
  assert.strictEqual(result.observed.length, 1);
  assert.deepStrictEqual(JSON.parse(JSON.stringify(result.window.intersectionObserver.options.threshold)), [0.5]);
  result.window.intersectionObserver.callback([
    { target: result.itemElement, isIntersecting: true, intersectionRatio: 0.49 }
  ]);
  result.runTimers(0);
  assert.strictEqual(result.events.filter((event) => event.event_name === 'view_item_list').length, 0);
  result.window.intersectionObserver.callback([
    { target: result.itemElement, isIntersecting: true, intersectionRatio: 0.5 }
  ]);
  result.runTimers(0);
  assert.strictEqual(result.events.filter((event) => event.event_name === 'view_item_list').length, 1);

  result.dispatch('click', null, result.productLink);
  result.dispatch('click', null, result.ajaxButton);
  assert.strictEqual(result.events.filter((event) => event.event_name === 'select_item').length, 1);
  assert.strictEqual(result.events.filter((event) => event.event_name === 'add_to_cart').length, 0);

  result.jqueryHandlers.added_to_cart({}, {}, 'cart-hash-1', { 0: result.ajaxButton });
  result.advanceTime(150);
  result.jqueryHandlers.added_to_cart({}, {}, 'cart-hash-1', { 0: result.ajaxButton });
  result.jqueryHandlers.added_to_cart({}, {}, 'cart-hash-2', { 0: result.ajaxButton });
  assert.strictEqual(result.events.filter((event) => event.event_name === 'add_to_cart').length, 2);
  assert.strictEqual(result.events.filter((event) => event.event_name === 'add_to_cart')[0].ecommerce.items[0].quantity, 2);

  result.jqueryHandlers.added_to_cart({}, {}, 'unknown', { 0: result.unknownButton });
  assert.strictEqual(result.events.filter((event) => event.event_name === 'add_to_cart').length, 2);
  result.dispatch('click', null, result.normalButton);
  result.dispatch('click', null, result.normalButton);
  assert.strictEqual(result.events.filter((event) => event.event_name === 'add_to_cart').length, 4);
  result.jqueryHandlers.added_to_cart({}, {}, 'plugin-confirmation', { 0: result.normalButton });
  assert.strictEqual(result.events.filter((event) => event.event_name === 'add_to_cart').length, 4);

  result.dispatch('click', null, result.classicRemoveButton);
  result.jqueryHandlers.removed_from_cart({}, {}, 'remove-hash-1', { 0: result.classicRemoveButton });
  result.dispatch('click', null, result.ajaxRemoveButton);
  result.jqueryHandlers.removed_from_cart({}, {}, 'remove-hash-2', { 0: result.ajaxRemoveButton });
  assert.strictEqual(result.events.filter((event) => event.event_name === 'remove_from_cart').length, 2);
  console.log('ok - visibility, select, confirmed AJAX dedupe, unknown-item rejection, and real actions are distinct');

  const variationA = {
    variation_id: 77,
    kdconsent_commerce: { item_id: '77', sku: 'VAR-77', item_name: 'Blue', price: 9, quantity: 1 }
  };
  const variationB = {
    variation_id: 78,
    kdconsent_commerce: { item_id: '78', sku: 'VAR-78', item_name: 'Red', price: 10, quantity: 1 }
  };
  result.jqueryHandlers.found_variation({}, variationA);
  result.jqueryHandlers.found_variation({}, variationA);
  result.jqueryHandlers.found_variation({}, variationB);
  result.jqueryHandlers.found_variation({}, variationA);
  result.jqueryHandlers.reset_data();
  result.jqueryHandlers.found_variation({}, variationA);
  assert.strictEqual(result.events.filter((event) => event.event_name === 'view_item').length, 4);
  console.log('ok - duplicate variation signals are ignored while reset and real reselection remain trackable');

  assert.deepStrictEqual(result.attributionValues, [false]);
  assert.strictEqual(result.localStorage.dump().sbjs_current, undefined);
  assert.strictEqual(result.localStorage.dump().keep, 'safe');
  assert.strictEqual(result.sessionStorage.dump().sbjs_custom, undefined);
  assert.ok(result.cookieWrites.some((value) => value.startsWith('sbjs_discovered=')));
  assert.ok(result.cookieWrites.some((value) => value.includes('Domain=shop.localhost')));
  assert.ok(result.cookieWrites.some((value) => value.includes('Domain=localhost')));
  result.setConsent({ c: { essential: true, analytics: true, marketing: true } });
  const consentedEvent = api.emit('page_view', {});
  assert.deepStrictEqual(
    JSON.parse(JSON.stringify(consentedEvent.planned_destinations)),
    ['warehouse_metrics', 'campaign_delivery', 'invalidservice']
  );
  result.setConsent({ c: { essential: true, analytics: true, marketing: false } });
  const revokedEvent = api.emit('page_view', {});
  assert.deepStrictEqual(
    JSON.parse(JSON.stringify(revokedEvent.planned_destinations)),
    ['warehouse_metrics', 'invalidservice']
  );
  assert.deepStrictEqual(result.attributionValues, [false, true, false]);
  assert.strictEqual((result.handlers['kdconsent:changed'] || []).length, 0);
  console.log('ok - attribution follows consent once, clears sourcebuster state, and prefers the consent API callback');
}
