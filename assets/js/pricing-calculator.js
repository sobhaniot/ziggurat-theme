(function () {
  'use strict';
  var form = document.querySelector('[data-lightbox-calculator]');
  if (!form) return;

  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  function normalize(value) { return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit] || digit; }); }
  function decimal(value) { return Math.max(0, parseFloat(normalize(value).replace(',', '.').replace(/[^0-9.]/g, '')) || 0); }
  function money(value) { return Math.max(0, parseInt(normalize(value).replace(/[^0-9]/g, ''), 10) || 0); }
  function formatMoney(value) { return Math.round(value).toLocaleString('fa-IR') + ' ریال'; }
  function formatMoneyInput(input) {
    var normalized = normalize(input.value).replace(/[^0-9]/g, '');
    input.value = normalized === '' ? '' : Number(normalized).toLocaleString('fa-IR');
  }
  function formatMeasure(value) { return Number(value.toFixed(3)).toLocaleString('fa-IR', { maximumFractionDigits: 3 }); }
  function setText(selector, value) { var node = form.querySelector(selector); if (node) node.textContent = value; }
  function field(name) { return form.elements.namedItem(name); }
  var costNames = ['installation', 'travel', 'supplies', 'transformer'];
  var saveTimer = null;

  function saveLastCosts() {
    if (!form.dataset.ajaxUrl || !form.dataset.costsNonce) return;
    var body = new URLSearchParams({
      action: 'zigurat_save_lightbox_last_costs',
      nonce: form.dataset.costsNonce,
      installation: money(field('installation').value),
      travel: money(field('travel').value),
      supplies: money(field('supplies').value),
      transformer: money(field('transformer').value)
    });
    fetch(form.dataset.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).catch(function () {});
  }

  function scheduleCostsSave() {
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(saveLastCosts, 500);
  }

  function calculate(shouldFocus) {
    var length = decimal(field('length').value);
    var width = decimal(field('width').value);
    var error = form.querySelector('[data-pricing-error]');
    if (length <= 0 || width <= 0) {
      error.textContent = 'طول و عرض را با عددی بیشتر از صفر وارد کنید.';
      error.hidden = false;
      return false;
    }
    error.hidden = true;

    var usePerimeter = length < 1.5 || width < 1.5;
    var measure = usePerimeter ? 2 * (length + width) : length * width;
    var rate = money(usePerimeter ? form.dataset.perimeterRate : form.dataset.squareRate);
    var base = Math.round(measure * rate);
    var usePVC = field('use_pvc').checked;
    var pvcRate = usePVC ? money(form.dataset.pvcRate) : 0;
    var pvcCost = Math.round(measure * pvcRate);
    var extras = money(field('installation').value) + money(field('travel').value) + money(field('supplies').value) + money(field('transformer').value);
    var subtotal = base + pvcCost + extras;
    var profitPercent = Math.min(1000, decimal(field('profit_percent').value));
    var profit = Math.round(subtotal * profitPercent / 100);
    var finalPrice = subtotal + profit;

    setText('[data-price-method]', usePerimeter ? 'متر محیط' : 'مترمربع');
    setText('[data-price-measure]', formatMeasure(measure) + (usePerimeter ? ' متر محیط' : ' مترمربع'));
    setText('[data-price-rate]', formatMoney(rate));
    setText('[data-price-base]', formatMoney(base));
    setText('[data-price-pvc]', formatMoney(pvcCost) + (usePVC ? ' (' + formatMoney(pvcRate) + ' × مبنا)' : ' (استفاده نشده)'));
    setText('[data-price-extras]', formatMoney(extras));
    setText('[data-price-subtotal]', formatMoney(subtotal));
    setText('[data-price-profit]', formatMoney(profit) + ' (' + profitPercent.toLocaleString('fa-IR') + '٪)');
    setText('[data-price-final]', formatMoney(finalPrice));
    if (shouldFocus) {
      var result = form.querySelector('[data-pricing-result]');
      result.setAttribute('tabindex', '-1');
      result.focus({ preventScroll: true });
    }
    return true;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (calculate(true)) {
      window.clearTimeout(saveTimer);
      saveLastCosts();
    }
  });
  document.querySelectorAll('[data-money-input]').forEach(function (input) {
    formatMoneyInput(input);
    input.addEventListener('input', function () {
      formatMoneyInput(input);
      if (form.contains(input) && costNames.indexOf(input.name) !== -1) scheduleCostsSave();
    });
  });
  form.querySelectorAll('input').forEach(function (input) {
    input.addEventListener('input', function () {
      if (decimal(field('length').value) > 0 && decimal(field('width').value) > 0) calculate(false);
    });
  });
}());
