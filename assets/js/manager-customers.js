(function () {
  'use strict';

  var root = document.querySelector('[data-crm-root]');
  var config = window.ziguratCrmConfig || {};
  if (!root || !config.ajaxUrl) return;

  var records = Array.isArray(config.records) ? config.records : [];
  var list = root.querySelector('[data-crm-list]');
  var empty = root.querySelector('[data-crm-empty]');
  var notice = root.querySelector('[data-crm-notice]');
  var editor = root.querySelector('[data-crm-editor]');
  var form = root.querySelector('[data-crm-form]');
  var logDialog = root.querySelector('[data-crm-log-dialog]');
  var logForm = root.querySelector('[data-crm-log-form]');
  var confirmDialog = root.querySelector('[data-crm-confirm]');
  var convertConfirmDialog = root.querySelector('[data-crm-convert-confirm]');
  var datePicker = root.querySelector('[data-crm-date-picker]');
  var datePickerTitle = root.querySelector('[data-crm-date-title]');
  var datePickerDays = root.querySelector('[data-crm-date-days]');
  var datePickerHour = root.querySelector('[data-crm-date-hour]');
  var datePickerMinute = root.querySelector('[data-crm-date-minute]');
  var search = root.querySelector('[data-crm-search]');
  var statusFilter = root.querySelector('[data-crm-status-filter]');
  var dueFilter = root.querySelector('[data-crm-due-filter]');

  function request(action, data) {
    var body = data instanceof FormData ? data : new FormData();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    return fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (response) { return response.json(); })
      .then(function (response) {
        if (!response || !response.success) throw new Error(response && response.data && response.data.message ? response.data.message : 'عملیات انجام نشد.');
        return response.data || {};
      });
  }

  function showNotice(message, type) {
    notice.textContent = message || '';
    notice.className = 'manager-crm__notice is-' + (type === 'error' ? 'error' : 'success');
    notice.hidden = !message;
    if (message) window.setTimeout(function () { notice.hidden = true; }, 5000);
  }

  function recordById(id) {
    id = Number(id || 0);
    return records.find(function (record) { return Number(record.id) === id; }) || null;
  }

  function make(tag, className, text) {
    var element = document.createElement(tag);
    if (className) element.className = className;
    if (typeof text !== 'undefined') element.textContent = text;
    return element;
  }

  function normalizeJalaliDateTime(field) {
    if (!field) return;
    var value = String(field.value || '').trim().replace(/[۰-۹٠-٩]/g, function (digit) {
      return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) >= 0
        ? String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
        : String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit));
    });
    value = value.replace(/[.\-]/g, '/').replace('T', ' ').replace(/\s+/g, ' ');
    if (/^\d{12}$/.test(value)) {
      value = value.slice(0, 4) + '/' + value.slice(4, 6) + '/' + value.slice(6, 8) + ' ' + value.slice(8, 10) + ':' + value.slice(10, 12);
    }
    var match = value.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$/);
    if (match) {
      value = match[1] + '/' + match[2].padStart(2, '0') + '/' + match[3].padStart(2, '0');
      if (match[4]) value += ' ' + match[4].padStart(2, '0') + ':' + match[5].padStart(2, '0');
    }
    field.value = value;
  }

  var jalaliMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  var activeDateField = null;
  var pickerValue = { year: 1405, month: 1, day: 1, hour: 9, minute: 0 };

  function integerDivide(first, second) { return Math.trunc(first / second); }
  function integerMod(first, second) { return first - Math.trunc(first / second) * second; }

  function jalaliCalculation(year, withoutLeap) {
    var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    var gregorianYear = year + 621;
    var leapJ = -14;
    var previous = breaks[0];
    var jump = 0;
    var current;
    var index;
    if (year < previous || year >= breaks[breaks.length - 1]) throw new Error('سال شمسی خارج از محدوده است.');
    for (index = 1; index < breaks.length; index++) {
      current = breaks[index];
      jump = current - previous;
      if (year < current) break;
      leapJ += integerDivide(jump, 33) * 8 + integerDivide(integerMod(jump, 33), 4);
      previous = current;
    }
    var distance = year - previous;
    leapJ += integerDivide(distance, 33) * 8 + integerDivide(integerMod(distance, 33) + 3, 4);
    if (integerMod(jump, 33) === 4 && jump - distance === 4) leapJ++;
    var leapG = integerDivide(gregorianYear, 4) - integerDivide((integerDivide(gregorianYear, 100) + 1) * 3, 4) - 150;
    var march = 20 + leapJ - leapG;
    if (withoutLeap) return { gregorianYear: gregorianYear, march: march };
    if (jump - distance < 6) distance = distance - jump + integerDivide(jump + 4, 33) * 33;
    var leap = integerMod(integerMod(distance + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;
    return { leap: leap, gregorianYear: gregorianYear, march: march };
  }

  function gregorianToDay(year, month, day) {
    var value = integerDivide((year + integerDivide(month - 8, 6) + 100100) * 1461, 4)
      + integerDivide(153 * integerMod(month + 9, 12) + 2, 5) + day - 34840408;
    return value - integerDivide(integerDivide(year + 100100 + integerDivide(month - 8, 6), 100) * 3, 4) + 752;
  }

  function dayToGregorian(dayNumber) {
    var value = 4 * dayNumber + 139361631;
    value = value + integerDivide(integerDivide(4 * dayNumber + 183187720, 146097) * 3, 4) * 4 - 3908;
    var offset = integerDivide(integerMod(value, 1461), 4) * 5 + 308;
    var day = integerDivide(integerMod(offset, 153), 5) + 1;
    var month = integerMod(integerDivide(offset, 153), 12) + 1;
    var year = integerDivide(value, 1461) - 100100 + integerDivide(8 - month, 6);
    return { year: year, month: month, day: day };
  }

  function jalaliToDay(year, month, day) {
    var calculation = jalaliCalculation(year, true);
    return gregorianToDay(calculation.gregorianYear, 3, calculation.march) + (month - 1) * 31 - integerDivide(month, 7) * (month - 7) + day - 1;
  }

  function dayToJalali(dayNumber) {
    var gregorian = dayToGregorian(dayNumber);
    var year = gregorian.year - 621;
    var calculation = jalaliCalculation(year, false);
    var firstFarvardin = gregorianToDay(gregorian.year, 3, calculation.march);
    var passed = dayNumber - firstFarvardin;
    if (passed >= 0) {
      if (passed <= 185) return { year: year, month: 1 + integerDivide(passed, 31), day: integerMod(passed, 31) + 1 };
      passed -= 186;
    } else {
      year--;
      passed += 179;
      if (calculation.leap === 1) passed++;
    }
    return { year: year, month: 7 + integerDivide(passed, 30), day: integerMod(passed, 30) + 1 };
  }

  function todayJalali() {
    var today = new Date();
    return dayToJalali(gregorianToDay(today.getFullYear(), today.getMonth() + 1, today.getDate()));
  }

  function jalaliMonthLength(year, month) {
    if (month <= 6) return 31;
    if (month <= 11) return 30;
    return jalaliCalculation(year, false).leap === 0 ? 30 : 29;
  }

  function persianNumber(value) {
    return String(value).replace(/\d/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]; });
  }

  function parsePickerValue(value) {
    var normalized = String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) {
      var persian = '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);
      return String(persian >= 0 ? persian : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit));
    });
    var match = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$/);
    if (!match) return null;
    var parsed = { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]), hour: Number(match[4] || 9), minute: Number(match[5] || 0) };
    if (parsed.month < 1 || parsed.month > 12 || parsed.day < 1 || parsed.day > jalaliMonthLength(parsed.year, parsed.month) || parsed.hour > 23 || parsed.minute > 59) return null;
    return parsed;
  }

  function timeWheelValue(type) {
    return type === 'hour' ? pickerValue.hour : pickerValue.minute;
  }

  function wrapTimeWheelValue(value, limit) {
    return ((value % limit) + limit) % limit;
  }

  function normalizeMinuteToFive(value) {
    return Math.min(55, Math.max(0, Math.round(Number(value || 0) / 5) * 5));
  }

  function renderTimeWheel(wheel) {
    if (!wheel) return;
    var type = wheel.dataset.crmTimeWheel;
    var current = timeWheelValue(type);
    if (type === 'minute') {
      current = normalizeMinuteToFive(current);
      pickerValue.minute = current;
    }
    var limit = type === 'hour' ? 24 : 60;
    var step = type === 'hour' ? 1 : 5;
    var valueTarget = wheel.querySelector('[data-crm-time-wheel-value]');
    var list = wheel.querySelector('[data-crm-time-wheel-list]');
    if (valueTarget) valueTarget.textContent = persianNumber(String(current).padStart(2, '0'));
    if (!list) return;
    list.innerHTML = '';
    for (var offset = -2; offset <= 2; offset++) {
      var value = wrapTimeWheelValue(current + (offset * step), limit);
      var option = make('button', 'manager-crm-time-wheel__option distance-' + Math.abs(offset), persianNumber(String(value).padStart(2, '0')));
      option.type = 'button';
      option.dataset.crmTimeOption = String(value);
      option.dataset.crmTimeType = type;
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', offset === 0 ? 'true' : 'false');
      option.tabIndex = offset === 0 ? 0 : -1;
      list.appendChild(option);
    }
  }

  function fillTimeOptions() {
    renderTimeWheel(datePickerHour);
    renderTimeWheel(datePickerMinute);
  }

  function closeTimeWheels(except) {
    if (!datePicker) return;
    datePicker.querySelectorAll('[data-crm-time-wheel]').forEach(function (wheel) {
      if (wheel === except) return;
      var list = wheel.querySelector('[data-crm-time-wheel-list]');
      var toggle = wheel.querySelector('[data-crm-time-wheel-toggle]');
      if (list) list.hidden = true;
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
  }

  function setTimeWheelValue(type, value) {
    if (type === 'hour') pickerValue.hour = wrapTimeWheelValue(Number(value), 24);
    if (type === 'minute') pickerValue.minute = normalizeMinuteToFive(wrapTimeWheelValue(Number(value), 60));
    renderTimeWheel(type === 'hour' ? datePickerHour : datePickerMinute);
  }

  function renderDatePicker() {
    var today = todayJalali();
    datePickerTitle.textContent = jalaliMonths[pickerValue.month - 1] + ' ' + persianNumber(pickerValue.year);
    datePickerDays.innerHTML = '';
    var gregorianFirst = dayToGregorian(jalaliToDay(pickerValue.year, pickerValue.month, 1));
    var firstWeekday = (new Date(gregorianFirst.year, gregorianFirst.month - 1, gregorianFirst.day).getDay() + 1) % 7;
    for (var blank = 0; blank < firstWeekday; blank++) datePickerDays.appendChild(make('span', 'is-empty'));
    var monthLength = jalaliMonthLength(pickerValue.year, pickerValue.month);
    for (var day = 1; day <= monthLength; day++) {
      var dayButton = make('button', '', persianNumber(day));
      dayButton.type = 'button';
      dayButton.dataset.crmDateDay = String(day);
      if (day === pickerValue.day) dayButton.classList.add('is-selected');
      if (pickerValue.year === today.year && pickerValue.month === today.month && day === today.day) dayButton.classList.add('is-today');
      datePickerDays.appendChild(dayButton);
    }
    fillTimeOptions();
  }

  function openDatePicker(field) {
    if (!datePicker || !field) return;
    activeDateField = field;
    var parsed = parsePickerValue(field.value);
    if (parsed) {
      pickerValue = parsed;
    } else {
      var now = new Date();
      now.setSeconds(0, 0);
      now.setMinutes(Math.ceil(now.getMinutes() / 5) * 5);
      var roundedDate = dayToJalali(gregorianToDay(now.getFullYear(), now.getMonth() + 1, now.getDate()));
      pickerValue = { year: roundedDate.year, month: roundedDate.month, day: roundedDate.day, hour: now.getHours(), minute: now.getMinutes() };
    }
    renderDatePicker();
    datePicker.showModal();
    document.documentElement.classList.add('has-crm-picker-open');
    document.body.classList.add('has-crm-picker-open');
  }

  function closeDatePicker() {
    if (datePicker && datePicker.open) datePicker.close();
  }

  function renderCard(record) {
    var card = make('article', 'manager-crm-card is-' + record.due_state + ' priority-' + record.priority);
    card.dataset.crmCard = String(record.id);
    card.dataset.status = record.status;
    card.dataset.due = record.due_state;

    var identityCell = make('div', 'manager-crm-card__identity');
    var header = make('header');
    var identity = make('div');
    identity.appendChild(make('span', 'manager-crm-card__priority', record.priority_label));
    identity.appendChild(make('h3', '', (record.prefix ? record.prefix + ' ' : '') + (record.name || record.company || 'بدون نام')));
    if (record.company && record.company !== record.name) identity.appendChild(make('p', '', record.company));
    var edit = make('button', 'manager-crm-card__edit', 'ویرایش');
    edit.type = 'button';
    edit.dataset.crmEdit = String(record.id);
    header.appendChild(identity);
    header.appendChild(edit);
    identityCell.appendChild(header);

    if (record.topic) identityCell.appendChild(make('strong', 'manager-crm-card__topic', record.topic));
    card.appendChild(identityCell);
    var meta = make('div', 'manager-crm-card__meta');
    var phone = make('a', '', record.phone || 'بدون شماره');
    phone.href = record.phone ? 'tel:' + record.phone.replace(/\s+/g, '') : '#';
    var due = make('span', 'manager-crm-card__due', record.next_label);
    meta.appendChild(phone);
    meta.appendChild(due);
    card.appendChild(meta);

    var info = make('div', 'manager-crm-card__info');
    info.appendChild(make('span', 'status-' + record.status, record.status_label));
    info.appendChild(make('small', '', 'مسئول: ' + record.owner_name));
    card.appendChild(info);

    if (record.history && record.history.length) {
      var last = record.history[0];
      var lastBox = make('div', 'manager-crm-card__last');
      lastBox.appendChild(make('small', '', 'آخرین پیگیری · ' + last.time));
      lastBox.appendChild(make('p', '', last.note || 'بدون توضیح'));
      card.appendChild(lastBox);
    } else {
      var emptyLastBox = make('div', 'manager-crm-card__last is-empty');
      emptyLastBox.appendChild(make('small', '', 'آخرین پیگیری'));
      emptyLastBox.appendChild(make('p', '', 'هنوز تماسی ثبت نشده است.'));
      card.appendChild(emptyLastBox);
    }

    var actions = make('footer');
    var log = make('button', 'is-primary', 'ثبت تماس');
    log.type = 'button';
    log.dataset.crmLog = String(record.id);
    actions.appendChild(log);
    if (record.phone) {
      var call = make('a', 'is-call', 'تماس');
      call.href = 'tel:' + record.phone.replace(/\s+/g, '');
      actions.appendChild(call);
    }
    if (!record.project_id && record.status !== 'lost') {
      var convert = make('button', '', 'ایجاد پروژه');
      convert.type = 'button';
      convert.dataset.crmConvert = String(record.id);
      actions.appendChild(convert);
    } else if (record.project_id) {
      actions.appendChild(make('span', 'manager-crm-card__project', 'پروژه ساخته شده'));
    }
    card.appendChild(actions);
    return card;
  }

  function render() {
    var term = (search.value || '').trim().toLowerCase();
    var status = statusFilter.value;
    var due = dueFilter.value;
    list.innerHTML = '';
    var visible = records.filter(function (record) {
      var haystack = [record.name, record.company, record.phone, record.topic].join(' ').toLowerCase();
      if (term && haystack.indexOf(term) === -1) return false;
      if (status && record.status !== status) return false;
      if (due === 'active' && ['new', 'contacting', 'waiting'].indexOf(record.status) === -1) return false;
      if (due !== 'all' && due !== 'active' && record.due_state !== due) return false;
      return true;
    });
    visible.forEach(function (record) { list.appendChild(renderCard(record)); });
    empty.hidden = visible.length > 0;
  }

  function updateSummary(summary) {
    if (!summary) return;
    ['overdue', 'today', 'upcoming', 'active'].forEach(function (key) {
      var target = root.querySelector('[data-crm-count="' + key + '"]');
      if (target) target.textContent = Number(summary[key] || 0).toLocaleString('fa-IR');
    });
  }

  function reloadRecords(message) {
    return request('zigurat_crm_list').then(function (data) {
      records = data.records || [];
      updateSummary(data.summary);
      render();
      if (message) showNotice(message, 'success');
    });
  }

  function setFormValue(name, value) {
    var field = form.elements[name];
    if (field) field.value = value == null ? '' : value;
  }

  function lockEditorBackground(locked) {
    document.documentElement.classList.toggle('has-crm-editor-open', locked);
    document.body.classList.toggle('has-crm-editor-open', locked);
  }

  function openEditor(record) {
    form.reset();
    root.querySelector('[data-crm-form-title]').textContent = record ? 'ویرایش مشتری' : 'مشتری جدید';
    root.querySelector('[data-crm-delete]').hidden = !record;
    ['customer_id', 'name', 'prefix', 'company', 'phone', 'topic', 'status', 'priority', 'owner_id', 'next_at', 'notes'].forEach(function (key) {
      var value = '';
      if (record) {
        if (key === 'customer_id') value = record.id;
        else if (key === 'next_at') value = record.next_input;
        else value = record[key];
      }
      setFormValue(key, value);
    });
    if (!record) {
      setFormValue('status', 'new');
      setFormValue('priority', 'normal');
      setFormValue('owner_id', String(config.currentUserId || 0));
    }
    var historyWrap = root.querySelector('[data-crm-history-wrap]');
    var history = root.querySelector('[data-crm-history]');
    history.innerHTML = '';
    if (record && record.history && record.history.length) {
      record.history.forEach(function (entry) {
        var item = make('li');
        item.appendChild(make('p', '', entry.note || 'بدون توضیح'));
        item.appendChild(make('small', '', entry.time + (entry.user ? ' · ' + entry.user : '')));
        history.appendChild(item);
      });
      historyWrap.hidden = false;
    } else {
      historyWrap.hidden = true;
    }
    editor.showModal();
    lockEditorBackground(true);
  }

  editor.addEventListener('close', function () { lockEditorBackground(false); });

  function openLog(record) {
    logForm.reset();
    logForm.elements.customer_id.value = record.id;
    logForm.elements.status.value = record.status;
    logForm.elements.next_at.value = record.next_input || '';
    root.querySelector('[data-crm-log-title]').textContent = 'پیگیری ' + (record.company || record.name);
    logDialog.showModal();
  }

  function askDelete() {
    return new Promise(function (resolve) {
      function closed() {
        confirmDialog.removeEventListener('close', closed);
        resolve(confirmDialog.returnValue === 'confirm');
      }
      confirmDialog.addEventListener('close', closed);
      confirmDialog.showModal();
    });
  }

  function askConvert(record) {
    if (!convertConfirmDialog) return Promise.resolve(false);
    var customerName = record ? (record.company || record.name || 'این مشتری') : 'این مشتری';
    var nameTarget = convertConfirmDialog.querySelector('[data-crm-convert-name]');
    if (nameTarget) nameTarget.textContent = customerName;
    return new Promise(function (resolve) {
      function closed() {
        convertConfirmDialog.removeEventListener('close', closed);
        resolve(convertConfirmDialog.returnValue === 'confirm');
      }
      convertConfirmDialog.addEventListener('close', closed);
      convertConfirmDialog.showModal();
    });
  }

  root.querySelectorAll('[data-crm-datetime]').forEach(function (field) {
    field.addEventListener('click', function () { openDatePicker(field); });
    field.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openDatePicker(field);
      }
    });
  });

  if (datePicker) {
    datePicker.addEventListener('click', function (event) {
      var wheelToggle = event.target.closest('[data-crm-time-wheel-toggle]');
      if (wheelToggle) {
        var wheel = wheelToggle.closest('[data-crm-time-wheel]');
        var wheelList = wheel.querySelector('[data-crm-time-wheel-list]');
        var opening = wheelList.hidden;
        closeTimeWheels(opening ? wheel : null);
        wheelList.hidden = !opening;
        wheelToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) {
          renderTimeWheel(wheel);
          window.requestAnimationFrame(function () {
            var selected = wheelList.querySelector('[aria-selected="true"]');
            if (selected) selected.focus({ preventScroll: true });
          });
        }
        return;
      }
      var timeOption = event.target.closest('[data-crm-time-option]');
      if (timeOption) {
        var optionWheel = timeOption.closest('[data-crm-time-wheel]');
        setTimeWheelValue(timeOption.dataset.crmTimeType, timeOption.dataset.crmTimeOption);
        window.requestAnimationFrame(function () {
          var selected = optionWheel.querySelector('[aria-selected="true"]');
          if (selected) selected.focus({ preventScroll: true });
        });
        return;
      }
      if (!event.target.closest('[data-crm-time-wheel]')) closeTimeWheels();
      var button = event.target.closest('button');
      if (!button) return;
      if (button.matches('[data-crm-date-prev],[data-crm-date-next]')) {
        var change = button.matches('[data-crm-date-prev]') ? -1 : 1;
        pickerValue.month += change;
        if (pickerValue.month < 1) { pickerValue.month = 12; pickerValue.year--; }
        if (pickerValue.month > 12) { pickerValue.month = 1; pickerValue.year++; }
        pickerValue.day = Math.min(pickerValue.day, jalaliMonthLength(pickerValue.year, pickerValue.month));
        renderDatePicker();
      }
      if (button.matches('[data-crm-date-day]')) {
        pickerValue.day = Number(button.dataset.crmDateDay);
        renderDatePicker();
      }
      if (button.matches('[data-crm-date-today]')) {
        var today = todayJalali();
        pickerValue.year = today.year;
        pickerValue.month = today.month;
        pickerValue.day = today.day;
        renderDatePicker();
      }
      if (button.matches('[data-crm-date-clear]')) {
        if (activeDateField) {
          activeDateField.value = '';
          activeDateField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closeDatePicker();
      }
      if (button.matches('[data-crm-date-cancel]')) closeDatePicker();
      if (button.matches('[data-crm-date-apply]')) {
        if (activeDateField) {
          activeDateField.value = String(pickerValue.year).padStart(4, '0') + '/' + String(pickerValue.month).padStart(2, '0') + '/' + String(pickerValue.day).padStart(2, '0') + ' ' + String(pickerValue.hour).padStart(2, '0') + ':' + String(pickerValue.minute).padStart(2, '0');
          activeDateField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closeDatePicker();
      }
    });
    datePicker.addEventListener('keydown', function (event) {
      var wheel = event.target.closest('[data-crm-time-wheel]');
      if (!wheel) return;
      var type = wheel.dataset.crmTimeWheel;
      var step = type === 'hour' ? 1 : 5;
      if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault();
        setTimeWheelValue(type, timeWheelValue(type) + (event.key === 'ArrowUp' ? -step : step));
        window.requestAnimationFrame(function () {
          var selected = wheel.querySelector('[aria-selected="true"]');
          if (selected) selected.focus({ preventScroll: true });
        });
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        closeTimeWheels();
        var toggle = wheel.querySelector('[data-crm-time-wheel-toggle]');
        if (toggle) toggle.focus({ preventScroll: true });
      }
    });
    datePicker.querySelectorAll('[data-crm-time-wheel-list]').forEach(function (wheelList) {
      wheelList.addEventListener('wheel', function (event) {
        event.preventDefault();
        var wheel = wheelList.closest('[data-crm-time-wheel]');
        var type = wheel.dataset.crmTimeWheel;
        var step = type === 'hour' ? 1 : 5;
        setTimeWheelValue(type, timeWheelValue(type) + (event.deltaY > 0 ? step : -step));
      }, { passive: false });
    });
    datePicker.addEventListener('close', function () {
      closeTimeWheels();
      document.documentElement.classList.remove('has-crm-picker-open');
      document.body.classList.remove('has-crm-picker-open');
      activeDateField = null;
    });
  }

  root.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (!button) return;
    if (button.matches('[data-crm-datetime-open]')) {
      openDatePicker(button.parentElement.querySelector('[data-crm-datetime]'));
      return;
    }
    if (button.matches('[data-crm-new]')) openEditor(null);
    if (button.matches('[data-crm-close]')) editor.close();
    if (button.matches('[data-crm-log-close]')) logDialog.close();
    if (button.matches('[data-crm-edit]')) openEditor(recordById(button.dataset.crmEdit));
    if (button.matches('[data-crm-log]')) openLog(recordById(button.dataset.crmLog));
    if (button.matches('[data-crm-quick-filter]')) {
      dueFilter.value = button.dataset.crmQuickFilter;
      render();
    }
    if (button.matches('[data-crm-convert]')) {
      var customerId = button.dataset.crmConvert;
      askConvert(recordById(customerId)).then(function (accepted) {
        if (!accepted) return;
        var convertData = new FormData();
        convertData.set('customer_id', customerId);
        button.disabled = true;
        return request('zigurat_crm_convert_project', convertData).then(function (data) {
          return reloadRecords(data.message);
        }).catch(function (error) {
          showNotice(error.message, 'error');
        }).finally(function () {
          button.disabled = false;
        });
      });
    }
    if (button.matches('[data-crm-delete]')) {
      askDelete().then(function (accepted) {
        if (!accepted) return;
        var deleteData = new FormData();
        deleteData.set('customer_id', form.elements.customer_id.value);
        return request('zigurat_crm_delete', deleteData).then(function (data) {
          editor.close();
          return reloadRecords(data.message);
        });
      }).catch(function (error) { showNotice(error.message, 'error'); });
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    normalizeJalaliDateTime(form.elements.next_at);
    var saveButton = form.querySelector('[type="submit"]');
    saveButton.disabled = true;
    request('zigurat_crm_save', new FormData(form)).then(function (data) {
      editor.close();
      return reloadRecords(data.message);
    }).catch(function (error) { showNotice(error.message, 'error'); }).finally(function () { saveButton.disabled = false; });
  });

  logForm.addEventListener('submit', function (event) {
    event.preventDefault();
    normalizeJalaliDateTime(logForm.elements.next_at);
    var saveButton = logForm.querySelector('[type="submit"]');
    saveButton.disabled = true;
    request('zigurat_crm_log', new FormData(logForm)).then(function (data) {
      logDialog.close();
      return reloadRecords(data.message);
    }).catch(function (error) { showNotice(error.message, 'error'); }).finally(function () { saveButton.disabled = false; });
  });

  [search, statusFilter, dueFilter].forEach(function (control) {
    control.addEventListener(control === search ? 'input' : 'change', render);
  });

  root.querySelectorAll('input[name="next_at"]').forEach(function (field) {
    field.addEventListener('blur', function () { normalizeJalaliDateTime(field); });
  });

  function applicationServerKey(value) {
    if (!value || typeof value !== 'string') throw new Error('کلید اعلان سایت ساخته نشده است؛ تنظیمات OpenSSL سرور را بررسی کنید.');
    var padding = '='.repeat((4 - value.length % 4) % 4);
    var raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    var key = Uint8Array.from(raw, function (char) { return char.charCodeAt(0); });
    if (key.length !== 65 || key[0] !== 4) throw new Error('کلید اعلان سایت معتبر نیست؛ کلید VAPID باید دوباره ساخته شود.');
    return key;
  }

  function enablePush(button) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      showNotice('مرورگر این دستگاه از اعلان تحت وب پشتیبانی نمی‌کند.', 'error');
      return;
    }
    if (!window.isSecureContext) {
      showNotice('برای اعلان این دستگاه باید سایت با HTTPS باز شود.', 'error');
      return;
    }
    button.disabled = true;
    Notification.requestPermission().then(function (permission) {
      if (permission !== 'granted') throw new Error('اجازه اعلان داده نشد. آن را از تنظیمات مرورگر فعال کنید.');
      return navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: config.serviceWorkerScope || '/' });
    }).then(function (registration) {
      return registration.pushManager.getSubscription().then(function (subscription) {
        return subscription || registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: applicationServerKey(config.vapidPublicKey) });
      });
    }).then(function (subscription) {
      var data = new FormData();
      data.set('subscription', JSON.stringify(subscription.toJSON()));
      return request('zigurat_crm_push_subscribe', data);
    }).then(function (data) {
      button.classList.add('is-enabled');
      button.querySelector('b').textContent = 'اعلان این دستگاه فعال است';
      showNotice(data.message, 'success');
    }).catch(function (error) {
      showNotice(error.message || 'فعال‌سازی اعلان انجام نشد.', 'error');
    }).finally(function () { button.disabled = false; });
  }

  var pushButton = root.querySelector('[data-crm-push]');
  if (pushButton) pushButton.addEventListener('click', function () { enablePush(pushButton); });
  if (pushButton && 'serviceWorker' in navigator && 'PushManager' in window && Notification.permission === 'granted') {
    navigator.serviceWorker.getRegistration(config.serviceWorkerScope || '/').then(function (registration) {
      if (!registration) return null;
      return registration.pushManager.getSubscription();
    }).then(function (subscription) {
      if (!subscription) return;
      pushButton.classList.add('is-enabled');
      pushButton.querySelector('b').textContent = 'اعلان این دستگاه فعال است';
    }).catch(function () {});
  }

  render();
  var requestedCustomer = new URLSearchParams(window.location.search).get('customer');
  if (requestedCustomer && recordById(requestedCustomer)) openEditor(recordById(requestedCustomer));
}());
