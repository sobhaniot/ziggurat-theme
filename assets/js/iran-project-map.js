(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-project-map]').forEach(function (map) {
      var title = map.querySelector('[data-map-status-title]');
      var description = map.querySelector('[data-map-status-description]');
      if (!title || !description) return;

      var defaultTitle = title.textContent;
      var defaultDescription = description.textContent;

      function showProvince(path) {
        var province = path.getAttribute('data-province') || '';
        var count = parseInt(path.getAttribute('data-count') || '0', 10);
        title.textContent = province;
        description.textContent = count > 0
          ? count.toLocaleString('fa-IR') + ' پروژه ثبت‌شده؛ برای مشاهده کلیک کنید.'
          : 'هنوز پروژه‌ای برای این استان ثبت نشده است.';
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
