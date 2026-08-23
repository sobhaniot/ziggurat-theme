(function () {
  'use strict';

  var config = window.ziguratPanelAjaxConfig || {};
  var rootSelectors = ['main.invoice-admin-page', 'main.inventory-page'];
  var requestController = null;
  var restoringHistory = false;

  function currentRoot() {
    return document.querySelector(rootSelectors.join(','));
  }

  function selectorFor(root) {
    return root && root.matches('main.invoice-admin-page') ? 'main.invoice-admin-page' : 'main.inventory-page';
  }

  function normalizedPath(pathname) {
    var value = String(pathname || '/').replace(/\/+$/, '');
    return value || '/';
  }

  function allowedPaths() {
    return (config.paths || []).map(normalizedPath);
  }

  function isPanelUrl(url, root) {
    if (!root || url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) return false;
    if (allowedPaths().indexOf(normalizedPath(url.pathname)) === -1) return false;
    if (root.matches('main.invoice-admin-page')) {
      var view = url.searchParams.get('invoice_view') || url.searchParams.get('view') || '';
      if (view === 'print' || view === 'export') return false;
    }
    return true;
  }

  function setBusy(root, busy) {
    if (!root) return;
    root.classList.toggle('is-ajax-loading', busy);
    if (busy) {
      root.setAttribute('aria-busy', 'true');
      if (!root.querySelector('.zigurat-panel-loader')) {
        var loader = document.createElement('div');
        loader.className = 'zigurat-panel-loader no-print';
        loader.setAttribute('role', 'status');
        loader.setAttribute('aria-live', 'polite');
        loader.innerHTML = '<span aria-hidden="true"></span><strong>در حال پردازش...</strong>';
        root.appendChild(loader);
      }
    } else {
      root.removeAttribute('aria-busy');
      var loader = root.querySelector('.zigurat-panel-loader');
      if (loader) loader.remove();
    }
  }

  function showError(root, message) {
    if (!root) return;
    var container = root.querySelector('.invoice-workspace, .inventory-card, .invoice-brand-picker, .container');
    if (!container) return;
    var notice = document.createElement('div');
    notice.className = root.matches('.invoice-admin-page') ? 'invoice-notice is-error' : 'inventory-notice inventory-notice--error';
    notice.setAttribute('role', 'alert');
    notice.textContent = message;
    container.insertBefore(notice, container.firstChild);
  }

  function syncStylesheets(parsed, finalUrl) {
    var currentLinks = Array.prototype.slice.call(document.querySelectorAll('link[rel~="stylesheet"][href]'));
    parsed.querySelectorAll('link[rel~="stylesheet"][href]').forEach(function (source) {
      var nextHref;
      try { nextHref = new URL(source.getAttribute('href'), finalUrl).href; } catch (error) { return; }
      if (currentLinks.some(function (link) { return link.href === nextHref; })) return;

      var nextPath;
      try { nextPath = new URL(nextHref).pathname; } catch (error) { return; }
      var staleLinks = currentLinks.filter(function (link) {
        try { return new URL(link.href).pathname === nextPath; } catch (error) { return false; }
      });
      var replacement = document.createElement('link');
      Array.prototype.slice.call(source.attributes).forEach(function (attribute) {
        replacement.setAttribute(attribute.name, attribute.name === 'href' ? nextHref : attribute.value);
      });
      replacement.addEventListener('load', function () {
        staleLinks.forEach(function (link) { if (link.parentNode) link.parentNode.removeChild(link); });
      }, { once: true });
      document.head.appendChild(replacement);
      currentLinks.push(replacement);
    });
  }

  function updatePage(parsed, newRoot, finalUrl, historyMode, shouldFocus) {
    var oldRoot = currentRoot();
    if (!oldRoot) return false;
    syncStylesheets(parsed, finalUrl);
    oldRoot.replaceWith(document.importNode(newRoot, true));
    if (parsed.title) document.title = parsed.title;
    if (parsed.body) document.body.className = parsed.body.className;
    if (historyMode === 'push') window.history.pushState({ ziguratPanel: true }, '', finalUrl);
    if (historyMode === 'replace') window.history.replaceState({ ziguratPanel: true }, '', finalUrl);

    var insertedRoot = currentRoot();
    document.dispatchEvent(new CustomEvent('zigurat:panel-updated', { detail: { root: insertedRoot, url: finalUrl } }));
    if (shouldFocus && insertedRoot) {
      window.scrollTo({ top: Math.max(0, insertedRoot.getBoundingClientRect().top + window.scrollY - 110), behavior: 'smooth' });
      var heading = insertedRoot.querySelector('h1');
      if (heading) {
        heading.setAttribute('tabindex', '-1');
        heading.focus({ preventScroll: true });
      }
    }
    return true;
  }

  async function loadPanel(url, options) {
    options = options || {};
    var root = currentRoot();
    if (!root) return;
    var expectedSelector = selectorFor(root);
    if (requestController) requestController.abort();
    var controller = new AbortController();
    requestController = controller;
    setBusy(root, true);

    try {
      var fetchOptions = {
        method: options.method || 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-Zigurat-Panel-Ajax': '1' },
        redirect: 'follow',
        signal: controller.signal
      };
      if (options.body) fetchOptions.body = options.body;
      var response = await fetch(url, fetchOptions);
      if (!response.ok) throw new Error('HTTP ' + response.status);
      var html = await response.text();
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var newRoot = parsed.querySelector(expectedSelector);
      if (!newRoot) {
        window.location.assign(response.url || url);
        return;
      }
      updatePage(parsed, newRoot, response.url || url, options.historyMode || 'push', options.focus !== false);
    } catch (error) {
      if (error.name !== 'AbortError') {
        setBusy(currentRoot() || root, false);
        showError(currentRoot() || root, 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.');
      }
    } finally {
      if (requestController === controller) {
        requestController = null;
        setBusy(currentRoot(), false);
      }
    }
  }

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var link = event.target.closest('a[href]');
    var root = link && link.closest(rootSelectors.join(','));
    if (!link || !root || link.target === '_blank' || link.hasAttribute('download') || link.dataset.noAjax !== undefined) return;
    var url;
    try { url = new URL(link.href, window.location.href); } catch (error) { return; }
    if (!isPanelUrl(url, root)) return;
    event.preventDefault();
    loadPanel(url.href, { method: 'GET', historyMode: 'push' });
  });

  document.addEventListener('submit', function (event) {
    if (event.defaultPrevented) return;
    var form = event.target;
    var root = form && form.closest(rootSelectors.join(','));
    if (!form || !root || form.dataset.noAjax !== undefined || form.target === '_blank') return;
    var method = String(form.method || 'GET').toUpperCase();
    var url = new URL(form.action || window.location.href, window.location.href);
    if (!isPanelUrl(url, root)) return;
    event.preventDefault();

    var data = new FormData(form);
    if (event.submitter && event.submitter.name && !data.has(event.submitter.name)) {
      data.append(event.submitter.name, event.submitter.value || '1');
    }
    if (method === 'GET') {
      url.search = '';
      data.forEach(function (value, key) {
        if (typeof value === 'string' && value !== '') url.searchParams.append(key, value);
      });
      loadPanel(url.href, { method: 'GET', historyMode: 'push' });
    } else {
      loadPanel(url.href, { method: method, body: data, historyMode: 'replace' });
    }
  });

  window.addEventListener('popstate', function () {
    if (restoringHistory) {
      restoringHistory = false;
      return;
    }
    var root = currentRoot();
    var url = new URL(window.location.href);
    if (!isPanelUrl(url, root)) return;
    var navigationEvent = new CustomEvent('zigurat:panel-before-navigate', {
      cancelable: true,
      detail: { url: url.href, reason: 'history' }
    });
    if (!document.dispatchEvent(navigationEvent)) {
      restoringHistory = true;
      window.history.forward();
      return;
    }
    loadPanel(url.href, { method: 'GET', historyMode: 'none', focus: false });
  });
}());
