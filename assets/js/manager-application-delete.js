(function () {
  'use strict';

  var dialog = document.querySelector('[data-application-delete-dialog]');
  if (!dialog) return;

  var pendingForm = null;
  document.querySelectorAll('[data-application-delete]').forEach(function (button) {
    button.addEventListener('click', function () {
      pendingForm = button.form;
      dialog.returnValue = '';
      dialog.showModal();
    });
  });

  dialog.addEventListener('close', function () {
    if (dialog.returnValue === 'delete' && pendingForm) pendingForm.requestSubmit();
    pendingForm = null;
  });
}());
