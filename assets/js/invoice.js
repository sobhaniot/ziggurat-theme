(function () {
  'use strict';
  var invoiceHasUnsavedChanges = false;
  var bypassUnsavedWarning = false;
  var approvedLinkNavigation = false;
  var approvedHistoryNavigation = false;
  var leaveDialog = null;
  var pendingLeaveAction = null;
  var leaveDialogLastFocus = null;
  var actionConfirmDialog = null;
  var actionConfirmCallback = null;
  var actionConfirmLastFocus = null;

  function ensureActionConfirmDialog() {
    if (actionConfirmDialog && document.documentElement.contains(actionConfirmDialog)) return actionConfirmDialog;
    var wrapper = document.createElement('div');
    wrapper.className = 'invoice-action-dialog-wrap';
    wrapper.hidden = true;
    wrapper.innerHTML = '<div class="invoice-action-dialog-backdrop" data-action-cancel></div>' +
      '<section class="invoice-action-dialog" role="dialog" aria-modal="true" aria-labelledby="invoice-action-title" aria-describedby="invoice-action-message">' +
        '<div class="invoice-action-dialog__icon" aria-hidden="true">✓</div>' +
        '<h2 id="invoice-action-title"></h2>' +
        '<p id="invoice-action-message"></p>' +
        '<div class="invoice-action-dialog__warning" data-action-warning></div>' +
        '<div class="invoice-action-dialog__actions"><button type="button" data-action-cancel>نه، برگرد</button><button type="button" data-action-confirm></button></div>' +
      '</section>';
    document.body.appendChild(wrapper);
    wrapper.addEventListener('click', function (event) {
      if (event.target.closest('[data-action-confirm]')) closeActionConfirm(true);
      else if (event.target.closest('[data-action-cancel]')) closeActionConfirm(false);
    });
    wrapper.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); closeActionConfirm(false); }
    });
    actionConfirmDialog = wrapper;
    return wrapper;
  }

  function showActionConfirm(options, callback) {
    var dialog = ensureActionConfirmDialog();
    actionConfirmCallback = callback;
    actionConfirmLastFocus = document.activeElement;
    dialog.querySelector('#invoice-action-title').textContent = options.title;
    dialog.querySelector('#invoice-action-message').textContent = options.message;
    dialog.querySelector('[data-action-confirm]').textContent = options.confirmText;
    var warning = dialog.querySelector('[data-action-warning]');
    warning.textContent = options.warning || '';
    warning.hidden = !options.warning;
    dialog.hidden = false;
    document.body.classList.add('invoice-action-dialog-open');
    dialog.querySelector('[data-action-cancel]').focus();
  }

  function closeActionConfirm(confirmed) {
    if (!actionConfirmDialog || actionConfirmDialog.hidden) return;
    var callback = actionConfirmCallback;
    actionConfirmCallback = null;
    actionConfirmDialog.hidden = true;
    document.body.classList.remove('invoice-action-dialog-open');
    if (!confirmed && actionConfirmLastFocus && document.documentElement.contains(actionConfirmLastFocus)) actionConfirmLastFocus.focus({preventScroll:true});
    actionConfirmLastFocus = null;
    if (confirmed && typeof callback === 'function') callback();
  }

  function ensureLeaveDialog() {
    if (leaveDialog && document.documentElement.contains(leaveDialog)) return leaveDialog;
    var config = window.ziguratInvoiceConfig || {};
    var managerName = String(config.managerName || 'دوست عزیز').trim();
    var wrapper = document.createElement('div');
    wrapper.className = 'invoice-leave-dialog-wrap';
    wrapper.hidden = true;
    wrapper.innerHTML = '<div class="invoice-leave-dialog-backdrop" data-leave-cancel></div>' +
      '<section class="invoice-leave-dialog" role="dialog" aria-modal="true" aria-labelledby="invoice-leave-title" aria-describedby="invoice-leave-message">' +
        '<div class="invoice-leave-dialog__icon" aria-hidden="true">!</div>' +
        '<h2 id="invoice-leave-title"></h2>' +
        '<p id="invoice-leave-message">تغییرات این فاکتور هنوز ذخیره نشده. مطمئنی می‌خوای بدون ذخیره از این قسمت بری؟</p>' +
        '<div class="invoice-leave-dialog__actions"><button type="button" data-leave-cancel>نه، همین‌جا می‌مونم</button><button type="button" data-leave-confirm>آره، بدون ذخیره برو</button></div>' +
      '</section>';
    wrapper.querySelector('#invoice-leave-title').textContent = managerName + ' جان، یه لحظه!';
    document.body.appendChild(wrapper);
    wrapper.addEventListener('click', function (event) {
      if (event.target.closest('[data-leave-confirm]')) closeLeaveDialog(true);
      if (event.target.closest('[data-leave-cancel]')) closeLeaveDialog(false);
    });
    wrapper.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeLeaveDialog(false);
      }
    });
    leaveDialog = wrapper;
    return wrapper;
  }

  function showLeaveDialog(onConfirm) {
    var dialog = ensureLeaveDialog();
    pendingLeaveAction = onConfirm;
    leaveDialogLastFocus = document.activeElement;
    dialog.hidden = false;
    document.body.classList.add('invoice-leave-dialog-open');
    dialog.querySelector('[data-leave-cancel]').focus();
  }

  function closeLeaveDialog(confirmed) {
    if (!leaveDialog || leaveDialog.hidden) return;
    var action = pendingLeaveAction;
    pendingLeaveAction = null;
    leaveDialog.hidden = true;
    document.body.classList.remove('invoice-leave-dialog-open');
    if (!confirmed && leaveDialogLastFocus && document.documentElement.contains(leaveDialogLastFocus)) {
      leaveDialogLastFocus.focus({preventScroll:true});
    }
    leaveDialogLastFocus = null;
    if (confirmed && typeof action === 'function') action();
  }

  window.addEventListener('beforeunload', function (event) {
    if (!invoiceHasUnsavedChanges || bypassUnsavedWarning) return;
    event.preventDefault();
    event.returnValue = '';
  });

  function setupInvoiceListAutoFilters(root) {
    (root || document).querySelectorAll('.invoice-list-filters select[name="filter_type"], .invoice-list-filters select[name="filter_status"], .invoice-list-filters select[name="filter_payment_status"], .invoice-list-filters select[name="filter_tax_status"], .invoice-list-filters select[name="tax_year"]').forEach(function (select) {
      if (select.dataset.autoFilterReady === '1') return;
      select.dataset.autoFilterReady = '1';
      select.addEventListener('change', function () {
        var form = select.form;
        if (!form) return;
        var typeSelect = form.querySelector('select[name="filter_type"]');
        var yearSelect = form.querySelector('select[name="tax_year"]');
        var quarterInput = form.querySelector('input[name="tax_quarter"]');
        if (select.name === 'filter_type' && select.value !== 'invoice') {
          if (yearSelect) yearSelect.value = '0';
          if (quarterInput) quarterInput.value = '0';
        }
        if (select.name === 'tax_year') {
          if (select.value !== '0' && typeSelect) typeSelect.value = 'invoice';
          if (quarterInput) quarterInput.value = '0';
        }
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.dispatchEvent(new Event('submit', {bubbles:true, cancelable:true}));
        }
      });
    });
  }

  function closeStatusQuickMenus() {
    document.querySelectorAll('[data-status-menu]').forEach(function (menu) {
      menu.hidden = true;
    });
    document.querySelectorAll('[data-status-quick]').forEach(function (control) {
      control.classList.remove('is-menu-open');
    });
    document.querySelectorAll('.invoice-list-table tr.is-status-menu-open').forEach(function (row) {
      row.classList.remove('is-status-menu-open');
    });
    document.querySelectorAll('.invoice-list-table td.is-status-menu-open').forEach(function (cell) {
      cell.classList.remove('is-status-menu-open');
    });
    document.querySelectorAll('[data-status-menu-toggle]').forEach(function (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    });
  }

  function setupStatusQuickControls(root) {
    (root || document).querySelectorAll('[data-status-quick]').forEach(function (control) {
      if (control.dataset.statusQuickReady === '1') return;
      control.dataset.statusQuickReady = '1';
      var toggle = control.querySelector('[data-status-menu-toggle]');
      var menu = control.querySelector('[data-status-menu]');
      var options = control.querySelectorAll('[data-status-option]');
      var form = control.querySelector('[data-status-quick-form]');
      if (!toggle || !menu || !options.length || !form) return;
      toggle.addEventListener('click', function () {
        var open = menu.hidden;
        closeStatusQuickMenus();
        if (open) {
          menu.hidden = false;
          control.classList.add('is-menu-open');
          var row = control.closest('tr');
          var cell = control.closest('td');
          if (row) row.classList.add('is-status-menu-open');
          if (cell) cell.classList.add('is-status-menu-open');
          toggle.setAttribute('aria-expanded', 'true');
        }
      });
      options.forEach(function (option) {
        option.addEventListener('click', function () {
          if (option.disabled) return;
          closeStatusQuickMenus();
          var kind = control.dataset.statusKind;
          var target = option.dataset.statusOption;
          var invoiceNumber = control.dataset.invoiceNumber || '';
          var prompt = kind === 'payment'
            ? (target === 'settled'
              ? {title:'تسویه کامل فاکتور',message:'فاکتور شماره ' + invoiceNumber + ' تسویه کامل می‌شود. مطمئنی؟',confirmText:'بله، تسویه کامل شود',warning:'بعد از تأیید، ویرایش مستقیم این فاکتور غیرفعال می‌شود.'}
              : {title:'تغییر به پرداخت‌نشده',message:'وضعیت پرداخت فاکتور شماره ' + invoiceNumber + ' به پرداخت‌نشده برمی‌گردد. مطمئنی؟',confirmText:'بله، پرداخت‌نشده شود',warning:'بعد از تأیید، امکان اصلاح مستقیم فاکتور دوباره فعال می‌شود.'})
            : (target === 'submitted'
              ? {title:'ثبت در سامانه مؤدیان',message:'وضعیت مؤدیان فاکتور شماره ' + invoiceNumber + ' ثبت‌شده می‌شود. مطمئنی؟',confirmText:'بله، ثبت‌شده شود'}
              : {title:'بازگشت وضعیت مؤدیان',message:'وضعیت فاکتور شماره ' + invoiceNumber + ' به ثبت‌نشده برمی‌گردد. مطمئنی؟',confirmText:'بله، ثبت‌نشده شود'});
          showActionConfirm(prompt, function () {
            if (kind === 'payment') {
              form.querySelector('[name="payment_status_target"]').value = target;
              form.querySelector('[name="confirm_payment_status"]').value = '1';
            } else {
              form.querySelector('[name="tax_status_target"]').value = target;
              form.querySelector('[name="confirm_tax_submission"]').value = '1';
            }
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
          });
        });
      });
    });
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-status-quick]')) return;
    var openMenus = document.querySelectorAll('[data-status-menu]:not([hidden])');
    if (!openMenus.length) return;
    closeStatusQuickMenus();
  });
  window.addEventListener('resize', closeStatusQuickMenus);

  function setupInvoiceRowSelection(root) {
    var scope = root || document;
    var summary = scope.querySelector('[data-invoice-selection-summary]');
    var table = scope.querySelector('.invoice-list-table');
    if (!summary || !table || table.dataset.selectionReady === '1') return;
    table.dataset.selectionReady = '1';

    var countOutput = summary.querySelector('[data-selection-count]');
    var grandOutput = summary.querySelector('[data-selection-grand]');
    var taxOutput = summary.querySelector('[data-selection-tax]');
    var paidOutput = summary.querySelector('[data-selection-paid]');
    var balanceOutput = summary.querySelector('[data-selection-balance]');
    var clearButton = summary.querySelector('[data-selection-clear]');

    function numberFrom(row, key) {
      var value = Number(row.dataset[key] || 0);
      return Number.isFinite(value) ? value : 0;
    }
    function money(value) {
      return Math.round(value).toLocaleString('fa-IR') + ' ریال';
    }
    function updateSummary() {
      var selected = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-invoice-selectable].is-selected'));
      var totals = selected.reduce(function (result, row) {
        result.grand += numberFrom(row, 'grandTotal');
        result.tax += numberFrom(row, 'taxAmount');
        result.paid += numberFrom(row, 'paidAmount');
        result.balance += numberFrom(row, 'balance');
        return result;
      }, { grand: 0, tax: 0, paid: 0, balance: 0 });
      summary.classList.toggle('is-visible', selected.length > 0);
      summary.setAttribute('aria-hidden', selected.length > 0 ? 'false' : 'true');
      if (countOutput) countOutput.textContent = selected.length.toLocaleString('fa-IR');
      if (grandOutput) grandOutput.textContent = money(totals.grand);
      if (taxOutput) taxOutput.textContent = money(totals.tax);
      if (paidOutput) paidOutput.textContent = money(totals.paid);
      if (balanceOutput) balanceOutput.textContent = money(totals.balance);
    }
    function toggleRow(row) {
      var selected = !row.classList.contains('is-selected');
      row.classList.toggle('is-selected', selected);
      row.setAttribute('aria-selected', selected ? 'true' : 'false');
      updateSummary();
    }
    function isInteractive(target) {
      return !!target.closest('a, button, input, select, textarea, label, form, details, summary');
    }

    table.addEventListener('click', function (event) {
      var row = event.target.closest('tbody tr[data-invoice-selectable]');
      if (!row || isInteractive(event.target)) return;
      toggleRow(row);
    });
    table.addEventListener('keydown', function (event) {
      var row = event.target.closest('tbody tr[data-invoice-selectable]');
      if (!row || event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) return;
      event.preventDefault();
      toggleRow(row);
    });
    if (clearButton) {
      clearButton.addEventListener('click', function () {
        table.querySelectorAll('tbody tr[data-invoice-selectable].is-selected').forEach(function (row) {
          row.classList.remove('is-selected');
          row.setAttribute('aria-selected', 'false');
        });
        updateSummary();
      });
    }
    updateSummary();
  }

  function setupInvoiceDeleteControls(root) {
    (root || document).querySelectorAll('[data-invoice-delete-form]').forEach(function (form) {
      if (form.dataset.deleteReady === '1') return;
      form.dataset.deleteReady = '1';
      form.addEventListener('submit', function (event) {
        if (form.dataset.deleteConfirmed === '1') return;
        event.preventDefault();
        var invoiceNumber = form.dataset.invoiceNumber || '';
        showActionConfirm({
          title: 'حذف فاکتور',
          message: 'فاکتور شماره ' + invoiceNumber + ' برای همیشه حذف شود؟',
          confirmText: 'بله، حذف شود',
          warning: 'این عملیات قابل بازگشت نیست و ردیف‌های کالا و پرداخت‌های این سند نیز حذف می‌شوند.'
        }, function () {
          form.dataset.deleteConfirmed = '1';
          if (typeof form.requestSubmit === 'function') form.requestSubmit();
          else form.submit();
        });
      });
    });
  }

  document.addEventListener('click', function (event) {
    if (!invoiceHasUnsavedChanges || event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
    if (approvedLinkNavigation) {
      approvedLinkNavigation = false;
      return;
    }
    var targetUrl;
    try { targetUrl = new URL(link.href, window.location.href); } catch (error) { return; }
    if (targetUrl.href === window.location.href || (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search && targetUrl.hash)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    showLeaveDialog(function () {
      approvedLinkNavigation = true;
      bypassUnsavedWarning = true;
      link.click();
      window.setTimeout(function () { bypassUnsavedWarning = false; }, 1500);
    });
  }, true);

  document.addEventListener('zigurat:panel-before-navigate', function (event) {
    if (!invoiceHasUnsavedChanges || !event.detail || event.detail.reason !== 'history') return;
    if (approvedHistoryNavigation) {
      approvedHistoryNavigation = false;
      return;
    }
    event.preventDefault();
    showLeaveDialog(function () {
      approvedHistoryNavigation = true;
      window.history.back();
    });
  });

  function setupStampLayoutEditors(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-stamp-editor]').forEach(function (dialog) {
      if (dialog.dataset.stampEditorReady === '1') return;
      dialog.dataset.stampEditorReady = '1';
      var brand = dialog.dataset.brand === 'official' ? 'official' : 'unofficial';
      var form = dialog.closest('form');
      var viewport = dialog.querySelector('.invoice-stamp-editor-viewport');
      var stage = dialog.querySelector('[data-stamp-stage]');
      var object = dialog.querySelector('[data-stamp-object]');
      var resizeHandle = dialog.querySelector('[data-stamp-resize]');
      var sizeOutput = dialog.querySelector('[data-stamp-size-output]');
      var bottomOutput = dialog.querySelector('[data-stamp-bottom-output]');
      var saveStatus = dialog.querySelector('[data-stamp-save-status]');
      var acceptButton = dialog.querySelector('[data-stamp-editor-accept]');
      if (!form || !stage || !object) return;
      var sizeInput = form.querySelector('[name="setting_' + brand + '_stamp_size_mm"]');
      var xInput = form.querySelector('[name="setting_' + brand + '_stamp_x_percent"]');
      var bottomInput = form.querySelector('[name="setting_' + brand + '_stamp_bottom_mm"]');
      var positionInput = form.querySelector('[name="setting_' + brand + '_stamp_position"]');
      if (!sizeInput || !xInput || !bottomInput || !positionInput) return;

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
        return (Math.round(value * 10) / 10).toLocaleString('fa-IR', {maximumFractionDigits:1});
      }
      function syncEditor(constrainInside) {
        var size = clamp(number(sizeInput.value, brand === 'official' ? 46 : 40), 20, 70);
        var x = clamp(number(xInput.value, 75), 10, 90);
        var bottom = clamp(number(bottomInput.value, 0), 0, 30);
        object.style.setProperty('--stamp-editor-size', clean(size) + 'mm');
        object.style.setProperty('--stamp-editor-x', clean(x) + '%');
        object.style.setProperty('--stamp-editor-bottom', clean(bottom) + 'mm');
        stage.style.setProperty('--invoice-stamp-size', clean(size) + 'mm');
        stage.style.setProperty('--invoice-stamp-x', clean(x) + '%');
        if (constrainInside && stage.clientWidth && object.getBoundingClientRect().width) {
          var halfWidthPercent = object.getBoundingClientRect().width * 50 / stage.clientWidth;
          x = clamp(x, Math.max(5, halfWidthPercent), Math.min(95, 100 - halfWidthPercent));
          object.style.setProperty('--stamp-editor-x', clean(x) + '%');
        }
        sizeInput.value = clean(size);
        xInput.value = clean(x);
        bottomInput.value = clean(bottom);
        positionInput.value = x >= 50 ? 'right' : 'left';
        stage.classList.toggle('invoice-signature--stamp-right', x >= 50);
        stage.classList.toggle('invoice-signature--stamp-left', x < 50);
        if (sizeOutput) sizeOutput.textContent = persian(size);
        if (bottomOutput) bottomOutput.textContent = persian(bottom);
      }
      function openEditor() {
        if (saveStatus) {
          saveStatus.hidden = true;
          saveStatus.classList.remove('is-error');
          saveStatus.textContent = '';
        }
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        document.body.classList.add('invoice-stamp-editor-opened');
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
        document.body.classList.remove('invoice-stamp-editor-opened');
      }
      scope.querySelectorAll('[data-stamp-editor-open]').forEach(function (button) {
        if (button.dataset.stampEditorOpen === dialog.id) button.addEventListener('click', openEditor);
      });
      dialog.querySelectorAll('[data-stamp-editor-close]').forEach(function (button) {
        button.addEventListener('click', closeEditor);
      });
      function showSaveStatus(message, isError) {
        if (!saveStatus) return;
        saveStatus.hidden = false;
        saveStatus.classList.toggle('is-error', !!isError);
        saveStatus.textContent = message;
      }
      function saveStampLayout() {
        var config = window.ziguratInvoiceConfig || {};
        if (!config.ajaxUrl || !config.stampLayoutNonce) {
          showSaveStatus('امکان ذخیره فوری در دسترس نیست؛ صفحه را تازه‌سازی کنید.', true);
          return;
        }
        var originalText = acceptButton.textContent;
        acceptButton.disabled = true;
        acceptButton.textContent = 'در حال ذخیره…';
        showSaveStatus('در حال ثبت جای مهر…', false);
        var body = new FormData();
        body.append('action', 'zigurat_invoice_save_stamp_layout');
        body.append('nonce', config.stampLayoutNonce);
        body.append('brand', brand);
        body.append('size_mm', sizeInput.value);
        body.append('x_percent', xInput.value);
        body.append('bottom_mm', bottomInput.value);
        window.fetch(config.ajaxUrl, {method:'POST', credentials:'same-origin', body:body})
          .then(function (response) { return response.json(); })
          .then(function (result) {
            if (!result || !result.success) {
              throw new Error(result && result.data && result.data.message ? result.data.message : 'ذخیره جای مهر انجام نشد.');
            }
            var layout = result.data && result.data.layout ? result.data.layout : {};
            if (layout.size_mm !== undefined) sizeInput.value = clean(number(layout.size_mm, number(sizeInput.value, 40)));
            if (layout.x_percent !== undefined) xInput.value = clean(number(layout.x_percent, number(xInput.value, 75)));
            if (layout.bottom_mm !== undefined) bottomInput.value = clean(number(layout.bottom_mm, number(bottomInput.value, 0)));
            syncEditor(false);
            showSaveStatus(result.data.message || 'جای مهر ذخیره شد.', false);
            window.setTimeout(closeEditor, 650);
          })
          .catch(function (error) {
            showSaveStatus(error && error.message ? error.message : 'ذخیره جای مهر انجام نشد.', true);
          })
          .finally(function () {
            acceptButton.disabled = false;
            acceptButton.textContent = originalText;
          });
      }
      if (acceptButton) acceptButton.addEventListener('click', saveStampLayout);
      dialog.addEventListener('close', function () { document.body.classList.remove('invoice-stamp-editor-opened'); });
      dialog.addEventListener('click', function (event) { if (event.target === dialog) closeEditor(); });
      [sizeInput, bottomInput].forEach(function (input) { input.addEventListener('input', function () { syncEditor(true); }); });
      var resetButton = dialog.querySelector('[data-stamp-reset]');
      if (resetButton) resetButton.addEventListener('click', function () {
        sizeInput.value = brand === 'official' ? '46' : '40';
        xInput.value = '75';
        bottomInput.value = '0';
        syncEditor(true);
      });

      function startPointerAction(event, mode) {
        if (event.button !== undefined && event.button !== 0) return;
        event.preventDefault();
        event.stopPropagation();
        var startX = event.clientX;
        var startY = event.clientY;
        var startSize = number(sizeInput.value, brand === 'official' ? 46 : 40);
        var startPercent = number(xInput.value, 75);
        var startBottom = number(bottomInput.value, 0);
        var objectRect = object.getBoundingClientRect();
        var stageRect = stage.getBoundingClientRect();
        var pixelsPerMm = objectRect.width / Math.max(1, startSize);
        function move(moveEvent) {
          if (moveEvent.pointerId !== event.pointerId) return;
          moveEvent.preventDefault();
          if (mode === 'resize') {
            sizeInput.value = clean(clamp(startSize + (moveEvent.clientX - startX) / pixelsPerMm, 20, 70));
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
        window.addEventListener('pointermove', move, {passive:false});
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
      }
      object.addEventListener('pointerdown', function (event) {
        if (event.target.closest('[data-stamp-resize]')) return;
        startPointerAction(event, 'move');
      });
      if (resizeHandle) resizeHandle.addEventListener('pointerdown', function (event) { startPointerAction(event, 'resize'); });
      object.addEventListener('keydown', function (event) {
        var handled = true;
        if (event.key === 'ArrowLeft') xInput.value = clean(number(xInput.value, 75) - 1);
        else if (event.key === 'ArrowRight') xInput.value = clean(number(xInput.value, 75) + 1);
        else if (event.key === 'ArrowUp') bottomInput.value = clean(number(bottomInput.value, 0) + 1);
        else if (event.key === 'ArrowDown') bottomInput.value = clean(number(bottomInput.value, 0) - 1);
        else if (event.key === '+' || event.key === '=') sizeInput.value = clean(number(sizeInput.value, 40) + 1);
        else if (event.key === '-' || event.key === '_') sizeInput.value = clean(number(sizeInput.value, 40) - 1);
        else handled = false;
        if (handled) { event.preventDefault(); syncEditor(true); }
      });
      syncEditor(false);
    });
  }

  function initializeInvoice(root) {
  bypassUnsavedWarning = false;
  approvedLinkNavigation = false;
  approvedHistoryNavigation = false;
  if (leaveDialog && !leaveDialog.hidden) closeLeaveDialog(false);
  setupInvoiceListAutoFilters(root);
  setupInvoiceRowSelection(root);
  setupInvoiceDeleteControls(root);
  setupStampLayoutEditors(root);
  setupStatusQuickControls(root);
  var editor = (root || document).querySelector('[data-invoice-editor]');
  if (!editor) {
    invoiceHasUnsavedChanges = false;
    return;
  }
  if (editor.dataset.invoiceReady === '1') return;
  editor.dataset.invoiceReady = '1';
  invoiceHasUnsavedChanges = editor.dataset.invoiceUnsaved === '1';
  editor.addEventListener('input', function () { invoiceHasUnsavedChanges = true; });
  editor.addEventListener('change', function () { invoiceHasUnsavedChanges = true; });
  var submitWasClicked = false;
  function setQuantityValidationMode(isSubmitting) {
    editor.querySelectorAll('[name="item_quantity[]"]').forEach(function (input) {
      input.setCustomValidity(isSubmitting && quantity(input) <= 0 ? 'مقدار باید بیشتر از صفر باشد.' : '');
    });
  }
  editor.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
      event.preventDefault();
    }
  });
  editor.addEventListener('click', function (event) {
    var submitButton = event.target.closest('button[type="submit"], input[type="submit"]');
    if (!submitButton) return;
    submitWasClicked = true;
    setQuantityValidationMode(true);
    window.setTimeout(function () {
      submitWasClicked = false;
      setQuantityValidationMode(false);
    }, 0);
  });
  editor.addEventListener('submit', function (event) {
    if (!submitWasClicked) {
      event.preventDefault();
      return;
    }
    submitWasClicked = false;
    invoiceHasUnsavedChanges = false;
    bypassUnsavedWarning = true;
    window.setTimeout(function () {
      bypassUnsavedWarning = false;
      if (document.documentElement.contains(editor)) invoiceHasUnsavedChanges = true;
    }, 1500);
    setQuantityValidationMode(false);
  });
  var body = editor.querySelector('[data-invoice-items]');
  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  function normalize(value) { return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit] || digit; }); }
  function money(input) { return Math.max(0, parseInt(normalize(input && input.value).replace(/[^0-9]/g, ''), 10) || 0); }
  function normalizeQuantity(value) {
    var normalized = normalize(value).replace(/[،٫,]/g, '.').replace(/[^0-9.]/g, '');
    var separator = normalized.indexOf('.');
    if (separator === -1) return normalized;
    return normalized.slice(0, separator) + '.' + normalized.slice(separator + 1).replace(/\./g, '').slice(0, 3);
  }
  function quantity(input) { return Math.max(0, parseFloat(normalizeQuantity(input && input.value)) || 0); }
  function formatQuantity(value) {
    var normalized = normalizeQuantity(value);
    if (normalized === '' || normalized === '.') return '';
    var parsed = parseFloat(normalized);
    if (!Number.isFinite(parsed)) return '';
    return String(Math.round(Math.max(0, parsed) * 1000) / 1000);
  }
  function format(value) { return Math.round(value).toLocaleString('fa-IR') + ' ریال'; }
  function formatMoneyInput(input) {
    if (!input) return;
    var value = normalize(input.value).replace(/[^0-9]/g, '');
    input.value = value === '' ? '' : Number(value).toLocaleString('fa-IR');
  }
  function bindMoneyInput(input) {
    if (!input || input.dataset.moneyReady === '1') return;
    input.dataset.moneyReady = '1';
    formatMoneyInput(input);
    input.addEventListener('focus', function () {
      if (money(input) === 0) input.value = '';
    });
    input.addEventListener('input', function () { formatMoneyInput(input); });
    input.addEventListener('blur', function () {
      if (normalize(input.value).replace(/[^0-9]/g, '') === '') input.value = Number(0).toLocaleString('fa-IR');
    });
  }
  function bindQuantityInput(input) {
    if (!input || input.dataset.quantityReady === '1') return;
    input.dataset.quantityReady = '1';
    input.value = formatQuantity(input.value) || '1';
    input.addEventListener('focus', function () {
      input.dataset.quantityBeforeEdit = input.value;
      input.value = '';
      input.setCustomValidity('');
    });
    input.addEventListener('input', function () {
      var cleaned = normalizeQuantity(input.value);
      if (input.value !== cleaned) input.value = cleaned;
      input.setCustomValidity('');
    });
    input.addEventListener('blur', function () {
      var formatted = formatQuantity(input.value);
      if (formatted === '') formatted = formatQuantity(input.dataset.quantityBeforeEdit) || '1';
      input.value = formatted;
      delete input.dataset.quantityBeforeEdit;
      calculate();
    });
  }
  function setupCustomerLookup() {
    var config = window.ziguratInvoiceConfig || {};
    var nameInput = editor.querySelector('[name="customer_name"]');
    if (!nameInput || !config.ajaxUrl || !config.customerNonce) return;
    var field = nameInput.parentElement;
    var listId = 'invoice-customer-list-' + Math.random().toString(36).slice(2);
    var customerList = document.createElement('div');
    var customerHint = document.createElement('small');
    field.classList.add('invoice-customer-lookup');
    customerList.id = listId;
    customerList.className = 'invoice-customer-suggestions';
    customerList.setAttribute('role', 'listbox');
    customerList.hidden = true;
    customerHint.className = 'invoice-customer-autofill-hint';
    customerHint.hidden = true;
    nameInput.setAttribute('autocomplete', 'off');
    nameInput.setAttribute('aria-autocomplete', 'list');
    nameInput.setAttribute('aria-controls', listId);
    nameInput.setAttribute('aria-expanded', 'false');
    field.appendChild(customerList);
    field.appendChild(customerHint);
    var timer = null;
    var requestId = 0;
    var matches = [];
    var activeIndex = -1;
    var fieldMap = ['national_id','economic_no','province','county','city','postal_code','address','phone'];

    function applyCustomer(customer) {
      nameInput.value = customer.customer_name || nameInput.value;
      fieldMap.forEach(function (key) {
        var input = editor.querySelector('[name="customer_' + key + '"]');
        if (input) input.value = customer['customer_' + key] || '';
      });
      nameInput.dataset.customerLoaded = customer.customer_name || '';
      invoiceHasUnsavedChanges = true;
      matches = [];
      customerList.innerHTML = '';
      closeSuggestions();
      nameInput.focus({preventScroll:true});
    }
    function closeSuggestions() {
      customerList.hidden = true;
      customerHint.hidden = true;
      nameInput.setAttribute('aria-expanded', 'false');
      nameInput.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    }
    function updateActive(index) {
      var options = customerList.querySelectorAll('[role="option"]');
      if (!options.length) return;
      activeIndex = Math.max(0, Math.min(options.length - 1, index));
      options.forEach(function (option, optionIndex) {
        var active = optionIndex === activeIndex;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      var activeOption = options[activeIndex];
      nameInput.setAttribute('aria-activedescendant', activeOption.id);
      activeOption.scrollIntoView({block:'nearest'});
      var activeCustomer = matches[activeIndex];
      customerHint.textContent = activeCustomer ? 'پیشنهاد: ' + activeCustomer.customer_name + ' — برای تکمیل اطلاعات Enter بزنید' : '';
      customerHint.hidden = !activeCustomer;
    }
    function render(items) {
      matches = Array.isArray(items) ? items : [];
      customerList.innerHTML = '';
      matches.forEach(function (customer, index) {
        var option = document.createElement('button');
        var meta = [customer.customer_phone, customer.customer_city].filter(Boolean).join(' • ');
        option.type = 'button';
        option.id = listId + '-option-' + index;
        option.className = 'invoice-customer-suggestion';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.innerHTML = '<strong></strong><small></small>';
        option.querySelector('strong').textContent = customer.customer_name || '';
        option.querySelector('small').textContent = meta || 'اطلاعات مشتری قبلی';
        option.addEventListener('mousedown', function (event) { event.preventDefault(); });
        option.addEventListener('click', function () { applyCustomer(customer); });
        customerList.appendChild(option);
      });
      if (!matches.length) {
        closeSuggestions();
        return;
      }
      customerList.hidden = false;
      nameInput.setAttribute('aria-expanded', 'true');
      updateActive(0);
    }
    function lookup() {
      var term = nameInput.value.trim();
      if (term.length < 2) { matches = []; customerList.innerHTML = ''; closeSuggestions(); return; }
      var currentRequest = ++requestId;
      var url = config.ajaxUrl + '?action=zigurat_invoice_customer_lookup&nonce=' + encodeURIComponent(config.customerNonce) + '&term=' + encodeURIComponent(term);
      fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (response) {
          if (currentRequest !== requestId || !response.success || !Array.isArray(response.data)) return;
          render(response.data);
        })
        .catch(function () { matches = []; customerList.innerHTML = ''; closeSuggestions(); });
    }
    nameInput.addEventListener('input', function () {
      delete nameInput.dataset.customerLoaded;
      matches = [];
      customerList.innerHTML = '';
      closeSuggestions();
      clearTimeout(timer);
      timer = setTimeout(lookup, 350);
    });
    nameInput.addEventListener('keydown', function (event) {
      if (customerList.hidden || !matches.length) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        updateActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        updateActive(activeIndex - 1);
      } else if (event.key === 'Enter') {
        event.preventDefault();
        event.stopPropagation();
        if (matches[activeIndex]) applyCustomer(matches[activeIndex]);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        closeSuggestions();
      }
    });
    nameInput.addEventListener('focus', function () {
      if (matches.length && nameInput.value.trim().length >= 2) {
        customerList.hidden = false;
        customerHint.hidden = false;
        nameInput.setAttribute('aria-expanded', 'true');
      }
    });
    nameInput.addEventListener('blur', function () {
      window.setTimeout(closeSuggestions, 160);
    });
  }
  function setupBranchNumberPreview() {
    var toggle = editor.querySelector('[name="allow_branches"]');
    var numberWrap = editor.querySelector('[data-invoice-number]');
    var numberValue = numberWrap && numberWrap.querySelector('[data-invoice-number-value]');
    if (!toggle || !numberWrap || !numberValue) return;
    var baseNumber = String(numberWrap.dataset.baseNumber || '').trim();
    function updateNumberPreview() {
      if (baseNumber) {
        numberValue.textContent = toggle.checked ? baseNumber + '/1' : baseNumber;
      } else {
        numberValue.textContent = toggle.checked ? 'خودکار با پسوند /1' : 'خودکار پس از ذخیره';
      }
    }
    toggle.addEventListener('change', updateNumberPreview);
    updateNumberPreview();
  }
  function calculate() {
    var subtotal = 0;
    body.querySelectorAll('tr').forEach(function (row) {
      var total = Math.max(0, Math.round(quantity(row.querySelector('[name="item_quantity[]"]')) * money(row.querySelector('[name="item_unit_price[]"]'))) - money(row.querySelector('[name="item_discount[]"]')));
      subtotal += total;
      var output = row.querySelector('[data-line-total]');
      if (output) output.textContent = format(total);
    });
    var discount = Math.min(subtotal, money(editor.querySelector('[name="discount"]')));
    var shipping = money(editor.querySelector('[name="shipping"]'));
    var overheadInput = editor.querySelector('[name="overhead_rate"]');
    var insuranceInput = editor.querySelector('[name="insurance_rate"]');
    var taxInput = editor.querySelector('[name="tax_rate"]');
    var overheadRate = Math.max(0, Math.min(100, parseFloat(normalize(overheadInput && overheadInput.value)) || 0));
    var insuranceRate = Math.max(0, Math.min(100, parseFloat(normalize(insuranceInput && insuranceInput.value)) || 0));
    var taxRate = Math.max(0, Math.min(100, parseFloat(normalize(taxInput && taxInput.value)) || 0));
    var baseAmount = Math.max(0, subtotal - discount + shipping);
    var overhead = Math.round(baseAmount * overheadRate / 100);
    var amountWithOverhead = baseAmount + overhead;
    var insurance = Math.round(amountWithOverhead * insuranceRate / 100);
    var taxable = amountWithOverhead + insurance;
    var tax = Math.round(taxable * taxRate / 100);
    var grand = taxable + tax;
    var balance = Math.max(0, grand - money(editor.querySelector('[name="paid_amount"]')));
    editor.querySelector('[data-subtotal]').textContent = format(baseAmount);
    var overheadOutput = editor.querySelector('[data-overhead]');
    var insuranceOutput = editor.querySelector('[data-insurance]');
    if (overheadOutput) overheadOutput.textContent = format(overhead);
    if (insuranceOutput) insuranceOutput.textContent = format(insurance);
    editor.querySelector('[data-tax]').textContent = format(tax);
    editor.querySelector('[data-grand-total]').textContent = format(grand);
    editor.querySelector('[data-balance]').textContent = format(balance);
  }
  function updateRowNumbers() {
    body.querySelectorAll('tr').forEach(function (row, index) {
      var output = row.querySelector('[data-row-number]');
      var handle = row.querySelector('[data-row-drag-handle]');
      var number = index + 1;
      if (output) output.textContent = number.toLocaleString('fa-IR');
      if (handle) handle.setAttribute('aria-label', 'جابجایی ردیف ' + number.toLocaleString('fa-IR'));
    });
  }
  function moveRow(row, direction) {
    if (direction < 0 && row.previousElementSibling) {
      body.insertBefore(row, row.previousElementSibling);
    } else if (direction > 0 && row.nextElementSibling) {
      body.insertBefore(row.nextElementSibling, row);
    } else {
      return;
    }
    invoiceHasUnsavedChanges = true;
    updateRowNumbers();
    row.querySelector('[data-row-drag-handle]').focus({preventScroll:true});
  }
  var nativeDraggedRow = null;
  var nativeRowMoved = false;
  function finishNativeRowSorting() {
    if (!nativeDraggedRow) return;
    nativeDraggedRow.classList.remove('is-row-dragging');
    var handle = nativeDraggedRow.querySelector('[data-row-drag-handle]');
    if (handle) handle.setAttribute('aria-grabbed', 'false');
    document.body.classList.remove('invoice-row-sorting');
    if (nativeRowMoved) {
      invoiceHasUnsavedChanges = true;
      updateRowNumbers();
    }
    nativeDraggedRow = null;
    nativeRowMoved = false;
  }
  body.addEventListener('dragover', function (event) {
    if (!nativeDraggedRow) return;
    event.preventDefault();
    var targetRow = event.target.closest('[data-invoice-items] > tr');
    if (!targetRow || targetRow === nativeDraggedRow || targetRow.parentElement !== body) return;
    var rectangle = targetRow.getBoundingClientRect();
    if (event.clientY < rectangle.top + rectangle.height / 2) {
      body.insertBefore(nativeDraggedRow, targetRow);
    } else {
      body.insertBefore(nativeDraggedRow, targetRow.nextElementSibling);
    }
    nativeRowMoved = true;
    updateRowNumbers();
  });
  body.addEventListener('drop', function (event) {
    if (!nativeDraggedRow) return;
    event.preventDefault();
    finishNativeRowSorting();
  });
  function bindRowSorting(row) {
    var handle = row.querySelector('[data-row-drag-handle]');
    if (!handle || handle.dataset.rowSortReady === '1') return;
    handle.dataset.rowSortReady = '1';
    var pointerId = null;
    var startY = 0;
    var moved = false;
    handle.draggable = true;
    handle.setAttribute('aria-grabbed', 'false');

    handle.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
      event.preventDefault();
      moveRow(row, event.key === 'ArrowUp' ? -1 : 1);
    });
    handle.addEventListener('dragstart', function (event) {
      nativeDraggedRow = row;
      nativeRowMoved = false;
      row.classList.add('is-row-dragging');
      document.body.classList.add('invoice-row-sorting');
      handle.setAttribute('aria-grabbed', 'true');
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'invoice-row');
      }
    });
    handle.addEventListener('dragend', finishNativeRowSorting);
    handle.addEventListener('pointerdown', function (event) {
      if (event.button !== 0 || event.pointerType === 'mouse') return;
      pointerId = event.pointerId;
      startY = event.clientY;
      moved = false;
      handle.setPointerCapture(pointerId);
      event.preventDefault();
    });
    handle.addEventListener('pointermove', function (event) {
      if (pointerId !== event.pointerId) return;
      if (!moved && Math.abs(event.clientY - startY) < 5) return;
      if (!moved) {
        moved = true;
        row.classList.add('is-row-dragging');
        document.body.classList.add('invoice-row-sorting');
      }
      var target = document.elementFromPoint(event.clientX, event.clientY);
      var targetRow = target && target.closest('[data-invoice-items] > tr');
      if (!targetRow || targetRow === row || targetRow.parentElement !== body) return;
      var rectangle = targetRow.getBoundingClientRect();
      if (event.clientY < rectangle.top + rectangle.height / 2) {
        body.insertBefore(row, targetRow);
      } else {
        body.insertBefore(row, targetRow.nextElementSibling);
      }
      updateRowNumbers();
      if (event.clientY < 70) window.scrollBy(0, -12);
      else if (event.clientY > window.innerHeight - 70) window.scrollBy(0, 12);
    });
    function finishSorting(event) {
      if (pointerId === null || (event && event.pointerId !== pointerId)) return;
      if (handle.hasPointerCapture(pointerId)) handle.releasePointerCapture(pointerId);
      pointerId = null;
      row.classList.remove('is-row-dragging');
      document.body.classList.remove('invoice-row-sorting');
      if (moved) {
        invoiceHasUnsavedChanges = true;
        updateRowNumbers();
      }
      moved = false;
    }
    handle.addEventListener('pointerup', finishSorting);
    handle.addEventListener('pointercancel', finishSorting);
  }
  function bindRow(row) {
    row.querySelectorAll('[data-money]').forEach(bindMoneyInput);
    row.querySelectorAll('[data-quantity]').forEach(bindQuantityInput);
    row.querySelectorAll('input,textarea').forEach(function (input) { input.addEventListener('input', calculate); });
    bindRowSorting(row);
    row.querySelector('[data-remove-item]').addEventListener('click', function () {
      if (body.querySelectorAll('tr').length > 1) row.remove();
      else row.querySelectorAll('input,textarea').forEach(function (input) { input.value = input.name === 'item_quantity[]' ? '1' : ''; });
      invoiceHasUnsavedChanges = true;
      updateRowNumbers();
      calculate();
    });
  }
  editor.querySelector('[data-add-item]').addEventListener('click', function () {
    var row = document.createElement('tr');
    row.innerHTML = '<td class="invoice-row-order"><button type="button" data-row-drag-handle aria-label="جابجایی ردیف"><span data-row-number></span><i aria-hidden="true"></i></button></td><td><textarea name="item_description[]" rows="2" required></textarea></td><td><input name="item_quantity[]" type="text" inputmode="decimal" value="1" data-quantity required aria-label="مقدار"></td><td><input name="item_unit[]" value="عدد" data-clear-on-focus></td><td><input name="item_unit_price[]" type="text" inputmode="numeric" value="0" data-money data-clear-on-focus required></td><td><input name="item_discount[]" type="text" inputmode="numeric" value="0" data-money></td><td data-line-total>۰ ریال</td><td><button type="button" data-remove-item aria-label="حذف ردیف">×</button></td>';
    body.appendChild(row);
    invoiceHasUnsavedChanges = true;
    bindRow(row);
    updateRowNumbers();
    row.querySelector('textarea').focus();
    calculate();
  });
  body.querySelectorAll('tr').forEach(bindRow);
  updateRowNumbers();
  editor.querySelectorAll('[data-money]').forEach(bindMoneyInput);
  editor.querySelectorAll('input[name="discount"],input[name="shipping"],input[name="overhead_rate"],input[name="insurance_rate"],input[name="tax_rate"],input[name="paid_amount"]').forEach(function (input) { input.addEventListener('input', calculate); });
  setupCustomerLookup();
  setupBranchNumberPreview();
  calculate();
  }
  document.addEventListener('DOMContentLoaded', function () { initializeInvoice(document); });
  document.addEventListener('zigurat:panel-updated', function (event) { initializeInvoice(event.detail && event.detail.root ? event.detail.root : document); });
}());
