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
  });
}());

