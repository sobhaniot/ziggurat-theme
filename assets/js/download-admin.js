(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var selectButton = document.getElementById('zigurat-download-select-file');
    var removeButton = document.getElementById('zigurat-download-remove-file');
    var idField = document.getElementById('zigurat-download-file-id');
    var nameField = document.getElementById('zigurat-download-file-name');
    if (!selectButton || !idField || !nameField || !window.wp || !wp.media) return;

    var frame;
    selectButton.addEventListener('click', function () {
      if (frame) {
        frame.open();
        return;
      }
      frame = wp.media({
        title: 'انتخاب فایل مرکز دانلود',
        button: { text: 'استفاده از این فایل' },
        multiple: false
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        idField.value = attachment.id || '';
        nameField.value = attachment.filename || attachment.title || '';
      });
      frame.open();
    });

    if (removeButton) {
      removeButton.addEventListener('click', function () {
        idField.value = '';
        nameField.value = '';
      });
    }

    var history = document.getElementById('zigurat-download-version-history');
    var addVersion = document.getElementById('zigurat-download-add-version');
    var rowTemplate = document.getElementById('tmpl-zigurat-download-version-row');
    var historyFrame;

    if (history && addVersion && rowTemplate && window.wp && wp.template) {
      addVersion.addEventListener('click', function () {
        var index = Date.now().toString();
        history.insertAdjacentHTML('beforeend', wp.template('zigurat-download-version-row')({ index: index }));
      });

      history.addEventListener('click', function (event) {
        var remove = event.target.closest('.zigurat-version-remove');
        if (remove) {
          event.preventDefault();
          remove.closest('.zigurat-version-row').remove();
          return;
        }

        var select = event.target.closest('.zigurat-version-select-file');
        if (!select) return;
        event.preventDefault();
        var row = select.closest('.zigurat-version-row');
        historyFrame = wp.media({
          title: 'انتخاب فایل نسخه قدیمی',
          button: { text: 'استفاده از این فایل' },
          multiple: false
        });
        historyFrame.on('select', function () {
          var attachment = historyFrame.state().get('selection').first().toJSON();
          row.querySelector('.zigurat-version-file-id').value = attachment.id || '';
          row.querySelector('.zigurat-version-file-name').value = attachment.filename || attachment.title || '';
        });
        historyFrame.open();
      });
    }
  });
}());
