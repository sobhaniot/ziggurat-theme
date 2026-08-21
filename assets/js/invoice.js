(function () {
  'use strict';
  var invoiceHasUnsavedChanges = false;
  var bypassUnsavedWarning = false;
  var approvedLinkNavigation = false;
  var approvedHistoryNavigation = false;
  var leaveDialog = null;
  var pendingLeaveAction = null;
  var leaveDialogLastFocus = null;

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
    (root || document).querySelectorAll('.invoice-list-filters select[name="filter_type"], .invoice-list-filters select[name="filter_status"]').forEach(function (select) {
      if (select.dataset.autoFilterReady === '1') return;
      select.dataset.autoFilterReady = '1';
      select.addEventListener('change', function () {
        var form = select.form;
        if (!form) return;
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.dispatchEvent(new Event('submit', {bubbles:true, cancelable:true}));
        }
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

  function initializeInvoice(root) {
  bypassUnsavedWarning = false;
  approvedLinkNavigation = false;
  approvedHistoryNavigation = false;
  if (leaveDialog && !leaveDialog.hidden) closeLeaveDialog(false);
  setupInvoiceListAutoFilters(root);
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
      input.min = isSubmitting ? '0.001' : '0';
      input.step = isSubmitting ? 'any' : '1';
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
  function quantity(input) { return Math.max(0, parseFloat(normalize(input && input.value).replace(',', '.')) || 0); }
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
  function bindZeroClearingRate(input) {
    if (!input || input.dataset.zeroClearingReady === '1') return;
    input.dataset.zeroClearingReady = '1';
    input.addEventListener('focus', function () {
      if ((parseFloat(normalize(input.value)) || 0) === 0) input.value = '';
    });
    input.addEventListener('blur', function () {
      if (String(input.value || '').trim() === '') {
        input.value = '0';
        input.dispatchEvent(new Event('input', {bubbles:true}));
      }
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
    editor.querySelector('[data-subtotal]').textContent = format(subtotal);
    var overheadOutput = editor.querySelector('[data-overhead]');
    var insuranceOutput = editor.querySelector('[data-insurance]');
    if (overheadOutput) overheadOutput.textContent = format(overhead);
    if (insuranceOutput) insuranceOutput.textContent = format(insurance);
    editor.querySelector('[data-tax]').textContent = format(tax);
    editor.querySelector('[data-grand-total]').textContent = format(grand);
    editor.querySelector('[data-balance]').textContent = format(balance);
  }
  function bindRow(row) {
    row.querySelectorAll('[data-money]').forEach(bindMoneyInput);
    row.querySelectorAll('input,textarea').forEach(function (input) { input.addEventListener('input', calculate); });
    row.querySelector('[data-remove-item]').addEventListener('click', function () {
      if (body.querySelectorAll('tr').length > 1) row.remove();
      else row.querySelectorAll('input,textarea').forEach(function (input) { input.value = input.name === 'item_quantity[]' ? '1' : ''; });
      invoiceHasUnsavedChanges = true;
      calculate();
    });
  }
  editor.querySelector('[data-add-item]').addEventListener('click', function () {
    var row = document.createElement('tr');
    row.innerHTML = '<td><textarea name="item_description[]" rows="2" required></textarea></td><td><input name="item_quantity[]" type="number" min="0" step="1" value="1" required></td><td><input name="item_unit[]" value="عدد"></td><td><input name="item_unit_price[]" type="text" inputmode="numeric" value="0" data-money required></td><td><input name="item_discount[]" type="text" inputmode="numeric" value="0" data-money></td><td data-line-total>۰ ریال</td><td><button type="button" data-remove-item aria-label="حذف ردیف">×</button></td>';
    body.appendChild(row);
    invoiceHasUnsavedChanges = true;
    bindRow(row);
    row.querySelector('textarea').focus();
    calculate();
  });
  body.querySelectorAll('tr').forEach(bindRow);
  editor.querySelectorAll('[data-money]').forEach(bindMoneyInput);
  editor.querySelectorAll('input[name="overhead_rate"],input[name="insurance_rate"]').forEach(bindZeroClearingRate);
  editor.querySelectorAll('input[name="discount"],input[name="shipping"],input[name="overhead_rate"],input[name="insurance_rate"],input[name="tax_rate"],input[name="paid_amount"]').forEach(function (input) { input.addEventListener('input', calculate); });
  setupCustomerLookup();
  setupBranchNumberPreview();
  calculate();
  }
  document.addEventListener('DOMContentLoaded', function () { initializeInvoice(document); });
  document.addEventListener('zigurat:panel-updated', function (event) { initializeInvoice(event.detail && event.detail.root ? event.detail.root : document); });
}());
