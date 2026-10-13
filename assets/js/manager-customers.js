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

  function renderCard(record) {
    var card = make('article', 'manager-crm-card is-' + record.due_state + ' priority-' + record.priority);
    card.dataset.crmCard = String(record.id);
    card.dataset.status = record.status;
    card.dataset.due = record.due_state;

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
    card.appendChild(header);

    if (record.topic) card.appendChild(make('strong', 'manager-crm-card__topic', record.topic));
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

  function openEditor(record) {
    form.reset();
    root.querySelector('[data-crm-form-title]').textContent = record ? 'ویرایش مشتری' : 'مشتری جدید';
    root.querySelector('[data-crm-delete]').hidden = !record;
    ['customer_id', 'name', 'prefix', 'company', 'phone', 'topic', 'status', 'priority', 'owner_id', 'next_at', 'notes'].forEach(function (key) {
      setFormValue(key, record ? (key === 'next_at' ? record.next_input : record[key]) : '');
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
  }

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

  root.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (!button) return;
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
      var convertData = new FormData();
      convertData.set('customer_id', button.dataset.crmConvert);
      button.disabled = true;
      request('zigurat_crm_convert_project', convertData).then(function (data) {
        return reloadRecords(data.message);
      }).catch(function (error) { showNotice(error.message, 'error'); }).finally(function () { button.disabled = false; });
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
    var saveButton = form.querySelector('[type="submit"]');
    saveButton.disabled = true;
    request('zigurat_crm_save', new FormData(form)).then(function (data) {
      editor.close();
      return reloadRecords(data.message);
    }).catch(function (error) { showNotice(error.message, 'error'); }).finally(function () { saveButton.disabled = false; });
  });

  logForm.addEventListener('submit', function (event) {
    event.preventDefault();
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

  function applicationServerKey(value) {
    var padding = '='.repeat((4 - value.length % 4) % 4);
    var raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, function (char) { return char.charCodeAt(0); });
  }

  function enablePush(button) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      showNotice('مرورگر این دستگاه از اعلان تحت وب پشتیبانی نمی‌کند.', 'error');
      return;
    }
    if (!window.isSecureContext) {
      showNotice('برای اعلان گوشی باید سایت با HTTPS باز شود.', 'error');
      return;
    }
    button.disabled = true;
    Notification.requestPermission().then(function (permission) {
      if (permission !== 'granted') throw new Error('اجازه اعلان داده نشد. آن را از تنظیمات مرورگر فعال کنید.');
      return navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: '/' });
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
      button.querySelector('b').textContent = 'اعلان گوشی فعال است';
      showNotice(data.message, 'success');
    }).catch(function (error) {
      showNotice(error.message || 'فعال‌سازی اعلان انجام نشد.', 'error');
    }).finally(function () { button.disabled = false; });
  }

  var pushButton = root.querySelector('[data-crm-push]');
  if (pushButton) pushButton.addEventListener('click', function () { enablePush(pushButton); });
  if (pushButton && 'serviceWorker' in navigator && 'PushManager' in window && Notification.permission === 'granted') {
    navigator.serviceWorker.getRegistration('/').then(function (registration) {
      if (!registration) return null;
      return registration.pushManager.getSubscription();
    }).then(function (subscription) {
      if (!subscription) return;
      pushButton.classList.add('is-enabled');
      pushButton.querySelector('b').textContent = 'اعلان گوشی فعال است';
    }).catch(function () {});
  }

  render();
  var requestedCustomer = new URLSearchParams(window.location.search).get('customer');
  if (requestedCustomer && recordById(requestedCustomer)) openEditor(recordById(requestedCustomer));
}());
