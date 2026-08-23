(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-auto-filter-form]').forEach(function (form) {
      form.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', function () {
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        });
      });
    });
  });
}());
