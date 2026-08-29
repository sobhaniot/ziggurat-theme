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

      function render(range) {
        var dataset = datasets[range] || datasets.daily || {title:'', items:[]};
        var items = Array.isArray(dataset.items) ? dataset.items : [];
        var maximum = items.reduce(function (max, item) { return Math.max(max, Number(item.total) || 0); }, 0);
        var sum = items.reduce(function (total, item) { return total + (Number(item.total) || 0); }, 0);
        barsElement.textContent = '';
        barsElement.style.setProperty('--traffic-columns', String(Math.max(1, items.length)));
        items.forEach(function (item) {
          var article = Math.max(0, Number(item.article) || 0);
          var project = Math.max(0, Number(item.project) || 0);
          var total = article + project;
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
          bar.appendChild(articleSegment);
          bar.appendChild(projectSegment);
          track.appendChild(bar);

          var label = document.createElement('span');
          label.className = 'manager-traffic__label';
          label.textContent = faDigits(item.label || '');
          point.setAttribute('title', 'کل: ' + faNumber(total) + ' | مطالب: ' + faNumber(article) + ' | پروژه‌ها: ' + faNumber(project));
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
      render('daily');
    });
  }

  document.addEventListener('DOMContentLoaded', function () { initializeTrafficChart(document); });
  document.addEventListener('zigurat:panel-updated', function (event) {
    initializeTrafficChart(event.detail && event.detail.root ? event.detail.root : document);
  });
}());
