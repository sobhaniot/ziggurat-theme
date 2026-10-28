(function () {
'use strict';
window.ziguratFollowupPicker = function(root) {
if (!root || root.dataset.followupPickerReady === '1') return;
root.dataset.followupPickerReady = '1';
  var datePicker = root.querySelector('[data-crm-date-picker]');
  var datePickerTitle = root.querySelector('[data-crm-date-title]');
  var datePickerDays = root.querySelector('[data-crm-date-days]');
  var datePickerHour = root.querySelector('[data-crm-date-hour]');
  var datePickerMinute = root.querySelector('[data-crm-date-minute]');
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
    var today = currentTehranDate();
    return dayToJalali(gregorianToDay(today.getFullYear(), today.getMonth() + 1, today.getDate()));
  }

  function currentTehranDate() {
    var parts = new Intl.DateTimeFormat('en-GB', {timeZone:'Asia/Tehran',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date());
    var values = {};
    parts.forEach(function (part) { values[part.type] = part.value; });
    return new Date(Number(values.year), Number(values.month)-1, Number(values.day), Number(values.hour), Number(values.minute));
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
      var now = currentTehranDate();
      now.setSeconds(0, 0);
      now.setMinutes(Math.ceil(now.getMinutes() / 5) * 5);
      var roundedDate = dayToJalali(gregorianToDay(now.getFullYear(), now.getMonth() + 1, now.getDate()));
      pickerValue = { year: roundedDate.year, month: roundedDate.month, day: roundedDate.day, hour: now.getHours(), minute: now.getMinutes() };
    }
    renderDatePicker();
    var timePanel = datePicker.querySelector('.manager-crm-date-picker__time');
    if (timePanel) timePanel.hidden = field.hasAttribute('data-date-only');
    var applyButton = datePicker.querySelector('[data-crm-date-apply]');
    if (applyButton) applyButton.textContent = field.hasAttribute('data-date-only') ? 'تأیید تاریخ' : 'تأیید زمان';
    datePicker.showModal();
    document.documentElement.classList.add('has-crm-picker-open');
    document.body.classList.add('has-crm-picker-open');
  }

  function closeDatePicker() {
    if (datePicker && datePicker.open) datePicker.close();
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
          activeDateField.value = String(pickerValue.year).padStart(4, '0') + '/' + String(pickerValue.month).padStart(2, '0') + '/' + String(pickerValue.day).padStart(2, '0');
          if (!activeDateField.hasAttribute('data-date-only')) activeDateField.value += ' ' + String(pickerValue.hour).padStart(2, '0') + ':' + String(pickerValue.minute).padStart(2, '0');
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


root.addEventListener('click',function(event){var button=event.target.closest('[data-crm-datetime-open]');if(button)openDatePicker(button.parentElement.querySelector('[data-crm-datetime]'));});
};
window.ziguratFollowupPush = function(root,config,showNotice){
if (!root || root.dataset.followupPushReady === '1') return;
root.dataset.followupPushReady = '1';
function request(action,data){data.set('action',action);data.set('nonce',config.pushNonce || config.nonce || '');return fetch(config.ajaxUrl,{method:'POST',credentials:'same-origin',body:data}).then(function(r){return r.json();}).then(function(r){if(!r.success)throw new Error(r.data && r.data.message || 'فعال‌سازی اعلان انجام نشد.');return r.data;});}
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
  if (pushButton && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && Notification.permission === 'granted') {
    navigator.serviceWorker.getRegistration(config.serviceWorkerScope || '/').then(function (registration) {
      if (!registration) return null;
      return registration.pushManager.getSubscription();
    }).then(function (subscription) {
      if (!subscription) return;
      pushButton.classList.add('is-enabled');
      pushButton.querySelector('b').textContent = 'اعلان این دستگاه فعال است';
    }).catch(function () {});
  }

};
}());
