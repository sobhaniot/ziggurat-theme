(function () {
  'use strict';
  var PX = 96 / 25.4;
  var PT = 72 / 25.4;
  var source = document.querySelector('.invoice-print-sheet .invoice-document');
  if (!source) return;
  var config = window.ziguratInvoicePdfConfig || {};
  var busy = false;
  var resultUrl = null;
  var previousFocus;
  var previousOverflow;
  var loadedLibraries;
  var imageCache = new Map();
  var modal = document.createElement('div');
  modal.className = 'invoice-pdf-modal no-print';
  modal.hidden = true;
  modal.innerHTML = '<section class="invoice-pdf-dialog" role="dialog" aria-modal="true" aria-labelledby="invoice-pdf-title" tabindex="-1" dir="rtl">' +
    '<h2 id="invoice-pdf-title">چاپ و دریافت PDF</h2><p>اندازهٔ کاغذ A4 است. PDF با جهت انتخاب‌شده ساخته می‌شود و در صورت فعال‌بودن مهر، روی هر صفحه یک مهر قرار می‌گیرد.</p>' +
    '<fieldset class="invoice-pdf-choices"><legend>جهت صفحه</legend><label><input type="radio" name="pdf_orientation" value="landscape" checked>افقی (Landscape)</label><label><input type="radio" name="pdf_orientation" value="portrait">عمودی (Portrait)</label></fieldset>' +
    '<div class="invoice-pdf-actions"><button type="button" data-pdf-generate>ساخت PDF</button><button type="button" data-pdf-close>بستن</button><a data-pdf-download hidden>دریافت PDF</a><a data-pdf-view target="_blank" rel="noopener" hidden>بازکردن PDF برای چاپ</a></div>' +
    '<output class="invoice-pdf-status" aria-live="polite"></output><div class="invoice-pdf-preview"></div></section>';
  document.body.appendChild(modal);
  var status = modal.querySelector('.invoice-pdf-status');
  var preview = modal.querySelector('.invoice-pdf-preview');
  var generateButton = modal.querySelector('[data-pdf-generate]');

  function open() {
    if (!modal.hidden) return;
    previousFocus = document.activeElement;
    previousOverflow = [document.documentElement.style.overflow, document.body.style.overflow];
    document.documentElement.style.overflow = document.body.style.overflow = 'hidden';
    modal.hidden = false;
    modal.querySelector('input:checked').focus();
  }
  function close() {
    if (busy) return;
    modal.hidden = true;
    document.documentElement.style.overflow = previousOverflow[0];
    document.body.style.overflow = previousOverflow[1];
    if (previousFocus && previousFocus.isConnected) previousFocus.focus({ preventScroll: true });
  }
  function message(text, error) {
    status.textContent = text;
    status.classList.toggle('is-error', !!error);
  }
  function loadScript(url, ready) {
    if (ready()) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      if (!url) { reject(new Error('فایل‌های ساخت PDF در دسترس نیستند؛ فایل‌های قالب را کامل به‌روز کنید.')); return; }
      var script = document.createElement('script');
      script.src = url;
      script.onload = function () { ready() ? resolve() : reject(new Error('بارگذاری ابزار ساخت PDF کامل نشد.')); };
      script.onerror = function () { script.remove(); reject(new Error('فایل‌های ساخت PDF بارگذاری نشدند. دوباره تلاش کنید.')); };
      document.head.appendChild(script);
    });
  }
  function libraries() {
    if (!loadedLibraries) loadedLibraries = Promise.all([
      loadScript(config.pdfUrl, function () { return !!window.PDFLib; }),
      loadScript(config.fontkitUrl, function () { return !!window.fontkit; }),
      loadScript(config.bidiUrl, function () { return !!window.bidi_js; })
    ]).catch(function (error) { loadedLibraries = null; throw error; });
    return loadedLibraries;
  }
  function imageData(url) {
    if (!imageCache.has(url)) imageCache.set(url, (async function () {
      var response = await fetch(url, { credentials: 'same-origin', signal: AbortSignal.timeout(20000) });
      if (!response.ok) throw new Error('دریافت تصویر لوگو یا مهر انجام نشد.');
      var blob = await response.blob();
      return new Promise(function (resolve, reject) {
        var reader = new FileReader();
        reader.onload = function () { resolve(reader.result); };
        reader.onerror = function () { reject(new Error('خواندن تصویر لوگو یا مهر انجام نشد.')); };
        reader.readAsDataURL(blob);
      });
    })().catch(function (error) { imageCache.delete(url); throw error; }));
    return imageCache.get(url);
  }
  async function inlineImages(node) {
    await Promise.all(Array.from(node.querySelectorAll('img')).map(async function (image) {
      image.src = await imageData(image.currentSrc || image.src);
      image.removeAttribute('srcset');
      image.loading = 'eager';
      await image.decode();
    }));
  }
  async function stampInfo() {
    var original = source.querySelector('.invoice-stamp-image');
    if (!original) return null;
    var image = new Image();
    image.src = await imageData(original.currentSrc || original.src);
    await image.decode();
    var style = getComputedStyle(original.parentElement);
    function number(key, fallback, min, max) {
      var value = parseFloat(style.getPropertyValue(key));
      return Math.max(min, Math.min(max, Number.isFinite(value) ? value : fallback));
    }
    var size = number('--invoice-stamp-size', 40, 20, 70);
    return { image: image, width: size, height: size * image.naturalHeight / image.naturalWidth,
      x: number('--invoice-stamp-x', 75, 10, 90), bottom: number('--invoice-stamp-bottom', 0, 0, 30) };
  }
  function clone(node) {
    var copy = node.cloneNode(true);
    copy.removeAttribute('id');
    copy.querySelectorAll('[id]').forEach(function (element) { element.removeAttribute('id'); });
    return copy;
  }

  async function paginate(orientation, stamp) {
    var width = orientation === 'landscape' ? 297 : 210;
    var height = orientation === 'landscape' ? 210 : 297;
    // Signature box geometry is independent of the configured stamp image.
    var footerHeight = 30;
    var stage = document.createElement('div');
    stage.className = 'invoice-pdf-stage';
    stage.setAttribute('aria-hidden', 'true');
    document.body.appendChild(stage);
    var prepared = clone(source);
    prepared.querySelectorAll('.invoice-stamp-image,.invoice-first-page-stamp').forEach(function (element) { element.remove(); });
    stage.appendChild(prepared);
    try {
      await inlineImages(prepared);
      await document.fonts.ready;
      var fontData = config.fontUrl ? await imageData(config.fontUrl) : null;
      var pages = [];
      var current;
      var tableSource = prepared.querySelector('.invoice-lines');
      var footerSource = prepared.querySelector('.invoice-signatures');
      var headings = Array.from(prepared.children).filter(function (element) {
        return !element.matches('.invoice-lines,.invoice-bottom,.invoice-signatures');
      });
      function newPage() {
        if (pages.length >= 150) throw new Error('تعداد صفحات بیش از حد مجاز است. شرح‌ها و اندازهٔ مهر را بررسی کنید.');
        var page = document.createElement('div');
        page.className = 'invoice-pdf-page';
        page.style.width = width + 'mm';
        page.style.height = height + 'mm';
        if (fontData) {
          var fontStyle = document.createElement('style');
          fontStyle.textContent = '@font-face{font-family:Tanha;src:url("' + fontData + '") format("woff2");font-weight:normal;font-style:normal;}';
          page.appendChild(fontStyle);
        }
        var article = document.createElement('article');
        article.className = source.className;
        article.dir = 'rtl';
        headings.forEach(function (heading) { article.appendChild(clone(heading)); });
        var flow = document.createElement('div');
        flow.className = 'invoice-pdf-flow';
        var table = clone(tableSource);
        table.querySelectorAll('.invoice-lines-page-spacer,tfoot').forEach(function (element) { element.remove(); });
        table.querySelector('tbody').replaceChildren();
        flow.appendChild(table);
        article.appendChild(flow);
        var footer = clone(footerSource);
        footer.style.height = footerHeight + 'mm';
        article.appendChild(footer);
        page.appendChild(article);
        stage.appendChild(page);
        current = { element: page, flow: flow, table: table, rows: table.querySelector('tbody'), footer: footer };
        pages.push(current);
        if (flow.clientHeight < 20 * PX) throw new Error('اندازهٔ مهر یا سربرگ برای این جهت کاغذ بیش از حد بزرگ است. اندازهٔ مهر را در تنظیمات کمتر کنید.');
      }
      function fits() { return current.flow.scrollHeight <= current.flow.clientHeight + 1; }
      function placeRow(original) {
        var row = clone(original);
        current.rows.appendChild(row);
        if (fits()) return;
        row.remove();
        if (current.rows.children.length) newPage();
        current.rows.appendChild(row);
        if (fits()) return;
        var description = row.cells[1].querySelector('.invoice-line-description') || row.cells[1];
        var text = Array.from(description.textContent);
        while (text.length) {
          var low = 0, high = text.length;
          while (low < high) {
            var mid = Math.ceil((low + high) / 2);
            description.textContent = text.slice(0, mid).join('');
            if (fits()) low = mid; else high = mid - 1;
          }
          if (!low) throw new Error('یک ردیف در فضای باقی‌مانده جا نمی‌شود؛ اندازهٔ مهر یا سربرگ را بررسی کنید.');
          var cut = low;
          if (cut < text.length) {
            var space = text.slice(0, cut).lastIndexOf(' ');
            if (space > cut / 2) cut = space + 1;
          }
          description.textContent = text.slice(0, cut).join('');
          text = text.slice(cut);
          if (text.length) {
            newPage();
            row = clone(original);
            Array.from(row.cells).forEach(function (cell, index) { if (index > 1) cell.textContent = ''; });
            row.cells[0].textContent += ' (ادامه)';
            description = row.cells[1].querySelector('.invoice-line-description') || row.cells[1];
            description.textContent = text.join('');
            current.rows.appendChild(row);
          }
        }
      }
      function placeBlock(block) {
        current.flow.appendChild(block);
        if (fits()) return true;
        block.remove();
        newPage();
        current.flow.appendChild(block);
        return fits();
      }
      newPage();
      var rows = Array.from(tableSource.querySelectorAll('tbody > tr'));
      for (var index = 0; index < rows.length; index++) {
        placeRow(rows[index]);
        if (index % 10 === 0) await new Promise(function (resolve) { setTimeout(resolve, 0); });
      }
      var bottom = prepared.querySelector('.invoice-bottom');
      if (bottom && !placeBlock(clone(bottom))) {
        current.flow.lastElementChild.remove();
        var totals = clone(bottom.querySelector('.invoice-totals'));
        if (!placeBlock(totals)) throw new Error('بخش جمع مبالغ در یک صفحه جا نمی‌شود.');
        var notes = bottom.querySelector('.invoice-notes');
        for (var child of Array.from(notes.children)) {
          var part = clone(child);
          part.className = child.tagName === 'H2' ? 'invoice-pdf-notes-title' : 'invoice-pdf-notes-part';
          current.flow.appendChild(part);
          if (fits()) continue;
          part.remove();
          var remaining = Array.from(child.textContent);
          while (remaining.length) {
            var fragment = clone(part);
            current.flow.appendChild(fragment);
            var lo = 0, hi = remaining.length;
            while (lo < hi) {
              var middle = Math.ceil((lo + hi) / 2);
              fragment.textContent = remaining.slice(0, middle).join('');
              if (fits()) lo = middle; else hi = middle - 1;
            }
            if (!lo) { fragment.remove(); newPage(); continue; }
            fragment.textContent = remaining.slice(0, lo).join('');
            remaining = remaining.slice(lo);
            if (remaining.length) newPage();
          }
        }
      }
      // Refuse to export a financial document if pagination lost/repeated data.
      var printedRows = pages.flatMap(function (page) { return Array.from(page.rows.children); });
      var descriptionText = function (row) { return row.cells[1].textContent; };
      var monetaryCells = function (row) { return Array.from(row.cells).slice(2).map(function (cell) { return cell.textContent; }).join('\u0000'); };
      var originalAmounts = rows.map(monetaryCells);
      var printedAmounts = printedRows.filter(function (row) { return Array.from(row.cells).slice(2).some(function (cell) { return cell.textContent !== ''; }); }).map(monetaryCells);
      var noteText = pages.flatMap(function (page) { return Array.from(page.flow.querySelectorAll('.invoice-notes > p,.invoice-pdf-notes-part')); }).map(function (part) { return part.textContent; }).join('');
      var originalNoteText = bottom ? Array.from(bottom.querySelectorAll('.invoice-notes > p')).map(function (part) { return part.textContent; }).join('') : '';
      if (printedRows.map(descriptionText).join('') !== rows.map(descriptionText).join('') || JSON.stringify(originalAmounts) !== JSON.stringify(printedAmounts) || noteText !== originalNoteText) {
        throw new Error('صفحه‌بندی کامل نشد؛ برای جلوگیری از فایل ناقص، خروجی ایجاد نشد.');
      }
      if (pages.some(function (page) { return page.flow.scrollHeight > page.flow.clientHeight + 1; })) {
        throw new Error('بخشی از فاکتور در صفحه جا نمی‌شود؛ اندازهٔ مهر یا شرح را بررسی کنید.');
      }
      // The full-height flow is only a pagination measuring area. Once rows
      // are assigned, let the signature box follow the payment/notes block.
      pages.forEach(function (page) {
        page.flow.style.flex = '0 0 auto';
        page.element.querySelector('.invoice-document').style.height = 'auto';
      });
      prepared.remove();
      return { stage: stage, pages: pages, width: width, height: height, footerHeight: footerHeight };
    } catch (error) { stage.remove(); throw error; }
  }

  // Use the measured DOM only as layout geometry. The actual PDF consists of
  // embedded-font text, vector rules and fills, NOT a screenshot of the page.
  async function vectorPage(pdf, page, element, font, metrics, bidi, images) {
    var origin = element.getBoundingClientRect();
    var scale = 72 / 96;
    var height = page.getHeight();
    page.setFont(font);
    var fontKey = page.node.newFontDictionary(font.name, font.ref);
    function drawText(text, options, weight) {
      font.encodeText(text);
      if (options.opacity < 1) { page.drawText(text, options); return; }
      var direction = options.direction || (/[\u0600-\u06ff]/.test(text) && /\p{Letter}/u.test(text) ? 'rtl' : 'ltr');
      var shaped = metrics.layout(text, undefined, undefined, undefined, direction);
      var unit = options.size / metrics.unitsPerEm;
      var angle = options.rotate ? options.rotate.angle * Math.PI / 180 : 0;
      var cos = Math.cos(angle), sin = Math.sin(angle);
      page.pushOperators(PDFLib.pushGraphicsState(), PDFLib.setFillingColor(options.color), PDFLib.setStrokingColor(options.color));
      // Preserve logical text for PDF readers even though Arabic glyphs are
      // painted in visual order and mirrored punctuation is drawn separately.
      page.pushOperators(PDFLib.PDFOperator.of('BDC', [PDFLib.PDFName.of('Span'), pdf.context.obj({ ActualText: PDFLib.PDFHexString.fromText(options.actualText || text) })]));
      if (parseInt(weight, 10) >= 600) {
        page.pushOperators(PDFLib.setTextRenderingMode(PDFLib.TextRenderingMode.FillAndOutline), PDFLib.setLineWidth(options.size * .018));
      }
      page.pushOperators(PDFLib.beginText(), PDFLib.setFontAndSize(fontKey, options.size));
      var cursorX = 0, cursorY = 0;
      shaped.glyphs.forEach(function (glyph, index) {
        var position = shaped.positions[index];
        var dx = (cursorX + position.xOffset) * unit;
        var dy = (cursorY + position.yOffset) * unit;
        page.pushOperators(PDFLib.setTextMatrix(cos, sin, -sin, cos, options.x + dx * cos - dy * sin, options.y + dx * sin + dy * cos), PDFLib.showText(PDFLib.PDFHexString.of(glyph.id.toString(16).padStart(4, '0'))));
        cursorX += position.xAdvance;
        cursorY += position.yAdvance;
      });
      page.pushOperators(PDFLib.endText(), PDFLib.PDFOperator.of('EMC'), PDFLib.popGraphicsState());
    }
    function color(value) {
      var parts = (value.match(/[\d.]+/g) || []).map(Number);
      return { rgb: PDFLib.rgb((parts[0] || 0) / 255, (parts[1] || 0) / 255, (parts[2] || 0) / 255), alpha: parts.length > 3 ? parts[3] : 1 };
    }
    function box(rect) {
      return { x: (rect.left - origin.left) * scale, y: height - (rect.bottom - origin.top) * scale, width: rect.width * scale, height: rect.height * scale };
    }
    var elements = [element].concat(Array.from(element.querySelectorAll('*'))).filter(function (el) {
      var style = getComputedStyle(el);
      return !el.matches('style,script') && style.display !== 'none' && style.visibility !== 'hidden' && el.getBoundingClientRect().width > 0;
    });
    // Paint fills first, then ALL borders. Adjacent white grid cells must never
    // erase a separator in the header, parties or subject.
    elements.forEach(function (el) {
      var fill = color(getComputedStyle(el).backgroundColor);
      if (fill.alpha) page.drawRectangle(Object.assign(box(el.getBoundingClientRect()), { color: fill.rgb, opacity: fill.alpha }));
    });
    elements.forEach(function (el) {
      if (el.matches('table,thead,tbody,tfoot,tr')) return;
      var rect = box(el.getBoundingClientRect());
      var style = getComputedStyle(el);
      ['Top', 'Right', 'Bottom', 'Left'].forEach(function (side) {
        var width = parseFloat(style['border' + side + 'Width']) * scale;
        var stroke = color(style['border' + side + 'Color']);
        if (!width || !stroke.alpha || style['border' + side + 'Style'] === 'none') return;
        var horizontal = side === 'Top' || side === 'Bottom';
        var inset = width > scale ? width / 2 : 0;
        var x = rect.x + (side === 'Right' ? rect.width - inset : side === 'Left' ? inset : 0);
        var y = rect.y + (side === 'Top' ? rect.height - inset : side === 'Bottom' ? inset : 0);
        page.drawLine({ start: { x: x, y: y }, end: { x: x + (horizontal ? rect.width : 0), y: y + (horizontal ? 0 : rect.height) }, thickness: width, color: stroke.rgb, opacity: stroke.alpha });
      });
    });
    for (var image of Array.from(element.querySelectorAll('img'))) {
      if (!images.has(image.src)) {
        var raster = document.createElement('canvas');
        raster.width = image.naturalWidth;
        raster.height = image.naturalHeight;
        raster.getContext('2d').drawImage(image, 0, 0);
        images.set(image.src, await pdf.embedPng(raster.toDataURL('image/png')));
      }
      var imageBox = box(image.getBoundingClientRect());
      var ratio = image.naturalWidth / image.naturalHeight;
      var imageWidth = Math.min(imageBox.width, imageBox.height * ratio);
      var imageHeight = imageWidth / ratio;
      page.drawImage(images.get(image.src), { x: imageBox.x + (imageBox.width - imageWidth) / 2, y: imageBox.y + (imageBox.height - imageHeight) / 2, width: imageWidth, height: imageHeight });
    }
    var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    var range = document.createRange();
    var node;
    while ((node = walker.nextNode())) {
      if (!node.textContent.trim() || node.parentElement.closest('style,script')) continue;
      var style = getComputedStyle(node.parentElement);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      var size = parseFloat(style.fontSize) * scale;
      var ink = color(style.color);
      if (!ink.alpha) continue;
      var text = node.textContent;
      if (style.writingMode.indexOf('vertical') === 0) {
        var vertical = box(node.parentElement.getBoundingClientRect());
        size = Math.min(size, (vertical.height - 4) / font.widthOfTextAtSize(text.trim(), 1));
        drawText(text.trim(), { size: size, color: ink.rgb, opacity: ink.alpha, rotate: PDFLib.degrees(270), x: vertical.x + vertical.width / 2 - size * .3, y: vertical.y + vertical.height / 2 + font.widthOfTextAtSize(text.trim(), size) / 2 }, style.fontWeight);
        continue;
      }
      var embedding = bidi.getEmbeddingLevels(text, style.direction === 'rtl' ? 'rtl' : 'ltr');
      var levels = embedding.levels;
      var mirrors = bidi.getMirroredCharactersMap(text, levels);
      var runs = [];
      // Split only at real browser line wraps or bidi-run boundaries, never at
      // individual Arabic letters. Joining/ligatures stay shaped by fontkit.
      for (var offset = 0; offset < text.length; offset++) {
        range.setStart(node, offset);
        range.setEnd(node, offset + 1);
        var charRect = range.getBoundingClientRect();
        var last = runs[runs.length - 1];
        // Zero-width join controls are part of the shaping context, not a
        // break between text runs (e.g. پرداخت‌شده). Keep them in the run.
        if (!charRect.width && /[\u200c\u200d]/.test(text[offset]) && last && last.end === offset) {
          last.end = offset + 1;
          continue;
        }
        if (!charRect.width || !charRect.height || /[\r\n]/.test(text[offset])) continue;
        if (last && last.end === offset && last.level === levels[offset] && Math.abs(last.top - charRect.top) < 1) last.end++;
        else runs.push({ start: offset, end: offset + 1, level: levels[offset], top: charRect.top });
      }
      runs.forEach(function (run) {
        while (run.start < run.end && /\s/.test(text[run.start])) run.start++;
        while (run.end > run.start && /\s/.test(text[run.end - 1])) run.end--;
        var content = text.slice(run.start, run.end).split('').map(function (character, index) {
          return mirrors.get(run.start + index) || character;
        }).join('').replace(/[\t\r\n]/g, ' ');
        if (!content.trim()) return;
        range.setStart(node, run.start);
        range.setEnd(node, run.end);
        var bounds = box(range.getBoundingClientRect());
        var advance = font.widthOfTextAtSize(content, size);
        var ascent = metrics.ascent / metrics.unitsPerEm * size;
        var descent = -metrics.descent / metrics.unitsPerEm * size;
        var baseline = bounds.y + (bounds.height - ascent - descent) / 2 + descent;
        drawText(content, { actualText: text.slice(run.start, run.end), direction: run.level % 2 ? 'rtl' : 'ltr', size: size, color: ink.rgb, opacity: ink.alpha, x: run.level % 2 ? bounds.x + bounds.width - advance : bounds.x, y: baseline }, style.fontWeight);
      });
    }
    range.detach();
  }

  async function buildPdf(orientation) {
    await libraries();
    var stamp = await stampInfo();
    var layout = await paginate(orientation, stamp);
    try {
      var pdf = await PDFLib.PDFDocument.create();
      pdf.setTitle(document.body.dataset.printFilename || document.title);
      pdf.setCreator('Ziggurat');
      pdf.setLanguage('fa-IR');
      pdf.registerFontkit({ create: function (bytes) {
        var face = window.fontkit.create(bytes);
        var shape = face.layout;
        face.layout = function (text, features) {
          // Persian digits have Arabic script but must remain left-to-right.
          var direction = /[\u0600-\u06ff]/.test(text) && /\p{Letter}/u.test(text) ? 'rtl' : 'ltr';
          return shape.call(face, text, features, undefined, undefined, direction);
        };
        return face;
      } });
      var fontResponse = await fetch(config.pdfFontUrl, { credentials: 'same-origin', signal: AbortSignal.timeout(20000) });
      if (!fontResponse.ok) throw new Error('فونت PDF دریافت نشد. فایل‌های قالب را کامل به‌روز کنید.');
      var fontBytes = new Uint8Array(await fontResponse.arrayBuffer());
      // Keep original glyph IDs for the positioned, shaped glyph operators.
      var font = await pdf.embedFont(fontBytes, { subset: false });
      var metrics = window.fontkit.create(fontBytes);
      var bidi = window.bidi_js();
      var images = new Map();
      var stampImage;
      if (stamp) {
        var stampCanvas = document.createElement('canvas');
        stampCanvas.width = stamp.image.naturalWidth;
        stampCanvas.height = stamp.image.naturalHeight;
        stampCanvas.getContext('2d').drawImage(stamp.image, 0, 0);
        stampImage = await pdf.embedPng(stampCanvas.toDataURL('image/png'));
      }
      for (var index = 0; index < layout.pages.length; index++) {
        message('در حال ساخت صفحهٔ ' + (index + 1).toLocaleString('fa-IR') + ' از ' + layout.pages.length.toLocaleString('fa-IR') + '…');
        var entry = layout.pages[index];
        entry.element.style.position = 'fixed';
        entry.element.style.left = '0';
        entry.element.style.top = '0';
        var page = pdf.addPage([layout.width * PT, layout.height * PT]);
        await vectorPage(pdf, page, entry.element, font, metrics, bidi, images);
        if (stampImage) {
          var pageBox = entry.element.getBoundingClientRect();
          var sellerBox = entry.footer.querySelector('.invoice-signature--seller').getBoundingClientRect();
          var xMm = (sellerBox.left - pageBox.left + sellerBox.width * stamp.x / 100) / PX - stamp.width / 2;
          var yMm = (pageBox.bottom - sellerBox.bottom) / PX + stamp.bottom;
          // Add exactly one shared stamp XObject to each finalized PDF page.
          page.drawImage(stampImage, { x: xMm * PT, y: yMm * PT, width: stamp.width * PT, height: stamp.height * PT });
        }
        page.drawText((index + 1) + ' / ' + layout.pages.length, { x: layout.width * PT / 2 - 10, y: 3 * PT, size: 8, color: PDFLib.rgb(.4, .4, .4) });
      }
      return { bytes: await pdf.save(), pageCount: layout.pages.length };
    } finally { layout.stage.remove(); }
  }

  async function previewPdf(bytes) {
    var viewer = await import(config.previewUrl);
    viewer.GlobalWorkerOptions.workerSrc = config.workerUrl;
    var loadingTask = viewer.getDocument({ data: bytes.slice(), isEvalSupported: false });
    var documentPdf = await loadingTask.promise;
    try {
      var thumbnails = [];
      for (var index = 1; index <= documentPdf.numPages; index++) {
        var page = await documentPdf.getPage(index);
        var viewport = page.getViewport({ scale: 1 });
        viewport = page.getViewport({ scale: 840 / viewport.width });
        var canvas = document.createElement('canvas');
        canvas.width = Math.ceil(viewport.width);
        canvas.height = Math.ceil(viewport.height);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
        thumbnails.push(canvas.toDataURL('image/jpeg', .9));
        canvas.width = canvas.height = 1;
        page.cleanup();
      }
      return thumbnails;
    } finally { await loadingTask.destroy(); }
  }

  async function generate() {
    if (busy) return;
    busy = true;
    var controls = modal.querySelectorAll('button,input');
    controls.forEach(function (control) { control.disabled = true; });
    modal.querySelector('.invoice-pdf-dialog').focus();
    modal.querySelectorAll('[data-pdf-download],[data-pdf-view]').forEach(function (link) { link.hidden = true; });
    message('در حال آماده‌سازی PDF…');
    try {
      var orientation = modal.querySelector('input:checked').value;
      var result = await buildPdf(orientation);
      var url = URL.createObjectURL(new Blob([result.bytes], { type: 'application/pdf' }));
      if (resultUrl) URL.revokeObjectURL(resultUrl);
      resultUrl = url;
      var filename = (document.body.dataset.printFilename || document.title).replace(/[\\/:*?"<>|]/g, '-') + '.pdf';
      var download = modal.querySelector('[data-pdf-download]');
      download.href = url;
      download.download = filename;
      download.hidden = false;
      var view = modal.querySelector('[data-pdf-view]');
      view.href = url;
      view.hidden = false;
      preview.replaceChildren();
      var thumbnails = [];
      try { thumbnails = await previewPdf(result.bytes); }
      catch (previewError) { console.warn('PDF ready; thumbnail preview unavailable', previewError); }
      thumbnails.forEach(function (thumbnail, index) {
        var figure = document.createElement('figure');
        var image = document.createElement('img');
        image.src = thumbnail;
        image.alt = 'پیش‌نمایش صفحهٔ ' + (index + 1).toLocaleString('fa-IR');
        var caption = document.createElement('figcaption');
        caption.textContent = 'صفحهٔ ' + (index + 1).toLocaleString('fa-IR') + ' از ' + result.pageCount.toLocaleString('fa-IR');
        figure.append(image, caption);
        preview.appendChild(figure);
      });
      message('PDF آماده شد؛ ' + result.pageCount.toLocaleString('fa-IR') + ' صفحه، ' + (orientation === 'portrait' ? 'عمودی' : 'افقی') + '. برای چاپ، فایل PDF را باز کنید.');
    } catch (error) {
      message(/[\u0600-\u06ff]/.test(error.message || '') ? error.message : 'ساخت PDF در این مرورگر انجام نشد. مرورگر را به‌روز کرده و دوباره تلاش کنید.', true);
      console.error('Invoice PDF generation failed', error);
    } finally {
      busy = false;
      controls.forEach(function (control) { control.disabled = false; });
    }
  }
  document.querySelectorAll('[data-invoice-pdf-open]').forEach(function (button) { button.addEventListener('click', open); });
  modal.querySelector('[data-pdf-close]').addEventListener('click', close);
  generateButton.addEventListener('click', generate);
  modal.querySelectorAll('input[name="pdf_orientation"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      modal.querySelectorAll('[data-pdf-download],[data-pdf-view]').forEach(function (link) { link.hidden = true; });
      preview.replaceChildren();
      message('جهت صفحه تغییر کرد؛ برای ساخت خروجی جدید، «ساخت PDF» را بزنید.');
    });
  });
  modal.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { event.preventDefault(); close(); }
    if (event.key === 'Tab') {
      var focusable = Array.from(modal.querySelectorAll('button:not(:disabled),input:not(:disabled),a[href]')).filter(function (el) { return !el.hidden; });
      var first = focusable[0], last = focusable[focusable.length - 1];
      if (!first) { event.preventDefault(); return; }
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.code === 'KeyP') { event.preventDefault(); open(); }
  });
  window.addEventListener('pagehide', function () { if (resultUrl) URL.revokeObjectURL(resultUrl); });
}());
