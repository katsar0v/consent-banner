(function () {
  'use strict';

  var config = window.kdconsentCommerceConfig || {};
  var allowedEvents = {
    page_view: true,
    view_item_list: true,
    select_item: true,
    view_item: true,
    add_to_cart: true,
    remove_from_cart: true,
    view_cart: true,
    begin_checkout: true,
    add_shipping_info: true,
    add_payment_info: true,
    search: true
  };
  var initialized = false;
  var emittedInitial = {};
  var processedSignals = {};
  var currentVariation = null;
  var listObserver = null;
  var listMutationObserver = null;
  var pendingLists = {};
  var pendingListTimer = null;
  var lastAttributionApi = null;
  var lastAttributionValue = null;
  var optimisticAddControls = [];
  var optimisticRemoveControls = [];
  var pendingRemoveSnapshots = [];
  var pendingBlockAdd = null;
  var pendingBlockRemoval = null;
  var blockIntentLifetime = 2000;

  function cleanText(value) {
    return typeof value === 'string' || typeof value === 'number'
      ? String(value).replace(/[\u0000-\u001f\u007f]/g, '').slice(0, 500)
      : '';
  }

  function cleanIdentifier(value) {
    return cleanText(value).toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 100);
  }

  function number(value) {
    var parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function clone(value) {
    return value ? JSON.parse(JSON.stringify(value)) : value;
  }

  function configuredServices() {
    var seen = {};
    return (Array.isArray(config.services) ? config.services : []).reduce(function (result, raw) {
      var id = raw && cleanIdentifier(raw.id);
      var purpose = raw && cleanIdentifier(raw.purpose);
      if (!id || !purpose || purpose === 'essential' || seen[id]) {
        return result;
      }
      seen[id] = true;
      result.push({ id: id, purpose: purpose });
      return result;
    }, []);
  }

  function consentCategories(state) {
    if (!state || typeof state !== 'object') {
      return {};
    }
    return state.c && typeof state.c === 'object' ? state.c : state;
  }

  function consentSnapshot(state) {
    var categories = consentCategories(state);
    var purposes = ['preferences', 'analytics', 'marketing'];
    configuredServices().forEach(function (service) {
      if (purposes.indexOf(service.purpose) === -1) {
        purposes.push(service.purpose);
      }
    });

    var snapshot = { essential: true };
    purposes.forEach(function (purpose) {
      snapshot[purpose] = !!categories[purpose];
    });
    return snapshot;
  }

  function getConsent() {
    if (window.kdconsent && typeof window.kdconsent.getConsent === 'function') {
      return window.kdconsent.getConsent();
    }
    return null;
  }

  function plannedDestinations(state) {
    var categories = consentCategories(state);
    return configuredServices()
      .filter(function (service) {
        return !!categories[service.purpose];
      })
      .map(function (service) {
        return service.id;
      });
  }

  function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    var bytes = null;
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
    }

    if (bytes) {
      bytes[6] = (bytes[6] & 15) | 64;
      bytes[8] = (bytes[8] & 63) | 128;
      return Array.prototype.map.call(bytes, function (byte, index) {
        var value = byte.toString(16).padStart(2, '0');
        return [4, 6, 8, 10].indexOf(index) !== -1 ? '-' + value : value;
      }).join('');
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
      var random = Math.floor(Math.random() * 16);
      var value = character === 'x' ? random : (random & 3) | 8;
      return value.toString(16);
    });
  }

  function redactItem(raw) {
    raw = raw && typeof raw === 'object' ? raw : {};
    var item = {
      item_id: cleanText(raw.item_id),
      sku: cleanText(raw.sku),
      item_name: cleanText(raw.item_name),
      price: number(raw.price),
      quantity: Math.max(1, number(raw.quantity || 1))
    };
    var position = Math.max(0, number(raw.index));
    if (position > 0) {
      item.index = position;
    }
    if (raw.item_list_id) {
      item.item_list_id = cleanIdentifier(raw.item_list_id);
    }
    if (raw.item_list_name) {
      item.item_list_name = cleanText(raw.item_list_name);
    }
    return item;
  }

  function exactItem(raw) {
    if (!raw || typeof raw !== 'object') {
      return null;
    }
    var parsedPrice = Number(raw.price);
    var hasPrice = Object.prototype.hasOwnProperty.call(raw, 'price') &&
      raw.price !== '' &&
      raw.price !== null &&
      Number.isFinite(parsedPrice);
    var hasQuantity = Object.prototype.hasOwnProperty.call(raw, 'quantity') && number(raw.quantity) > 0;
    var item = redactItem(raw);
    return item.item_id && item.item_name && hasPrice && hasQuantity ? item : null;
  }

  function redactEcommerce(raw) {
    raw = raw && typeof raw === 'object' ? raw : {};
    var ecommerce = {};
    ['currency', 'transaction_id', 'refund_id', 'item_list_id', 'item_list_name'].forEach(function (key) {
      if (typeof raw[key] !== 'undefined' && cleanText(raw[key])) {
        ecommerce[key] = key.indexOf('_id') !== -1 ? cleanIdentifier(raw[key]) : cleanText(raw[key]);
      }
    });
    ['value', 'paid_value', 'shipping', 'tax'].forEach(function (key) {
      if (typeof raw[key] !== 'undefined') {
        ecommerce[key] = number(raw[key]);
      }
    });
    ['has_email', 'has_phone', 'has_click_id'].forEach(function (key) {
      if (typeof raw[key] !== 'undefined') {
        ecommerce[key] = !!raw[key];
      }
    });
    if (Array.isArray(raw.items)) {
      ecommerce.items = raw.items.map(redactItem).filter(function (item) {
        return !!item.item_id;
      });
    }
    return ecommerce;
  }

  function redact(raw) {
    raw = raw && typeof raw === 'object' ? raw : {};
    var state = raw.consent && typeof raw.consent === 'object' ? raw.consent : {};
    return {
      schema_version: Math.max(1, Math.floor(number(raw.schema_version || 1))),
      event_name: allowedEvents[raw.event_name] ? raw.event_name : '',
      event_id: cleanText(raw.event_id),
      occurred_at: cleanText(raw.occurred_at),
      source: 'browser',
      consent: consentSnapshot(state),
      ecommerce: redactEcommerce(raw.ecommerce),
      planned_destinations: plannedDestinations(state)
    };
  }

  function buildEvent(name, ecommerce, state) {
    if (!allowedEvents[name]) {
      return null;
    }
    var consent = typeof state === 'undefined' ? getConsent() : state;
    return redact({
      schema_version: Number(config.schemaVersion) || 1,
      event_name: name,
      event_id: 'browser:' + name + ':' + uuid(),
      occurred_at: new Date().toISOString(),
      source: 'browser',
      consent: consentSnapshot(consent),
      ecommerce: ecommerce || {}
    });
  }

  function signalProcessed(key) {
    key = cleanText(key);
    if (!key) {
      return false;
    }
    if (processedSignals[key]) {
      return true;
    }
    processedSignals[key] = true;
    return false;
  }

  function emit(name, ecommerce, signalKey) {
    if (!allowedEvents[name] || (signalKey && signalProcessed(signalKey))) {
      return null;
    }
    var event = buildEvent(name, ecommerce);
    if (!event) {
      return null;
    }
    var publicEvent = clone(event);
    if (config.debug && window.console && typeof window.console.log === 'function') {
      window.console.log('[kdconsent-commerce]', clone(publicEvent));
    }
    document.dispatchEvent(new CustomEvent('kdconsent:commerce', { detail: clone(publicEvent) }));
    return publicEvent;
  }

  function emitInitial(name, ecommerce) {
    if (emittedInitial[name]) {
      return null;
    }
    emittedInitial[name] = true;
    return emit(name, ecommerce);
  }

  function pageItems() {
    var items = config.page && Array.isArray(config.page.items) ? config.page.items : [];
    return items.map(redactItem).filter(function (item) {
      return !!item.item_id;
    });
  }

  function makeEcommerce(items, extra) {
    var result = Object.assign({}, extra || {});
    result.currency = cleanText((config.page && config.page.currency) || '');
    result.items = (Array.isArray(items) ? items : []).map(redactItem).filter(function (item) {
      return !!item.item_id;
    });
    var value = result.items.reduce(function (total, item) {
      return total + number(item.price) * Math.max(1, number(item.quantity));
    }, 0);
    var configuredDecimals = config.page && typeof config.page.currencyDecimals !== 'undefined'
      ? config.page.currencyDecimals
      : 2;
    var decimals = Math.max(0, Math.min(6, Math.floor(number(configuredDecimals))));
    var factor = Math.pow(10, decimals);
    result.value = Math.round((value + Number.EPSILON) * factor) / factor;
    return result;
  }

  function itemFromElement(element) {
    if (!element || !element.dataset) {
      return null;
    }
    var data = element.dataset;
    var item = redactItem({
      item_id: data.kdconsentCommerceItemId,
      sku: data.kdconsentCommerceItemSku,
      item_name: data.kdconsentCommerceItemName,
      price: data.kdconsentCommerceItemPrice,
      quantity: data.kdconsentCommerceQuantity || 1,
      index: data.kdconsentCommerceIndex,
      item_list_id: data.kdconsentCommerceListId,
      item_list_name: data.kdconsentCommerceListName
    });
    return item.item_id ? item : null;
  }

  function findConfiguredItem(productId) {
    productId = cleanText(productId);
    if (!productId) {
      return null;
    }
    return pageItems().find(function (item) {
      return item.item_id === productId;
    }) || null;
  }

  function findExactConfiguredItem(productId) {
    productId = cleanText(productId);
    if (!productId) {
      return null;
    }
    var items = config.page && Array.isArray(config.page.items) ? config.page.items : [];
    var matches = items.filter(function (item) {
      return item && cleanText(item.item_id) === productId;
    });
    return matches.length === 1 ? exactItem(matches[0]) : null;
  }

  function unwrapElement(candidate) {
    if (!candidate) {
      return null;
    }
    return candidate.nodeType ? candidate : candidate[0] || null;
  }

  function closestItem(element) {
    return element && typeof element.closest === 'function'
      ? element.closest('[data-kdconsent-commerce-item="1"]')
      : null;
  }

  function itemForControl(control) {
    var wrapperItem = itemFromElement(closestItem(control));
    if (wrapperItem) {
      return wrapperItem;
    }
    var productId = control && control.dataset
      ? control.dataset.product_id || control.dataset.productId || control.dataset.kdconsentCommerceItemId
      : '';
    return findConfiguredItem(productId);
  }

  function cartKeyForControl(control) {
    var key = control && control.dataset
      ? control.dataset.kdconsentCommerceCartKey || control.dataset.cart_item_key || control.dataset.cartItemKey
      : '';
    if (!key && control && control.href) {
      var match = /[?&]remove_item=([^&#]+)/.exec(String(control.href));
      if (match) {
        try {
          key = decodeURIComponent(match[1].replace(/\+/g, ' '));
        } catch (error) {
          key = '';
        }
      }
    }
    return cleanText(key);
  }

  function itemFromCartContext(control) {
    var pageType = config.page && cleanIdentifier(config.page.type);
    if (pageType !== 'cart' && pageType !== 'checkout') {
      return null;
    }
    var cartItems = config.page && Array.isArray(config.page.cartItems) ? config.page.cartItems : [];
    var cartKey = cartKeyForControl(control);
    var productId = control && control.dataset
      ? cleanText(control.dataset.variation_id || control.dataset.variationId || control.dataset.product_id || control.dataset.productId)
      : '';
    var matches = cartItems.filter(function (entry) {
      if (!entry || typeof entry !== 'object') {
        return false;
      }
      if (cartKey) {
        return cleanText(entry.cart_key) === cartKey;
      }
      if (!productId) {
        return false;
      }
      return cleanText(entry.variation_id) === productId || cleanText(entry.item && entry.item.item_id) === productId;
    });
    if (!cartKey && matches.length === 0 && productId) {
      matches = cartItems.filter(function (entry) {
        return entry && cleanText(entry.product_id) === productId;
      });
    }
    return matches.length === 1 ? exactItem(matches[0].item) : null;
  }

  function controlQuantity(control, fallback) {
    var quantity = control && control.dataset ? control.dataset.quantity : 0;
    if ((!quantity || number(quantity) < 1) && control && typeof control.closest === 'function') {
      var form = control.closest('form.cart');
      var input = form && typeof form.querySelector === 'function' ? form.querySelector('input.qty') : null;
      quantity = input && input.value;
    }
    if ((!quantity || number(quantity) < 1) && control && typeof control.closest === 'function') {
      var row = control.closest('.mini_cart_item, .woocommerce-cart-form__cart-item, .wc-block-cart-items__row, .wc-block-mini-cart-contents__product');
      var rowInput = row && typeof row.querySelector === 'function'
        ? row.querySelector('input.qty, input.wc-block-components-quantity-selector__input')
        : null;
      var quantityText = row && typeof row.querySelector === 'function' ? row.querySelector('.quantity') : null;
      var quantityMatch = quantityText && /^\s*([0-9]+(?:[.,][0-9]+)?)/.exec(quantityText.textContent || '');
      quantity = (rowInput && rowInput.value) || (quantityMatch && quantityMatch[1].replace(',', '.'));
    }
    return Math.max(1, number(quantity || fallback || 1));
  }

  function emitControlEvent(name, control, signalKey) {
    var item = itemForControl(control);
    if (!item) {
      return null;
    }
    item.quantity = controlQuantity(control, item.quantity);
    return emit(name, makeEcommerce([item]), signalKey);
  }

  function itemForRemovalControl(control) {
    var data = control && control.dataset ? control.dataset : {};
    var item = exactItem({
      item_id: data.kdconsentCommerceItemId,
      sku: data.kdconsentCommerceItemSku,
      item_name: data.kdconsentCommerceItemName,
      price: data.kdconsentCommerceItemPrice,
      quantity: data.kdconsentCommerceQuantity
    });
    var wrapper = closestItem(control);
    var wrapperData = wrapper && wrapper.dataset ? wrapper.dataset : {};
    var wrapperItem = exactItem({
      item_id: wrapperData.kdconsentCommerceItemId,
      sku: wrapperData.kdconsentCommerceItemSku,
      item_name: wrapperData.kdconsentCommerceItemName,
      price: wrapperData.kdconsentCommerceItemPrice,
      quantity: wrapperData.kdconsentCommerceQuantity || 1
    });
    var productId = control && control.dataset
      ? control.dataset.variation_id || control.dataset.variationId || control.dataset.product_id || control.dataset.productId
      : '';
    item = item || itemFromCartContext(control) || wrapperItem || findExactConfiguredItem(productId);
    if (item) {
      item.quantity = controlQuantity(control, item.quantity);
    }
    return item;
  }

  function rememberRemoval(control, item) {
    if (!control || !item) {
      return;
    }
    pendingRemoveSnapshots.push({ control: control, item: clone(item) });
    if (pendingRemoveSnapshots.length > 100) {
      pendingRemoveSnapshots.shift();
    }
  }

  function takeRemoval(control) {
    for (var index = 0; index < pendingRemoveSnapshots.length; index += 1) {
      if (pendingRemoveSnapshots[index].control === control) {
        return pendingRemoveSnapshots.splice(index, 1)[0].item;
      }
    }
    return null;
  }

  function blockContext(control) {
    if (!control || typeof control.closest !== 'function') {
      return {};
    }
    var owner = control.closest('[data-wc-context]');
    if (!owner || typeof owner.getAttribute !== 'function') {
      return {};
    }
    try {
      var parsed = JSON.parse(owner.getAttribute('data-wc-context') || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function itemForBlockControl(control) {
    var item = itemForRemovalControl(control);
    if (item) {
      return item;
    }
    var context = blockContext(control);
    var product = context.product && typeof context.product === 'object' ? context.product : {};
    item = exactItem({
      item_id: context.variationId || context.variation_id || context.productId || context.product_id || product.id,
      sku: context.sku || product.sku,
      item_name: context.productName || context.product_name || product.name,
      price: context.kdconsentCommercePrice,
      quantity: context.quantity
    });
    return item;
  }

  function rememberBlockItem(type, item) {
    var intent = item ? { item: clone(item), expiresAt: Date.now() + blockIntentLifetime } : null;
    if (type === 'add') {
      pendingBlockAdd = intent;
    } else {
      pendingBlockRemoval = intent;
    }
  }

  function takeBlockItem(type) {
    var intent = type === 'add' ? pendingBlockAdd : pendingBlockRemoval;
    if (type === 'add') {
      pendingBlockAdd = null;
    } else {
      pendingBlockRemoval = null;
    }
    return intent && intent.expiresAt >= Date.now() ? intent.item : null;
  }

  function listKey(item) {
    return item.item_list_id || 'products';
  }

  function queueVisibleItem(element) {
    if (!element || !element.dataset || element.dataset.kdconsentCommerceImpressed === '1') {
      return;
    }
    var item = itemFromElement(element);
    if (!item) {
      return;
    }
    element.dataset.kdconsentCommerceImpressed = '1';
    var key = listKey(item);
    pendingLists[key] = pendingLists[key] || [];
    pendingLists[key].push(item);
    if (pendingListTimer === null) {
      pendingListTimer = window.setTimeout(flushVisibleLists, 0);
    }
  }

  function flushVisibleLists() {
    var batches = pendingLists;
    pendingLists = {};
    pendingListTimer = null;
    Object.keys(batches).forEach(function (key) {
      var items = batches[key].sort(function (left, right) {
        return number(left.index) - number(right.index);
      });
      if (!items.length) {
        return;
      }
      emit('view_item_list', makeEcommerce(items, {
        item_list_id: items[0].item_list_id || key,
        item_list_name: items[0].item_list_name || ''
      }));
    });
  }

  function observeItem(element) {
    if (!element || !element.dataset || element.dataset.kdconsentCommerceObserved === '1') {
      return;
    }
    element.dataset.kdconsentCommerceObserved = '1';
    if (listObserver) {
      listObserver.observe(element);
    } else {
      queueVisibleItem(element);
    }
  }

  function observeItemsWithin(root) {
    if (!root) {
      return;
    }
    if (root.matches && root.matches('[data-kdconsent-commerce-item="1"]')) {
      observeItem(root);
    }
    if (root.querySelectorAll) {
      Array.prototype.forEach.call(
        root.querySelectorAll('[data-kdconsent-commerce-item="1"]'),
        observeItem
      );
    }
  }

  function trackVisibleLists() {
    if ('IntersectionObserver' in window) {
      listObserver = new window.IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting || number(entry.intersectionRatio) < 0.5) {
            return;
          }
          queueVisibleItem(entry.target);
          listObserver.unobserve(entry.target);
        });
      }, { threshold: [0.5] });
    }
    observeItemsWithin(document);

    if ('MutationObserver' in window && document.body) {
      listMutationObserver = new window.MutationObserver(function (records) {
        records.forEach(function (record) {
          Array.prototype.forEach.call(record.addedNodes || [], observeItemsWithin);
        });
      });
      listMutationObserver.observe(document.body, { childList: true, subtree: true });
    }
  }

  function isCommerceControl(element) {
    return !!(
      element &&
      typeof element.closest === 'function' &&
      element.closest('.add_to_cart_button, .single_add_to_cart_button, .remove, .remove_from_cart_button, button, [role="button"]')
    );
  }

  function handleClick(event) {
    var target = event.target;
    if (!target || typeof target.closest !== 'function') {
      return;
    }

    var itemElement = target.closest('[data-kdconsent-commerce-item="1"]');
    var productLink = target.closest('[data-kdconsent-commerce-item="1"] a[href]');
    if (itemElement && productLink && !isCommerceControl(target) && !/[?&]add-to-cart=/.test(productLink.href || '')) {
      var selected = itemFromElement(itemElement);
      if (selected) {
        emit('select_item', makeEcommerce([selected], {
          item_list_id: selected.item_list_id || '',
          item_list_name: selected.item_list_name || ''
        }));
      }
    }

    var blockRemoveButton = target.closest('.wc-block-cart-item__remove-link, .wc-block-components-product-button__button--remove, [data-kdconsent-commerce-action="remove"]');
    var blockAddButton = blockRemoveButton
      ? null
      : target.closest('.wc-block-components-product-button__button, [data-kdconsent-commerce-action="add"]');

    var addButton = target.closest('.add_to_cart_button');
    if (!blockAddButton && addButton && !(addButton.classList && addButton.classList.contains('ajax_add_to_cart'))) {
      if (emitControlEvent('add_to_cart', addButton)) {
        optimisticAddControls.push(addButton);
      }
    }

    var removeButton = target.closest('.remove, .remove_from_cart_button');
    if (!blockRemoveButton && removeButton) {
      var removalItem = itemForRemovalControl(removeButton);
      if (removeButton.classList && removeButton.classList.contains('remove_from_cart_button')) {
        rememberRemoval(removeButton, removalItem);
      } else if (removalItem && emit('remove_from_cart', makeEcommerce([removalItem]))) {
        optimisticRemoveControls.push(removeButton);
      }
    }

    if (blockAddButton) {
      rememberBlockItem('add', itemForBlockControl(blockAddButton));
    }
    if (blockRemoveButton) {
      rememberBlockItem('remove', itemForBlockControl(blockRemoveButton));
    }
  }

  function handleSubmit(event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('form.cart')) {
      return;
    }
    var button = form.querySelector ? form.querySelector('.single_add_to_cart_button') : null;
    var item = currentVariation || itemForControl(button);
    if (!item && config.page && config.page.type === 'product' && pageItems().length === 1) {
      item = pageItems()[0];
    }
    if (!item) {
      return;
    }
    item = Object.assign({}, item, { quantity: controlQuantity(button || form, item.quantity) });
    if (emit('add_to_cart', makeEcommerce([item]))) {
      optimisticAddControls.push(button || form);
    }
  }

  function handleChange(event) {
    var target = event.target;
    if (!target || !target.matches) {
      return;
    }
    if (target.matches('input[name^="shipping_method"]')) {
      emit('add_shipping_info', makeEcommerce(pageItems()));
    }
    if (target.matches('input[name="payment_method"]')) {
      emit('add_payment_info', makeEcommerce(pageItems()));
    }
  }

  function variationItem(variation) {
    if (!variation || typeof variation !== 'object') {
      return null;
    }
    if (variation.kdconsent_commerce && typeof variation.kdconsent_commerce === 'object') {
      return redactItem(variation.kdconsent_commerce);
    }
    var base = config.page && config.page.type === 'product' && pageItems().length === 1 ? pageItems()[0] : {};
    return redactItem({
      item_id: variation.variation_id,
      sku: variation.sku || base.sku,
      item_name: base.item_name,
      price: typeof variation.kdconsent_commerce_net_price !== 'undefined'
        ? variation.kdconsent_commerce_net_price
        : base.price,
      quantity: 1
    });
  }

  function rememberVariation(variation) {
    var item = variationItem(variation);
    if (!item || !item.item_id) {
      return;
    }
    if (currentVariation && currentVariation.item_id === item.item_id) {
      currentVariation = item;
      return;
    }
    currentVariation = item;
    emit('view_item', makeEcommerce([item]));
  }

  function resetVariation() {
    currentVariation = null;
  }

  function wooSignalKey(prefix, cartHash, item) {
    cartHash = cleanText(cartHash);
    return cartHash ? prefix + ':' + cartHash + ':' + item.item_id + ':' + item.quantity : '';
  }

  function itemForConfirmedAdd(control) {
    var listedItem = itemFromElement(closestItem(control));
    if (listedItem) {
      return listedItem;
    }
    var isSingleProductControl = !!(
      control &&
      control.classList &&
      control.classList.contains('single_add_to_cart_button')
    );
    var productForm = control && typeof control.closest === 'function'
      ? control.closest('form.cart, form.variations_form')
      : null;
    if (currentVariation && (isSingleProductControl || productForm || (!control && config.page && config.page.type === 'product'))) {
      return clone(currentVariation);
    }
    return itemForControl(control);
  }

  function exactItemFromDataset(data) {
    data = data && typeof data === 'object' ? data : {};
    return exactItem({
      item_id: data.kdconsentCommerceItemId,
      sku: data.kdconsentCommerceItemSku,
      item_name: data.kdconsentCommerceItemName,
      price: data.kdconsentCommerceItemPrice,
      quantity: data.kdconsentCommerceQuantity
    });
  }

  function fragmentItems(fragments) {
    if (!fragments || typeof fragments !== 'object' || typeof document.createElement !== 'function') {
      return [];
    }
    var items = [];
    var fingerprints = {};
    Object.keys(fragments).forEach(function (key) {
      if (typeof fragments[key] !== 'string') {
        return;
      }
      var template = document.createElement('template');
      template.innerHTML = fragments[key];
      var root = template.content || template;
      if (!root || typeof root.querySelectorAll !== 'function') {
        return;
      }
      Array.prototype.forEach.call(
        root.querySelectorAll('[data-kdconsent-commerce-item-id][data-kdconsent-commerce-item-name][data-kdconsent-commerce-item-price][data-kdconsent-commerce-quantity]'),
        function (element) {
          var item = exactItemFromDataset(element.dataset);
          if (!item) {
            return;
          }
          var cartKey = cleanText(element.dataset && element.dataset.kdconsentCommerceCartKey);
          var fingerprint = [item.item_id, item.sku, item.item_name, item.price, item.quantity, cartKey].join('|');
          if (!fingerprints[fingerprint]) {
            fingerprints[fingerprint] = true;
            items.push(item);
          }
        }
      );
    });
    return items;
  }

  function exactFragmentItemForAdd(fragments, desired) {
    var matches = fragmentItems(fragments).filter(function (item) {
      return item.item_id === desired.item_id;
    });
    if (desired.sku) {
      matches = matches.filter(function (item) {
        return item.sku === desired.sku;
      });
    }
    return {
      status: matches.length === 1 ? 'unique' : (matches.length > 1 ? 'ambiguous' : 'none'),
      item: matches.length === 1 ? matches[0] : null
    };
  }

  function handleWooAdded(fragments, cartHash, button) {
    var control = unwrapElement(button);
    var optimisticIndex = optimisticAddControls.indexOf(control);
    if (optimisticIndex !== -1) {
      optimisticAddControls.splice(optimisticIndex, 1);
      return;
    }
    var desired = itemForConfirmedAdd(control);
    if (!desired) {
      return;
    }
    var fragmentResult = exactFragmentItemForAdd(fragments, desired);
    if (fragmentResult.status === 'ambiguous') {
      return;
    }
    var item = fragmentResult.item || desired;
    item = Object.assign({}, item, { quantity: controlQuantity(control, desired.quantity) });
    emit('add_to_cart', makeEcommerce([item]), wooSignalKey('woo-add', cartHash, item));
  }

  function handleWooRemoved(fragments, cartHash, button) {
    var control = unwrapElement(button);
    var optimisticIndex = optimisticRemoveControls.indexOf(control);
    if (optimisticIndex !== -1) {
      optimisticRemoveControls.splice(optimisticIndex, 1);
      return;
    }
    var item = takeRemoval(control) || itemForRemovalControl(control);
    if (!item) {
      return;
    }
    emit('remove_from_cart', makeEcommerce([item]), wooSignalKey('woo-remove', cartHash, item));
  }

  function itemFromBlockDetail(detail) {
    detail = detail && typeof detail === 'object' ? detail : {};
    var raw = detail.item || detail.product || {};
    var item = exactItem({
      item_id: raw.item_id || raw.product_id || raw.id || detail.productId,
      sku: raw.sku,
      item_name: raw.item_name || raw.name,
      price: raw.price,
      quantity: raw.quantity || detail.quantity
    });
    if (item) {
      var configured = findConfiguredItem(item.item_id);
      if (configured) {
        item = exactItem(Object.assign({}, configured, item));
      }
      return item;
    }
    return null;
  }

  function bindWooEvents() {
    document.addEventListener('wc-blocks_added_to_cart', function (event) {
      var pendingItem = takeBlockItem('add');
      var item = itemFromBlockDetail(event.detail) || pendingItem;
      if (item) {
        emit('add_to_cart', makeEcommerce([item]));
      }
    });
    document.addEventListener('wc-blocks_removed_from_cart', function (event) {
      var pendingItem = takeBlockItem('remove');
      var item = itemFromBlockDetail(event.detail) || pendingItem;
      if (item) {
        emit('remove_from_cart', makeEcommerce([item]));
      }
    });
    document.addEventListener('found_variation', function (event) {
      rememberVariation(event.detail && event.detail.variation);
    });
    document.addEventListener('reset_data', resetVariation);

    if (typeof window.jQuery === 'function') {
      var jqueryDocument = window.jQuery(document);
      if (jqueryDocument && typeof jqueryDocument.on === 'function') {
        jqueryDocument.on('added_to_cart', function (event, fragments, cartHash, button) {
          handleWooAdded(fragments, cartHash, button);
        });
        jqueryDocument.on('removed_from_cart', function (event, fragments, cartHash, button) {
          handleWooRemoved(fragments, cartHash, button);
        });
        jqueryDocument.on('found_variation', function (event, variation) {
          rememberVariation(variation);
        });
        jqueryDocument.on('reset_data', resetVariation);
      }
    }
  }

  function sourcebusterNames() {
    var names = [
      'sbjs_current',
      'sbjs_current_add',
      'sbjs_first',
      'sbjs_first_add',
      'sbjs_session',
      'sbjs_udata',
      'sbjs_migrations',
      'sbjs_promo'
    ];
    try {
      String(document.cookie || '').split(';').forEach(function (part) {
        var name = decodeURIComponent(part.split('=')[0].trim());
        if (name.indexOf('sbjs_') === 0 && names.indexOf(name) === -1) {
          names.push(name);
        }
      });
    } catch (error) {
      // An inaccessible cookie jar contains no usable attribution state.
    }
    return names;
  }

  function clearStorage(storage, names) {
    if (!storage || typeof storage.removeItem !== 'function') {
      return;
    }
    var discovered = [];
    try {
      for (var index = 0; index < number(storage.length); index += 1) {
        var key = storage.key(index);
        if (key && String(key).indexOf('sbjs_') === 0) {
          discovered.push(String(key));
        }
      }
    } catch (error) {
      discovered = [];
    }
    names.concat(discovered).filter(function (name, index, all) {
      return all.indexOf(name) === index;
    }).forEach(function (name) {
      try {
        storage.removeItem(name);
      } catch (error) {
        // A disabled storage backend is already effectively cleared.
      }
    });
  }

  function safeStorage(name) {
    try {
      return window[name] || null;
    } catch (error) {
      return null;
    }
  }

  function clearSourcebuster() {
    var names = sourcebusterNames();
    var domains = [];
    var hostname = window.location && cleanText(window.location.hostname).toLowerCase();
    if (hostname && !/^\d{1,3}(?:\.\d{1,3}){3}$/.test(hostname) && hostname.indexOf(':') === -1) {
      var labels = hostname.split('.');
      for (var index = 0; index < labels.length - 1; index += 1) {
        domains.push(labels.slice(index).join('.'));
      }
      if (labels.length === 1) {
        domains.push(hostname);
      } else if (labels[labels.length - 1] === 'localhost') {
        domains.push('localhost');
      }
    }
    names.forEach(function (name) {
      try {
        document.cookie = encodeURIComponent(name) + '=; Max-Age=0; path=/; SameSite=Lax';
        domains.forEach(function (domain) {
          document.cookie = encodeURIComponent(name) + '=; Max-Age=0; path=/; Domain=' + domain + '; SameSite=Lax';
        });
      } catch (error) {
        // An inaccessible cookie jar is already unavailable to attribution.
      }
    });
    clearStorage(safeStorage('localStorage'), names);
    clearStorage(safeStorage('sessionStorage'), names);
  }

  function synchronizeAttribution(state) {
    var marketing = !!consentSnapshot(state).marketing;
    var attribution = window.wc_order_attribution;
    if (attribution && typeof attribution.setOrderTracking === 'function') {
      if (attribution !== lastAttributionApi || marketing !== lastAttributionValue) {
        attribution.setOrderTracking(marketing);
        lastAttributionApi = attribution;
        lastAttributionValue = marketing;
      }
    }
    if (!marketing) {
      clearSourcebuster();
    }
    return marketing;
  }

  function bindConsentAndAttribution() {
    document.addEventListener('kdconsent:ready', function (event) {
      synchronizeAttribution(event.detail && event.detail.consent);
    });
    if (window.kdconsent && typeof window.kdconsent.onChange === 'function') {
      window.kdconsent.onChange(synchronizeAttribution);
    } else {
      document.addEventListener('kdconsent:changed', function (event) {
        synchronizeAttribution(event.detail || null);
      });
    }

    synchronizeAttribution(getConsent());
    if (document.readyState !== 'complete' && typeof window.addEventListener === 'function') {
      window.addEventListener('load', function () {
        synchronizeAttribution(getConsent());
      }, { once: true });
    }
    window.setTimeout(function () {
      synchronizeAttribution(getConsent());
    }, 500);
  }

  function initializePageEvents() {
    var page = config.page || {};
    emitInitial('page_view', {});
    if (page.type === 'product' && pageItems().length) {
      emitInitial('view_item', makeEcommerce(pageItems()));
    }
    if (page.type === 'cart') {
      emitInitial('view_cart', makeEcommerce(pageItems()));
    }
    if (page.type === 'checkout') {
      emitInitial('begin_checkout', makeEcommerce(pageItems()));
    }
    if (page.type === 'search') {
      emitInitial('search', {});
    }
    trackVisibleLists();
  }

  function init() {
    if (initialized) {
      return window.kdconsentCommerce;
    }
    initialized = true;
    document.addEventListener('click', handleClick, true);
    document.addEventListener('submit', handleSubmit, true);
    document.addEventListener('change', handleChange, true);
    bindWooEvents();
    bindConsentAndAttribution();
    initializePageEvents();
    return window.kdconsentCommerce;
  }

  window.kdconsentCommerce = {
    init: init,
    emit: emit,
    buildEvent: buildEvent,
    redact: redact,
    consentSnapshot: consentSnapshot,
    plannedDestinations: plannedDestinations,
    itemFromElement: itemFromElement,
    synchronizeAttribution: synchronizeAttribution
  };

  function scheduleInit() {
    window.setTimeout(init, 0);
  }

  if (config.autoStart !== false) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', scheduleInit, { once: true });
    } else {
      scheduleInit();
    }
  }
})();
