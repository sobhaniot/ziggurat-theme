(function () {
  'use strict';

  var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

  function toPersianDigits(value) {
    return String(value == null ? '' : value)
      .replace(/[0-9]/g, function (digit) { return persianDigits[Number(digit)]; })
      .replace(/[٠-٩]/g, function (digit) { return persianDigits['٠١٢٣٤٥٦٧٨٩'.indexOf(digit)]; });
  }

  function persianizeTextNodes(element) {
    if (!element) return;
    var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(function (node) { node.nodeValue = toPersianDigits(node.nodeValue); });
  }

  function isOverflowing(element) {
    return element.scrollHeight > element.clientHeight + 2;
  }

  function normalizedBlocks(html) {
    var holder = document.createElement('div');
    holder.innerHTML = html;
    persianizeTextNodes(holder);
    var blocks = [];

    Array.prototype.slice.call(holder.childNodes).forEach(function (node) {
      if (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()) return;
      if (node.nodeType === Node.ELEMENT_NODE && /^(UL|OL)$/.test(node.tagName)) {
        Array.prototype.slice.call(node.children).forEach(function (item) {
          var list = node.cloneNode(false);
          list.appendChild(item.cloneNode(true));
          blocks.push(list);
        });
        return;
      }
      blocks.push(node.cloneNode(true));
    });
    return blocks;
  }

  function splitTextBlockToFit(node, body, content) {
    if (node.nodeType !== Node.ELEMENT_NODE || !/^(P|DIV|BLOCKQUOTE|LI)$/.test(node.tagName)) return null;
    var words = node.textContent.trim().split(/\s+/);
    if (words.length < 2) return null;
    var low = 1;
    var high = words.length - 1;
    var best = 0;

    while (low <= high) {
      var middle = Math.floor((low + high) / 2);
      var sample = node.cloneNode(false);
      sample.textContent = words.slice(0, middle).join(' ');
      body.appendChild(sample);
      var fits = !isOverflowing(content);
      sample.remove();
      if (fits) {
        best = middle;
        low = middle + 1;
      } else {
        high = middle - 1;
      }
    }

    if (!best) return null;
    var first = node.cloneNode(false);
    first.textContent = words.slice(0, best).join(' ');
    var rest = node.cloneNode(false);
    rest.textContent = words.slice(best).join(' ');
    body.appendChild(first);
    return rest;
  }

  function paginateDocument(letterDocument, html) {
    var pages = letterDocument.querySelectorAll('[data-letter-page]');
    var bodies = letterDocument.querySelectorAll('[data-letter-body-page]');
    var contents = letterDocument.querySelectorAll('[data-letter-content-page]');
    var signatures = letterDocument.querySelectorAll('[data-letter-signature]');
    if (pages.length < 2 || bodies.length < 2 || contents.length < 2) return false;

    pages[0].hidden = false;
    pages[1].hidden = false;
    bodies[0].innerHTML = '';
    bodies[1].innerHTML = '';
    signatures[0].hidden = false;
    signatures[1].hidden = true;

    var blocks = normalizedBlocks(html || '<p>متن نامه در این قسمت قرار می‌گیرد.</p>');
    var pageIndex = 0;
    var overflow = false;
    var secondPageStarted = false;

    blocks.forEach(function (sourceNode) {
      if (overflow) return;
      var node = sourceNode.cloneNode(true);
      bodies[pageIndex].appendChild(node);
      if (!isOverflowing(contents[pageIndex])) return;
      node.remove();

      if (pageIndex === 0) {
        signatures[0].hidden = true;
        signatures[1].hidden = false;
        secondPageStarted = true;
        bodies[0].appendChild(node);
        if (!isOverflowing(contents[0])) return;
        node.remove();

        var remainder = splitTextBlockToFit(sourceNode, bodies[0], contents[0]);
        pageIndex = 1;
        node = (remainder || sourceNode).cloneNode(true);
        bodies[1].appendChild(node);
        if (!isOverflowing(contents[1])) return;
        node.remove();
      }

      var finalRemainder = splitTextBlockToFit(node, bodies[1], contents[1]);
      if (finalRemainder || isOverflowing(contents[1])) overflow = true;
    });

    var hasSecondPage = secondPageStarted || (pageIndex === 1 && bodies[1].textContent.trim() !== '');
    pages[1].hidden = !hasSecondPage;
    signatures[0].hidden = hasSecondPage;
    signatures[1].hidden = !hasSecondPage;

    letterDocument.querySelectorAll('[data-letter-page-counter]').forEach(function (counter, index) {
      counter.textContent = toPersianDigits('صفحه ' + (index + 1) + ' از ' + (hasSecondPage ? 2 : 1));
    });
    overflow = overflow || (hasSecondPage && isOverflowing(contents[1]));
    letterDocument.dataset.pageCount = hasSecondPage ? '2' : '1';
    letterDocument.dataset.overflow = overflow ? '1' : '0';
    return overflow;
  }

  function sourceHtml(letterDocument) {
    var source = letterDocument.querySelector('[data-letter-body-source]');
    return source ? source.innerHTML : '';
  }

  function initStandaloneDocument(letterDocument) {
    var run = function () {
      var overflow = paginateDocument(letterDocument, sourceHtml(letterDocument));
      var warning = document.querySelector('[data-letter-print-overflow]');
      if (warning) warning.hidden = !overflow;
    };
    run();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(run);
  }

  function createLeaveDialog(managerName) {
    var wrapper = document.createElement('div');
    wrapper.className = 'letter-leave-dialog-wrap';
    wrapper.hidden = true;
    wrapper.innerHTML = '<div class="letter-leave-dialog__backdrop" data-letter-leave-cancel></div>' +
      '<section class="letter-leave-dialog" role="dialog" aria-modal="true" aria-labelledby="letter-leave-title" aria-describedby="letter-leave-message">' +
        '<div class="letter-leave-dialog__icon" aria-hidden="true">!</div>' +
        '<h2 id="letter-leave-title"></h2>' +
        '<p id="letter-leave-message">تغییرات این نامه هنوز ذخیره نشده. مطمئنی می‌خوای بدون ذخیره از این صفحه بری؟</p>' +
        '<div class="letter-leave-dialog__actions"><button type="button" data-letter-leave-cancel>نه، همین‌جا می‌مونم</button><button type="button" data-letter-leave-confirm>آره، بدون ذخیره برو</button></div>' +
      '</section>';
    wrapper.querySelector('#letter-leave-title').textContent = (managerName || 'دوست من') + ' جان، یه لحظه!';
    document.body.appendChild(wrapper);

    var pendingUrl = '';
    var lastFocus = null;
    var onConfirm = null;

    function close(confirmed) {
      wrapper.hidden = true;
      document.body.classList.remove('letter-leave-dialog-open');
      if (confirmed && onConfirm) onConfirm(pendingUrl);
      if (!confirmed && lastFocus && document.documentElement.contains(lastFocus)) lastFocus.focus({preventScroll: true});
      pendingUrl = '';
      onConfirm = null;
      lastFocus = null;
    }

    wrapper.addEventListener('click', function (event) {
      if (event.target.closest('[data-letter-leave-confirm]')) close(true);
      else if (event.target.closest('[data-letter-leave-cancel]')) close(false);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !wrapper.hidden) close(false);
    });

    return {
      open: function (url, confirmCallback) {
        pendingUrl = url;
        onConfirm = confirmCallback;
        lastFocus = document.activeElement;
        wrapper.hidden = false;
        document.body.classList.add('letter-leave-dialog-open');
        wrapper.querySelector('[data-letter-leave-cancel]').focus();
      }
    };
  }

  function initLetterStampEditors() {
    document.querySelectorAll('[data-letter-stamp-editor]').forEach(function (dialog) {
      if (dialog.dataset.letterStampEditorReady === '1') return;
      dialog.dataset.letterStampEditorReady = '1';

      var type = dialog.dataset.letterType === 'unofficial' ? 'unofficial' : 'official';
      var form = dialog.closest('form');
      var viewport = dialog.querySelector('.letter-stamp-editor-viewport');
      var stage = dialog.querySelector('[data-letter-stamp-stage]');
      var object = dialog.querySelector('[data-letter-stamp-object]');
      var resizeHandle = dialog.querySelector('[data-letter-stamp-resize]');
      var sizeOutput = dialog.querySelector('[data-letter-stamp-size-output]');
      var bottomOutput = dialog.querySelector('[data-letter-stamp-bottom-output]');
      var saveStatus = dialog.querySelector('[data-letter-stamp-save-status]');
      var acceptButton = dialog.querySelector('[data-letter-stamp-editor-accept]');
      if (!form || !stage || !object) return;

      var sizeInput = form.querySelector('[name="letter_' + type + '_stamp_size_mm"]');
      var xInput = form.querySelector('[name="letter_' + type + '_stamp_x_percent"]');
      var bottomInput = form.querySelector('[name="letter_' + type + '_stamp_bottom_mm"]');
      if (!sizeInput || !xInput || !bottomInput) return;

      function number(value, fallback) {
        var parsed = parseFloat(String(value).replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : fallback;
      }
      function clamp(value, minimum, maximum) {
        return Math.max(minimum, Math.min(maximum, value));
      }
      function clean(value) {
        return String(Math.round(value * 10) / 10);
      }
      function persian(value) {
        return (Math.round(value * 10) / 10).toLocaleString('fa-IR', {maximumFractionDigits: 1});
      }
      function syncEditor(constrainInside) {
        var size = clamp(number(sizeInput.value, 34), 18, 55);
        var x = clamp(number(xInput.value, 50), 10, 90);
        var bottom = clamp(number(bottomInput.value, 0), 0, 25);

        object.style.setProperty('--letter-stamp-editor-size', clean(size) + 'mm');
        object.style.setProperty('--letter-stamp-editor-x', clean(x) + '%');
        object.style.setProperty('--letter-stamp-editor-bottom', clean(bottom) + 'mm');

        if (constrainInside && stage.clientWidth && object.getBoundingClientRect().width) {
          var halfWidthPercent = object.getBoundingClientRect().width * 50 / stage.clientWidth;
          var minimumX = Math.min(50, Math.max(5, halfWidthPercent));
          var maximumX = Math.max(50, Math.min(95, 100 - halfWidthPercent));
          x = clamp(x, minimumX, maximumX);
          object.style.setProperty('--letter-stamp-editor-x', clean(x) + '%');
        }

        sizeInput.value = clean(size);
        xInput.value = clean(x);
        bottomInput.value = clean(bottom);
        if (sizeOutput) sizeOutput.textContent = persian(size);
        if (bottomOutput) bottomOutput.textContent = persian(bottom);
      }
      function showSaveStatus(message, isError) {
        if (!saveStatus) return;
        saveStatus.hidden = false;
        saveStatus.classList.toggle('is-error', !!isError);
        saveStatus.textContent = message;
      }
      function openEditor() {
        if (saveStatus) {
          saveStatus.hidden = true;
          saveStatus.classList.remove('is-error');
          saveStatus.textContent = '';
        }
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        document.body.classList.add('letter-stamp-editor-opened');
        window.requestAnimationFrame(function () {
          if (viewport) {
            viewport.scrollTop = viewport.scrollHeight;
            viewport.scrollLeft = viewport.scrollWidth - viewport.clientWidth;
          }
          syncEditor(true);
        });
      }
      function closeEditor() {
        if (typeof dialog.close === 'function' && dialog.open) dialog.close();
        else dialog.removeAttribute('open');
        document.body.classList.remove('letter-stamp-editor-opened');
      }

      form.querySelectorAll('[data-letter-stamp-editor-open]').forEach(function (button) {
        if (button.dataset.letterStampEditorOpen === dialog.id) button.addEventListener('click', openEditor);
      });
      dialog.querySelectorAll('[data-letter-stamp-editor-close]').forEach(function (button) {
        button.addEventListener('click', closeEditor);
      });
      dialog.addEventListener('close', function () { document.body.classList.remove('letter-stamp-editor-opened'); });
      dialog.addEventListener('click', function (event) { if (event.target === dialog) closeEditor(); });

      function saveStampLayout() {
        var config = window.ziguratLettersConfig || {};
        if (!config.ajaxUrl || !config.stampLayoutNonce) {
          showSaveStatus('امکان ذخیره فوری در دسترس نیست؛ تنظیمات اصلی را ذخیره کنید.', true);
          return;
        }
        var originalText = acceptButton ? acceptButton.textContent : '';
        if (acceptButton) {
          acceptButton.disabled = true;
          acceptButton.textContent = 'در حال ذخیره…';
        }
        showSaveStatus('در حال ثبت جای مهر…', false);
        var body = new FormData();
        body.append('action', 'zigurat_letter_save_stamp_layout');
        body.append('nonce', config.stampLayoutNonce);
        body.append('letter_type', type);
        body.append('size_mm', sizeInput.value);
        body.append('x_percent', xInput.value);
        body.append('bottom_mm', bottomInput.value);
        window.fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: body})
          .then(function (response) { return response.json(); })
          .then(function (result) {
            if (!result || !result.success) {
              throw new Error(result && result.data && result.data.message ? result.data.message : 'ذخیره جای مهر انجام نشد.');
            }
            var layout = result.data && result.data.layout ? result.data.layout : {};
            if (layout.size_mm !== undefined) sizeInput.value = clean(number(layout.size_mm, 34));
            if (layout.x_percent !== undefined) xInput.value = clean(number(layout.x_percent, 50));
            if (layout.bottom_mm !== undefined) bottomInput.value = clean(number(layout.bottom_mm, 0));
            syncEditor(false);
            showSaveStatus(result.data.message || 'اندازه و جای مهر ذخیره شد.', false);
            window.setTimeout(closeEditor, 650);
          })
          .catch(function (error) {
            showSaveStatus(error && error.message ? error.message : 'ذخیره جای مهر انجام نشد.', true);
          })
          .finally(function () {
            if (acceptButton) {
              acceptButton.disabled = false;
              acceptButton.textContent = originalText;
            }
          });
      }
      if (acceptButton) acceptButton.addEventListener('click', saveStampLayout);

      var resetButton = dialog.querySelector('[data-letter-stamp-reset]');
      if (resetButton) resetButton.addEventListener('click', function () {
        sizeInput.value = '34';
        xInput.value = '50';
        bottomInput.value = '0';
        syncEditor(true);
      });

      function startPointerAction(event, mode) {
        if (event.button !== undefined && event.button !== 0) return;
        event.preventDefault();
        event.stopPropagation();
        var startX = event.clientX;
        var startY = event.clientY;
        var startSize = number(sizeInput.value, 34);
        var startPercent = number(xInput.value, 50);
        var startBottom = number(bottomInput.value, 0);
        var objectRect = object.getBoundingClientRect();
        var stageRect = stage.getBoundingClientRect();
        var pixelsPerMm = objectRect.width / Math.max(1, startSize);

        function move(moveEvent) {
          if (moveEvent.pointerId !== event.pointerId) return;
          moveEvent.preventDefault();
          if (mode === 'resize') {
            sizeInput.value = clean(clamp(startSize + (moveEvent.clientX - startX) / pixelsPerMm, 18, 55));
          } else {
            xInput.value = clean(startPercent + (moveEvent.clientX - startX) * 100 / stageRect.width);
            bottomInput.value = clean(startBottom - (moveEvent.clientY - startY) / pixelsPerMm);
          }
          syncEditor(true);
        }
        function end(endEvent) {
          if (endEvent.pointerId !== event.pointerId) return;
          window.removeEventListener('pointermove', move);
          window.removeEventListener('pointerup', end);
          window.removeEventListener('pointercancel', end);
        }
        window.addEventListener('pointermove', move, {passive: false});
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
      }

      object.addEventListener('pointerdown', function (event) {
        if (event.target.closest('[data-letter-stamp-resize]')) return;
        startPointerAction(event, 'move');
      });
      if (resizeHandle) resizeHandle.addEventListener('pointerdown', function (event) { startPointerAction(event, 'resize'); });
      object.addEventListener('keydown', function (event) {
        var handled = true;
        if (event.key === 'ArrowLeft') xInput.value = clean(number(xInput.value, 50) - 1);
        else if (event.key === 'ArrowRight') xInput.value = clean(number(xInput.value, 50) + 1);
        else if (event.key === 'ArrowUp') bottomInput.value = clean(number(bottomInput.value, 0) + 1);
        else if (event.key === 'ArrowDown') bottomInput.value = clean(number(bottomInput.value, 0) - 1);
        else if (event.key === '+' || event.key === '=') sizeInput.value = clean(number(sizeInput.value, 34) + 1);
        else if (event.key === '-' || event.key === '_') sizeInput.value = clean(number(sizeInput.value, 34) - 1);
        else handled = false;
        if (handled) {
          event.preventDefault();
          syncEditor(true);
        }
      });
      syncEditor(false);
    });
  }

  function initLetterEditor(root) {
    var form = root.querySelector('.manager-letter-form');
    var letterDocument = root.querySelector('[data-letter-document]');
    var bodyField = form ? form.querySelector('[name="letter_body"]') : null;
    var fontSizeField = form ? form.querySelector('[data-letter-font-size]') : null;
    var overflowNotice = root.querySelector('[data-letter-overflow]');
    if (!form || !letterDocument) return;
    var managerName = root.getAttribute('data-manager-name') || '';
    var leaveDialog = createLeaveDialog(managerName);
    var hasUnsavedChanges = false;
    var allowLeave = false;

    var placeholders = {
      number: 'پس از ذخیره',
      date: '',
      attachment: 'ندارد',
      recipient: 'نام گیرنده',
      subject: 'موضوع نامه',
      greeting: 'با سلام و احترام',
      signer_name: 'عبارت پایانی',
      signer_title: 'نام امضاکننده',
      header_title: '',
      header_subtitle: ''
    };
    var pendingFrame = 0;

    function markDirty() {
      hasUnsavedChanges = true;
    }

    function bodyHtml() {
      if (window.tinymce) {
        var editor = window.tinymce.get('zigurat_letter_body_editor');
        if (editor) return editor.getContent();
      }
      var value = bodyField ? bodyField.value.trim() : '';
      if (!value) return '';
      return value.split(/\n{2,}/).map(function (paragraph) {
        return '<p>' + paragraph.replace(/\n/g, '<br>') + '</p>';
      }).join('');
    }

    function repaginate() {
      window.cancelAnimationFrame(pendingFrame);
      pendingFrame = window.requestAnimationFrame(function () {
        var overflow = paginateDocument(letterDocument, bodyHtml());
        if (overflowNotice) overflowNotice.hidden = !overflow;
      });
    }

    function updateText(name) {
      var input = form.querySelector('[data-letter-source="' + name + '"]');
      var value = input ? input.value.trim() : '';
      value = toPersianDigits(value || placeholders[name] || '');
      root.querySelectorAll('[data-letter-preview="' + name + '"]').forEach(function (target) {
        target.textContent = value;
      });
      repaginate();
    }

    form.querySelectorAll('[data-letter-source]').forEach(function (input) {
      var name = input.getAttribute('data-letter-source');
      input.addEventListener('input', function () { updateText(name); });
      input.addEventListener('change', function () { updateText(name); });
      updateText(name);
    });

    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', function () { allowLeave = true; });

    document.addEventListener('click', function (event) {
      if (!hasUnsavedChanges || allowLeave || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      var anchor = event.target.closest('a[href]');
      if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download')) return;
      var destination;
      try {
        destination = new URL(anchor.href, window.location.href);
      } catch (error) {
        return;
      }
      if (destination.protocol === 'javascript:') return;
      if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;
      event.preventDefault();
      leaveDialog.open(destination.href, function (url) {
        allowLeave = true;
        window.location.assign(url);
      });
    });

    window.addEventListener('beforeunload', function (event) {
      if (!hasUnsavedChanges || allowLeave) return;
      event.preventDefault();
      event.returnValue = '';
    });

    if (fontSizeField) {
      var updateFontSize = function () {
        var size = Math.max(9, Math.min(22, parseFloat(fontSizeField.value) || 13));
        letterDocument.style.setProperty('--letter-body-size', size + 'pt');
        repaginate();
      };
      fontSizeField.addEventListener('input', updateFontSize);
      fontSizeField.addEventListener('change', updateFontSize);
      updateFontSize();
    }
    if (bodyField) bodyField.addEventListener('input', repaginate);

    var stampToggle = form.querySelector('[data-letter-stamp-toggle]');
    if (stampToggle) {
      var updateStamp = function () {
        letterDocument.querySelectorAll('[data-letter-stamp]').forEach(function (stamp) {
          stamp.hidden = !stampToggle.checked;
        });
        repaginate();
      };
      stampToggle.addEventListener('change', updateStamp);
      updateStamp();
    }

    var attempts = 0;
    var editorTimer = window.setInterval(function () {
      attempts += 1;
      var editor = window.tinymce && window.tinymce.get('zigurat_letter_body_editor');
      if (editor) {
        editor.on('input change keyup Undo Redo ExecCommand', function () {
          markDirty();
          repaginate();
        });
        editor.on('SetContent', repaginate);
        repaginate();
        window.clearInterval(editorTimer);
      } else if (attempts > 40) {
        window.clearInterval(editorTimer);
      }
    }, 250);

    window.addEventListener('resize', repaginate);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(repaginate);
    repaginate();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initLetterStampEditors();
    var editorDocuments = [];
    document.querySelectorAll('[data-letter-editor]').forEach(function (root) {
      var documentNode = root.querySelector('[data-letter-document]');
      if (documentNode) editorDocuments.push(documentNode);
      initLetterEditor(root);
    });
    document.querySelectorAll('[data-letter-document]').forEach(function (letterDocument) {
      if (editorDocuments.indexOf(letterDocument) === -1) initStandaloneDocument(letterDocument);
    });
  });
})();
