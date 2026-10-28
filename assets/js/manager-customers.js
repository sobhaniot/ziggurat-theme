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
  var projectCreatedDialog = root.querySelector('[data-crm-project-created]');
  var search = root.querySelector('[data-crm-search]');
  var statusFilter = root.querySelector('[data-crm-status-filter]');
  var dueFilter = root.querySelector('[data-crm-due-filter]');
  var currentView = 'followups';
  var directoryRecords = [];
  var directoryPage = 1;
  var directoryPages = 1;
  var directoryRequest = 0;
  var directoryTimer;
  var pagination = root.querySelector('[data-crm-pages]');
  var restartingCustomer = false;
  var directory = root.querySelector('[data-crm-directory]');
  var directoryBody = root.querySelector('[data-crm-directory-body]');
  var profile = root.querySelector('[data-crm-profile]');

  function openProfile(record) {
    if (!record || !profile) return;
    root.querySelector('[data-crm-profile-title]').textContent = record.company || record.name || 'بدون نام';
    var body = root.querySelector('[data-crm-profile-body]');
    body.replaceChildren();
    var details = make('dl', 'manager-crm-profile__details');
    [['نام مخاطب', (record.prefix ? record.prefix + ' ' : '') + (record.name || '—')], ['شرکت', record.company], ['شماره تماس', record.phone], ['موضوع', record.topic], ['وضعیت', record.status_label], ['اولویت', record.priority_label], ['مسئول پیگیری', record.owner_name], ['پیگیری بعدی', record.next_label]].forEach(function (pair) {
      details.appendChild(make('dt', '', pair[0]));
      details.appendChild(make('dd', '', pair[1] || '—'));
    });
    body.appendChild(details);
    body.appendChild(make('h4', '', 'توضیحات ثابت'));
    body.appendChild(make('p', 'manager-crm-profile__notes', record.notes || 'توضیحی ثبت نشده است.'));
    if (record.project_id) {
      var project = make('a', 'manager-crm-profile__project', 'مشاهده پروژه مرتبط');
      var url = new URL(window.location.href);
      url.search = '';
      url.searchParams.set('manager-section', 'projects');
      url.searchParams.set('project', String(record.project_id));
      project.href = url.href;
      body.appendChild(project);
    }
    body.appendChild(make('h4', '', 'تاریخچه ارتباط و اقدامات'));
    var history = make('ol', 'manager-crm-profile__history');
    (record.history || []).forEach(function (entry) {
      var item = make('li');
      item.appendChild(make('p', '', entry.note || 'بدون توضیح'));
      item.appendChild(make('small', '', [entry.time, entry.user].filter(Boolean).join(' · ')));
      history.appendChild(item);
    });
    if (!history.children.length) body.appendChild(make('p', '', 'هنوز اقدامی ثبت نشده است.'));
    else body.appendChild(history);
    profile.showModal();
    lockEditorBackground(true);
  }
  if (profile) profile.addEventListener('close', function () { lockEditorBackground(false); });

  function renderCustomerRow(record) {
    var row = make('tr');
    [record.company || record.name || 'بدون نام', record.phone, record.topic, record.status_label, record.owner_name].forEach(function (value) { row.appendChild(make('td', '', value || '—')); });
    var cell = make('td');
    var button = make('button', '', 'مشاهده پرونده');
    button.type = 'button';
    button.dataset.crmProfile = String(record.id);
    cell.appendChild(button);
    if (['won', 'lost'].indexOf(record.status) !== -1) {
      var restart = make('button', '', 'شروع مجدد پیگیری');
      restart.type = 'button';
      restart.dataset.crmRestart = String(record.id);
      cell.appendChild(restart);
    }
    row.appendChild(cell);
    return row;
  }

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
    return directoryRecords.concat(records).find(function (record) { return Number(record.id) === id; }) || null;
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
    if (pagination) pagination.hidden = currentView !== 'customers';
    if (currentView === 'customers' && pagination) {
      list.hidden = true;
      directory.hidden = false;
      loadDirectory();
      return;
    }
    directoryRequest++;
    var term = (search.value || '').trim().toLowerCase();
    var status = statusFilter.value;
    var due = dueFilter.value;
    list.innerHTML = '';
    if (directoryBody) directoryBody.replaceChildren();
    list.hidden = currentView === 'customers';
    if (directory) directory.hidden = currentView !== 'customers';
    var visible = records.filter(function (record) {
      var haystack = [record.name, record.company, record.phone, record.topic].join(' ').toLowerCase();
      if (term && haystack.indexOf(term) === -1) return false;
      if (status && record.status !== status) return false;
      if (due === 'active' && ['new', 'contacting', 'waiting'].indexOf(record.status) === -1) return false;
      if (due !== 'all' && due !== 'active' && record.due_state !== due) return false;
      return true;
    });
    if (currentView === 'customers') visible.sort(function (a, b) {
      return (Number(b.last_activity) || 0) - (Number(a.last_activity) || 0) || Number(b.id) - Number(a.id);
    });
    visible.forEach(function (record) {
      if (currentView === 'customers' && directoryBody) directoryBody.appendChild(renderCustomerRow(record));
      else list.appendChild(renderCard(record));
    });
    empty.hidden = visible.length > 0;
  }

  function loadDirectory() {
    var sequence = ++directoryRequest;
    var data = new FormData();
    data.set('page', String(directoryPage));
    data.set('search', search.value.trim());
    data.set('status', statusFilter.value);
    data.set('due', dueFilter.value);
    directory.setAttribute('aria-busy', 'true');
    directoryBody.replaceChildren();
    empty.hidden = true;
    root.querySelector('[data-crm-page-info]').textContent = 'در حال دریافت…';
    root.querySelector('[data-crm-page-prev]').disabled = true;
    root.querySelector('[data-crm-page-next]').disabled = true;
    return request('zigurat_crm_directory', data).then(function (result) {
      if (sequence !== directoryRequest || currentView !== 'customers') return;
      directoryRecords = result.records || [];
      directoryPage = result.page;
      directoryPages = result.pages;
      directoryRecords.forEach(function (record) { directoryBody.appendChild(renderCustomerRow(record)); });
      empty.hidden = directoryRecords.length > 0;
      root.querySelector('[data-crm-page-info]').textContent = 'صفحه ' + Number(directoryPage).toLocaleString('fa-IR') + ' از ' + Number(directoryPages).toLocaleString('fa-IR') + ' · ' + Number(result.total).toLocaleString('fa-IR') + ' مشتری';
      root.querySelector('[data-crm-page-prev]').disabled = directoryPage <= 1;
      root.querySelector('[data-crm-page-next]').disabled = directoryPage >= directoryPages;
    }).catch(function (error) {
      if (sequence === directoryRequest && currentView === 'customers') {
        root.querySelector('[data-crm-page-info]').textContent = 'دریافت لیست انجام نشد؛ دوباره جستجو کنید.';
        showNotice(error.message, 'error');
      }
    }).finally(function () { if (sequence === directoryRequest) directory.removeAttribute('aria-busy'); });
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
      directoryRecords = [];
      updateSummary(data.summary);
      render();
      if (message) showNotice(message, 'success');
    });
  }

  function setFormValue(name, value) {
    var field = form.elements[name];
    if (field) field.value = value == null ? '' : value;
  }

  var contactSequence = 0;
  var contactTimer;
  function closeContactSuggestions() {
    contactSequence++;
    clearTimeout(contactTimer);
    root.querySelectorAll('[data-crm-contact-results]').forEach(function (panel) { panel.hidden = true; });
    root.querySelectorAll('[data-crm-contact-search]').forEach(function (input) { input.setAttribute('aria-expanded', 'false'); });
  }
  function applyContact(contact) {
    // Copy identity only: never reuse customer/project IDs or another job's data.
    ['prefix', 'name', 'company', 'phone'].forEach(function (key) { setFormValue(key, contact[key] || ''); });
    closeContactSuggestions();
  }
  root.querySelectorAll('[data-crm-contact-search]').forEach(function (input) {
    var panel = root.querySelector('[data-crm-contact-results="' + input.dataset.crmContactSearch + '"]');
    input.addEventListener('input', function () {
      closeContactSuggestions();
      if (form.elements.customer_id.value || input.value.trim().length < 2) return;
      var sequence = contactSequence;
      var term = input.value.trim();
      contactTimer = window.setTimeout(function () {
        var data = new FormData();
        data.set('term', term);
        data.set('field', input.dataset.crmContactSearch);
        request('zigurat_crm_contacts', data).then(function (result) {
          if (sequence !== contactSequence || !editor.open || input.value.trim() !== term) return;
          panel.replaceChildren();
          (result.contacts || []).forEach(function (contact) {
            var button = make('button');
            button.type = 'button';
            button.appendChild(make('strong', '', [contact.company, contact.name].filter(Boolean).join(' · ')));
            button.appendChild(make('small', '', contact.phone || 'بدون شماره'));
            button.addEventListener('click', function () { applyContact(contact); });
            panel.appendChild(button);
          });
          if (!panel.children.length) panel.appendChild(make('p', '', 'مخاطب قبلی پیدا نشد؛ مشخصات جدید را وارد کنید.'));
          panel.hidden = false;
          input.setAttribute('aria-expanded', 'true');
        }).catch(function () {
          if (sequence !== contactSequence || !editor.open) return;
          panel.replaceChildren(make('p', '', 'دریافت پیشنهادها ممکن نشد؛ می‌توانید مشخصات را دستی وارد کنید.'));
          panel.hidden = false;
          input.setAttribute('aria-expanded', 'true');
        });
      }, 300);
    });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); event.stopPropagation(); closeContactSuggestions(); }
      if (event.key === 'ArrowDown' && !panel.hidden) {
        var first = panel.querySelector('button');
        if (first) { event.preventDefault(); first.focus(); }
      }
    });
  });
  document.addEventListener('click', function (event) { if (!event.target.closest('.manager-crm-lookup')) closeContactSuggestions(); });
  editor.addEventListener('close', closeContactSuggestions);

  function lockEditorBackground(locked) {
    document.documentElement.classList.toggle('has-crm-editor-open', locked);
    document.body.classList.toggle('has-crm-editor-open', locked);
  }

  function openEditor(record, restart) {
    closeContactSuggestions();
    restartingCustomer = !!restart;
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
    if (form.elements.repeat_daily) form.elements.repeat_daily.checked = !!(record && record.repeat_daily);
    Array.from(form.elements.status.options).forEach(function (option) {
      option.disabled = !!restart && ['new', 'contacting', 'waiting'].indexOf(option.value) === -1;
    });
    if (restart) {
      setFormValue('status', 'new');
      setFormValue('next_at', '');
      if (form.elements.repeat_daily) form.elements.repeat_daily.checked = false;
      root.querySelector('[data-crm-form-title]').textContent = 'شروع مجدد پیگیری مشتری';
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

  editor.addEventListener('close', function () { lockEditorBackground(!!(projectCreatedDialog && projectCreatedDialog.open)); });

  function openLog(record) {
    logForm.reset();
    logForm.elements.customer_id.value = record.id;
    logForm.elements.status.value = record.status;
    logForm.elements.next_at.value = record.next_input || '';
    if (logForm.elements.repeat_daily) logForm.elements.repeat_daily.checked = !!record.repeat_daily;
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

  function showProjectCreated(data) {
    // Never announce creation when the server failed or did not return a project.
    if (!data || !Number(data.project_id) || !projectCreatedDialog || projectCreatedDialog.open) return;
    try {
      window.localStorage.setItem('zigurat_workflow_event', JSON.stringify({type:'project-created', projectId:Number(data.project_id), timestamp:Date.now()}));
    } catch (error) { /* The project is saved even when browser storage is unavailable. */ }
    projectCreatedDialog.showModal();
    lockEditorBackground(true);
  }
  if (projectCreatedDialog) {
    projectCreatedDialog.addEventListener('close', function () { lockEditorBackground(false); });
  }

  window.ziguratFollowupPicker(root);

  root.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (!button) return;
    if (button.matches('[data-crm-page-prev],[data-crm-page-next]')) {
      directoryPage = Math.max(1, Math.min(directoryPages, directoryPage + (button.matches('[data-crm-page-next]') ? 1 : -1)));
      render();
    }
    if (button.matches('[data-crm-view]')) {
      currentView = button.dataset.crmView;
      directoryPage = 1;
      statusFilter.value = '';
      dueFilter.value = currentView === 'customers' ? 'all' : 'active';
      root.querySelectorAll('[data-crm-view]').forEach(function (tab) { tab.setAttribute('aria-pressed', String(tab.dataset.crmView === currentView)); });
      render();
    }
    if (button.matches('[data-crm-profile]')) {
      var profileData = new FormData();
      profileData.set('customer_id', button.dataset.crmProfile);
      button.disabled = true;
      request('zigurat_crm_profile', profileData).then(function (data) { openProfile(data.record); })
        .catch(function (error) { showNotice(error.message, 'error'); })
        .finally(function () { button.disabled = false; });
    }
    if (button.matches('[data-crm-profile-close]')) profile.close();
    if (button.matches('[data-crm-new]')) openEditor(null);
    if (button.matches('[data-crm-restart]')) openEditor(recordById(button.dataset.crmRestart), true);
    if (button.matches('[data-crm-close]')) editor.close();
    if (button.matches('[data-crm-log-close]')) logDialog.close();
    if (button.matches('[data-crm-edit]')) openEditor(recordById(button.dataset.crmEdit));
    if (button.matches('[data-crm-log]')) openLog(recordById(button.dataset.crmLog));
    if (button.matches('[data-crm-quick-filter]')) {
      directoryPage = 1;
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
          showProjectCreated(data);
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

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    normalizeJalaliDateTime(form.elements.next_at);
    var saveButton = form.querySelector('[type="submit"]');
    if (form.elements.status.value === 'won' && !(recordById(form.elements.customer_id.value) || {}).project_id) {
      var accepted = await askConvert({name: form.elements.name.value, company: form.elements.company.value});
      if (!accepted) return;
    }
    saveButton.disabled = true;
    request('zigurat_crm_save', new FormData(form)).then(function (data) {
      editor.close();
      if (restartingCustomer) {
        currentView = 'followups';
        dueFilter.value = 'active';
        statusFilter.value = '';
        search.value = '';
        root.querySelectorAll('[data-crm-view]').forEach(function (tab) { tab.setAttribute('aria-pressed', String(tab.dataset.crmView === currentView)); });
        restartingCustomer = false;
      }
      showProjectCreated(data);
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
    control.addEventListener(control === search ? 'input' : 'change', function () {
      directoryPage = 1;
      clearTimeout(directoryTimer);
      if (control === search && currentView === 'customers') {
        directoryRequest++;
        directoryTimer = window.setTimeout(render, 300);
      } else render();
    });
  });

  root.querySelectorAll('input[name="next_at"]').forEach(function (field) {
    field.addEventListener('blur', function () { normalizeJalaliDateTime(field); });
  });

  window.ziguratFollowupPush(root, config, showNotice);
  render();
  var requestedCustomer = new URLSearchParams(window.location.search).get('customer');
  if (requestedCustomer && recordById(requestedCustomer)) openEditor(recordById(requestedCustomer));
}());
