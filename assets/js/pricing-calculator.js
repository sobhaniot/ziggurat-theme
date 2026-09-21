(function () {
  'use strict';

  var pricingSection = document.querySelector('.manager-pricing');
  if (!pricingSection) return;

  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  function normalize(value) { return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit] || digit; }); }
  function decimalParts(value) {
    var raw = normalize(value).replace(/\s/g, '').replace(/٬/g, '').replace(/٫/g, '.').replace(/،/g, ',');
    if (raw.indexOf('.') !== -1) {
      raw = raw.replace(/,/g, '');
    } else {
      var commaParts = raw.split(',');
      var commaIsDecimal = commaParts.length === 2
        && commaParts[1].length > 0
        && (commaParts[0] === '0' || commaParts[1].length < 3);
      raw = commaIsDecimal ? commaParts[0] + '.' + commaParts[1] : raw.replace(/,/g, '');
    }
    raw = raw.replace(/[^0-9.]/g, '');
    var decimalIndex = raw.indexOf('.');
    return {
      integer: (decimalIndex === -1 ? raw : raw.slice(0, decimalIndex)).replace(/[^0-9]/g, ''),
      fraction: decimalIndex === -1 ? '' : raw.slice(decimalIndex + 1).replace(/[^0-9]/g, ''),
      hasDecimal: decimalIndex !== -1
    };
  }
  function decimal(value) {
    var parts = decimalParts(value);
    return Math.max(0, parseFloat((parts.integer || '0') + (parts.hasDecimal ? '.' + parts.fraction : '')) || 0);
  }
  function money(value) { return Math.max(0, parseInt(normalize(value).replace(/[^0-9]/g, ''), 10) || 0); }
  function formatMoney(value) { return Math.round(value).toLocaleString('fa-IR') + ' ریال'; }
  function localizeDigits(value) {
    return String(value).replace(/[0-9]/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]; });
  }
  function groupedInteger(value) {
    var clean = String(value || '').replace(/^0+(?=\d)/, '');
    return localizeDigits((clean || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '٬'));
  }
  function formatMoneyInput(input) {
    var normalized = normalize(input.value).replace(/[^0-9]/g, '');
    input.value = normalized === '' ? '' : groupedInteger(normalized);
  }
  function formatDecimalInput(input) {
    if (String(input.value || '').trim() === '') return;
    var parts = decimalParts(input.value);
    input.value = groupedInteger(parts.integer || '0') + (parts.hasDecimal ? '٫' + localizeDigits(parts.fraction) : '');
  }
  function formatMeasure(value) { return Number(value.toFixed(3)).toLocaleString('fa-IR', { maximumFractionDigits: 3 }); }

  pricingSection.querySelectorAll('[data-money-input]').forEach(function (input) {
    formatMoneyInput(input);
    input.addEventListener('input', function () { formatMoneyInput(input); });
  });

  pricingSection.querySelectorAll('input[inputmode="decimal"]:not([name="roll_widths"]), input[inputmode="numeric"]:not([data-money-input])').forEach(function (input) {
    formatDecimalInput(input);
    input.addEventListener('input', function () { formatDecimalInput(input); });
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
    var SHEET_WIDTH = 3200;
    var SHEET_HEIGHT = 1250;
    var SHEET_AREA = (SHEET_WIDTH * SHEET_HEIGHT) / 1000000;
    var CUT_GAP = 4;
    function field(name) { return form.elements.namedItem(name); }
    function setText(selector, value) { var node = form.querySelector(selector); if (node) node.textContent = value; }
    function formatCentimeters(value) { return Number((value / 10).toFixed(1)).toLocaleString('fa-IR', { maximumFractionDigits: 1 }); }
    var saveTimer = null;
    var estimateSearchTimer = null;
    var compositeState = null;

    function splitSurface(surface, allowances, forcedDirection) {
      if (surface.width <= 0 || surface.height <= 0) return [];
      var horizontalAllowance = allowances.horizontal;
      var verticalAllowance = allowances.vertical;
      var usableSheetWidth = Math.max(1, SHEET_WIDTH - horizontalAllowance);
      var usableSheetHeight = Math.max(1, SHEET_HEIGHT - verticalAllowance);
      var rotatedUsableWidth = Math.max(1, SHEET_HEIGHT - horizontalAllowance);
      var rotatedUsableHeight = Math.max(1, SHEET_WIDTH - verticalAllowance);
      var variants = surface.type === 'face'
        ? [surface.height + verticalAllowance <= SHEET_HEIGHT
          ? { columns: Math.ceil(surface.width / usableSheetWidth), rows: Math.ceil(surface.height / usableSheetHeight), partWidth: usableSheetWidth, partHeight: usableSheetHeight }
          : { columns: Math.ceil(surface.width / rotatedUsableWidth), rows: Math.ceil(surface.height / rotatedUsableHeight), partWidth: rotatedUsableWidth, partHeight: rotatedUsableHeight }]
        : forcedDirection === 'vertical'
          ? [{ columns: Math.ceil(surface.width / rotatedUsableWidth), rows: Math.ceil(surface.height / rotatedUsableHeight), partWidth: rotatedUsableWidth, partHeight: rotatedUsableHeight }]
          : forcedDirection === 'horizontal'
            ? [{ columns: Math.ceil(surface.width / usableSheetWidth), rows: Math.ceil(surface.height / usableSheetHeight), partWidth: usableSheetWidth, partHeight: usableSheetHeight }]
        : [
          { columns: Math.ceil(surface.width / usableSheetWidth), rows: Math.ceil(surface.height / usableSheetHeight), partWidth: usableSheetWidth, partHeight: usableSheetHeight },
          { columns: Math.ceil(surface.width / rotatedUsableWidth), rows: Math.ceil(surface.height / rotatedUsableHeight), partWidth: rotatedUsableWidth, partHeight: rotatedUsableHeight }
        ];
      variants.forEach(function (variant) {
        variant.count = variant.columns * variant.rows;
        variant.seams = (variant.columns - 1) * surface.height + (variant.rows - 1) * surface.width;
      });
      variants.sort(function (a, b) { return a.count - b.count || a.seams - b.seams; });
      var chosen = variants[0];
      var parts = [];
      for (var row = 0; row < chosen.rows; row += 1) {
        for (var column = 0; column < chosen.columns; column += 1) {
          var partWidth = Math.min(chosen.partWidth, surface.width - chosen.partWidth * column);
          var partHeight = Math.min(chosen.partHeight, surface.height - chosen.partHeight * row);
          parts.push({
            type: surface.type,
            title: surface.title,
            label: surface.title + (chosen.count > 1 ? ' ' + localizeDigits(parts.length + 1) : ''),
            width: Math.round(partWidth + horizontalAllowance),
            height: Math.round(partHeight + verticalAllowance)
          });
        }
      }
      return parts;
    }

    function splitDripSurface(surface, direction, startFoldMm, endFoldMm) {
      if (surface.width <= 0 || surface.height <= 0) return [];
      var alongCapacity = direction === 'vertical' ? SHEET_HEIGHT : SHEET_WIDTH;
      var crossCapacity = direction === 'vertical' ? SHEET_WIDTH : SHEET_HEIGHT;
      var columns = Math.max(1, Math.ceil((surface.width + startFoldMm + endFoldMm) / alongCapacity));
      var rows = Math.max(1, Math.ceil(surface.height / crossCapacity));
      var capacities = [];
      for (var column = 0; column < columns; column += 1) {
        capacities.push(Math.max(1, alongCapacity - (column === 0 ? startFoldMm : 0) - (column === columns - 1 ? endFoldMm : 0)));
      }
      var remainingWidth = surface.width;
      var rawWidths = capacities.map(function (capacity, index) {
        var remainingColumns = capacities.length - index;
        var width = remainingWidth > capacity ? capacity : remainingWidth / remainingColumns;
        remainingWidth -= width;
        return width;
      });
      var parts = [];
      var remainingHeight = surface.height;
      for (var row = 0; row < rows; row += 1) {
        var partHeight = Math.min(crossCapacity, remainingHeight);
        remainingHeight -= partHeight;
        rawWidths.forEach(function (rawWidth, index) {
          parts.push({
            type: 'drip',
            title: surface.title,
            label: surface.title + ((columns * rows) > 1 ? ' ' + localizeDigits(parts.length + 1) : ''),
            width: Math.round(rawWidth + (index === 0 ? startFoldMm : 0) + (index === columns - 1 ? endFoldMm : 0)),
            height: Math.round(partHeight)
          });
        });
      }
      return parts;
    }

    function pruneFreeRectangles(rectangles) {
      return rectangles.filter(function (rect, index) {
        if (rect.width < 1 || rect.height < 1) return false;
        return !rectangles.some(function (other, otherIndex) {
          return index !== otherIndex
            && rect.x >= other.x && rect.y >= other.y
            && rect.x + rect.width <= other.x + other.width
            && rect.y + rect.height <= other.y + other.height;
        });
      });
    }

    function placeOnSheet(sheet, piece) {
      var best = null;
      sheet.free.forEach(function (space, spaceIndex) {
        [[piece.width, piece.height, false], [piece.height, piece.width, true]].forEach(function (option) {
          if (option[0] > space.width || option[1] > space.height) return;
          var placedWidth = Math.min(space.width, option[0] + CUT_GAP);
          var placedHeight = Math.min(space.height, option[1] + CUT_GAP);
          var score = (space.width * space.height) - (placedWidth * placedHeight)
            + Math.min(space.width - placedWidth, space.height - placedHeight) * 10;
          if (!best || score < best.score) {
            best = {
              score: score,
              spaceIndex: spaceIndex,
              width: option[0],
              height: option[1],
              footprintWidth: placedWidth,
              footprintHeight: placedHeight,
              rotated: option[2]
            };
          }
        });
      });
      if (!best) return false;
      var space = sheet.free.splice(best.spaceIndex, 1)[0];
      var remainingWidth = space.width - best.footprintWidth;
      var remainingHeight = space.height - best.footprintHeight;
      if (remainingWidth > remainingHeight) {
        sheet.free.push({ x: space.x + best.footprintWidth, y: space.y, width: remainingWidth, height: space.height });
        sheet.free.push({ x: space.x, y: space.y + best.footprintHeight, width: best.footprintWidth, height: remainingHeight });
      } else {
        sheet.free.push({ x: space.x + best.footprintWidth, y: space.y, width: remainingWidth, height: best.footprintHeight });
        sheet.free.push({ x: space.x, y: space.y + best.footprintHeight, width: space.width, height: remainingHeight });
      }
      sheet.free = pruneFreeRectangles(sheet.free);
      sheet.placements.push({
        piece: piece,
        x: space.x,
        y: space.y,
        width: best.width,
        height: best.height,
        rotated: best.rotated
      });
      return true;
    }

    function nestPieces(parts) {
      var sheets = [];
      parts.slice().sort(function (a, b) {
        return Math.max(b.width, b.height) - Math.max(a.width, a.height)
          || (b.width * b.height) - (a.width * a.height);
      }).forEach(function (piece) {
        var placed = sheets.some(function (sheet) { return placeOnSheet(sheet, piece); });
        if (!placed) {
          var sheet = { free: [{ x: 0, y: 0, width: SHEET_WIDTH, height: SHEET_HEIGHT }], placements: [] };
          if (placeOnSheet(sheet, piece)) sheets.push(sheet);
        }
      });
      return sheets;
    }

    function escapeCompositeSvg(value) {
      return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, function (character) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&apos;'}[character];
      });
    }

    function compositeOverviewSvg(model) {
      var inputs = model && model.inputs ? model.inputs : {};
      var lengthMm = Number(inputs.length || 0) * 10;
      var heightMm = Number(inputs.width || 0) * 10;
      if (lengthMm <= 0 || heightMm <= 0) return '';
      var legacyFold = Number(inputs.install_allowance || 8) / 2;
      var foldHorizontal = (Number(inputs.fold_left !== undefined ? inputs.fold_left : legacyFold) + Number(inputs.fold_right !== undefined ? inputs.fold_right : legacyFold)) * 10;
      var foldVertical = (Number(inputs.fold_top !== undefined ? inputs.fold_top : legacyFold) + Number(inputs.fold_bottom !== undefined ? inputs.fold_bottom : legacyFold)) * 10;
      function fullFirstSegments(total, capacity) {
        var segments = [];
        var remaining = Math.max(0, total);
        while (remaining > 0) {
          var segment = Math.min(Math.max(1, capacity), remaining);
          segments.push(segment);
          remaining -= segment;
        }
        return segments.length ? segments : [1];
      }
      function dripSegments(total, capacity, startFold, endFold) {
        var count = Math.max(1, Math.ceil((total + startFold + endFold) / capacity));
        var capacities = [];
        for (var index = 0; index < count; index += 1) {
          capacities.push(Math.max(1, capacity - (index === 0 ? startFold : 0) - (index === count - 1 ? endFold : 0)));
        }
        var remaining = total;
        return capacities.map(function (partCapacity, index) {
          var remainingParts = capacities.length - index;
          var segment = remaining > partCapacity ? partCapacity : remaining / remainingParts;
          remaining -= segment;
          return segment;
        });
      }
      var faceHorizontal = heightMm + foldVertical <= SHEET_HEIGHT;
      var faceColumnSegments = fullFirstSegments(lengthMm, Math.max(1, (faceHorizontal ? SHEET_WIDTH : SHEET_HEIGHT) - foldHorizontal));
      var faceRowSegments = fullFirstSegments(heightMm, Math.max(1, (faceHorizontal ? SHEET_HEIGHT : SHEET_WIDTH) - foldVertical));
      var dripStartFoldMm = Number(inputs.drip_start_fold !== undefined ? inputs.drip_start_fold : 15) * 10;
      var dripEndFoldMm = Number(inputs.drip_end_fold !== undefined ? inputs.drip_end_fold : 15) * 10;
      var dripAlongCapacity = model.drip_direction === 'vertical' ? SHEET_HEIGHT : SHEET_WIDTH;
      var dripCrossCapacity = model.drip_direction === 'vertical' ? SHEET_WIDTH : SHEET_HEIGHT;
      var dripColumnSegments = Number(inputs.drip_depth || 0) > 0 ? dripSegments(lengthMm, dripAlongCapacity, dripStartFoldMm, dripEndFoldMm) : [];
      var dripRowSegments = Number(inputs.drip_depth || 0) > 0 ? fullFirstSegments(Number(inputs.drip_depth) * 10, dripCrossCapacity) : [];
      var bottomHorizontalFold = (Number(inputs.bottom_fold_left !== undefined ? inputs.bottom_fold_left : 4) + Number(inputs.bottom_fold_right !== undefined ? inputs.bottom_fold_right : 4)) * 10;
      var bottomVerticalFold = (Number(inputs.bottom_fold_top !== undefined ? inputs.bottom_fold_top : 4) + Number(inputs.bottom_fold_bottom !== undefined ? inputs.bottom_fold_bottom : 4)) * 10;
      var bottomColumnSegments = Number(inputs.bottom_depth || 0) > 0
        ? fullFirstSegments(lengthMm, Math.max(1, (model.bottom_direction === 'vertical' ? SHEET_HEIGHT : SHEET_WIDTH) - bottomHorizontalFold)) : [];
      var bottomRowSegments = Number(inputs.bottom_depth || 0) > 0
        ? fullFirstSegments(Number(inputs.bottom_depth) * 10, Math.max(1, (model.bottom_direction === 'vertical' ? SHEET_WIDTH : SHEET_HEIGHT) - bottomVerticalFold)) : [];
      var svgWidth = 1000;
      var faceWidth = 650;
      var faceHeight = Math.max(190, Math.min(360, faceWidth * heightMm / lengthMm));
      var sideWidth = Number(inputs.side_depth || 0) > 0 ? Math.max(55, Math.min(130, faceWidth * Number(inputs.side_depth) * 10 / lengthMm)) : 0;
      var dripHeight = Number(inputs.drip_depth || 0) > 0 ? Math.max(55, Math.min(125, faceHeight * Number(inputs.drip_depth) * 10 / heightMm)) : 0;
      var bottomHeight = Number(inputs.bottom_depth || 0) > 0 ? Math.max(55, Math.min(125, faceHeight * Number(inputs.bottom_depth) * 10 / heightMm)) : 0;
      var gap = 18;
      var topMargin = 48;
      var faceX = (svgWidth - faceWidth) / 2;
      var faceY = topMargin + (dripHeight ? dripHeight + gap : 0);
      var svgHeight = faceY + faceHeight + (bottomHeight ? gap + bottomHeight : 0) + 55;
      var content = '<rect width="100%" height="100%" fill="#fff"/>';
      function findSheetNumber(partLabel) {
        var sheets = Array.isArray(model.sheets) ? model.sheets : [];
        for (var sheetIndex = 0; sheetIndex < sheets.length; sheetIndex += 1) {
          var placements = sheets[sheetIndex].placements || [];
          for (var placementIndex = 0; placementIndex < placements.length; placementIndex += 1) {
            if (String(placements[placementIndex].label || '') === partLabel) return sheetIndex + 1;
          }
        }
        return 0;
      }
      function addGrid(x, y, width, height, columns, rows, fill, stroke, prefix, showNumbers, numberOffset) {
        var count = 0;
        numberOffset = Number(numberOffset || 0);
        var columnWeights = Array.isArray(columns) ? columns : Array.from({length: columns}, function () { return 1; });
        var rowWeights = Array.isArray(rows) ? rows : Array.from({length: rows}, function () { return 1; });
        var totalColumnWeight = columnWeights.reduce(function (sum, value) { return sum + value; }, 0);
        var totalRowWeight = rowWeights.reduce(function (sum, value) { return sum + value; }, 0);
        var totalParts = columnWeights.length * rowWeights.length;
        var partLabelPrefix = prefix === 'نمای تابلو' ? 'نما' : prefix;
        var rowOffset = 0;
        for (var row = 0; row < rowWeights.length; row += 1) {
          var columnOffset = 0;
          for (var column = 0; column < columnWeights.length; column += 1) {
            count += 1;
            var partX = x + columnOffset / totalColumnWeight * width;
            var partY = y + rowOffset / totalRowWeight * height;
            var partWidth = columnWeights[column] / totalColumnWeight * width;
            var partHeight = rowWeights[row] / totalRowWeight * height;
            content += '<rect x="' + partX.toFixed(2) + '" y="' + partY.toFixed(2) + '" width="' + partWidth.toFixed(2) + '" height="' + partHeight.toFixed(2) + '" fill="' + fill + '" stroke="' + stroke + '" stroke-width="2"/>';
            if (showNumbers) {
              var partNumber = count + numberOffset;
              var partLabel = partLabelPrefix + (totalParts > 1 ? ' ' + localizeDigits(partNumber) : '');
              var sheetNumber = findSheetNumber(partLabel);
              content += '<text x="' + (partX + partWidth / 2).toFixed(2) + '" y="' + (partY + partHeight / 2 + 7).toFixed(2) + '" text-anchor="middle" font-family="Tahoma,Arial" font-size="20" font-weight="700" fill="#222">' + escapeCompositeSvg(sheetNumber ? localizeDigits(sheetNumber) : '—') + '</text>';
            }
            columnOffset += columnWeights[column];
          }
          rowOffset += rowWeights[row];
        }
        content += '<text x="' + (x + width / 2).toFixed(2) + '" y="' + (y - 8).toFixed(2) + '" text-anchor="middle" font-family="Tahoma,Arial" font-size="13" font-weight="700" fill="' + stroke + '">' + escapeCompositeSvg(prefix) + '</text>';
      }
      addGrid(faceX, faceY, faceWidth, faceHeight, faceColumnSegments, faceRowSegments, '#fbfbfb', '#d34a4a', 'نمای تابلو', true);
      if (dripHeight) addGrid(faceX, topMargin, faceWidth, dripHeight, dripColumnSegments, dripRowSegments, '#fff8e8', '#c58b20', 'آبچکان', true, 0);
      if (bottomHeight) addGrid(faceX, faceY + faceHeight + gap, faceWidth, bottomHeight, bottomColumnSegments, bottomRowSegments, '#eef5ff', '#4774ad', 'زیر تابلو', true, 0);
      if (sideWidth) {
        addGrid(faceX - gap - sideWidth, faceY, sideWidth, faceHeight, 1, 1, '#f6effb', '#815795', 'بغل چپ', true, 0);
        addGrid(faceX + faceWidth + gap, faceY, sideWidth, faceHeight, 1, 1, '#f6effb', '#815795', 'بغل راست', true, 1);
      }
      return '<svg xmlns="http://www.w3.org/2000/svg" width="' + svgWidth + '" height="' + svgHeight + '" viewBox="0 0 ' + svgWidth + ' ' + svgHeight + '" direction="rtl">' + content + '</svg>';
    }

    function compositeOverviewDataUrl(model) {
      var svg = compositeOverviewSvg(model);
      return svg ? 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg) : '';
    }

    function renderLayout(sheets, parts) {
      var section = form.querySelector('[data-composite-layout]');
      var sheetsNode = form.querySelector('[data-composite-sheets]');
      var partsNode = form.querySelector('[data-composite-parts]');
      sheetsNode.textContent = '';
      partsNode.textContent = '';
      section.hidden = sheets.length === 0;
      var overview = form.querySelector('[data-composite-overview]');
      if (overview) overview.src = compositeOverviewDataUrl(compositeState);
      sheets.forEach(function (sheet, index) {
        var card = document.createElement('article');
        card.className = 'manager-composite-sheet';
        var title = document.createElement('strong');
        title.textContent = 'ورق ' + localizeDigits(index + 1);
        var board = document.createElement('div');
        board.className = 'manager-composite-sheet__board';
        sheet.placements.forEach(function (placement) {
          var part = document.createElement('span');
          part.className = 'manager-composite-piece is-' + placement.piece.type;
          part.style.left = (placement.x / SHEET_WIDTH * 100) + '%';
          part.style.top = (placement.y / SHEET_HEIGHT * 100) + '%';
          part.style.width = (placement.width / SHEET_WIDTH * 100) + '%';
          part.style.height = (placement.height / SHEET_HEIGHT * 100) + '%';
          part.title = placement.piece.label + ' — ' + formatCentimeters(placement.width) + '×' + formatCentimeters(placement.height) + ' سانتی‌متر';
          var label = document.createElement('b');
          label.textContent = placement.piece.label;
          var size = document.createElement('small');
          size.textContent = formatCentimeters(placement.width) + '×' + formatCentimeters(placement.height) + ' سانتی‌متر';
          part.appendChild(label);
          part.appendChild(size);
          board.appendChild(part);
        });
        card.appendChild(title);
        card.appendChild(board);
        sheetsNode.appendChild(card);
      });
      var summary = {};
      parts.forEach(function (part) {
        if (!summary[part.type]) summary[part.type] = { title: part.title, count: 0, area: 0 };
        summary[part.type].count += 1;
        summary[part.type].area += part.width * part.height / 1000000;
      });
      Object.keys(summary).forEach(function (type) {
        var item = document.createElement('span');
        item.className = 'is-' + type;
        item.textContent = summary[type].title + ': ' + summary[type].count.toLocaleString('fa-IR') + ' قطعه — ' + formatMeasure(summary[type].area) + ' مترمربع';
        partsNode.appendChild(item);
      });
    }

    function saveLastValues() {
      window.clearTimeout(saveTimer);
      if (!form.dataset.ajaxUrl || !form.dataset.valuesNonce) return;
      var body = new URLSearchParams({
        action: 'zigurat_save_composite_last_values',
        nonce: form.dataset.valuesNonce,
        length: decimal(field('length').value),
        width: decimal(field('width').value),
        drip_depth: decimal(field('drip_depth').value),
        drip_start_fold: decimal(field('drip_start_fold').value),
        drip_end_fold: decimal(field('drip_end_fold').value),
        bottom_depth: decimal(field('bottom_depth').value),
        side_depth: decimal(field('side_depth').value),
        fold_left: decimal(field('fold_left').value),
        fold_right: decimal(field('fold_right').value),
        fold_top: decimal(field('fold_top').value),
        fold_bottom: decimal(field('fold_bottom').value),
        bottom_fold_left: decimal(field('bottom_fold_left').value),
        bottom_fold_right: decimal(field('bottom_fold_right').value),
        bottom_fold_top: decimal(field('bottom_fold_top').value),
        bottom_fold_bottom: decimal(field('bottom_fold_bottom').value),
        freight: money(field('freight').value),
        bracing_cost: money(field('bracing_cost').value),
        profit_percent: decimal(field('profit_percent').value),
        insurance_tax_percent: decimal(field('insurance_tax_percent').value)
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
      var length = decimal(field('length').value) / 100;
      var width = decimal(field('width').value) / 100;
      var dripDepth = decimal(field('drip_depth').value) / 100;
      var bottomDepth = decimal(field('bottom_depth').value) / 100;
      var sideDepth = decimal(field('side_depth').value) / 100;
      var dripStartFoldMm = decimal(field('drip_start_fold').value) * 10;
      var dripEndFoldMm = decimal(field('drip_end_fold').value) * 10;
      var foldLeftMm = decimal(field('fold_left').value) * 10;
      var foldRightMm = decimal(field('fold_right').value) * 10;
      var foldTopMm = decimal(field('fold_top').value) * 10;
      var foldBottomMm = decimal(field('fold_bottom').value) * 10;
      var foldAllowances = {
        horizontal: foldLeftMm + foldRightMm,
        vertical: foldTopMm + foldBottomMm
      };
      var bottomFoldAllowances = {
        horizontal: (decimal(field('bottom_fold_left').value) + decimal(field('bottom_fold_right').value)) * 10,
        vertical: (decimal(field('bottom_fold_top').value) + decimal(field('bottom_fold_bottom').value)) * 10
      };
      var error = form.querySelector('[data-composite-error]');
      if (length <= 0 || width <= 0) {
        if (shouldFocus) {
          error.textContent = 'طول و ارتفاع را با عددی بیشتر از صفر وارد کنید.';
          error.classList.remove('is-warning');
          error.hidden = false;
        }
        form.querySelector('[data-composite-layout]').hidden = true;
        return false;
      }
      var faceHorizontalRows = Math.max(1, Math.ceil((width * 1000) / Math.max(1, SHEET_WIDTH - foldAllowances.vertical)));
      var faceHorizontalSeams = Math.max(0, faceHorizontalRows - 1);
      if (faceHorizontalSeams > 0) {
        error.textContent = 'ارتفاع نما با احتساب خم بالا و پایین از طول ۳۲۰ سانتی‌متری ورق بیشتر است؛ محاسبه با کمترین حالت ممکن و ' + faceHorizontalSeams.toLocaleString('fa-IR') + ' شیار افقی انجام شد.';
        error.classList.add('is-warning');
        error.hidden = false;
      } else {
        error.hidden = true;
        error.classList.remove('is-warning');
      }
      var faceArea = length * width;
      var visibleArea = faceArea + length * dripDepth + length * bottomDepth + 2 * width * sideDepth;
      var surfaces = [
        { type: 'face', title: 'نما', width: length * 1000, height: width * 1000 },
        { type: 'side', title: 'بغل راست', width: width * 1000, height: sideDepth * 1000 },
        { type: 'side', title: 'بغل چپ', width: width * 1000, height: sideDepth * 1000 }
      ];
      var baseParts = [];
      surfaces.forEach(function (surface) {
        baseParts = baseParts.concat(splitSurface(surface, surface.type === 'face' ? foldAllowances : {horizontal:0,vertical:0}));
      });
      var dripSurface = { type: 'drip', title: 'آبچکان', width: length * 1000, height: dripDepth * 1000 };
      var dripTrials = dripDepth > 0 ? [
        { direction: 'vertical', parts: splitDripSurface(dripSurface, 'vertical', dripStartFoldMm, dripEndFoldMm) },
        { direction: 'horizontal', parts: splitDripSurface(dripSurface, 'horizontal', dripStartFoldMm, dripEndFoldMm) }
      ] : [{ direction: 'none', parts: [] }];
      var bottomSurface = { type: 'bottom', title: 'زیر تابلو', width: length * 1000, height: bottomDepth * 1000 };
      var bottomTrials = bottomDepth > 0 ? [
        { direction: 'vertical', parts: splitSurface(bottomSurface, bottomFoldAllowances, 'vertical') },
        { direction: 'horizontal', parts: splitSurface(bottomSurface, bottomFoldAllowances, 'horizontal') }
      ] : [{ direction: 'none', parts: [] }];
      var chosenTrial = null;
      dripTrials.forEach(function (dripTrial) {
        bottomTrials.forEach(function (bottomTrial) {
          var trial = {
            dripDirection: dripTrial.direction,
            bottomDirection: bottomTrial.direction,
            allParts: baseParts.concat(dripTrial.parts, bottomTrial.parts)
          };
          trial.sheets = nestPieces(trial.allParts);
          if (!chosenTrial || trial.sheets.length < chosenTrial.sheets.length
            || (trial.sheets.length === chosenTrial.sheets.length && trial.allParts.length < chosenTrial.allParts.length)) chosenTrial = trial;
        });
      });
      var parts = chosenTrial.allParts;
      var sheets = chosenTrial.sheets;
      var cutArea = parts.reduce(function (total, part) { return total + part.width * part.height / 1000000; }, 0);
      var purchasedArea = sheets.length * SHEET_AREA;
      var utilization = purchasedArea > 0 ? cutArea / purchasedArea * 100 : 0;
      var ironCost = Math.round(faceArea * money(form.dataset.ironRate));
      var compositeCost = Math.round(purchasedArea * money(form.dataset.compositeRate));
      var installerCost = Math.round(visibleArea * money(form.dataset.installerRate));
      var suppliesCost = Math.round(visibleArea * money(form.dataset.suppliesRate));
      var freight = money(field('freight').value);
      var bracingCost = money(field('bracing_cost').value);
      var baseTotal = ironCost + compositeCost + installerCost + suppliesCost + freight + bracingCost;
      var profitPercent = Math.min(1000, decimal(field('profit_percent').value));
      var profitAmount = Math.round(baseTotal * profitPercent / 100);
      var afterProfit = baseTotal + profitAmount;
      var insuranceTaxPercent = Math.min(1000, decimal(field('insurance_tax_percent').value));
      var insuranceTaxAmount = Math.round(afterProfit * insuranceTaxPercent / 100);
      var finalPrice = afterProfit + insuranceTaxAmount;
      var pricePerPurchasedSquareMeter = purchasedArea > 0 ? finalPrice / purchasedArea : 0;
      var pricePerVisibleSquareMeter = visibleArea > 0 ? finalPrice / visibleArea : 0;
      compositeState = {
        calculator_type: 'composite',
        inputs: {
          length: decimal(field('length').value), width: decimal(field('width').value),
          drip_depth: decimal(field('drip_depth').value), bottom_depth: decimal(field('bottom_depth').value), side_depth: decimal(field('side_depth').value),
          drip_start_fold: decimal(field('drip_start_fold').value), drip_end_fold: decimal(field('drip_end_fold').value),
          fold_left: decimal(field('fold_left').value), fold_right: decimal(field('fold_right').value), fold_top: decimal(field('fold_top').value), fold_bottom: decimal(field('fold_bottom').value),
          bottom_fold_left: decimal(field('bottom_fold_left').value), bottom_fold_right: decimal(field('bottom_fold_right').value), bottom_fold_top: decimal(field('bottom_fold_top').value), bottom_fold_bottom: decimal(field('bottom_fold_bottom').value)
        },
        drip_direction: chosenTrial.dripDirection,
        bottom_direction: chosenTrial.bottomDirection,
        parts: parts.map(function (part) {
          return {type:part.type,title:part.title,label:part.label,width:part.width,height:part.height};
        }),
        sheets: sheets.map(function (sheet) {
          return {placements:sheet.placements.map(function (placement) {
            return {type:placement.piece.type,label:placement.piece.label,x:placement.x,y:placement.y,width:placement.width,height:placement.height};
          })};
        }),
        results: {
          face_area: faceArea, visible_area: visibleArea, cut_area: cutArea,
          horizontal_seams: faceHorizontalSeams,
          sheet_count: sheets.length, purchased_area: purchasedArea, utilization_percent: utilization,
          iron_cost: ironCost, composite_cost: compositeCost, installer_cost: installerCost,
          supplies_cost: suppliesCost, freight: freight, bracing_cost: bracingCost,
          base_total: baseTotal, profit_percent: profitPercent, profit_amount: profitAmount,
          insurance_tax_percent: insuranceTaxPercent, insurance_tax_amount: insuranceTaxAmount,
          price_per_purchased_square_meter: pricePerPurchasedSquareMeter,
          price_per_visible_square_meter: pricePerVisibleSquareMeter,
          price_per_square_meter: pricePerVisibleSquareMeter,
          final_price: finalPrice
        }
      };
      setText('[data-composite-face-area]', formatMeasure(faceArea) + ' مترمربع');
      setText('[data-composite-area]', formatMeasure(visibleArea) + ' مترمربع');
      setText('[data-composite-cut-area]', formatMeasure(cutArea) + ' مترمربع');
      setText('[data-composite-sheet-count]', sheets.length.toLocaleString('fa-IR') + ' ورق (' + formatMeasure(purchasedArea) + ' مترمربع)');
      setText('[data-composite-utilization]', utilization.toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + '٪ مصرف — ' + (100 - utilization).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + '٪ پرت');
      setText('[data-composite-face-direction]', (width * 1000) + foldAllowances.vertical <= SHEET_HEIGHT
        ? 'نما با ورق افقی چیده می‌شود؛ ارتفاع نما و خم نصب در عرض ۱۲۵ سانتی‌متری ورق جا می‌گیرد و شیارها فقط عمودی هستند.'
        : faceHorizontalSeams > 0
          ? 'ورق‌های نما عمودی چیده می‌شوند و برای پوشش ارتفاع، ' + faceHorizontalRows.toLocaleString('fa-IR') + ' ردیف با ' + faceHorizontalSeams.toLocaleString('fa-IR') + ' شیار افقی لازم است؛ این کمترین تعداد شیار افقی ممکن است.'
          : 'ورق‌های نما عمودی و کنار هم قرار می‌گیرند؛ شیارهای اتصال فقط عمودی هستند.');
      setText('[data-composite-bottom-direction]', chosenTrial.bottomDirection === 'none'
        ? 'برای زیر تابلو ابعادی وارد نشده است.'
        : 'زیر تابلو در دو حالت عمودی و طولی آزمایش شد؛ چیدمان ' + (chosenTrial.bottomDirection === 'vertical' ? 'عمودی' : 'طولی') + ' در ترکیب کم‌مصرف انتخاب شد.');
      setText('[data-composite-drip-direction]', chosenTrial.dripDirection === 'none'
        ? 'برای آبچکان ابعادی وارد نشده است.'
        : 'آبچکان بدون خم در دو حالت عمودی و طولی آزمایش شد؛ چیدمان ' + (chosenTrial.dripDirection === 'vertical' ? 'عمودی' : 'طولی') + ' در ترکیب کم‌مصرف انتخاب شد.');
      setText('[data-composite-iron]', formatMoney(ironCost));
      setText('[data-composite-sheet]', formatMoney(compositeCost));
      setText('[data-composite-installer]', formatMoney(installerCost));
      setText('[data-composite-supplies]', formatMoney(suppliesCost));
      setText('[data-composite-freight]', formatMoney(freight));
      setText('[data-composite-bracing]', formatMoney(bracingCost));
      setText('[data-composite-base]', formatMoney(baseTotal));
      setText('[data-composite-profit]', formatMoney(profitAmount) + ' (' + profitPercent.toLocaleString('fa-IR') + '٪)');
      setText('[data-composite-insurance-tax]', insuranceTaxPercent > 0 ? formatMoney(insuranceTaxAmount) + ' (' + insuranceTaxPercent.toLocaleString('fa-IR') + '٪)' : 'محاسبه نشده');
      setText('[data-composite-sheet-unit]', formatMoney(pricePerPurchasedSquareMeter));
      setText('[data-composite-surface-unit]', formatMoney(pricePerVisibleSquareMeter));
      setText('[data-composite-final]', formatMoney(finalPrice));
      renderLayout(sheets, parts);
      if (shouldFocus) {
        var result = form.querySelector('[data-composite-result]');
        result.setAttribute('tabindex', '-1');
        result.focus({ preventScroll: true });
      }
      return true;
    }

    function compositeEstimateRequest(action, fields) {
      var body = new FormData();
      body.append('action', action);
      body.append('nonce', form.dataset.estimatesNonce || '');
      Object.keys(fields || {}).forEach(function (key) { body.append(key, fields[key]); });
      return fetch(form.dataset.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: body
      }).then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload.success) {
            throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'عملیات انجام نشد.');
          }
          return payload.data;
        });
      });
    }

    function compositeRateSnapshot() {
      return {
        iron_rate: money(form.dataset.ironRate),
        composite_rate: money(form.dataset.compositeRate),
        installer_rate: money(form.dataset.installerRate),
        supplies_rate: money(form.dataset.suppliesRate)
      };
    }

    function buildCompositeEstimateSnapshot() {
      if (!calculate(false) || !compositeState) throw new Error('ابتدا ابعاد معتبر وارد کنید تا محاسبه انجام شود.');
      return {
        version: 1,
        calculator_type: 'composite',
        inputs: {
          length: decimal(field('length').value),
          width: decimal(field('width').value),
          drip_depth: decimal(field('drip_depth').value),
          drip_start_fold: decimal(field('drip_start_fold').value),
          drip_end_fold: decimal(field('drip_end_fold').value),
          bottom_depth: decimal(field('bottom_depth').value),
          side_depth: decimal(field('side_depth').value),
          fold_left: decimal(field('fold_left').value),
          fold_right: decimal(field('fold_right').value),
          fold_top: decimal(field('fold_top').value),
          fold_bottom: decimal(field('fold_bottom').value),
          bottom_fold_left: decimal(field('bottom_fold_left').value),
          bottom_fold_right: decimal(field('bottom_fold_right').value),
          bottom_fold_top: decimal(field('bottom_fold_top').value),
          bottom_fold_bottom: decimal(field('bottom_fold_bottom').value),
          freight: money(field('freight').value),
          bracing_cost: money(field('bracing_cost').value),
          profit_percent: decimal(field('profit_percent').value),
          insurance_tax_percent: decimal(field('insurance_tax_percent').value)
        },
        rates: compositeRateSnapshot(),
        results: compositeState.results,
        drip_direction: compositeState.drip_direction,
        bottom_direction: compositeState.bottom_direction,
        parts: compositeState.parts,
        sheets: compositeState.sheets
      };
    }

    function setCompositeEstimateStatus(message, state) {
      var status = form.querySelector('[data-composite-estimate-status]');
      if (!status) return;
      status.textContent = message || '';
      status.className = state ? 'is-' + state : '';
    }

    function setCompositeFieldValue(name, value, isMoney) {
      var input = field(name);
      if (!input) return;
      input.value = value === undefined || value === null ? '' : String(value);
      if (isMoney) formatMoneyInput(input); else formatDecimalInput(input);
    }

    function restoreCompositeEstimate(payload) {
      var snapshot = payload.snapshot || {};
      if (snapshot.calculator_type !== 'composite') throw new Error('این رکورد مربوط به محاسبه کامپوزیت نیست.');
      var inputs = snapshot.inputs || {};
      var rates = snapshot.rates || {};
      ['length','width','drip_depth','drip_start_fold','drip_end_fold','bottom_depth','side_depth','profit_percent','insurance_tax_percent'].forEach(function (name) {
        setCompositeFieldValue(name, inputs[name] || 0, false);
      });
      var legacyFold = Number(inputs.install_allowance || 8) / 2;
      ['fold_left','fold_right','fold_top','fold_bottom'].forEach(function (name) {
        setCompositeFieldValue(name, inputs[name] !== undefined ? inputs[name] : legacyFold, false);
      });
      ['bottom_fold_left','bottom_fold_right','bottom_fold_top','bottom_fold_bottom'].forEach(function (name) {
        setCompositeFieldValue(name, inputs[name] !== undefined ? inputs[name] : legacyFold, false);
      });
      ['freight','bracing_cost'].forEach(function (name) { setCompositeFieldValue(name, inputs[name] || 0, true); });
      var ratesForm = document.querySelector('[data-pricing-rates-form="composite"]');
      var datasetMap = {iron_rate:'ironRate',composite_rate:'compositeRate',installer_rate:'installerRate',supplies_rate:'suppliesRate'};
      Object.keys(datasetMap).forEach(function (name) {
        var value = Number(rates[name] || 0);
        form.dataset[datasetMap[name]] = value;
        if (ratesForm && ratesForm.elements.namedItem(name)) {
          ratesForm.elements.namedItem(name).value = value;
          formatMoneyInput(ratesForm.elements.namedItem(name));
        }
      });
      field('estimate_project_name').value = payload.project_name || '';
      field('estimate_id').value = payload.id || 0;
      var mode = form.querySelector('[data-composite-estimate-mode]');
      var newButton = form.querySelector('[data-composite-estimate-new]');
      if (mode) mode.textContent = 'در حال ویرایش برآورد ذخیره‌شده شماره ' + Number(payload.id).toLocaleString('fa-IR') + ' هستید.';
      if (newButton) newButton.hidden = false;
      if (!calculate(false)) throw new Error('اطلاعات ذخیره‌شده قابل محاسبه نیست.');
      setCompositeEstimateStatus('محاسبه ذخیره‌شده با نرخ‌های همان زمان باز شد.', 'success');
      form.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    function escapeCompositeHtml(value) {
      return String(value === undefined || value === null ? '' : value).replace(/[&<>'"]/g, function (character) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character];
      });
    }

    function compositeLayoutPrintHtml(sheets) {
      if (!Array.isArray(sheets) || !sheets.length) return '';
      return '<section class="layout-section"><h2>چیدمان ورق‌های کامپوزیت</h2><div class="layouts">' + sheets.map(function (sheet, index) {
        var pieces = (sheet.placements || []).map(function (placement) {
          var style = 'left:' + (Number(placement.x || 0) / SHEET_WIDTH * 100) + '%;top:' + (Number(placement.y || 0) / SHEET_HEIGHT * 100) + '%;width:' + (Number(placement.width || 0) / SHEET_WIDTH * 100) + '%;height:' + (Number(placement.height || 0) / SHEET_HEIGHT * 100) + '%';
          return '<span class="piece is-' + escapeCompositeHtml(placement.type) + '" style="' + style + '"><b>' + escapeCompositeHtml(placement.label) + '</b><small>' + escapeCompositeHtml(formatCentimeters(Number(placement.width || 0)) + '×' + formatCentimeters(Number(placement.height || 0)) + ' سانتی‌متر') + '</small></span>';
        }).join('');
        return '<figure><figcaption>ورق ' + Number(index + 1).toLocaleString('fa-IR') + '</figcaption><div class="sheet">' + pieces + '</div></figure>';
      }).join('') + '</div></section>';
    }

    function compositeOverviewPrintHtml(model) {
      var source = compositeOverviewDataUrl(model);
      return source ? '<section class="overview-section"><h2>نمای یکپارچه قطعات تابلو</h2><img src="' + escapeCompositeHtml(source) + '" alt="نمای یکپارچه قطعات تابلو کامپوزیت"></section>' : '';
    }

    function compositeEstimatePrintHtml(projectName, snapshot, includePrices) {
      var inputs = snapshot.inputs || {};
      var rates = snapshot.rates || {};
      var results = snapshot.results || {};
      includePrices = includePrices !== false;
      var safeTitle = escapeCompositeHtml(projectName || 'برآورد کامپوزیت');
      var dripDirection = snapshot.drip_direction === 'vertical' ? 'عمودی' : (snapshot.drip_direction === 'horizontal' ? 'طولی' : 'بدون آبچکان');
      var bottomDirection = snapshot.bottom_direction === 'vertical' ? 'عمودی' : (snapshot.bottom_direction === 'horizontal' ? 'طولی' : 'بدون زیر تابلو');
      var rows = [
        ['آهن', formatMeasure(Number(results.face_area || 0)) + ' مترمربع', rates.iron_rate, results.iron_cost],
        ['ورق کامپوزیت', Number(results.sheet_count || 0).toLocaleString('fa-IR') + ' ورق؛ ' + formatMeasure(Number(results.purchased_area || 0)) + ' مترمربع', rates.composite_rate, results.composite_cost],
        ['دستمزد نصاب', formatMeasure(Number(results.visible_area || 0)) + ' مترمربع', rates.installer_rate, results.installer_cost],
        ['لوازم مصرفی', formatMeasure(Number(results.visible_area || 0)) + ' مترمربع', rates.supplies_rate, results.supplies_cost],
        ['کرایه', '', null, results.freight],
        ['آهن‌کشی جهت مهار تابلو', '', null, results.bracing_cost],
        ['جمع هزینه پایه', '', null, results.base_total],
        ['سود (' + formatMeasure(Number(results.profit_percent || 0)) + '٪)', '', null, results.profit_amount],
        ['بیمه و مالیات (' + formatMeasure(Number(results.insurance_tax_percent || 0)) + '٪)', '', null, results.insurance_tax_amount]
      ];
      var rowsHtml = rows.map(function (row) {
        var zeroRate = row[2] !== null && Number(row[2] || 0) === 0 && Number(row[3] || 0) === 0;
        return '<tr' + (zeroRate ? ' class="zero-rate"' : '') + '><td>' + escapeCompositeHtml(row[0]) + '</td><td>' + escapeCompositeHtml(row[1]) + '</td><td>' + escapeCompositeHtml(row[2] === null ? '—' : formatMoney(Number(row[2] || 0))) + '</td><td>' + escapeCompositeHtml(formatMoney(Number(row[3] || 0))) + '</td></tr>';
      }).join('');
      var consumptionHtml = '<section class="consumption"><h2>خلاصه مصرف کامپوزیت</h2><div>'
        + '<span><b>' + escapeCompositeHtml(Number(results.sheet_count || 0).toLocaleString('fa-IR')) + '</b><small>ورق ۳۲۰×۱۲۵ سانتی‌متر</small></span>'
        + '<span><b>' + escapeCompositeHtml(formatMeasure(Number(results.purchased_area || 0))) + '</b><small>مترمربع ورق مصرفی</small></span>'
        + '<span><b>' + escapeCompositeHtml(formatMeasure(Number(results.cut_area || 0))) + '</b><small>مترمربع مساحت برش</small></span>'
        + '<span><b>' + escapeCompositeHtml(formatMeasure(Number(results.utilization_percent || 0)) + '٪') + '</b><small>بهره‌وری ورق</small></span>'
        + '<span><b>' + escapeCompositeHtml(formatMeasure(Math.max(0, 100 - Number(results.utilization_percent || 0))) + '٪') + '</b><small>پرت ورق</small></span>'
        + '<span><b>' + escapeCompositeHtml(Number((snapshot.parts || []).length).toLocaleString('fa-IR')) + '</b><small>تعداد قطعات برش</small></span>'
        + '</div></section>';
      var pricingHtml = includePrices
        ? '<table><thead><tr><th>شرح</th><th>مبنای محاسبه</th><th>نرخ واحد</th><th>هزینه</th></tr></thead><tbody>' + rowsHtml + '</tbody></table><div class="final"><span>قیمت نهایی</span><strong>' + escapeCompositeHtml(formatMoney(Number(results.final_price || 0))) + '<small>' + escapeCompositeHtml(formatMoney(Number(results.price_per_purchased_square_meter || (Number(results.purchased_area || 0) > 0 ? Number(results.final_price || 0) / Number(results.purchased_area) : 0))) + ' به‌ازای هر مترمربع ورق مصرفی') + '</small><small>' + escapeCompositeHtml(formatMoney(Number(results.price_per_visible_square_meter || (Number(results.visible_area || 0) > 0 ? Number(results.final_price || 0) / Number(results.visible_area) : 0))) + ' به‌ازای هر مترمربع کل سطوح') + '</small></strong></div>'
        : '';
      var reportTitle = includePrices ? 'برآورد قیمت تابلو کامپوزیت' : 'گزارش مصرف کامپوزیت';
      return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>' + reportTitle + ' - ' + safeTitle + '</title><style>'
        + '@page{size:A4 portrait;margin:0}*{box-sizing:border-box}html,body{margin:0;padding:0}body{padding:12mm;font-family:Tahoma,Arial,sans-serif;color:#171717;direction:rtl}header{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #b78a2d;padding-bottom:10px;margin-bottom:15px}h1{font-size:22px;margin:0}h2{font-size:14px;margin:0 0 8px}header span{color:#6b5a32}.meta{display:grid;grid-template-columns:repeat(2,1fr);border:1px solid #bbb;margin-bottom:14px}.meta div{padding:7px 9px;border-bottom:1px solid #ddd}.meta div:nth-child(odd){border-left:1px solid #ddd}.consumption{margin:10px 0 14px;padding:10px;border:1px solid #b9c8d2;background:#f5f9fb;break-inside:avoid}.consumption>div{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}.consumption span{display:flex;flex-direction:column;padding:8px;border:1px solid #d5e0e6;background:#fff}.consumption b{font-size:14px}.consumption small{margin-top:3px;color:#526873;font-size:9px}.overview-section{margin:12px 0;padding:9px;border:1px solid #b9c8d2;background:#f7fafb;break-inside:avoid}.overview-section img{display:block;width:100%;height:auto;max-height:175mm;object-fit:contain}.customer-note{margin-top:12px;padding:10px;border:1px solid #b9c8d2;background:#f5f9fb;color:#405966;font-size:10px}table{width:100%;border-collapse:collapse;font-size:11px}th,td{border:1px solid #999;padding:6px 8px;text-align:right}th{background:#eee}td:last-child{text-align:left}.zero-rate td{background:#fff0ee;color:#a51f1a;font-weight:bold}.layout-section{margin:12px 0;padding:9px;border:1px solid #cfc7b7;background:#faf8f2}.layouts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.layouts figure{margin:0;break-inside:avoid}.layouts figcaption{font-size:9px;font-weight:bold;margin-bottom:3px}.sheet{position:relative;width:100%;aspect-ratio:3200/1250;border:1px solid #b78a2d;background:#fff;overflow:hidden}.piece{position:absolute;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;border:1px solid #476f8d;background:#e6f2f9;color:#24455b;font-size:7px}.piece small{font-size:6px}.piece.is-face{background:#dff2e5;border-color:#3d8151}.piece.is-drip{background:#fff0c9;border-color:#a97814}.piece.is-bottom{background:#e2ecfb;border-color:#4774ad}.piece.is-side{background:#f0e5f7;border-color:#815795}.final{display:flex;justify-content:space-between;margin-top:12px;padding:12px 14px;background:#222;color:#fff;font-size:18px;font-weight:bold}.final strong{display:grid;text-align:left}.final small{font-size:10px;color:#e8d8a7;margin-top:4px}.note{font-size:9px;color:#666;margin-top:9px}@media print{body{padding:12mm}}</style></head><body>'
        + '<header><h1>' + reportTitle + '</h1><span>زیگورات</span></header>'
        + '<section class="meta"><div><b>نام پروژه:</b> ' + safeTitle + '</div><div><b>ابعاد نما:</b> ' + escapeCompositeHtml(formatMeasure(Number(inputs.length || 0)) + ' × ' + formatMeasure(Number(inputs.width || 0)) + ' سانتی‌متر') + '</div><div><b>آبچکان / زیر / بغل:</b> ' + escapeCompositeHtml(formatMeasure(Number(inputs.drip_depth || 0)) + ' / ' + formatMeasure(Number(inputs.bottom_depth || 0)) + ' / ' + formatMeasure(Number(inputs.side_depth || 0)) + ' سانتی‌متر') + '</div><div><b>خم نما؛ چپ / راست / بالا / پایین:</b> ' + escapeCompositeHtml(formatMeasure(Number(inputs.fold_left !== undefined ? inputs.fold_left : (Number(inputs.install_allowance || 8) / 2))) + ' / ' + formatMeasure(Number(inputs.fold_right !== undefined ? inputs.fold_right : (Number(inputs.install_allowance || 8) / 2))) + ' / ' + formatMeasure(Number(inputs.fold_top !== undefined ? inputs.fold_top : (Number(inputs.install_allowance || 8) / 2))) + ' / ' + formatMeasure(Number(inputs.fold_bottom !== undefined ? inputs.fold_bottom : (Number(inputs.install_allowance || 8) / 2))) + ' سانتی‌متر') + '</div><div><b>خم ابتدا / انتهای آبچکان:</b> ' + escapeCompositeHtml(formatMeasure(Number(inputs.drip_start_fold !== undefined ? inputs.drip_start_fold : 15)) + ' / ' + formatMeasure(Number(inputs.drip_end_fold !== undefined ? inputs.drip_end_fold : 15)) + ' سانتی‌متر') + '</div><div><b>خم زیر تابلو؛ چپ / راست / بالا / پایین:</b> ' + escapeCompositeHtml(formatMeasure(Number(inputs.bottom_fold_left !== undefined ? inputs.bottom_fold_left : 4)) + ' / ' + formatMeasure(Number(inputs.bottom_fold_right !== undefined ? inputs.bottom_fold_right : 4)) + ' / ' + formatMeasure(Number(inputs.bottom_fold_top !== undefined ? inputs.bottom_fold_top : 4)) + ' / ' + formatMeasure(Number(inputs.bottom_fold_bottom !== undefined ? inputs.bottom_fold_bottom : 4)) + ' سانتی‌متر') + '</div><div><b>خم بغل‌ها:</b> ندارد</div><div><b>مساحت نما:</b> ' + escapeCompositeHtml(formatMeasure(Number(results.face_area || 0)) + ' مترمربع') + '</div><div><b>مساحت کل سطوح:</b> ' + escapeCompositeHtml(formatMeasure(Number(results.visible_area || 0)) + ' مترمربع') + '</div><div><b>شیار افقی نما:</b> ' + escapeCompositeHtml(Number(results.horizontal_seams || 0).toLocaleString('fa-IR') + ' شیار') + '</div><div><b>مصرف ورق:</b> ' + escapeCompositeHtml(formatMeasure(Number(results.utilization_percent || 0)) + '٪ مصرف، ' + formatMeasure(100 - Number(results.utilization_percent || 0)) + '٪ پرت') + '</div><div><b>جهت آبچکان:</b> ' + escapeCompositeHtml(dripDirection) + '</div><div><b>جهت زیر تابلو:</b> ' + escapeCompositeHtml(bottomDirection) + '</div></section>'
        + consumptionHtml + compositeOverviewPrintHtml(snapshot) + compositeLayoutPrintHtml(snapshot.sheets) + pricingHtml + (includePrices ? '<p class="note">این گزارش براساس ابعاد و اطلاعات ذخیره‌شده همین برآورد تهیه شده است.</p>' : '')
        + '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},300)})<\/script></body></html>';
    }

    function openCompositeEstimatePrint(projectName, snapshot, printWindow, includePrices) {
      var popup = printWindow || window.open('', '_blank');
      if (!popup) throw new Error('مرورگر پنجره چاپ را مسدود کرده است. اجازه Pop-up را فعال کنید.');
      popup.document.open();
      popup.document.write(compositeEstimatePrintHtml(projectName, snapshot, includePrices));
      popup.document.close();
    }

    function renderCompositeEstimateRecords(records, listing) {
      var list = document.querySelector('[data-composite-estimate-list]');
      var count = document.querySelector('[data-composite-estimate-count]');
      var section = document.querySelector('[data-composite-estimates]');
      var pageLabel = document.querySelector('[data-composite-estimate-page-label]');
      var previousButton = document.querySelector('[data-composite-estimate-page="prev"]');
      var nextButton = document.querySelector('[data-composite-estimate-page="next"]');
      if (!list) return;
      list.innerHTML = '';
      var total = listing ? Number(listing.total || 0) : records.length;
      var currentPage = listing ? Math.max(1, Number(listing.page || 1)) : 1;
      var totalPages = listing ? Math.max(1, Number(listing.pages || 1)) : 1;
      if (count) count.textContent = total.toLocaleString('fa-IR') + ' مورد';
      if (section) { section.dataset.currentPage = currentPage; section.dataset.totalPages = totalPages; }
      if (pageLabel) pageLabel.textContent = 'صفحه ' + currentPage.toLocaleString('fa-IR') + ' از ' + totalPages.toLocaleString('fa-IR');
      if (previousButton) previousButton.disabled = currentPage <= 1;
      if (nextButton) nextButton.disabled = currentPage >= totalPages;
      if (!records.length) {
        var empty = document.createElement('p');
        empty.className = 'manager-pricing-estimates__empty';
        var search = document.querySelector('[data-composite-estimate-search]');
        empty.textContent = search && search.value.trim() ? 'برآوردی با این نام پیدا نشد.' : 'هنوز محاسبه کامپوزیتی ذخیره نشده است.';
        list.appendChild(empty);
        return;
      }
      records.forEach(function (record) {
        var article = document.createElement('article');
        article.dataset.estimateId = record.id;
        var info = document.createElement('div');
        var title = document.createElement('strong'); title.textContent = record.project_name;
        var date = document.createElement('small'); date.textContent = 'آخرین تغییر: ' + record.modified;
        info.appendChild(title); info.appendChild(date);
        var price = document.createElement('b'); price.textContent = Number(record.final_price || 0).toLocaleString('fa-IR') + ' ریال';
        var sheetDetail = document.createElement('small');
        sheetDetail.textContent = Number(record.purchased_area || 0).toLocaleString('fa-IR', {maximumFractionDigits:2}) + ' مترمربع ورق · ' + Number(record.price_per_purchased_sqm || 0).toLocaleString('fa-IR') + ' ریال/مترمربع';
        var surfaceDetail = document.createElement('small');
        surfaceDetail.textContent = Number(record.visible_area || 0).toLocaleString('fa-IR', {maximumFractionDigits:2}) + ' مترمربع سطوح · ' + Number(record.price_per_visible_sqm || 0).toLocaleString('fa-IR') + ' ریال/مترمربع';
        price.appendChild(sheetDetail);
        price.appendChild(surfaceDetail);
        var actions = document.createElement('div'); actions.className = 'manager-pricing-estimates__actions';
        var load = document.createElement('button'); load.type = 'button'; load.dataset.compositeEstimateLoad = record.id; load.textContent = 'بازکردن و ویرایش';
        var print = document.createElement('button'); print.type = 'button'; print.dataset.compositeEstimatePrintSaved = record.id; print.textContent = 'چاپ / PDF';
        var customerPrint = document.createElement('button'); customerPrint.type = 'button'; customerPrint.dataset.compositeEstimatePrintCustomerSaved = record.id; customerPrint.textContent = 'چاپ مشتری';
        print.textContent = 'چاپ داخلی';
        actions.appendChild(load); actions.appendChild(customerPrint); actions.appendChild(print);
        article.appendChild(info); article.appendChild(price); article.appendChild(actions); list.appendChild(article);
      });
    }

    function loadCompositeEstimateList(requestedPage) {
      var section = document.querySelector('[data-composite-estimates]');
      var search = document.querySelector('[data-composite-estimate-search]');
      var status = document.querySelector('[data-composite-estimate-list-status]');
      var page = Math.max(1, Number(requestedPage || (section ? section.dataset.currentPage : 1)));
      if (status) { status.textContent = 'در حال به‌روزرسانی فهرست…'; status.className = 'manager-pricing-estimates__status is-saving'; }
      return compositeEstimateRequest('zigurat_list_pricing_estimates', {page:page,search:search ? search.value.trim() : '',calculator_type:'composite'}).then(function (data) {
        renderCompositeEstimateRecords(data.records || [], data);
        if (status) { status.textContent = ''; status.className = 'manager-pricing-estimates__status'; }
        return data;
      }).catch(function (error) {
        if (status) { status.textContent = error.message; status.className = 'manager-pricing-estimates__status is-error'; }
        throw error;
      });
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
      if (calculate(false)) saveLastValues();
    });
    form.querySelectorAll('input').forEach(function (input) {
      input.addEventListener('input', function () { calculate(false); });
      input.addEventListener('change', function () { calculate(false); });
    });
    ['length', 'width', 'drip_depth', 'drip_start_fold', 'drip_end_fold', 'bottom_depth', 'side_depth', 'fold_left', 'fold_right', 'fold_top', 'fold_bottom', 'bottom_fold_left', 'bottom_fold_right', 'bottom_fold_top', 'bottom_fold_bottom', 'freight', 'bracing_cost', 'profit_percent', 'insurance_tax_percent'].forEach(function (name) {
      var input = field(name);
      input.addEventListener('input', function () {
        if (input.value.trim() !== '') scheduleValuesSave();
      });
      input.addEventListener('change', function () {
        if (input.value.trim() !== '') saveLastValues();
      });
    });

    var saveEstimateButton = form.querySelector('[data-composite-estimate-save]');
    var newEstimateButton = form.querySelector('[data-composite-estimate-new]');
    var printEstimateButton = form.querySelector('[data-composite-estimate-print]');
    var printCustomerEstimateButton = form.querySelector('[data-composite-estimate-print-customer]');
    var estimateList = document.querySelector('[data-composite-estimate-list]');
    var estimateListStatus = document.querySelector('[data-composite-estimate-list-status]');
    var estimateSection = document.querySelector('[data-composite-estimates]');
    var estimateSearch = document.querySelector('[data-composite-estimate-search]');
    var estimateSearchClear = document.querySelector('[data-composite-estimate-search-clear]');
    if (saveEstimateButton) saveEstimateButton.addEventListener('click', function () {
      var projectName = String(field('estimate_project_name').value || '').trim();
      if (!projectName) { setCompositeEstimateStatus('برای ذخیره، ابتدا نام پروژه را وارد کنید.', 'error'); field('estimate_project_name').focus(); return; }
      var snapshot;
      try { snapshot = buildCompositeEstimateSnapshot(); } catch (error) { setCompositeEstimateStatus(error.message, 'error'); return; }
      saveEstimateButton.disabled = true;
      setCompositeEstimateStatus('در حال ذخیره محاسبه…', 'saving');
      compositeEstimateRequest('zigurat_save_pricing_estimate', {estimate_id:field('estimate_id').value || 0,project_name:projectName,snapshot:JSON.stringify(snapshot)}).then(function (data) {
        field('estimate_id').value = data.id;
        var mode = form.querySelector('[data-composite-estimate-mode]');
        if (mode) mode.textContent = 'این برآورد ذخیره شده و تغییرات بعدی روی همین رکورد ثبت می‌شود.';
        if (newEstimateButton) newEstimateButton.hidden = false;
        loadCompositeEstimateList(1).catch(function () {});
        setCompositeEstimateStatus(data.message || 'محاسبه ذخیره شد.', 'success');
      }).catch(function (error) { setCompositeEstimateStatus(error.message, 'error'); }).finally(function () { saveEstimateButton.disabled = false; });
    });
    if (newEstimateButton) newEstimateButton.addEventListener('click', function () {
      field('estimate_id').value = 0; field('estimate_project_name').value = '';
      var mode = form.querySelector('[data-composite-estimate-mode]');
      if (mode) mode.textContent = 'به‌عنوان یک برآورد جدید ذخیره می‌شود.';
      newEstimateButton.hidden = true; setCompositeEstimateStatus('نام پروژه جدید را وارد و ذخیره کنید.', 'success'); field('estimate_project_name').focus();
    });
    if (printEstimateButton) printEstimateButton.addEventListener('click', function () {
      try { openCompositeEstimatePrint(String(field('estimate_project_name').value || '').trim() || 'برآورد جدید', buildCompositeEstimateSnapshot(), null, true); }
      catch (error) { setCompositeEstimateStatus(error.message, 'error'); }
    });
    if (printCustomerEstimateButton) printCustomerEstimateButton.addEventListener('click', function () {
      try { openCompositeEstimatePrint(String(field('estimate_project_name').value || '').trim() || 'گزارش مصرف کامپوزیت', buildCompositeEstimateSnapshot(), null, false); }
      catch (error) { setCompositeEstimateStatus(error.message, 'error'); }
    });
    if (estimateList) estimateList.addEventListener('click', function (event) {
      var loadButton = event.target.closest('[data-composite-estimate-load]');
      var savedPrintButton = event.target.closest('[data-composite-estimate-print-saved]');
      var savedCustomerPrintButton = event.target.closest('[data-composite-estimate-print-customer-saved]');
      if (!loadButton && !savedPrintButton && !savedCustomerPrintButton) return;
      event.preventDefault();
      var estimateId = loadButton ? loadButton.dataset.compositeEstimateLoad : (savedPrintButton ? savedPrintButton.dataset.compositeEstimatePrintSaved : savedCustomerPrintButton.dataset.compositeEstimatePrintCustomerSaved);
      var isPrint = Boolean(savedPrintButton || savedCustomerPrintButton);
      var printWindow = isPrint ? window.open('', '_blank') : null;
      if (isPrint && !printWindow) { setCompositeEstimateStatus('مرورگر پنجره چاپ را مسدود کرده است. اجازه Pop-up را فعال کنید.', 'error'); return; }
      var clickedButton = loadButton || savedPrintButton || savedCustomerPrintButton;
      var originalText = clickedButton.textContent;
      clickedButton.disabled = true; clickedButton.textContent = loadButton ? 'در حال بازیابی…' : 'در حال آماده‌سازی…';
      if (estimateListStatus) { estimateListStatus.textContent = clickedButton.textContent; estimateListStatus.className = 'manager-pricing-estimates__status is-saving'; }
      if (printWindow) printWindow.document.write('<p dir="rtl" style="font-family:Tahoma;padding:30px">در حال آماده‌سازی گزارش…</p>');
      compositeEstimateRequest('zigurat_get_pricing_estimate', {estimate_id:estimateId}).then(function (data) {
        if (loadButton) { restoreCompositeEstimate(data); if (estimateListStatus) { estimateListStatus.textContent = 'محاسبه برای ویرایش بازیابی شد.'; estimateListStatus.className = 'manager-pricing-estimates__status is-success'; } return; }
        if (!data.snapshot || data.snapshot.calculator_type !== 'composite') throw new Error('این رکورد مربوط به کامپوزیت نیست.');
        openCompositeEstimatePrint(data.project_name, data.snapshot, printWindow, !savedCustomerPrintButton);
        if (estimateListStatus) { estimateListStatus.textContent = 'گزارش چاپ آماده شد.'; estimateListStatus.className = 'manager-pricing-estimates__status is-success'; }
      }).catch(function (error) {
        if (printWindow) printWindow.close(); setCompositeEstimateStatus(error.message, 'error');
        if (estimateListStatus) { estimateListStatus.textContent = error.message; estimateListStatus.className = 'manager-pricing-estimates__status is-error'; }
      }).finally(function () { clickedButton.disabled = false; clickedButton.textContent = originalText; });
    });
    if (estimateSection) estimateSection.addEventListener('click', function (event) {
      var pageButton = event.target.closest('[data-composite-estimate-page]');
      if (!pageButton || pageButton.disabled) return;
      var currentPage = Math.max(1, Number(estimateSection.dataset.currentPage || 1));
      var totalPages = Math.max(1, Number(estimateSection.dataset.totalPages || 1));
      var nextPage = pageButton.dataset.compositeEstimatePage === 'next' ? currentPage + 1 : currentPage - 1;
      pageButton.disabled = true; loadCompositeEstimateList(Math.max(1, Math.min(totalPages, nextPage))).catch(function () {});
    });
    if (estimateSearch) estimateSearch.addEventListener('input', function () {
      window.clearTimeout(estimateSearchTimer); if (estimateSearchClear) estimateSearchClear.hidden = estimateSearch.value.trim() === '';
      estimateSearchTimer = window.setTimeout(function () { loadCompositeEstimateList(1).catch(function () {}); }, 400);
    });
    if (estimateSearchClear) estimateSearchClear.addEventListener('click', function () {
      estimateSearch.value = ''; estimateSearchClear.hidden = true; loadCompositeEstimateList(1).catch(function () {}); estimateSearch.focus();
    });
    if (decimal(field('length').value) > 0 && decimal(field('width').value) > 0) {
      calculate(false);
    }
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
