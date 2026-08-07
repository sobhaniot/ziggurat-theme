document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var editor = document.querySelector('[data-invoice-editor]');
  if (!editor) return;
  var submitWasClicked = false;
  editor.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
      event.preventDefault();
    }
  });
  editor.addEventListener('click', function (event) {
    var submitButton = event.target.closest('button[type="submit"], input[type="submit"]');
    if (!submitButton) return;
    submitWasClicked = true;
    window.setTimeout(function () { submitWasClicked = false; }, 0);
  });
  editor.addEventListener('submit', function (event) {
    if (!submitWasClicked) {
      event.preventDefault();
      return;
    }
    submitWasClicked = false;
  });
  var body = editor.querySelector('[data-invoice-items]');
  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  function normalize(value) { return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit] || digit; }); }
  function money(input) { return Math.max(0, parseInt(normalize(input && input.value).replace(/[^0-9]/g, ''), 10) || 0); }
  function quantity(input) { return Math.max(0, parseFloat(normalize(input && input.value).replace(',', '.')) || 0); }
  function format(value) { return Math.round(value).toLocaleString('fa-IR') + ' ریال'; }
  function setupCustomerLookup() {
    var config = window.ziguratInvoiceConfig || {};
    var nameInput = editor.querySelector('[name="customer_name"]');
    if (!nameInput || !config.ajaxUrl || !config.customerNonce) return;
    var customerList = document.createElement('datalist');
    customerList.id = 'invoice-customer-history';
    nameInput.setAttribute('list', customerList.id);
    nameInput.setAttribute('autocomplete', 'off');
    nameInput.parentElement.appendChild(customerList);
    var timer = null;
    var requestId = 0;
    var fieldMap = ['national_id','economic_no','province','county','city','postal_code','address','phone'];

    function applyCustomer(customer) {
      nameInput.value = customer.customer_name || nameInput.value;
      fieldMap.forEach(function (key) {
        var input = editor.querySelector('[name="customer_' + key + '"]');
        if (input) input.value = customer['customer_' + key] || '';
      });
      nameInput.dataset.customerLoaded = customer.customer_name || '';
    }
    function render(items) {
      customerList.innerHTML = '';
      items.forEach(function (customer) {
        var option = document.createElement('option');
        option.value = customer.customer_name || '';
        option.label = customer.customer_phone || customer.customer_city || '';
        customerList.appendChild(option);
      });
    }
    function lookup() {
      var term = nameInput.value.trim();
      if (term.length < 2) { customerList.innerHTML = ''; return; }
      var currentRequest = ++requestId;
      var url = config.ajaxUrl + '?action=zigurat_invoice_customer_lookup&nonce=' + encodeURIComponent(config.customerNonce) + '&term=' + encodeURIComponent(term);
      fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (response) {
          if (currentRequest !== requestId || !response.success || !Array.isArray(response.data)) return;
          render(response.data);
          var exact = response.data.find(function (customer) { return String(customer.customer_name).trim() === term; });
          if (exact && nameInput.dataset.customerLoaded !== exact.customer_name) applyCustomer(exact);
        })
        .catch(function () { customerList.innerHTML = ''; });
    }
    nameInput.addEventListener('input', function () {
      delete nameInput.dataset.customerLoaded;
      clearTimeout(timer);
      timer = setTimeout(lookup, 350);
    });
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
    var rate = Math.max(0, parseFloat(normalize(editor.querySelector('[name="tax_rate"]').value)) || 0);
    var taxable = Math.max(0, subtotal - discount + shipping);
    var tax = Math.round(taxable * rate / 100);
    var grand = taxable + tax;
    var balance = Math.max(0, grand - money(editor.querySelector('[name="paid_amount"]')));
    editor.querySelector('[data-subtotal]').textContent = format(subtotal);
    editor.querySelector('[data-tax]').textContent = format(tax);
    editor.querySelector('[data-grand-total]').textContent = format(grand);
    editor.querySelector('[data-balance]').textContent = format(balance);
  }
  function bindRow(row) {
    row.querySelectorAll('input,textarea').forEach(function (input) { input.addEventListener('input', calculate); });
    row.querySelector('[data-remove-item]').addEventListener('click', function () {
      if (body.querySelectorAll('tr').length > 1) row.remove();
      else row.querySelectorAll('input,textarea').forEach(function (input) { input.value = input.name === 'item_quantity[]' ? '1' : ''; });
      calculate();
    });
  }
  editor.querySelector('[data-add-item]').addEventListener('click', function () {
    var row = document.createElement('tr');
    row.innerHTML = '<td><textarea name="item_description[]" rows="2" required></textarea></td><td><input name="item_quantity[]" type="number" min="0.001" step="0.001" value="1" required></td><td><input name="item_unit[]" value="عدد"></td><td><input name="item_unit_price[]" type="text" inputmode="numeric" value="0" data-money required></td><td><input name="item_discount[]" type="text" inputmode="numeric" value="0" data-money></td><td data-line-total>۰ ریال</td><td><button type="button" data-remove-item aria-label="حذف ردیف">×</button></td>';
    body.appendChild(row);
    bindRow(row);
    row.querySelector('textarea').focus();
    calculate();
  });
  body.querySelectorAll('tr').forEach(bindRow);
  editor.querySelectorAll('input[name="discount"],input[name="shipping"],input[name="tax_rate"],input[name="paid_amount"]').forEach(function (input) { input.addEventListener('input', calculate); });
  setupCustomerLookup();
  calculate();
});
