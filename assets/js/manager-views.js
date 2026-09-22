(function () {
  'use strict';

  function faNumber(value) {
    return Math.max(0, Math.round(Number(value) || 0)).toLocaleString('fa-IR');
  }

  function faDigits(value) {
    return String(value).replace(/[0-9]/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]; });
  }

  function initializeTrafficChart(root) {
    (root || document).querySelectorAll('[data-manager-traffic-chart]').forEach(function (chart) {
      if (chart.dataset.trafficReady === '1') return;
      chart.dataset.trafficReady = '1';
      var dataElement = chart.querySelector('[data-manager-traffic-data]');
      var barsElement = chart.querySelector('[data-traffic-bars]');
      var emptyElement = chart.querySelector('[data-traffic-empty]');
      var totalElement = chart.querySelector('[data-traffic-total]');
      var subtitleElement = chart.querySelector('[data-traffic-subtitle]');
      var titleElement = chart.querySelector('#manager-traffic-title');
      var plotElement = chart.querySelector('[data-traffic-plot]');
      if (!dataElement || !barsElement || !plotElement) return;
      var datasets;
      try { datasets = JSON.parse(dataElement.textContent || '{}'); } catch (error) { datasets = {}; }
      var titles = {
        daily: 'نمودار بازدید روزانه',
        weekly: 'نمودار بازدید هفتگی',
        monthly: 'نمودار بازدید ماهانه',
        yearly: 'نمودار بازدید سالانه'
      };
      var activeRange = 'daily';

      function render(range) {
        activeRange = range;
        var dataset = datasets[range] || datasets.daily || {title:'', items:[]};
        var items = Array.isArray(dataset.items) ? dataset.items : [];
        var maximum = items.reduce(function (max, item) { return Math.max(max, Number(item.total) || 0); }, 0);
        var sum = items.reduce(function (total, item) { return total + (Number(item.total) || 0); }, 0);
        barsElement.textContent = '';
        barsElement.style.setProperty('--traffic-columns', String(Math.max(1, items.length)));
        items.forEach(function (item) {
          var article = Math.max(0, Number(item.article) || 0);
          var project = Math.max(0, Number(item.project) || 0);
          var download = Math.max(0, Number(item.download) || 0);
          var total = article + project + download;
          var point = document.createElement('div');
          point.className = 'manager-traffic__point';

          var value = document.createElement('strong');
          value.className = 'manager-traffic__value';
          value.textContent = faNumber(total);

          var track = document.createElement('div');
          track.className = 'manager-traffic__track';
          var bar = document.createElement('div');
          bar.className = 'manager-traffic__bar';
          bar.style.height = maximum && total ? Math.max(3, (total / maximum) * 100) + '%' : '0%';
          var articleSegment = document.createElement('i');
          articleSegment.className = 'is-article';
          articleSegment.style.height = total ? (article / total) * 100 + '%' : '0%';
          var projectSegment = document.createElement('i');
          projectSegment.className = 'is-project';
          projectSegment.style.height = total ? (project / total) * 100 + '%' : '0%';
          var downloadSegment = document.createElement('i');
          downloadSegment.className = 'is-download';
          downloadSegment.style.height = total ? (download / total) * 100 + '%' : '0%';
          bar.appendChild(articleSegment);
          bar.appendChild(projectSegment);
          bar.appendChild(downloadSegment);
          track.appendChild(bar);

          var label = document.createElement('span');
          label.className = 'manager-traffic__label';
          label.textContent = faDigits(item.label || '');
          point.setAttribute('title', 'کل: ' + faNumber(total) + ' | مطالب: ' + faNumber(article) + ' | پروژه‌ها: ' + faNumber(project) + ' | دانلودها: ' + faNumber(download));
          point.setAttribute('aria-label', faDigits(item.label || '') + '، ' + point.getAttribute('title'));
          point.appendChild(value);
          point.appendChild(track);
          point.appendChild(label);
          barsElement.appendChild(point);
        });
        if (totalElement) totalElement.textContent = faNumber(sum);
        if (subtitleElement) subtitleElement.textContent = faDigits(dataset.title || '');
        if (titleElement) titleElement.textContent = titles[range] || titles.daily;
        if (emptyElement) emptyElement.hidden = sum > 0;
        plotElement.classList.toggle('is-empty', sum === 0);
        plotElement.setAttribute('aria-label', (titles[range] || titles.daily) + '، مجموع ' + faNumber(sum) + ' بازدید');
        chart.querySelectorAll('[data-traffic-range]').forEach(function (button) {
          var active = button.dataset.trafficRange === range;
          button.classList.toggle('is-active', active);
          button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
      }

      chart.querySelectorAll('[data-traffic-range]').forEach(function (button) {
        button.addEventListener('click', function () { render(button.dataset.trafficRange || 'daily'); });
      });
      chart._ziguratUpdate = function (nextDatasets) {
        if (!nextDatasets || typeof nextDatasets !== 'object') return;
        datasets = nextDatasets;
        dataElement.textContent = JSON.stringify(nextDatasets);
        render(activeRange);
      };
      render('daily');
    });
  }

  var liveController = null;

  function initializeLiveViews(root) {
    var statsRoot = (root || document).querySelector('[data-live-views-root]');
    var counter = document.querySelector('[data-live-counter]');
    var readyElement = statsRoot || counter;
    if (!readyElement || readyElement.dataset.liveReady === '1') return;
    readyElement.dataset.liveReady = '1';
    if (liveController && typeof liveController.destroy === 'function') liveController.destroy();

    var liveRoot = statsRoot || document;
    var config = window.ziguratManagerViewsConfig || {};
    var stateElement = statsRoot && statsRoot.querySelector('[data-live-state]');
    var stateText = stateElement && stateElement.querySelector('b');
    var counterValue = counter && counter.querySelector('[data-live-counter-value]');
    var counterStatus = counter && counter.querySelector('[data-live-counter-status]');
    var toast = counter && counter.querySelector('[data-live-toast]');
    var cardToast = statsRoot && statsRoot.querySelector('[data-live-card-toast]');
    var total = Math.max(0, Number((statsRoot && statsRoot.dataset.initialTotal) || (counter && counter.dataset.initialTotal)) || 0);
    var stopped = false;
    var fetching = false;
    var socket = null;
    var pollTimer = null;
    var toastTimer = null;
    var reconnectTimer = null;
    var reconnectDelay = 1500;

    function setStatus(mode, message) {
      if (stateElement) stateElement.dataset.status = mode;
      if (stateText) stateText.textContent = message;
      if (counterStatus) counterStatus.textContent = message;
    }

    function pulse(element) {
      if (!element) return;
      element.classList.remove('is-live-changed');
      void element.offsetWidth;
      element.classList.add('is-live-changed');
      window.setTimeout(function () { element.classList.remove('is-live-changed'); }, 900);
    }

    function showIncrease(amount) {
      if (amount <= 0) return;
      var activeToasts = [toast, cardToast].filter(Boolean);
      activeToasts.forEach(function (element) {
        element.textContent = '+' + faNumber(amount) + ' بازدید جدید';
        element.hidden = false;
        element.classList.remove('is-visible');
        void element.offsetWidth;
        element.classList.add('is-visible');
      });
      window.clearTimeout(toastTimer);
      toastTimer = window.setTimeout(function () {
        activeToasts.forEach(function (element) { element.classList.remove('is-visible'); });
        window.setTimeout(function () {
          activeToasts.forEach(function (element) { element.hidden = true; });
        }, 250);
      }, 2800);
    }

    function currentMetricValue(element) {
      if (element.dataset.liveValue !== undefined) return Number(element.dataset.liveValue) || 0;
      var ascii = String(element.textContent || '').replace(/[۰-۹]/g, function (digit) {
        return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit));
      }).replace(/[^0-9]/g, '');
      return Number(ascii) || 0;
    }

    function applyPayload(payload) {
      if (!payload || typeof payload !== 'object') return;
      var nextTotal = Math.max(0, Number(payload.total_views) || 0);
      var increase = Math.max(0, nextTotal - total);
      liveRoot.querySelectorAll('[data-live-metric]').forEach(function (element) {
        var key = element.dataset.liveMetric;
        if (!Object.prototype.hasOwnProperty.call(payload, key)) return;
        var next = Math.max(0, Number(payload[key]) || 0);
        var previous = currentMetricValue(element);
        element.dataset.liveValue = String(next);
        element.textContent = faNumber(next);
        if (next !== previous) pulse(element.closest('.manager-view-card') || element);
      });
      if (counterValue) {
        counterValue.textContent = faNumber(nextTotal);
        if (nextTotal !== total) pulse(counterValue);
      }
      var chart = liveRoot.querySelector('[data-manager-traffic-chart]');
      if (chart && chart._ziguratUpdate && payload.chart) chart._ziguratUpdate(payload.chart);
      var articleShare = nextTotal ? ((Number(payload.article_views) || 0) / nextTotal) * 100 : 0;
      var projectShare = nextTotal ? ((Number(payload.project_views) || 0) / nextTotal) * 100 : 0;
      var downloadShare = Math.max(0, 100 - articleShare - projectShare);
      var donut = liveRoot.querySelector('[data-live-donut]');
      if (donut) {
        donut.style.setProperty('--article-share', articleShare + '%');
        donut.style.setProperty('--project-share-end', Math.min(100, articleShare + projectShare) + '%');
        donut.classList.toggle('is-empty', nextTotal === 0);
        donut.setAttribute('aria-label', 'سهم مطالب ' + articleShare.toFixed(1) + ' درصد، سهم پروژه‌ها ' + projectShare.toFixed(1) + ' درصد و سهم دانلودها ' + downloadShare.toFixed(1) + ' درصد');
      }
      [['article', articleShare], ['project', projectShare], ['download', downloadShare]].forEach(function (item) {
        var share = liveRoot.querySelector('[data-live-share="' + item[0] + '"]');
        if (share) share.textContent = faDigits(item[1].toFixed(1).replace('.', '/')) + '٪';
      });
      var topList = liveRoot.querySelector('[data-live-top-content]');
      if (topList && Array.isArray(payload.top_content)) {
        var topMaximum = payload.top_content.reduce(function (maximum, item) { return Math.max(maximum, Number(item.views) || 0); }, 0);
        var typeLabels = { project: 'پروژه', zig_download: 'دانلود', article: 'مطلب' };
        topList.textContent = '';
        payload.top_content.forEach(function (item) {
          var views = Math.max(0, Number(item.views) || 0);
          var row = document.createElement('li');
          var label = document.createElement('div');
          label.className = 'manager-view-bar__label';
          var link = document.createElement('a');
          link.href = String(item.url || '#');
          link.target = '_blank';
          link.rel = 'noopener';
          link.textContent = String(item.title || 'بدون عنوان');
          var kind = document.createElement('small');
          kind.textContent = typeLabels[item.type] || 'مطلب';
          label.appendChild(link);
          label.appendChild(kind);
          var bar = document.createElement('div');
          bar.className = 'manager-view-bar';
          bar.setAttribute('aria-hidden', 'true');
          var fill = document.createElement('span');
          fill.style.width = topMaximum && views ? Math.max(2, (views / topMaximum) * 100) + '%' : '0%';
          bar.appendChild(fill);
          var value = document.createElement('strong');
          value.textContent = faNumber(views);
          row.appendChild(label);
          row.appendChild(bar);
          row.appendChild(value);
          topList.appendChild(row);
        });
      }
      showIncrease(increase);
      total = nextTotal;
      if (statsRoot) statsRoot.dataset.initialTotal = String(nextTotal);
      if (counter) counter.dataset.initialTotal = String(nextTotal);
    }

    function schedulePoll(delay) {
      window.clearTimeout(pollTimer);
      if (stopped || (socket && socket.readyState === WebSocket.OPEN)) return;
      pollTimer = window.setTimeout(fetchLatest, Math.max(1000, delay));
    }

    async function fetchLatest() {
      if (stopped || fetching || document.hidden) {
        schedulePoll(Number(config.pollInterval) || 30000);
        return;
      }
      fetching = true;
      var body = new URLSearchParams();
      body.set('action', 'zigurat_get_live_views');
      body.set('nonce', config.nonce || '');
      try {
        var response = await fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString()
        });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        var result = await response.json();
        if (!result || !result.success) throw new Error('Invalid response');
        applyPayload(result.data);
        setStatus('fallback', 'به‌روزرسانی خودکار هر ۳۰ ثانیه');
      } catch (error) {
        setStatus('error', 'ارتباط آمار قطع است؛ تلاش دوباره…');
      } finally {
        fetching = false;
        schedulePoll(Number(config.pollInterval) || 30000);
      }
    }

    function scheduleReconnect() {
      if (stopped) return;
      setStatus('fallback', 'اتصال زنده قطع شد؛ به‌روزرسانی خودکار فعال است');
      fetchLatest();
      window.clearTimeout(reconnectTimer);
      reconnectTimer = window.setTimeout(connectSocket, reconnectDelay);
      reconnectDelay = Math.min(30000, reconnectDelay * 2);
    }

    function connectSocket() {
      var url = String(config.websocketUrl || '').trim();
      if (!url || !window.WebSocket) {
        setStatus('fallback', 'به‌روزرسانی خودکار هر ۳۰ ثانیه');
        fetchLatest();
        return;
      }
      setStatus('connecting', 'در حال اتصال زنده…');
      try { socket = new WebSocket(url); } catch (error) { scheduleReconnect(); return; }
      socket.addEventListener('open', function () {
        reconnectDelay = 1500;
        window.clearTimeout(pollTimer);
        setStatus('live', 'اتصال زنده برقرار است');
      });
      socket.addEventListener('message', function (event) {
        try {
          var message = JSON.parse(event.data);
          applyPayload(message && message.data && typeof message.data === 'object' ? message.data : message);
        } catch (error) { /* پیام‌های نامرتبط نادیده گرفته می‌شوند. */ }
      });
      socket.addEventListener('close', scheduleReconnect);
      socket.addEventListener('error', function () { if (socket) socket.close(); });
    }

    function onVisibilityChange() {
      if (!document.hidden && (!socket || socket.readyState !== WebSocket.OPEN)) fetchLatest();
    }
    document.addEventListener('visibilitychange', onVisibilityChange);
    connectSocket();

    liveController = {
      destroy: function () {
        stopped = true;
        window.clearTimeout(pollTimer);
        window.clearTimeout(toastTimer);
        window.clearTimeout(reconnectTimer);
        document.removeEventListener('visibilitychange', onVisibilityChange);
        if (socket) socket.close();
      }
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    initializeTrafficChart(document);
    initializeLiveViews(document);
  });
  document.addEventListener('zigurat:panel-updated', function (event) {
    var root = event.detail && event.detail.root ? event.detail.root : document;
    initializeTrafficChart(root);
    initializeLiveViews(root);
  });
}());
