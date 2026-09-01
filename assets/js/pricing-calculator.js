(function () {
  'use strict';

  var pricingSection = document.querySelector('.manager-pricing');
  if (!pricingSection) return;

  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  function normalize(value) { return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit] || digit; }); }
  function decimal(value) { return Math.max(0, parseFloat(normalize(value).replace(/[،٫,]/g, '.').replace(/[^0-9.]/g, '')) || 0); }
  function money(value) { return Math.max(0, parseInt(normalize(value).replace(/[^0-9]/g, ''), 10) || 0); }
  function formatMoney(value) { return Math.round(value).toLocaleString('fa-IR') + ' ریال'; }
  function formatMoneyInput(input) {
    var normalized = normalize(input.value).replace(/[^0-9]/g, '');
    input.value = normalized === '' ? '' : Number(normalized).toLocaleString('fa-IR');
  }
  function formatMeasure(value) { return Number(value.toFixed(3)).toLocaleString('fa-IR', { maximumFractionDigits: 3 }); }

  pricingSection.querySelectorAll('[data-money-input]').forEach(function (input) {
    formatMoneyInput(input);
    input.addEventListener('input', function () { formatMoneyInput(input); });
  });

  pricingSection.querySelectorAll('input[type="text"]').forEach(function (input) {
    input.addEventListener('focus', function () {
      if (input.dataset.pricingEditing === '1') return;
      input.dataset.pricingEditing = '1';
      input.dataset.pricingPreviousValue = input.value;
      input.value = '';
    });
    input.addEventListener('blur', function () {
      if (input.value.trim() === '' && input.dataset.pricingPreviousValue !== undefined) {
        input.value = input.dataset.pricingPreviousValue;
      }
      delete input.dataset.pricingEditing;
      delete input.dataset.pricingPreviousValue;
    });
  });

  function setupRatesAutosave(options) {
    var ratesForm = document.querySelector('[data-pricing-rates-form="' + options.kind + '"]');
    if (!ratesForm) return;
    var status = ratesForm.querySelector('[data-pricing-rates-status]');
    var timer = null;
    var saving = false;
    var queued = false;

    function showStatus(message, state) {
      if (!status) return;
      status.textContent = message;
      status.classList.toggle('is-saving', state === 'saving');
      status.classList.toggle('is-error', state === 'error');
      status.classList.toggle('is-saved', state === 'saved');
    }

    function saveRates() {
      window.clearTimeout(timer);
      if (saving) {
        queued = true;
        return;
      }
      saving = true;
      showStatus('در حال ذخیره نرخ‌ها…', 'saving');
      var body = new URLSearchParams({ action: options.action, nonce: ratesForm.dataset.ratesNonce || '' });
      options.fields.forEach(function (name) {
        var inputValue = ratesForm.elements.namedItem(name).value;
        var normalizer = options.normalizers && options.normalizers[name] ? options.normalizers[name] : money;
        body.append(name, normalizer(inputValue));
      });
      fetch(ratesForm.dataset.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      }).then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload.success) throw new Error('save_failed');
          Object.keys(options.datasetMap).forEach(function (fieldName) {
            if (options.calculator && payload.data[fieldName] !== undefined) {
              options.calculator.dataset[options.datasetMap[fieldName]] = payload.data[fieldName];
            }
          });
          showStatus('نرخ‌ها ذخیره شدند.', 'saved');
          if (typeof options.afterSave === 'function') options.afterSave();
        });
      }).catch(function () {
        showStatus('ذخیره نرخ‌ها انجام نشد؛ دوباره تلاش کنید.', 'error');
      }).finally(function () {
        saving = false;
        if (queued) {
          queued = false;
          saveRates();
        }
      });
    }

    function scheduleSave() {
      window.clearTimeout(timer);
      timer = window.setTimeout(saveRates, 500);
    }

    ratesForm.addEventListener('submit', function (event) {
      event.preventDefault();
      saveRates();
    });
    options.fields.forEach(function (name) {
      var input = ratesForm.elements.namedItem(name);
      input.addEventListener('input', function () {
        if (input.value.trim() !== '') scheduleSave();
      });
      input.addEventListener('change', function () {
        if (input.value.trim() !== '') saveRates();
      });
    });
  }

  function initLightbox() {
    var form = document.querySelector('[data-lightbox-calculator]');
    if (!form) return;
    function field(name) { return form.elements.namedItem(name); }
    function setText(selector, value) { var node = form.querySelector(selector); if (node) node.textContent = value; }
    var costNames = ['installation', 'travel', 'supplies', 'transformer'];
    var saveTimer = null;

    function saveLastCosts() {
      window.clearTimeout(saveTimer);
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
        if (shouldFocus) {
          error.textContent = 'طول و عرض را با عددی بیشتر از صفر وارد کنید.';
          error.hidden = false;
        }
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

    setupRatesAutosave({
      kind: 'lightbox',
      action: 'zigurat_save_lightbox_rates',
      fields: ['perimeter_rate', 'square_rate', 'pvc_rate'],
      calculator: form,
      datasetMap: { perimeter_rate: 'perimeterRate', square_rate: 'squareRate', pvc_rate: 'pvcRate' },
      afterSave: function () { calculate(false); }
    });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      calculate(true);
    });
    form.querySelectorAll('input').forEach(function (input) {
      input.addEventListener('input', function () { calculate(false); });
      input.addEventListener('change', function () { calculate(false); });
      if (costNames.indexOf(input.name) !== -1) {
        input.addEventListener('input', function () {
          if (input.value.trim() !== '') scheduleCostsSave();
        });
        input.addEventListener('change', function () {
          if (input.value.trim() !== '') saveLastCosts();
        });
      }
    });
  }

  function initComposite() {
    var form = document.querySelector('[data-composite-calculator]');
    if (!form) return;
    function field(name) { return form.elements.namedItem(name); }
    function setText(selector, value) { var node = form.querySelector(selector); if (node) node.textContent = value; }
    var saveTimer = null;

    function saveLastValues() {
      window.clearTimeout(saveTimer);
      if (!form.dataset.ajaxUrl || !form.dataset.valuesNonce) return;
      var body = new URLSearchParams({
        action: 'zigurat_save_composite_last_values',
        nonce: form.dataset.valuesNonce,
        freight: money(field('freight').value),
        bracing_cost: money(field('bracing_cost').value),
        profit_percent: decimal(field('profit_percent').value),
        insurance_percent: decimal(field('insurance_percent').value),
        tax_percent: decimal(field('tax_percent').value)
      });
      fetch(form.dataset.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      }).catch(function () {});
    }

    function scheduleValuesSave() {
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(saveLastValues, 500);
    }

    function calculate(shouldFocus) {
      var length = decimal(field('length').value);
      var width = decimal(field('width').value);
      var error = form.querySelector('[data-composite-error]');
      if (length <= 0 || width <= 0) {
        if (shouldFocus) {
          error.textContent = 'طول و ارتفاع را با عددی بیشتر از صفر وارد کنید.';
          error.hidden = false;
        }
        return false;
      }
      error.hidden = true;
      var area = length * width;
      var ironCost = Math.round(area * money(form.dataset.ironRate));
      var compositeCost = Math.round(area * money(form.dataset.compositeRate));
      var installerCost = Math.round(area * money(form.dataset.installerRate));
      var suppliesCost = Math.round(area * money(form.dataset.suppliesRate));
      var freight = money(field('freight').value);
      var bracingCost = money(field('bracing_cost').value);
      var baseTotal = ironCost + compositeCost + installerCost + suppliesCost + freight + bracingCost;
      var profitPercent = Math.min(1000, decimal(field('profit_percent').value));
      var profitAmount = Math.round(baseTotal * profitPercent / 100);
      var afterProfit = baseTotal + profitAmount;
      var insurancePercent = Math.min(1000, decimal(field('insurance_percent').value));
      var insuranceAmount = Math.round(afterProfit * insurancePercent / 100);
      var afterInsurance = afterProfit + insuranceAmount;
      var taxPercent = Math.min(1000, decimal(field('tax_percent').value));
      var taxAmount = Math.round(afterInsurance * taxPercent / 100);
      var finalPrice = afterInsurance + taxAmount;
      setText('[data-composite-area]', formatMeasure(area) + ' مترمربع');
      setText('[data-composite-iron]', formatMoney(ironCost));
      setText('[data-composite-sheet]', formatMoney(compositeCost));
      setText('[data-composite-installer]', formatMoney(installerCost));
      setText('[data-composite-supplies]', formatMoney(suppliesCost));
      setText('[data-composite-freight]', formatMoney(freight));
      setText('[data-composite-bracing]', formatMoney(bracingCost));
      setText('[data-composite-base]', formatMoney(baseTotal));
      setText('[data-composite-profit]', formatMoney(profitAmount) + ' (' + profitPercent.toLocaleString('fa-IR') + '٪)');
      setText('[data-composite-insurance]', insurancePercent > 0 ? formatMoney(insuranceAmount) + ' (' + insurancePercent.toLocaleString('fa-IR') + '٪)' : 'محاسبه نشده');
      setText('[data-composite-tax]', taxPercent > 0 ? formatMoney(taxAmount) + ' (' + taxPercent.toLocaleString('fa-IR') + '٪)' : 'محاسبه نشده');
      setText('[data-composite-unit]', formatMoney(finalPrice / area));
      setText('[data-composite-final]', formatMoney(finalPrice));
      if (shouldFocus) {
        var result = form.querySelector('[data-composite-result]');
        result.setAttribute('tabindex', '-1');
        result.focus({ preventScroll: true });
      }
      return true;
    }

    setupRatesAutosave({
      kind: 'composite',
      action: 'zigurat_save_composite_rates',
      fields: ['iron_rate', 'composite_rate', 'installer_rate', 'supplies_rate'],
      calculator: form,
      datasetMap: { iron_rate: 'ironRate', composite_rate: 'compositeRate', installer_rate: 'installerRate', supplies_rate: 'suppliesRate' },
      afterSave: function () { calculate(false); }
    });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (calculate(true)) saveLastValues();
    });
    form.querySelectorAll('input').forEach(function (input) {
      input.addEventListener('input', function () { calculate(false); });
      input.addEventListener('change', function () { calculate(false); });
    });
    ['freight', 'bracing_cost', 'profit_percent', 'insurance_percent', 'tax_percent'].forEach(function (name) {
      var input = field(name);
      input.addEventListener('input', function () {
        if (input.value.trim() !== '') scheduleValuesSave();
      });
      input.addEventListener('change', function () {
        if (input.value.trim() !== '') saveLastValues();
      });
    });
  }

  function initFlexi() {
    var form = document.querySelector('[data-flexi-calculator]');
    if (!form) return;
    function field(name) { return form.elements.namedItem(name); }
    function setText(selector, value) { var node = form.querySelector(selector); if (node) node.textContent = value; }
    function ceilPurchase(value) { return Math.max(1, Math.ceil(value - 0.0000001)); }
    function parseRollWidths(value) {
      var cleaned = normalize(value).replace(/[،؛;|\s]+/g, ',');
      var unique = {};
      cleaned.split(',').forEach(function (part) {
        var width = decimal(part);
        if (width > 0 && width <= 10) unique[width.toFixed(3)] = width;
      });
      return Object.keys(unique).map(function (key) { return unique[key]; }).sort(function (a, b) { return a - b; });
    }
    function materialPlan(length, width, rollWidths) {
      var candidates = [];
      [
        { across: width, run: length, direction: 'length' },
        { across: length, run: width, direction: 'width' }
      ].forEach(function (orientation) {
        rollWidths.forEach(function (rollWidth) {
          var strips = ceilPurchase(orientation.across / rollWidth);
          candidates.push({
            rollWidth: rollWidth,
            strips: strips,
            runLength: orientation.run,
            direction: orientation.direction,
            purchasedArea: strips * rollWidth * orientation.run
          });
        });
      });
      candidates.sort(function (first, second) {
        var stripDifference = first.strips - second.strips;
        return stripDifference !== 0 ? stripDifference : first.purchasedArea - second.purchasedArea;
      });
      return candidates[0];
    }

    var saveTimer = null;
    function saveLastValues() {
      window.clearTimeout(saveTimer);
      if (!form.dataset.ajaxUrl || !form.dataset.valuesNonce) return;
      var body = new URLSearchParams({
        action: 'zigurat_save_flexi_last_values',
        nonce: form.dataset.valuesNonce,
        iron_type: field('iron_type').value,
        iron_branch_weight: decimal(field('iron_branch_weight').value),
        bracing_iron_type: field('bracing_iron_type').value,
        bracing_iron_branch_weight: decimal(field('bracing_iron_branch_weight').value),
        flex_margin_cm: decimal(field('flex_margin_cm').value),
        freight: money(field('freight').value),
        profit_percent: decimal(field('profit_percent').value),
        insurance_percent: decimal(field('insurance_percent').value),
        tax_percent: decimal(field('tax_percent').value)
      });
      fetch(form.dataset.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      }).catch(function () {});
    }
    function scheduleValuesSave() {
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(saveLastValues, 500);
    }

    function calculate(shouldFocus) {
      var length = decimal(field('length').value);
      var width = decimal(field('width').value);
      var rolls = parseRollWidths(form.dataset.rollWidths || '');
      var error = form.querySelector('[data-flexi-error]');
      if (length <= 0 || width <= 0) {
        if (shouldFocus) {
          error.textContent = 'طول و ارتفاع را با عددی بیشتر از صفر وارد کنید.';
          error.hidden = false;
        }
        return false;
      }
      if (!rolls.length) {
        error.textContent = 'حداقل یک عرض معتبر برای رول فلکسی در نرخ‌های پایه وارد کنید.';
        error.hidden = false;
        return false;
      }
      error.hidden = true;

      var area = length * width;
      var perimeter = 2 * (length + width);
      var flexMarginCm = Math.min(10, Math.max(7, decimal(field('flex_margin_cm').value)));
      var flexMarginM = flexMarginCm / 100;
      var flexPrintLength = length + (2 * flexMarginM);
      var flexPrintWidth = width + (2 * flexMarginM);
      var flexPrintArea = flexPrintLength * flexPrintWidth;
      var flexExtraArea = Math.max(0, flexPrintArea - area);
      var plan = materialPlan(flexPrintLength, flexPrintWidth, rolls);
      var flexWaste = Math.max(0, plan.purchasedArea - flexPrintArea);
      var flexCost = Math.round(plan.purchasedArea * money(form.dataset.flexRate));

      var separatorBranchLength = 3;
      var separatorBranches = ceilPurchase(perimeter / separatorBranchLength);
      var separatorPurchased = separatorBranches * separatorBranchLength;
      var separatorWaste = Math.max(0, separatorPurchased - perimeter);
      var separatorCost = Math.round(separatorPurchased * money(form.dataset.separatorRate));

      var coreBranches = ceilPurchase(perimeter / 1.8);
      var corePurchased = coreBranches * 1.8;
      var coreWaste = Math.max(0, corePurchased - perimeter);
      var coreCost = coreBranches * money(form.dataset.coreBranchRate);

      var tapeCost = Math.round(perimeter * money(form.dataset.tapeRate));
      var clipCount = ceilPurchase(perimeter / 0.15);
      var clipCost = clipCount * money(form.dataset.clipRate);
      var coverBranches = ceilPurchase(perimeter / 2.5);
      var coverPurchased = coverBranches * 2.5;
      var coverWaste = Math.max(0, coverPurchased - perimeter);
      var coverCost = coverBranches * money(form.dataset.coverBranchRate);
      var installerCost = Math.round(area * money(form.dataset.installerRate));

      var lengthBraces = length > 1.5 ? Math.max(1, Math.ceil(length - 0.0000001) - 1) : 0;
      var widthBraces = width > 2 ? Math.max(1, Math.ceil((width / 2) - 0.0000001) - 1) : 0;
      var ironLength = perimeter + (lengthBraces * width) + (widthBraces * length);
      var ironBranches = ceilPurchase(ironLength / 6);
      var ironPurchasedLength = ironBranches * 6;
      var ironWaste = Math.max(0, ironPurchasedLength - ironLength);
      var ironBranchWeight = Math.min(200, decimal(field('iron_branch_weight').value));
      var ironWeight = ironBranches * ironBranchWeight;
      var ironCost = Math.round(ironWeight * money(form.dataset.ironPricePerKg));

      var bracingIronLength = Math.max(0, decimal(field('bracing_iron_length').value));
      var bracingIronBranches = bracingIronLength > 0 ? Math.ceil((bracingIronLength / 6) - 0.0000001) : 0;
      var bracingIronPurchasedLength = bracingIronBranches * 6;
      var bracingIronWaste = Math.max(0, bracingIronPurchasedLength - bracingIronLength);
      var bracingIronBranchWeight = Math.min(200, decimal(field('bracing_iron_branch_weight').value));
      var bracingIronWeight = bracingIronBranches * bracingIronBranchWeight;
      var bracingIronCost = Math.round(bracingIronWeight * money(form.dataset.ironPricePerKg));

      var freight = money(field('freight').value);
      var baseTotal = flexCost + separatorCost + coreCost + tapeCost + clipCost + coverCost + installerCost + ironCost + bracingIronCost + freight;
      var profitPercent = Math.min(1000, decimal(field('profit_percent').value));
      var profitAmount = Math.round(baseTotal * profitPercent / 100);
      var afterProfit = baseTotal + profitAmount;
      var insurancePercent = Math.min(1000, decimal(field('insurance_percent').value));
      var insuranceAmount = Math.round(afterProfit * insurancePercent / 100);
      var afterInsurance = afterProfit + insuranceAmount;
      var taxPercent = Math.min(1000, decimal(field('tax_percent').value));
      var taxAmount = Math.round(afterInsurance * taxPercent / 100);
      var finalPrice = afterInsurance + taxAmount;
      var selectedIron = field('iron_type').options[field('iron_type').selectedIndex];
      var ironLabel = selectedIron ? selectedIron.textContent.trim() : 'آهن انتخابی';
      var selectedBracingIron = field('bracing_iron_type').options[field('bracing_iron_type').selectedIndex];
      var bracingIronLabel = selectedBracingIron ? selectedBracingIron.textContent.trim() : 'آهن مهار انتخابی';
      var directionLabel = plan.direction === 'length' ? 'در امتداد طول تابلو' : 'در امتداد ارتفاع تابلو';

      setText('[data-flexi-area]', formatMeasure(area) + ' مترمربع');
      setText('[data-flexi-perimeter]', formatMeasure(perimeter) + ' متر');
      setText('[data-flexi-plan]', 'ابعاد چاپ ' + formatMeasure(flexPrintLength) + ' × ' + formatMeasure(flexPrintWidth) + ' متر با ' + flexMarginCm.toLocaleString('fa-IR') + ' سانتی‌متر اضافه از هر طرف؛ مساحت چاپ ' + formatMeasure(flexPrintArea) + ' مترمربع (' + formatMeasure(flexExtraArea) + ' مترمربع بیشتر از تابلو)؛ ' + plan.strips.toLocaleString('fa-IR') + ' تکه از رول عرض ' + formatMeasure(plan.rollWidth) + ' متر، هرکدام به طول ' + formatMeasure(plan.runLength) + ' متر (' + directionLabel + ')؛ خرید ' + formatMeasure(plan.purchasedArea) + ' مترمربع، پرت رول ' + formatMeasure(flexWaste) + ' مترمربع — ' + formatMoney(flexCost));
      setText('[data-flexi-separator]', separatorBranches.toLocaleString('fa-IR') + ' شاخه ۳ متری، خرید ' + formatMeasure(separatorPurchased) + ' متر، پرت ' + formatMeasure(separatorWaste) + ' متر — ' + formatMoney(separatorCost));
      setText('[data-flexi-core]', coreBranches.toLocaleString('fa-IR') + ' شاخه ۱٫۸ متری، خرید ' + formatMeasure(corePurchased) + ' متر، پرت ' + formatMeasure(coreWaste) + ' متر — ' + formatMoney(coreCost));
      setText('[data-flexi-tape]', formatMeasure(perimeter) + ' متر — ' + formatMoney(tapeCost));
      setText('[data-flexi-clips]', clipCount.toLocaleString('fa-IR') + ' عدد — ' + formatMoney(clipCost));
      setText('[data-flexi-cover]', coverBranches.toLocaleString('fa-IR') + ' شاخه ۲٫۵ متری، پرت ' + formatMeasure(coverWaste) + ' متر — ' + formatMoney(coverCost));
      setText('[data-flexi-installer]', formatMoney(installerCost));
      setText('[data-flexi-braces]', lengthBraces.toLocaleString('fa-IR') + ' تودلی برای طول + ' + widthBraces.toLocaleString('fa-IR') + ' تودلی برای ارتفاع');
      setText('[data-flexi-iron]', ironLabel + ' — مصرف واقعی ' + formatMeasure(ironLength) + ' متر؛ خرید ' + ironBranches.toLocaleString('fa-IR') + ' شاخه ۶ متری (' + formatMeasure(ironPurchasedLength) + ' متر)، پرت ' + formatMeasure(ironWaste) + ' متر، وزن کل ' + formatMeasure(ironWeight) + ' کیلوگرم — ' + formatMoney(ironCost));
      setText('[data-flexi-iron-unit]', formatMeasure(ironPurchasedLength / area) + ' متر و ' + formatMeasure(ironWeight / area) + ' کیلوگرم در مترمربع');
      setText('[data-flexi-bracing-iron]', bracingIronBranches > 0 ? bracingIronLabel + ' — مصرف واقعی ' + formatMeasure(bracingIronLength) + ' متر؛ خرید ' + bracingIronBranches.toLocaleString('fa-IR') + ' شاخه ۶ متری (' + formatMeasure(bracingIronPurchasedLength) + ' متر)، پرت ' + formatMeasure(bracingIronWaste) + ' متر، وزن کل ' + formatMeasure(bracingIronWeight) + ' کیلوگرم — ' + formatMoney(bracingIronCost) : 'در نظر گرفته نشده');
      setText('[data-flexi-freight]', formatMoney(freight));
      setText('[data-flexi-base]', formatMoney(baseTotal));
      setText('[data-flexi-profit]', formatMoney(profitAmount) + ' (' + profitPercent.toLocaleString('fa-IR') + '٪)');
      setText('[data-flexi-insurance]', insurancePercent > 0 ? formatMoney(insuranceAmount) + ' (' + insurancePercent.toLocaleString('fa-IR') + '٪)' : 'محاسبه نشده');
      setText('[data-flexi-tax]', taxPercent > 0 ? formatMoney(taxAmount) + ' (' + taxPercent.toLocaleString('fa-IR') + '٪)' : 'محاسبه نشده');
      setText('[data-flexi-unit]', formatMoney(finalPrice / area));
      setText('[data-flexi-final]', formatMoney(finalPrice));
      if (shouldFocus) {
        var result = form.querySelector('[data-flexi-result]');
        result.setAttribute('tabindex', '-1');
        result.focus({ preventScroll: true });
      }
      return true;
    }

    setupRatesAutosave({
      kind: 'flexi',
      action: 'zigurat_save_flexi_rates',
      fields: ['flex_rate', 'roll_widths', 'separator_rate', 'core_branch_rate', 'tape_rate', 'clip_rate', 'cover_branch_rate', 'installer_rate', 'iron_price_per_kg'],
      normalizers: {
        roll_widths: function (value) { return normalize(value).trim(); }
      },
      calculator: form,
      datasetMap: {
        flex_rate: 'flexRate', roll_widths: 'rollWidths', separator_rate: 'separatorRate',
        core_branch_rate: 'coreBranchRate', tape_rate: 'tapeRate', clip_rate: 'clipRate', cover_branch_rate: 'coverBranchRate',
        installer_rate: 'installerRate', iron_price_per_kg: 'ironPricePerKg'
      },
      afterSave: function () { calculate(false); }
    });

    field('iron_type').addEventListener('change', function () {
      var selected = field('iron_type').options[field('iron_type').selectedIndex];
      if (selected && selected.dataset.branchWeight) field('iron_branch_weight').value = selected.dataset.branchWeight;
      calculate(false);
      saveLastValues();
    });
    field('bracing_iron_type').addEventListener('change', function () {
      var selected = field('bracing_iron_type').options[field('bracing_iron_type').selectedIndex];
      if (selected && selected.dataset.branchWeight) field('bracing_iron_branch_weight').value = selected.dataset.branchWeight;
      calculate(false);
      saveLastValues();
    });
    field('flex_margin_cm').addEventListener('change', function () {
      calculate(false);
      saveLastValues();
    });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (calculate(true)) saveLastValues();
    });
    form.querySelectorAll('input').forEach(function (input) {
      input.addEventListener('input', function () { calculate(false); });
      input.addEventListener('change', function () { calculate(false); });
    });
    ['iron_branch_weight', 'bracing_iron_branch_weight', 'freight', 'profit_percent', 'insurance_percent', 'tax_percent'].forEach(function (name) {
      var input = field(name);
      input.addEventListener('input', function () {
        if (input.value.trim() !== '') scheduleValuesSave();
      });
      input.addEventListener('change', function () {
        if (input.value.trim() !== '') saveLastValues();
      });
    });
  }

  initLightbox();
  initComposite();
  initFlexi();
}());
