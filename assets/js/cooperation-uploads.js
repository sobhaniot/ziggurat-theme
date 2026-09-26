(function () {
  'use strict';

  function fileKey(file) {
    return [file.name, file.size, file.lastModified].join('::');
  }

  function acceptsFile(input, file) {
    var accepted = (input.getAttribute('accept') || '').split(',').map(function (item) {
      return item.trim().toLowerCase();
    }).filter(Boolean);
    if (!accepted.length) return true;
    var name = file.name.toLowerCase();
    var type = (file.type || '').toLowerCase();
    return accepted.some(function (rule) {
      if (rule.charAt(0) === '.') return name.endsWith(rule);
      if (rule.endsWith('/*')) return type.indexOf(rule.slice(0, -1)) === 0;
      return type === rule;
    });
  }

  function assignFiles(input, files) {
    var transfer = new DataTransfer();
    files.forEach(function (file) { transfer.items.add(file); });
    input.files = transfer.files;
  }

  function createPreview(file, index, removeFile) {
    var item = document.createElement('div');
    item.className = 'application-upload-preview__item';

    if ((file.type || '').indexOf('image/') === 0) {
      var image = document.createElement('img');
      image.src = URL.createObjectURL(file);
      image.alt = '';
      image.addEventListener('load', function () { URL.revokeObjectURL(image.src); }, { once: true });
      item.appendChild(image);
    } else {
      var icon = document.createElement('span');
      icon.className = 'application-upload-preview__pdf';
      icon.textContent = 'PDF';
      item.appendChild(icon);
    }

    var name = document.createElement('span');
    name.className = 'application-upload-preview__name';
    name.textContent = file.name;
    item.appendChild(name);

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'application-upload-preview__remove';
    remove.setAttribute('aria-label', 'حذف ' + file.name);
    remove.textContent = '×';
    remove.addEventListener('click', function () { removeFile(index); });
    item.appendChild(remove);
    return item;
  }

  document.querySelectorAll('[data-upload-zone]').forEach(function (zone) {
    var input = zone.querySelector('[data-upload-input]');
    var preview = zone.querySelector('[data-upload-preview]');
    if (!input || !preview || typeof DataTransfer === 'undefined') return;

    var files = [];
    var append = input.getAttribute('data-append-files') === '1';
    var maxFiles = Math.max(1, parseInt(input.getAttribute('data-max-files') || '1', 10));

    function render() {
      preview.textContent = '';
      files.forEach(function (file, index) {
        preview.appendChild(createPreview(file, index, function (removeIndex) {
          files.splice(removeIndex, 1);
          assignFiles(input, files);
          render();
        }));
      });
      zone.classList.toggle('has-files', files.length > 0);
    }

    function addFiles(fileList) {
      var incoming = Array.prototype.slice.call(fileList).filter(function (file) {
        return acceptsFile(input, file) && file.size <= 5 * 1024 * 1024;
      });
      var next = append ? files.slice() : [];
      incoming.forEach(function (file) {
        if (!next.some(function (current) { return fileKey(current) === fileKey(file); })) {
          next.push(file);
        }
      });
      if (next.length > maxFiles) {
        next = next.slice(0, maxFiles);
        zone.classList.add('has-upload-warning');
        window.setTimeout(function () { zone.classList.remove('has-upload-warning'); }, 1800);
      }
      files = next;
      assignFiles(input, files);
      render();
    }

    input.addEventListener('change', function () { addFiles(input.files); });
    ['dragenter', 'dragover'].forEach(function (eventName) {
      zone.addEventListener(eventName, function (event) {
        event.preventDefault();
        zone.classList.add('is-dragging');
      });
    });
    ['dragleave', 'drop'].forEach(function (eventName) {
      zone.addEventListener(eventName, function (event) {
        event.preventDefault();
        zone.classList.remove('is-dragging');
      });
    });
    zone.addEventListener('drop', function (event) {
      if (event.dataTransfer && event.dataTransfer.files.length) {
        addFiles(event.dataTransfer.files);
      }
    });
  });
})();
