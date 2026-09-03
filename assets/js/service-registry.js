(function () {
  var services = [];
  var active = {};
  var initialized = false;
  var mode = 'live';

  window.kdconsentServices = {
    init: init,
    reconcile: reconcile,
    activeServices: function () {
      return Object.keys(active);
    }
  };

  document.addEventListener('kdconsent:changed', function (event) {
    if (initialized) {
      reconcile(event.detail || null, true);
    }
  });

  function init(nextServices, consent, options) {
    services = Array.isArray(nextServices) ? nextServices : [];
    mode = options && options.mode === 'debug' ? 'debug' : 'live';
    initialized = true;
    reconcile(consent, false);
    return window.kdconsentServices;
  }

  function reconcile(consent, reloadOnRevoke) {
    var categories = consent && consent.c ? consent.c : {};
    var revoked = false;

    services.forEach(function (service) {
      if (!service || !service.id || !service.purpose) {
        return;
      }

      var granted = !!categories[service.purpose];
      if (granted && !active[service.id]) {
        activate(service);
        active[service.id] = true;
      } else if (!granted && active[service.id]) {
        teardown(service);
        delete active[service.id];
        revoked = true;
      }
    });

    if (revoked && reloadOnRevoke && window.location && typeof window.location.reload === 'function') {
      window.location.reload();
    }
  }

  function activate(service) {
    if (mode === 'debug') {
      debug('activate', service);
      return;
    }

    var allowed = Array.isArray(service.allowedUrls) ? service.allowedUrls : [];
    (Array.isArray(service.scripts) ? service.scripts : []).forEach(function (script) {
      if (!script || !script.handle || allowed.indexOf(script.src) === -1) {
        return;
      }

      var id = scriptId(service.id, script.handle);
      if (document.getElementById(id)) {
        return;
      }

      var element = document.createElement('script');
      element.id = id;
      element.src = script.src;
      element.async = !!script.async;
      element.defer = !!script.defer;
      element.dataset.kdconsentService = service.id;
      document.head.appendChild(element);
    });
  }

  function teardown(service) {
    debug('teardown', service);
    (Array.isArray(service.scripts) ? service.scripts : []).forEach(function (script) {
      var element = document.getElementById(scriptId(service.id, script.handle));
      if (element && element.parentNode) {
        element.parentNode.removeChild(element);
      }
    });

    (Array.isArray(service.cookies) ? service.cookies : []).forEach(clearCookie);
	clearStorage(safeStorage('localStorage'), service.localStorageKeys);
	clearStorage(safeStorage('sessionStorage'), service.sessionStorageKeys);

    var teardown = service.teardown || {};
    var callback = resolveGlobal(teardown.globalFunction || '');
    if (typeof callback === 'function') {
      try {
        callback();
      } catch (error) {
        debug('teardown-error', service);
      }
    }

    if (teardown.event) {
      document.dispatchEvent(new CustomEvent(teardown.event, { detail: { serviceId: service.id } }));
    }
  }

  function clearCookie(name) {
    if (!name) {
      return;
    }

    document.cookie = encodeURIComponent(name) + '=; Max-Age=0; path=/; SameSite=Lax';
  }

  function clearStorage(storage, keys) {
    if (!storage || !Array.isArray(keys)) {
      return;
    }

    keys.forEach(function (key) {
      try {
        storage.removeItem(key);
      } catch (error) {
        // A disabled storage backend is already effectively torn down.
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

  function resolveGlobal(path) {
    if (!path) {
      return null;
    }

    return path.split('.').reduce(function (value, key) {
      return value && value[key];
    }, window);
  }

  function scriptId(serviceId, handle) {
    return 'kdconsent-service-' + String(serviceId) + '-' + String(handle);
  }

  function debug(action, service) {
    if (mode === 'debug' && window.console && typeof window.console.log === 'function') {
      window.console.log('[kdconsent-service]', {
        action: action,
        service_id: service.id,
        purpose: service.purpose
      });
    }
  }
})();
