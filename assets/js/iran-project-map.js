(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-project-map]').forEach(function (map) {
      var title = map.querySelector('[data-map-status-title]');
      var description = map.querySelector('[data-map-status-description]');
      if (!title || !description) return;

      var defaultTitle = title.textContent;
      var defaultDescription = description.textContent;
      var locale = map.getAttribute('data-map-locale') || 'fa-IR';
      var projectsLabel = map.getAttribute('data-map-projects-label') || '%s پروژه ثبت‌شده؛ برای مشاهده کلیک کنید.';
      var emptyLabel = map.getAttribute('data-map-empty-label') || 'هنوز پروژه‌ای برای این استان ثبت نشده است.';

      function showProvince(path) {
        var province = path.getAttribute('data-province') || '';
        var count = parseInt(path.getAttribute('data-count') || '0', 10);
        title.textContent = province;
        description.textContent = count > 0
          ? projectsLabel.replace('%s', count.toLocaleString(locale.replace('_', '-')))
          : emptyLabel;
      }

      function resetStatus() {
        title.textContent = defaultTitle;
        description.textContent = defaultDescription;
      }

      map.querySelectorAll('.iran-project-map__province').forEach(function (path) {
        path.addEventListener('pointerenter', function () { showProvince(path); });
        path.addEventListener('pointerleave', resetStatus);
        var link = path.closest('a');
        if (link) {
          link.addEventListener('focus', function () { showProvince(path); });
          link.addEventListener('blur', resetStatus);
        }
      });
    });
  });
}());
