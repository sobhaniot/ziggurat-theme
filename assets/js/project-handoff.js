(function () {
  'use strict';

  var board = document.querySelector('[data-project-board]');
  var dialog = board && board.querySelector('[data-project-handoff-dialog]');
  var config = window.ziguratProjectHandoff;
  var workflow = window.ziguratWorkflowProjects;
  if (!dialog && window.ziguratHandoffRender) {
    var job = window.ziguratHandoffRender;
    Promise.resolve().then(async function () {
      var logo = await loadImage(job.logo).catch(function () { return null; });
      await document.fonts.load('25px ProjectHandoffTanha').catch(function () {});
      await renderCard(job.card, logo, false, function (canvas) {
        document.body.textContent = canvas.toDataURL('image/png');
      });
    }).catch(function () { document.body.textContent = 'render_failed'; });
    return;
  }
  if (!dialog || !config || !workflow) return;

  var partner = dialog.querySelector('[data-handoff-partner]');
  var partnerField = dialog.querySelector('[data-handoff-partner-field]');
  var status = dialog.querySelector('[data-handoff-status]');
  var preview = dialog.querySelector('[data-handoff-preview]');
  var pngButton = dialog.querySelector('[data-handoff-download="png"]');
  var pdfButton = dialog.querySelector('[data-handoff-download="pdf"]');
  var resultLink = dialog.querySelector('[data-handoff-result]');
  var cards = [];
  var filename = '';
  var requestId = 0;
  var exporting = false;
  var fontPromise;
  var pdfLibrary;
  var resultUrl;

  async function prepareRubikaRender(projectId) {
    if (!window.zigguratRubikaBrowserRender) return;
    async function call(action, fields) {
      var response = await fetch(workflow.ajaxUrl, {
        method: 'POST', credentials: 'same-origin',
        body: new URLSearchParams(Object.assign({action: action, nonce: workflow.nonce, project_id: projectId}, fields))
      });
      var result = await response.json();
      if (!response.ok || !result.success) throw new Error('ساخت عکس اعلان روبیکا انجام نشد.');
      return result.data;
    }
    try {
      var jobs = await call('ziggurat_rubika_render_jobs');
      if (!jobs.length) return;
      await loadHandoffFont();
      var logo = await loadImage(config.logo).catch(function () { return null; });
      for (var job of jobs) {
        await renderCard(job.card, logo, false, function (canvas) {
          return call('ziggurat_rubika_store_render', {key: job.key, png: canvas.toDataURL('image/png')});
        });
      }
    } catch (error) {
      console.warn(error.message);
    }
  }
  window.zigguratPrepareRubikaRender = prepareRubikaRender;
  document.addEventListener('ziggurat:rubika-render', function (event) {
    prepareRubikaRender(event.detail);
  });

  function element(tag, text) {
    var node = document.createElement(tag);
    if (text) node.textContent = text;
    return node;
  }

  function renderPreview() {
    preview.replaceChildren();
    cards.forEach(function (card) {
      var box = element('article');
      box.appendChild(element('strong', card.title));
      (card.photos || [card.photo]).filter(Boolean).forEach(function (url) {
        var photo = element('img');
        photo.src = url;
        photo.alt = 'عکس پروژه';
        photo.loading = 'lazy';
        box.appendChild(photo);
      });
      if (card.people.length > 1) {
        var personLabel = element('label', 'انتخاب مجری');
        personLabel.className = 'manager-project-handoff-person';
        var select = element('select');
        var warning = element('span');
        warning.className = 'manager-project-handoff-person-warning';
        warning.setAttribute('role', 'alert');
        select.required = true;
        select.add(new Option('یک مجری انتخاب کنید', ''));
        card.people.forEach(function (person, index) {
          select.add(new Option(person.name, String(index)));
        });
        select.addEventListener('change', function () {
          card.selectedPerson = select.value === '' ? null : card.people[Number(select.value)];
          if (card.selectedPerson) warning.textContent = '';
        });
        personLabel.appendChild(warning);
        personLabel.appendChild(select);
        box.appendChild(personLabel);
      } else {
        card.selectedPerson = card.people[0] || null;
        box.appendChild(element('p', card.selectedPerson?.name || 'نصاب تعیین نشده'));
      }
      card.tasks.forEach(function (task, index) {
        var label = element('label');
        var input = element('input');
        input.type = 'checkbox';
        input.checked = true;
        input.addEventListener('change', function () { card.included[index] = input.checked; });
        label.append(input, element('span', task));
        box.appendChild(label);
      });
      preview.appendChild(box);
    });
  }

  async function loadCards(projectId, partnerId) {
    var currentRequest = ++requestId;
    cards = [];
    preview.replaceChildren();
    pngButton.disabled = pdfButton.disabled = true;
    status.textContent = projectId || partnerId ? 'در حال دریافت کارت‌ها…' : 'یک همکار را انتخاب کنید.';
    if (!projectId && !partnerId) return;
    try {
      var response = await fetch(workflow.ajaxUrl, {
        method: 'POST', credentials: 'same-origin',
        body: new URLSearchParams({
          action: 'zigurat_project_handoff_data', nonce: workflow.nonce,
          project_id: projectId || '', partner_id: partnerId || ''
        })
      });
      var payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.data?.message || 'دریافت کارت‌ها انجام نشد.');
      if (currentRequest !== requestId) return;
      cards = payload.data.cards;
      cards.forEach(function (card) { card.included = card.tasks.map(function () { return true; }); });
      filename = projectId ? cards[0]?.title : payload.data.name;
      status.textContent = cards.length
        ? cards.length.toLocaleString('fa-IR') + ' کارت. کارهای موردنظر برای خروجی را انتخاب کنید.'
        : 'کارتی برای این همکار پیدا نشد.';
      renderPreview();
      pdfButton.disabled = !cards.length;
      pngButton.disabled = !projectId || !cards.length;
    } catch (error) {
      if (currentRequest === requestId) status.textContent = error.message;
    }
  }

  function loadImage(url) {
    if (!url) return Promise.resolve(null);
    var img = new Image();
    img.src = url;
    return img.decode().then(function () { return img; }, function () {
      throw new Error('بارگذاری یکی از عکس‌های برگه انجام نشد. دوباره تلاش کنید.');
    });
  }

  function font(ctx, size, bold) {
    ctx.font = (bold ? 'bold ' : '') + size + 'px ProjectHandoffTanha, sans-serif';
  }

  function wrap(ctx, text, width) {
    var lines = [];
    var line = '';
    for (var word of String(text).split(/\s+/u)) {
      var next = line ? line + ' ' + word : word;
      if (ctx.measureText(next).width <= width) {
        line = next;
        continue;
      }
      if (line) lines.push(line);
      line = '';
      for (var character of word) {
        if (line && ctx.measureText(line + character).width > width) {
          lines.push(line);
          line = '';
        }
        line += character;
      }
    }
    if (line) lines.push(line);
    return lines;
  }

  function write(ctx, text, x, y, size, color, bold) {
    font(ctx, size, bold);
    ctx.fillStyle = color;
    ctx.fillText(text, x, y);
  }

  function fitImage(ctx, image, x, y, width, height, cover = false) {
    var scale = (cover ? Math.max : Math.min)(width / image.naturalWidth, height / image.naturalHeight);
    var w = image.naturalWidth * scale;
    var h = image.naturalHeight * scale;
    ctx.drawImage(image, x + (width - w) / 2, y + (height - h) / 2, w, h);
  }

  async function renderCard(card, logo, paged, consume) {
    var projectPhotos;
    try {
      projectPhotos = await Promise.all((card.photos || [card.photo]).filter(Boolean).slice(0, 2).map(loadImage));
    } catch {
      throw new Error('بارگذاری عکس پروژه برای برگه اجرا انجام نشد. عکس را بررسی کنید و دوباره تلاش کنید.');
    }
    var person = card.selectedPerson;
    var portrait = await loadImage(person?.photo).catch(function () { return null; });
    var measure = element('canvas').getContext('2d');
    font(measure, 40, true);
    var titleLines = wrap(measure, card.title, 864);
    var headerHeight = 142 + titleLines.length * 54;
    var blocks = [];
    if (projectPhotos.length) {
      blocks.push({height: 316, draw: function (ctx, y) {
        var width = projectPhotos.length === 2 ? 424 : 864;
        projectPhotos.forEach(function (photo, index) {
          var frameX = 912 - width - index * (width + 16);
          var scale = Math.min(width / photo.naturalWidth, 300 / photo.naturalHeight);
          var imageWidth = photo.naturalWidth * scale;
          var imageHeight = photo.naturalHeight * scale;
          var x = frameX + (width - imageWidth) / 2;
          var top = y + (300 - imageHeight) / 2;
          ctx.save();
          ctx.beginPath();
          ctx.roundRect(x, top, imageWidth, imageHeight, 18);
          ctx.clip();
          ctx.drawImage(photo, x, top, imageWidth, imageHeight);
          ctx.lineWidth = 5;
          ctx.strokeStyle = '#c89b3c';
          ctx.stroke();
          ctx.restore();
        });
      }});
    }
    font(measure, 30, true);
    var nameLines = wrap(measure, person?.name || 'نصاب تعیین نشده', 690);
    var personHeight = Math.max(122, 60 + nameLines.length * 40);
    blocks.push({height: personHeight + 14, draw: function (ctx, y) {
      ctx.fillStyle = '#fff';
      ctx.beginPath();
      ctx.roundRect(48, y, 864, personHeight, 18);
      ctx.fill();
      if (portrait) {
        ctx.save();
        ctx.beginPath();
        ctx.roundRect(797, y + 12, 98, 98, 15);
        ctx.clip();
        fitImage(ctx, portrait, 797, y + 12, 98, 98, true);
        ctx.restore();
      }
      write(ctx, 'همکار اجرایی', 778, y + 33, 21, '#695a3d');
      nameLines.forEach(function (line, i) { write(ctx, line, 778, y + 73 + i * 40, 30, '#242522', true); });
    }});
    var tasks = card.tasks.filter(function (task, index) { return card.included[index]; });
    blocks.push({height: 52, draw: function (ctx, y) { write(ctx, 'کارهای اجرایی', 912, y + 35, 30, '#242522', true); }});
    if (!tasks.length) {
      blocks.push({height: 54, draw: function (ctx, y) { write(ctx, 'کاری برای این برگه انتخاب نشده است.', 898, y + 31, 24, '#695a3d'); }});
    }
    font(measure, 25, true);
    tasks.forEach(function (task) {
      wrap(measure, task, 768).forEach(function (line, index, lines) {
        var top = index === 0 ? 14 : 0;
        var bottom = index === lines.length - 1 ? 14 : 0;
        blocks.push({height: 38 + top + bottom, draw: function (ctx, y) {
          ctx.fillStyle = '#fff';
          ctx.beginPath();
          ctx.roundRect(48, y, 864, 38 + top + bottom - (bottom ? 6 : 0), [top, top, bottom, bottom]);
          ctx.fill();
          if (!index) {
            ctx.strokeStyle = '#ad8638';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.roundRect(867, y + 19, 23, 23, 5);
            ctx.stroke();
          }
          write(ctx, line, 848, y + top + 30, 25, '#242522', true);
        }});
      });
    });

    var totalHeight = headerHeight + 64 + blocks.reduce(function (sum, block) { return sum + block.height; }, 0);
    var pageHeight = paged ? 1358 : totalHeight;
    if (pageHeight > 16000) throw new Error('برگه برای یک عکس بیش از حد بلند است. خروجی PDF را بگیرید.');
    var index = 0;
    do {
      var canvas = element('canvas');
      canvas.width = 960;
      canvas.height = pageHeight;
      var ctx = canvas.getContext('2d');
      ctx.direction = 'rtl';
      ctx.textAlign = 'right';
      if (!paged) {
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, pageHeight);

        ctx.save();
        ctx.beginPath();
        ctx.roundRect(15, 15, canvas.width - 30, pageHeight - 30, 23);
        ctx.clip();
      }
      ctx.fillStyle = '#f4f1ea';
      ctx.fillRect(0, 0, 960, pageHeight);
      ctx.fillStyle = '#20211f';
      ctx.fillRect(0, 0, 960, headerHeight);
      ctx.fillStyle = '#c89b3c';
      ctx.fillRect(0, headerHeight, 960, 6);
      ctx.fillStyle = '#fff';
      ctx.beginPath();
      ctx.roundRect(48, 32, 76, 76, 13);
      ctx.fill();
      if (logo) fitImage(ctx, logo, 54, 38, 64, 64);
      else write(ctx, 'زیگورات', 118, 84, 14, '#c89b3c', true);
      write(ctx, 'برگه اجرای پروژه', 912, 69, 25, '#e8c777');
      titleLines.forEach(function (line, i) { write(ctx, line, 912, 131 + i * 54, 40, '#fff', true); });
      var y = headerHeight + 26;
      var firstBlock = index;
      while (index < blocks.length && y + blocks[index].height <= pageHeight - 25) {
        var block = blocks[index++];
        block.draw(ctx, y);
        y += block.height;
      }
      if (index === firstBlock && index < blocks.length) throw new Error('محتوای برگه در صفحه جا نشد.');
      if (!paged) {
        ctx.restore();
        ctx.beginPath();
        ctx.roundRect(15, 15, canvas.width - 30, pageHeight - 30, 23);
        ctx.strokeStyle = '#c89b3c';
        ctx.lineWidth = 4;
        ctx.stroke();
      }
      await consume(canvas);
      canvas.width = canvas.height = 0;
    } while (index < blocks.length);
  }

  function loadPdfLibrary() {
    if (!pdfLibrary) {
      pdfLibrary = new Promise(function (resolve, reject) {
        if (window.PDFLib) return resolve(window.PDFLib);
        var script = element('script');
        script.src = config.pdfLib;
        script.onload = function () { resolve(window.PDFLib); };
        script.onerror = function () { script.remove(); reject(new Error('ابزار ساخت PDF بارگذاری نشد.')); };
        document.head.appendChild(script);
      }).catch(function (error) { pdfLibrary = null; throw error; });
    }
    return pdfLibrary;
  }

  function loadHandoffFont() {
    if (!fontPromise) {
      var sources = 'url("' + config.font + '") format("woff2")';
      if (config.fontFallback) sources += ', url("' + config.fontFallback + '") format("truetype")';
      fontPromise = new FontFace('ProjectHandoffTanha', sources).load()
        .then(function (face) {
          document.fonts.add(face);
        })
        .catch(function () {
          fontPromise = null;
          throw new Error('فونت تنها بارگذاری نشد. دوباره تلاش کنید.');
        });
    }
    return fontPromise;
  }

  function download(blob, extension) {
    if (resultUrl) URL.revokeObjectURL(resultUrl);
    resultUrl = URL.createObjectURL(blob);
    resultLink.href = resultUrl;
    resultLink.download = 'برگه-اجرا-' + (filename || 'پروژه').replace(/[\\/:*?"<>|\r\n]/g, '-').trim() + '.' + extension;
    resultLink.click();
  }

  async function exportCards(format) {
    if (exporting || !cards.length) return;
    var missingPerson = preview.querySelector('select:invalid');
    if (missingPerson) {
      var warning = missingPerson.previousElementSibling;
      warning.textContent = 'برای دریافت برگهٔ اجرا، لطفاً یک مجری انتخاب کنید.';
      warning.scrollIntoView({block: 'nearest'});
      missingPerson.focus({preventScroll: true});
      return;
    }
    exporting = true;
    dialog.querySelectorAll('button, select, input').forEach(function (control) { control.disabled = true; });
    status.textContent = 'در حال ساخت خروجی…';
    try {
      var results = await Promise.all([
        loadImage(config.logo).catch(function () { return null; }),
        loadHandoffFont()
      ]);
      var logo = results[0];
      if (format === 'png') {
        await renderCard(cards[0], logo, false, async function (canvas) {
          var blob = await new Promise(function (resolve) { canvas.toBlob(resolve, 'image/png'); });
          if (!blob) throw new Error('ساخت عکس انجام نشد.');
          download(blob, 'png');
        });
      } else {
        var library = await loadPdfLibrary();
        var pdf = await library.PDFDocument.create();
        for (var card of cards) {
          await renderCard(card, logo, true, async function (canvas) {
            var image = await pdf.embedJpg(canvas.toDataURL('image/jpeg', 0.93));
            var page = pdf.addPage([595.28, 841.89]);
            page.drawImage(image, {x: 0, y: 0, width: 595.28, height: 841.89});
          });
        }
        download(new Blob([await pdf.save()], {type: 'application/pdf'}), 'pdf');
      }
      var readyMessage = format === 'png'
        ? 'عکس آماده شد.'
        : 'PDF آماده شد.';
      status.textContent = readyMessage + (!logo ? ' لوگو در دسترس نبود.' : '');
    } catch (error) {
      var message = error.message || 'ساخت خروجی انجام نشد.';
      status.textContent = /network error|failed to fetch/i.test(message)
        ? 'بارگذاری یکی از فایل‌های برگه اجرا انجام نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.'
        : message;
    } finally {
      exporting = false;
      dialog.querySelectorAll('button, select, input').forEach(function (control) { control.disabled = false; });
      pngButton.disabled = !partnerField.hidden;
    }
  }

  board.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-project-handoff], [data-project-handoff-bulk]');
    if (!trigger) return;
    var card = trigger.closest('[data-project-card], [data-project-handoff-card]');
    var projectId = card?.dataset.projectId || '';
    partnerField.hidden = !!projectId;
    pngButton.hidden = !projectId;
    partner.value = '';
    dialog.showModal();
    loadCards(projectId, '');
  });
  partner.addEventListener('change', function () { loadCards('', partner.value); });
  dialog.querySelector('[data-handoff-close]').addEventListener('click', function () { dialog.close(); });
  dialog.addEventListener('cancel', function (event) { if (exporting) event.preventDefault(); });
  dialog.addEventListener('close', function () { requestId++; });
  pngButton.addEventListener('click', function () { exportCards('png'); });
  pdfButton.addEventListener('click', function () { exportCards('pdf'); });
}());
