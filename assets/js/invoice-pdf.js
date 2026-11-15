(function () {
  'use strict';
  var PX = 96 / 25.4;
  var PT = 72 / 25.4;
  var source = document.querySelector('.invoice-print-sheet .invoice-document');
  var controls = document.querySelector('.invoice-pdf-controls');
  if (!source || !controls) return;
  var config = window.ziguratInvoicePdfConfig || {};
  var busy = false;
  var resultBlob = null;
  var loadedLibraries;
  var imageCache = new Map();
  var status = controls.querySelector('.invoice-pdf-status');
  var generateButton = controls.querySelector('[data-pdf-generate]');
  var downloadButton = controls.querySelector('[data-pdf-print]');
  var preview = document.querySelector('[data-pdf-preview]');
  var previewCanvas = preview && preview.querySelector('[data-pdf-canvas]');
  var previewPageLabel = preview && preview.querySelector('[data-pdf-page]');
  var previewPrevious = preview && preview.querySelector('[data-pdf-prev]');
  var previewNext = preview && preview.querySelector('[data-pdf-next]');
  var previewDocument = null;
  var previewLoadingTask = null;
  var previewPageNumber = 1;
  var pdfjsLibrary = null;

  function pdfFilename() {
    return (document.body.dataset.printFilename || document.title)
      .replace(/[\\/:*?"<>|\r\n]/g, '-')
      .replace(/\.pdf$/i, '').trim() + '.pdf';
  }

  function message(text, error) {
    status.textContent = text;
    status.classList.toggle('is-error', !!error);
  }
  async function clearPreview() {
    var previousTask = previewLoadingTask;
    previewLoadingTask = null;
    previewDocument = null;
    if (preview) preview.hidden = true;
    if (previewCanvas) previewCanvas.width = previewCanvas.height = 0;
    if (previousTask) {
      try {
        await previousTask.destroy();
      } catch (error) {
        console.warn('Invoice PDF preview cleanup failed', error);
      }
    }
  }
  async function renderPreviewPage() {
    if (!previewDocument || !previewCanvas) return;
    var page = await previewDocument.getPage(previewPageNumber);
    var base = page.getViewport({ scale: 1 });
    var maxWidth = Math.min(1000, Math.max(280, preview.querySelector('.invoice-pdf-preview__canvas-wrap').clientWidth - 24));
    var ratio = Math.min(window.devicePixelRatio || 1, 1.5);
    var viewport = page.getViewport({ scale: Math.min(maxWidth, 1000) * ratio / base.width });
    previewCanvas.width = Math.round(viewport.width);
    previewCanvas.height = Math.round(viewport.height);
    previewCanvas.style.width = Math.round(viewport.width / ratio) + 'px';
    previewCanvas.style.height = Math.round(viewport.height / ratio) + 'px';
    await page.render({ canvasContext: previewCanvas.getContext('2d', { alpha: false }), viewport: viewport }).promise;
    previewPageLabel.textContent = 'صفحهٔ ' + previewPageNumber.toLocaleString('fa-IR') + ' از ' + previewDocument.numPages.toLocaleString('fa-IR');
    previewPrevious.disabled = previewPageNumber <= 1;
    previewNext.disabled = previewPageNumber >= previewDocument.numPages;
  }
  async function showPreview(bytes) {
    if (!preview || !previewCanvas || !config.pdfModuleUrl || !config.workerUrl) {
      throw new Error('پیش‌نمایش PDF در دسترس نیست. فایل‌های قالب را کامل به‌روز کنید.');
    }
    await clearPreview();
    if (!pdfjsLibrary) pdfjsLibrary = import(config.pdfModuleUrl);
    var pdfjs = await pdfjsLibrary;
    pdfjs.GlobalWorkerOptions.workerSrc = config.workerUrl;
    previewLoadingTask = pdfjs.getDocument({ data: bytes.slice(), isEvalSupported: false, disableFontFace: true });
    previewDocument = await previewLoadingTask.promise;
    previewPageNumber = 1;
    preview.hidden = false;
    await renderPreviewPage();
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

  function measuredTextRuns(text, levels, measure) {
    var runs = [], hasGeometry = false;
    for (var offset = 0; offset < text.length; offset++) {
      if (/[\r\n]/.test(text[offset])) continue;
      var rect = measure(offset);
      var visible = rect.width > 0 && rect.height > 0;
      hasGeometry = hasGeometry || visible;
      var last = runs[runs.length - 1];
      // Firefox can report zero width for the LETTER before a ZWNJ, not only
      // for the join control. Geometry must never decide whether text exists.
      if (last && last.end === offset &&
          (last.level === levels[offset] || (!visible && /[\u200c\u200d]/.test(text[offset]))) &&
          (!visible || last.pending || Math.abs(last.top - rect.top) < 1)) {
        last.end = offset + 1;
        if (visible && last.pending) { last.top = rect.top; last.pending = false; }
      } else {
        runs.push({ start: offset, end: offset + 1, level: levels[offset], top: rect.top, pending: !visible });
      }
    }
    // Entirely hidden DOM nodes must remain hidden in the PDF.
    return hasGeometry ? runs : [];
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
      // Split only at real browser line wraps or bidi-run boundaries, never at
      // individual Arabic letters. Joining/ligatures stay shaped by fontkit.
      var runs = measuredTextRuns(text, levels, function (offset) {
        range.setStart(node, offset);
        range.setEnd(node, offset + 1);
        return range.getBoundingClientRect();
      });
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
      // Let the PDF viewer open its own print dialog after loading this exact file.
      // Named Print is supported by Firefox/PDF.js; it never silently prints.
      pdf.catalog.set(PDFLib.PDFName.of('OpenAction'), pdf.context.obj({
        S: PDFLib.PDFName.of('Named'), N: PDFLib.PDFName.of('Print')
      }));
      // Firefox combines a nonempty metadata title with the URL filename.
      // Use the named URL fragment below as the single source of filename.
      pdf.setTitle('');
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

  async function generate() {
    if (busy) return;
    busy = true;
    var inputsAndButtons = controls.querySelectorAll('button,input');
    inputsAndButtons.forEach(function (control) { control.disabled = true; });
    var generateLabel = generateButton.textContent;
    generateButton.textContent = 'در حال ساخت PDF…';
    downloadButton.hidden = true;
    resultBlob = null;
    message('در حال آماده‌سازی PDF…');
    try {
      // Let the disabled state appear before starting PDF work.
      await new Promise(function (resolve) { setTimeout(resolve, 0); });
      var orientation = controls.querySelector('input[name="pdf_orientation"]:checked').value;
      var result = await buildPdf(orientation);
      resultBlob = new Blob([result.bytes], { type: 'application/pdf' });
      await showPreview(result.bytes);
      downloadButton.hidden = false;
      message('PDF آماده است؛ ' + result.pageCount.toLocaleString('fa-IR') + ' صفحه، ' + (orientation === 'portrait' ? 'عمودی' : 'افقی') + '. «چاپ / دریافت PDF» صفحهٔ چاپ را باز می‌کند.');
    } catch (error) {
      message(/[\u0600-\u06ff]/.test(error.message || '') ? error.message : 'ساخت PDF در این مرورگر انجام نشد. مرورگر را به‌روز کرده و دوباره تلاش کنید.', true);
      console.error('Invoice PDF generation failed', error);
    } finally {
      generateButton.textContent = generateLabel;
      busy = false;
      inputsAndButtons.forEach(function (control) { control.disabled = false; });
    }
  }
  function printPdf() {
    if (busy || !resultBlob || downloadButton.hidden) return;
    if (!config.pdfViewUrl || !config.pdfViewNonce || typeof DataTransfer === 'undefined') {
      message('بازکردن صفحهٔ چاپ با نام فایل درست در این مرورگر در دسترس نیست.', true);
      return;
    }
    var form = document.createElement('form');
    form.method = 'post';
    form.action = config.pdfViewUrl;
    form.enctype = 'multipart/form-data';
    form.hidden = true;
    var nonce = document.createElement('input');
    nonce.type = 'hidden';
    nonce.name = 'zigurat_pdf_nonce';
    nonce.value = config.pdfViewNonce;
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.name = 'zigurat_invoice_pdf';
    var transfer = new DataTransfer();
    transfer.items.add(new File([resultBlob], pdfFilename(), { type: 'application/pdf' }));
    fileInput.files = transfer.files;
    form.append(nonce, fileInput);
    document.body.appendChild(form);
    form.submit();
  }
  generateButton.addEventListener('click', generate);
  downloadButton.addEventListener('click', printPdf);
  if (previewPrevious) previewPrevious.addEventListener('click', async function () { if (previewPageNumber > 1) { previewPageNumber--; await renderPreviewPage(); } });
  if (previewNext) previewNext.addEventListener('click', async function () { if (previewDocument && previewPageNumber < previewDocument.numPages) { previewPageNumber++; await renderPreviewPage(); } });
  controls.querySelectorAll('input[name="pdf_orientation"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      downloadButton.hidden = true;
      clearPreview();
      resultBlob = null;
      message('جهت صفحه تغییر کرد؛ برای ساخت خروجی جدید، «ساخت PDF» را بزنید.');
    });
  });
  window.addEventListener('pagehide', function () { clearPreview(); });
}());
