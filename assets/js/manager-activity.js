(function () {
  'use strict';
  var root = document.querySelector('.manager-activity');
  if (!root) return;
  window.ziguratFollowupPicker(root);
  var form = root.querySelector('[data-activity-export]');
  var error = root.querySelector('[data-activity-range-error]');
  if (!form || !error) return;
  form.addEventListener('submit', function (event) {
    var from = form.elements.date_from.value;
    var to = form.elements.date_to.value;
    error.hidden = true;
    if (from && to && from > to) {
      event.preventDefault();
      error.textContent = 'تاریخ شروع نباید بعد از تاریخ پایان باشد.';
      error.hidden = false;
    }
  });
  form.addEventListener('reset', function () { error.hidden = true; });
}());
