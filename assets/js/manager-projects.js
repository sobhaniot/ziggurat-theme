(function () {
  'use strict';

  var board = document.querySelector('[data-project-board]');
  var config = window.ziguratWorkflowProjects || {};
  if (!board || !config.ajaxUrl || !config.nonce) return;

  var dialog = board.querySelector('[data-project-dialog]');
  var form = board.querySelector('[data-project-form]');
  var notice = board.querySelector('[data-project-notice]');
  var destructiveActions = form.querySelector('[data-project-destructive-actions]');
  var archiveButton = form.querySelector('[data-project-archive-action]');
  var deleteButton = form.querySelector('[data-project-delete-action]');
  var draggedCard = null;
  var originalStage = '';
  var touchState = null;
  var mobileBoardSwipe = null;
  var mobileStageNav = null;
  var mobileActiveColumn = null;
  var suppressCardClickUntil = 0;
  var confirmDialog = null;
  var confirmResolver = null;
  var confirmLastFocus = null;
  var undoStack = [];
  var redoStack = [];
  var historyBusy = false;
  var undoButton = board.querySelector('[data-project-undo]');
  var redoButton = board.querySelector('[data-project-redo]');

  function syncProjectHistoryButtons() {
    if (undoButton) undoButton.disabled = historyBusy || undoStack.length === 0;
    if (redoButton) redoButton.disabled = historyBusy || redoStack.length === 0;
  }

  function clearProjectHistory() {
    undoStack = [];
    redoStack = [];
    syncProjectHistoryButtons();
  }

  function ensureProjectConfirmDialog() {
    if (confirmDialog && document.documentElement.contains(confirmDialog)) return confirmDialog;
    var wrapper = document.createElement('dialog');
    wrapper.className = 'manager-project-confirm-wrap';
    wrapper.innerHTML = '<div class="manager-project-confirm__backdrop" data-project-confirm-cancel></div>' +
      '<section class="manager-project-confirm" role="dialog" aria-modal="true" aria-labelledby="manager-project-confirm-title" aria-describedby="manager-project-confirm-message">' +
        '<div class="manager-project-confirm__icon" data-project-confirm-icon aria-hidden="true">!</div>' +
        '<h3 id="manager-project-confirm-title"></h3>' +
        '<p id="manager-project-confirm-message"></p>' +
        '<div class="manager-project-confirm__actions"><button type="button" data-project-confirm-cancel>انصراف</button><button type="button" data-project-confirm-accept></button></div>' +
      '</section>';
    document.body.appendChild(wrapper);
    wrapper.addEventListener('click', function (event) {
      if (event.target.closest('[data-project-confirm-accept]')) closeProjectConfirm(true);
      else if (event.target.closest('[data-project-confirm-cancel]')) closeProjectConfirm(false);
    });
    wrapper.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeProjectConfirm(false);
      }
    });
    wrapper.addEventListener('cancel', function (event) {
      event.preventDefault();
      closeProjectConfirm(false);
    });
    confirmDialog = wrapper;
    return wrapper;
  }

  function showProjectConfirm(options) {
    return new Promise(function (resolve) {
      var wrapper = ensureProjectConfirmDialog();
      confirmResolver = resolve;
      confirmLastFocus = document.activeElement;
      var panel = wrapper.querySelector('.manager-project-confirm');
      panel.className = 'manager-project-confirm is-' + (options.variant || 'archive');
      wrapper.querySelector('[data-project-confirm-icon]').textContent = options.icon || '!';
      wrapper.querySelector('#manager-project-confirm-title').textContent = options.title || 'تأیید عملیات';
      wrapper.querySelector('#manager-project-confirm-message').textContent = options.message || '';
      wrapper.querySelector('[data-project-confirm-accept]').textContent = options.confirmText || 'تأیید';
      if (typeof wrapper.showModal === 'function') wrapper.showModal();
      else wrapper.setAttribute('open', 'open');
      document.body.classList.add('manager-project-confirm-open');
      wrapper.querySelector('.manager-project-confirm__actions [data-project-confirm-cancel]').focus();
    });
  }

  function closeProjectConfirm(confirmed) {
    if (!confirmDialog || (!confirmDialog.open && !confirmDialog.hasAttribute('open'))) return;
    var resolver = confirmResolver;
    confirmResolver = null;
    if (typeof confirmDialog.close === 'function') confirmDialog.close();
    else confirmDialog.removeAttribute('open');
    document.body.classList.remove('manager-project-confirm-open');
    if (confirmLastFocus && document.documentElement.contains(confirmLastFocus)) confirmLastFocus.focus({ preventScroll: true });
    confirmLastFocus = null;
    if (resolver) resolver(Boolean(confirmed));
  }

  function showNotice(message, type) {
    notice.textContent = message || '';
    notice.className = 'manager-projects__notice ' + (type === 'error' ? 'is-error' : 'is-success');
    notice.hidden = !message;
    if (message) window.setTimeout(function () { notice.hidden = true; }, 5000);
  }

  function showFormResult(message, type) {
    var result = form.querySelector('[data-project-form-result]');
    result.textContent = message || '';
    result.className = 'manager-project-form__result ' + (type === 'error' ? 'is-error' : 'is-success');
    result.hidden = !message;
  }

  function request(action, values) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce);
    if (values instanceof FormData) {
      values.forEach(function (value, key) { body.append(key, value); });
    } else {
      Object.keys(values || {}).forEach(function (key) {
        if (Array.isArray(values[key])) {
          values[key].forEach(function (value) { body.append(key, value); });
        } else {
          body.set(key, values[key] == null ? '' : values[key]);
        }
      });
    }
    return window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().catch(function () { return null; }).then(function (json) {
        if (!response.ok || !json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : 'ارتباط با سایت انجام نشد.');
        }
        return json.data;
      });
    });
  }

  function setBusy(isBusy) {
    Array.prototype.forEach.call(form.elements, function (element) { element.disabled = isBusy; });
    form.classList.toggle('is-busy', isBusy);
  }

  function documentPicker(type) {
    return form.querySelector('[data-document-picker="' + type + '"]');
  }

  function selectedDocumentValues(type) {
    var selected = form.querySelector('[data-document-selected="' + type + '"]');
    if (!selected) return [];
    return Array.prototype.map.call(selected.querySelectorAll('input[type="hidden"]'), function (input) {
      return { id: String(input.value), number: String(input.getAttribute('data-document-number') || input.value) };
    });
  }

  function selectedDocumentIds(type) {
    return selectedDocumentValues(type).map(function (documentValue) { return documentValue.id; });
  }

  function renderDocumentSelection(type, values) {
    var picker = documentPicker(type);
    var selected = form.querySelector('[data-document-selected="' + type + '"]');
    if (!picker || !selected) return;
    var numbers = {};
    var ids = Array.from(new Set((Array.isArray(values) ? values : []).map(function (value) {
      if (value && typeof value === 'object') {
        numbers[String(value.id)] = String(value.number || value.id || '');
        return String(value.id || '');
      }
      return String(value || '');
    }).filter(Boolean)));
    selected.replaceChildren();
    Array.prototype.forEach.call(picker.options, function (option) {
      var isSelected = ids.indexOf(String(option.value)) !== -1;
      option.classList.toggle('is-selected', isSelected);
      option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    });
    ids.forEach(function (id) {
      var option = Array.prototype.find.call(picker.options, function (item) { return String(item.value) === id; });
      var chip = document.createElement('span');
      chip.className = 'manager-project-document-chip';
      var text = document.createElement('b');
      text.textContent = (type === 'invoice' ? 'فاکتور ' : 'پیش‌فاکتور ') + (option ? (option.getAttribute('data-document-number') || id) : (numbers[id] || id));
      var remove = document.createElement('button');
      remove.type = 'button';
      remove.setAttribute('data-document-remove', type);
      remove.setAttribute('data-document-id', id);
      remove.setAttribute('aria-label', 'حذف ' + text.textContent);
      remove.textContent = '×';
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = type + '_ids[]';
      input.value = id;
      input.setAttribute('data-document-number', option ? (option.getAttribute('data-document-number') || id) : (numbers[id] || id));
      chip.appendChild(text);
      chip.appendChild(remove);
      chip.appendChild(input);
      selected.appendChild(chip);
    });
    selected.classList.toggle('is-empty', selected.children.length === 0);
  }

  function addDocumentSelection(type, documentId) {
    var values = selectedDocumentValues(type);
    var ids = values.map(function (documentValue) { return documentValue.id; });
    documentId = String(documentId || '');
    if (documentId && ids.indexOf(documentId) === -1) {
      var picker = documentPicker(type);
      var option = picker ? Array.prototype.find.call(picker.options, function (item) { return String(item.value) === documentId; }) : null;
      values.push({ id: documentId, number: option ? (option.getAttribute('data-document-number') || documentId) : documentId });
    }
    renderDocumentSelection(type, values);
  }

  function removeDocumentSelection(type, documentId) {
    renderDocumentSelection(type, selectedDocumentValues(type).filter(function (documentValue) {
      return documentValue.id !== String(documentId);
    }));
  }

  function cardDocumentIds(card, type) {
    var value = card.getAttribute('data-project-' + type + '-ids') || '';
    return value.split(',').map(function (id) { return Number(id); }).filter(Boolean);
  }

  function renderCardDocuments(card, record) {
    if (!card || !record) return;
    var proformas = Array.isArray(record.proformas) ? record.proformas : [];
    var invoices = Array.isArray(record.invoices) ? record.invoices : [];
    card.setAttribute('data-project-proforma-ids', proformas.map(function (item) { return Number(item.id); }).filter(Boolean).join(','));
    card.setAttribute('data-project-proforma-id', proformas.length ? String(proformas[proformas.length - 1].id) : '0');
    card.setAttribute('data-project-invoice-ids', invoices.map(function (item) { return Number(item.id); }).filter(Boolean).join(','));
    card.setAttribute('data-project-invoice-id', invoices.length ? String(invoices[invoices.length - 1].id) : '0');
    var documents = card.querySelector('.manager-project-card__documents');
    if (!proformas.length && !invoices.length) {
      if (documents) documents.remove();
      return;
    }
    if (!documents) {
      documents = document.createElement('div');
      documents.className = 'manager-project-card__documents';
      card.appendChild(documents);
    }
    documents.replaceChildren();
    proformas.concat(invoices).forEach(function (documentRecord, index) {
      if (!documentRecord || !documentRecord.url) return;
      var isInvoice = index >= proformas.length;
      var link = document.createElement('a');
      link.href = documentRecord.url;
      link.textContent = (isInvoice ? 'فاکتور ' : 'پیش‌فاکتور ') + String(documentRecord.number || documentRecord.id || '');
      if (isInvoice) link.setAttribute('data-project-invoice-link', '');
      documents.appendChild(link);
    });
  }

  function openDialog(project) {
    form.reset();
    showFormResult('', '');
    form.elements.project_id.value = project ? project.id : '';
    form.elements.title.value = project ? project.title : '';
    form.elements.client.value = project ? project.client : '';
    form.elements.phone.value = project ? project.phone : '';
    form.elements.stage.value = project ? project.stage : 'lead';
    form.elements.followup.value = project ? project.followup : '';
    renderDocumentSelection('proforma', project && Array.isArray(project.proformas) ? project.proformas : []);
    renderDocumentSelection('invoice', project && Array.isArray(project.invoices) ? project.invoices : []);
    form.elements.notes.value = project ? project.notes : '';
    renderWorkItems(project && Array.isArray(project.work_items) ? project.work_items : []);
    board.querySelector('[data-project-dialog-eyebrow]').textContent = project ? 'ویرایش پروژه' : 'پروژه جدید';
    board.querySelector('[data-project-dialog-title]').textContent = project ? project.title : 'ثبت پروژه';
    destructiveActions.hidden = !project;
    var historyBox = board.querySelector('[data-project-history]');
    var historyList = board.querySelector('[data-project-history-list]');
    historyList.replaceChildren();
    var history = project && Array.isArray(project.history) ? project.history : [];
    historyBox.hidden = !history.length;
    history.forEach(function (entry) {
      var item = document.createElement('li');
      var description = entry.action === 'created'
        ? 'پروژه در مرحله «' + entry.to_label + '» ایجاد شد.'
        : (entry.action === 'merged'
          ? 'کارت تکراری و اسناد آن در این پروژه ادغام شد.'
          : 'مرحله از «' + entry.from_label + '» به «' + entry.to_label + '» تغییر کرد.');
      var text = document.createElement('span');
      var meta = document.createElement('small');
      text.textContent = description;
      meta.textContent = entry.user_name + (entry.time ? ' — ' + entry.time : '');
      item.appendChild(text);
      item.appendChild(meta);
      historyList.appendChild(item);
    });
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', 'open');
    document.documentElement.classList.add('manager-project-dialog-open');
    document.body.classList.add('manager-project-dialog-open');
    window.setTimeout(function () { form.elements.title.focus(); }, 30);
  }

  function closeDialog() {
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
    document.documentElement.classList.remove('manager-project-dialog-open');
    document.body.classList.remove('manager-project-dialog-open');
  }

  dialog.addEventListener('close', function () {
    document.documentElement.classList.remove('manager-project-dialog-open');
    document.body.classList.remove('manager-project-dialog-open');
  });

  function refreshColumnState() {
    var totalCards = board.querySelectorAll('[data-project-card]').length;
    var activeCount = board.querySelector('[data-project-active-count]');
    var dueCount = board.querySelector('[data-project-due-count]');
    var readyInvoiceCount = board.querySelector('[data-project-ready-invoice-count]');
    if (activeCount) activeCount.textContent = Number(totalCards).toLocaleString('fa-IR');
    if (dueCount) {
      var dueCards = board.querySelectorAll('[data-project-card] .manager-project-card__meta .is-due').length;
      dueCount.textContent = Number(dueCards).toLocaleString('fa-IR');
      dueCount.classList.toggle('is-alert', dueCards > 0);
    }
    if (readyInvoiceCount) {
      var readyCards = Array.prototype.filter.call(board.querySelectorAll('[data-project-stage="delivery"] [data-project-card]'), function (card) {
        return Number(card.getAttribute('data-project-invoice-id') || 0) === 0;
      }).length;
      readyInvoiceCount.textContent = Number(readyCards).toLocaleString('fa-IR');
    }
    Array.prototype.forEach.call(board.querySelectorAll('[data-project-stage]'), function (column) {
      var cards = column.querySelectorAll('[data-project-card]');
      var counter = column.querySelector('[data-project-stage-count]');
      var empty = column.querySelector('[data-project-empty]');
      if (counter) counter.textContent = Number(cards.length).toLocaleString('fa-IR');
      if (empty) empty.hidden = cards.length > 0;
    });
    var proformaCount = board.querySelector('[data-project-stage="proforma"] [data-project-stage-count]');
    var summary = board.querySelector('[data-project-proforma-count]');
    if (proformaCount && summary) summary.textContent = proformaCount.textContent;
    syncMobileStageNav();
  }

  function isMobileProjectBoard() {
    return window.matchMedia('(max-width: 720px)').matches;
  }

  function projectColumns() {
    return Array.prototype.slice.call(board.querySelectorAll('[data-project-columns] > [data-project-stage]'));
  }

  function syncMobileStageNav() {
    if (!mobileStageNav) return;
    projectColumns().forEach(function (column) {
      var stage = column.getAttribute('data-project-stage');
      var button = mobileStageNav.querySelector('[data-mobile-project-stage="' + stage + '"]');
      var sourceCount = column.querySelector('[data-project-stage-count]');
      var targetCount = button && button.querySelector('[data-mobile-project-count]');
      if (targetCount && sourceCount) targetCount.textContent = sourceCount.textContent;
    });
  }

  function setActiveMobileColumn(column, options) {
    if (!column) return;
    mobileActiveColumn = column;
    var stage = column.getAttribute('data-project-stage');
    projectColumns().forEach(function (item) {
      item.classList.toggle('is-mobile-active', item === column);
      item.setAttribute('aria-hidden', isMobileProjectBoard() && item !== column ? 'true' : 'false');
    });
    if (mobileStageNav) {
      mobileStageNav.querySelectorAll('[data-mobile-project-stage]').forEach(function (button) {
        var active = button.getAttribute('data-mobile-project-stage') === stage;
        button.classList.toggle('is-active', active);
        button.classList.remove('is-swipe-target');
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    }
    if (!options || options.remember !== false) {
      try { window.sessionStorage.setItem('zigurat-project-mobile-stage', stage); } catch (error) { /* Storage may be disabled. */ }
    }
  }

  function adjacentProjectColumn(column, direction) {
    var columns = projectColumns();
    var index = columns.indexOf(column);
    return index < 0 ? null : (columns[index + direction] || null);
  }

  function previewMobileStage(column) {
    if (!mobileStageNav) return;
    var stage = column ? column.getAttribute('data-project-stage') : '';
    mobileStageNav.querySelectorAll('[data-mobile-project-stage]').forEach(function (button) {
      button.classList.toggle('is-swipe-target', button.getAttribute('data-mobile-project-stage') === stage);
    });
  }

  function initMobileProjectBoard() {
    var columnsRoot = board.querySelector('[data-project-columns]');
    var columns = projectColumns();
    if (!columnsRoot || !columns.length || mobileStageNav) return;
    mobileStageNav = document.createElement('nav');
    mobileStageNav.className = 'manager-project-mobile-stages';
    mobileStageNav.setAttribute('aria-label', 'مراحل پروژه');
    columns.forEach(function (column, index) {
      var stage = column.getAttribute('data-project-stage');
      var heading = column.querySelector('h3');
      var counter = column.querySelector('[data-project-stage-count]');
      var button = document.createElement('button');
      var columnId = 'manager-project-stage-' + stage;
      column.id = columnId;
      button.type = 'button';
      button.className = 'manager-project-mobile-stage is-' + stage;
      button.setAttribute('data-mobile-project-stage', stage);
      button.setAttribute('aria-controls', columnId);
      button.setAttribute('aria-label', heading ? heading.textContent.trim() : ('مرحله ' + (index + 1)));
      button.innerHTML = '<span class="manager-project-mobile-stage__label"></span><span class="manager-project-mobile-stage__count" data-mobile-project-count></span>';
      button.querySelector('.manager-project-mobile-stage__label').textContent = heading ? heading.textContent.trim() : '';
      button.querySelector('[data-mobile-project-count]').textContent = counter ? counter.textContent : '۰';
      button.addEventListener('click', function () {
        setActiveMobileColumn(column);
        columnsRoot.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
      mobileStageNav.appendChild(button);
    });
    columnsRoot.parentNode.insertBefore(mobileStageNav, columnsRoot);
    var remembered = '';
    try { remembered = window.sessionStorage.getItem('zigurat-project-mobile-stage') || ''; } catch (error) { /* Storage may be disabled. */ }
    var initial = columns.filter(function (column) { return column.getAttribute('data-project-stage') === remembered; })[0] || columns[0];
    setActiveMobileColumn(initial, { remember: false });
    syncMobileStageNav();
    var viewport = window.matchMedia('(max-width: 720px)');
    var handleViewportChange = function () {
      if (viewport.matches) {
        setActiveMobileColumn(mobileActiveColumn || columns[0], { remember: false });
      } else {
        columns.forEach(function (column) { column.setAttribute('aria-hidden', 'false'); });
        previewMobileStage(null);
      }
    };
    if (typeof viewport.addEventListener === 'function') viewport.addEventListener('change', handleViewportChange);
    else if (typeof viewport.addListener === 'function') viewport.addListener(handleViewportChange);
  }

  function refreshActiveCollaborators() {
    var columns = board.querySelectorAll('[data-active-collaborator]');
    var total = board.querySelector('[data-active-collaborator-count]');
    var empty = board.querySelector('[data-active-collaborator-empty]');
    if (total) total.textContent = Number(columns.length).toLocaleString('fa-IR');
    if (empty) empty.hidden = columns.length > 0;
    columns.forEach(function (column) {
      var projects = column.querySelectorAll('[data-active-project]');
      var counter = column.querySelector('[data-active-project-count]');
      if (counter) counter.textContent = Number(projects.length).toLocaleString('fa-IR');
    });
  }

  function removeProjectFromActiveLists(projectId) {
    board.querySelectorAll('[data-active-project][data-project-id="' + projectId + '"]').forEach(function (project) {
      var column = project.closest('[data-active-collaborator]');
      project.remove();
      if (column && !column.querySelector('[data-active-project]')) column.remove();
    });
    refreshActiveCollaborators();
  }

  function appendProjectCard(record) {
    if (!record || !record.id || board.querySelector('[data-project-card][data-project-id="' + record.id + '"]')) return;
    var list = board.querySelector('[data-project-stage="' + record.stage + '"] [data-project-card-list]');
    if (!list) return;
    var card = document.createElement('article');
    card.className = 'manager-project-card';
    card.draggable = true;
    card.setAttribute('data-project-card', '');
    card.dataset.projectId = record.id;
    card.dataset.projectCurrentStage = record.stage || 'proforma';
    card.dataset.projectProformaId = record.proforma_id || 0;
    card.dataset.projectProformaIds = (record.proforma_ids || []).join(',');
    card.dataset.projectInvoiceId = record.invoice_id || 0;
    card.dataset.projectInvoiceIds = (record.invoice_ids || []).join(',');
    card.dataset.projectConversionUrl = record.conversion_url || '';

    var top = document.createElement('div');
    top.className = 'manager-project-card__top';
    top.innerHTML = '<span class="manager-project-card__grip" aria-label="دستگیره جابه‌جایی" title="برای جابه‌جایی بکشید">⠿</span>';
    var edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'manager-project-card__edit';
    edit.setAttribute('data-project-edit', '');
    edit.setAttribute('aria-label', 'ویرایش ' + (record.title || 'پروژه'));
    var title = document.createElement('strong');
    title.textContent = record.title || 'بدون عنوان';
    edit.appendChild(title);
    top.appendChild(edit);
    card.appendChild(top);

    if (record.client) {
      var client = document.createElement('div');
      client.className = 'manager-project-card__client';
      var clientName = document.createElement('b');
      clientName.textContent = record.client;
      client.appendChild(clientName);
      card.appendChild(client);
    }
    if (record.followup) {
      var meta = document.createElement('div');
      meta.className = 'manager-project-card__meta';
      var followup = document.createElement('span');
      followup.textContent = 'پیگیری: ' + record.followup;
      meta.appendChild(followup);
      card.appendChild(meta);
    }
    if ((record.proformas && record.proformas.length) || (record.invoices && record.invoices.length)) {
      renderCardDocuments(card, record);
    }
    list.insertBefore(card, list.querySelector('[data-project-empty]'));
    refreshColumnState();
  }

  // Invoice settlement can happen in a separate tab. Listen for the event
  // written by the invoice page so the board stays live without a timer.
  function handleWorkflowStorageEvent(event) {
    if (!event || event.key !== 'zigurat_workflow_event' || !event.newValue) return;
    var payload;
    try { payload = JSON.parse(event.newValue); } catch (error) { return; }
    if (!payload || payload.type !== 'invoice-saved' || !Number(payload.invoiceId)) return;
    var invoiceId = String(Number(payload.invoiceId));
    var sourceId = String(Number(payload.sourceProformaId || 0));
    var card = Array.prototype.find.call(board.querySelectorAll('[data-project-card]'), function (item) {
      return cardDocumentIds(item, 'invoice').indexOf(Number(invoiceId)) !== -1
        || (Number(sourceId) > 0 && cardDocumentIds(item, 'proforma').indexOf(Number(sourceId)) !== -1)
        || (payload.documentType === 'proforma' && cardDocumentIds(item, 'proforma').indexOf(Number(invoiceId)) !== -1);
    });
    if (payload.paymentStatus === 'settled') {
      if (card) {
        var projectId = card.getAttribute('data-project-id');
        request('zigurat_get_workflow_project', { project_id: projectId }).then(function (data) {
          if (data.project && data.project.archived) {
            card.remove();
            removeProjectFromActiveLists(projectId);
            refreshColumnState();
            showNotice('تمام فاکتورهای پروژه تسویه شد؛ پروژه به آرشیو منتقل شد.', 'success');
            return;
          }
          var paymentColumn = board.querySelector('[data-project-stage="payment"] [data-project-card-list]');
          if (paymentColumn) paymentColumn.insertBefore(card, paymentColumn.querySelector('[data-project-empty]'));
          card.setAttribute('data-project-current-stage', 'payment');
          refreshColumnState();
          showNotice('این فاکتور تسویه شد؛ کارت تا تسویه سایر فاکتورها فعال می‌ماند.', 'success');
        }).catch(function (error) {
          showNotice(error.message, 'error');
        });
      }
      return;
    }
    if (payload.documentType === 'invoice' && card) {
      request('zigurat_get_workflow_project', { project_id: card.getAttribute('data-project-id') }).then(function (data) {
        if (data.project) renderCardDocuments(card, data.project);
        var stage = data.project && data.project.stage ? data.project.stage : 'production';
        var target = board.querySelector('[data-project-stage="' + stage + '"] [data-project-card-list]');
        var empty = target && target.querySelector('[data-project-empty]');
        if (target) target.insertBefore(card, empty || null);
        card.setAttribute('data-project-current-stage', stage);
        refreshColumnState();
        showNotice('فاکتور ذخیره شد و کارت پروژه به‌روز شد.', 'success');
      }).catch(function (error) {
        showNotice(error.message, 'error');
      });
      return;
    }
    if (payload.documentType === 'proforma' && !card) {
      request('zigurat_get_workflow_project_by_document', {
        document_id: payload.invoiceId,
        document_type: 'proforma'
      }).then(function (data) {
        appendProjectCard(data.record);
        showNotice('پیش‌فاکتور ذخیره شد و کارت پروژه اضافه شد.', 'success');
      }).catch(function (error) { showNotice(error.message, 'error'); });
    }
  }
  window.addEventListener('storage', handleWorkflowStorageEvent);

  function broadcastWorkflowEvent(type, invoiceId) {
    if (!invoiceId) return;
    try {
      window.localStorage.setItem('zigurat_workflow_event', JSON.stringify({
        type: type,
        invoiceId: Number(invoiceId),
        at: Date.now()
      }));
    } catch (error) {}
  }

  function updateWorkEmptyState() {
    var list = board.querySelector('[data-project-work-list]');
    var empty = board.querySelector('[data-project-work-empty]');
    empty.hidden = list.querySelector('[data-project-work-row]') !== null;
  }

  function updatePartnerSummary(row) {
    var selected = Array.prototype.map.call(row.querySelectorAll('[data-partner-checkbox]:checked'), function (checkbox) {
      var label = checkbox.closest('label');
      var name = label ? label.querySelector('b') : null;
      return name ? name.textContent.trim() : '';
    }).filter(Boolean);
    var otherToggle = row.querySelector('[data-partner-other-toggle]');
    var otherInput = row.querySelector('[data-partner-other-input]');
    if (otherToggle && otherToggle.checked) {
      selected.push(otherInput && otherInput.value.trim() ? otherInput.value.trim() : 'سایر');
    }
    var summary = row.querySelector('[data-partner-summary]');
    summary.textContent = selected.length ? selected.join('، ') : 'انتخاب همکاران';
    summary.classList.toggle('has-selection', selected.length > 0);
  }

  function addWorkRow(item) {
    var list = board.querySelector('[data-project-work-list]');
    var template = board.querySelector('[data-project-work-template]');
    var row = template.content.firstElementChild.cloneNode(true);
    var index = String(Date.now()) + String(Math.floor(Math.random() * 10000));
    var itemId = item && item.id ? item.id : '';
    var selectedPartners = item && Array.isArray(item.partner_ids) ? item.partner_ids.map(String) : [];
    row.querySelector('[data-work-field="id"]').name = 'work_items[' + index + '][id]';
    row.querySelector('[data-work-field="id"]').value = itemId;
    row.querySelector('[data-work-field="title"]').name = 'work_items[' + index + '][title]';
    row.querySelector('[data-work-field="title"]').value = item && item.title ? item.title : '';
    row.querySelectorAll('[data-partner-checkbox]').forEach(function (checkbox) {
      checkbox.name = 'work_items[' + index + '][partner_ids][]';
      checkbox.checked = selectedPartners.indexOf(String(checkbox.value)) !== -1;
    });
    var otherToggle = row.querySelector('[data-partner-other-toggle]');
    var otherInput = row.querySelector('[data-partner-other-input]');
    var otherPartner = item && item.other_partner ? item.other_partner : '';
    otherToggle.checked = otherPartner !== '';
    otherInput.name = 'work_items[' + index + '][other_partner]';
    otherInput.value = otherPartner;
    otherInput.hidden = !otherToggle.checked;
    list.appendChild(row);
    updatePartnerSummary(row);
    updateWorkEmptyState();
    return row;
  }

  function renderWorkItems(items) {
    var list = board.querySelector('[data-project-work-list]');
    list.replaceChildren();
    items.forEach(function (item) { addWorkRow(item); });
    updateWorkEmptyState();
  }

  function clearDragState() {
    board.querySelectorAll('.is-drag-over').forEach(function (column) { column.classList.remove('is-drag-over'); });
    if (draggedCard) draggedCard.classList.remove('is-dragging');
    draggedCard = null;
    originalStage = '';
  }

  function moveCard(card, column, historyAction, historyEntry) {
    if (!card || !column || (historyBusy && !historyAction)) return Promise.resolve(false);
    var stage = column.getAttribute('data-project-stage');
    var previousColumn = card.closest('[data-project-stage]');
    var previousStage = card.getAttribute('data-project-current-stage') || (previousColumn ? previousColumn.getAttribute('data-project-stage') : '');
    if (!stage || stage === previousStage) {
      clearDragState();
      return Promise.resolve(false);
    }
    if (stage === 'delivery' && cardDocumentIds(card, 'invoice').length === 0 && previousStage === 'proforma' && cardDocumentIds(card, 'proforma').length === 0) {
        clearDragState();
        showNotice('این کارت به پیش‌فاکتوری متصل نیست؛ ابتدا پیش‌فاکتور را در ویرایش پروژه انتخاب کنید.', 'error');
        return Promise.resolve(false);
    }
    column.querySelector('[data-project-card-list]').insertBefore(card, column.querySelector('[data-project-empty]'));
    card.setAttribute('data-project-current-stage', stage);
    card.classList.add('is-saving');
    if (isMobileProjectBoard()) setActiveMobileColumn(column);
    refreshColumnState();
    return request('zigurat_move_workflow_project', {
      project_id: card.getAttribute('data-project-id'),
      stage: stage
    }).then(function (data) {
      card.classList.remove('is-saving');
      var confirmedStage = data.stage || stage;
      var confirmedColumn = board.querySelector('[data-project-stage="' + confirmedStage + '"]');
      if (confirmedColumn) {
        var confirmedList = confirmedColumn.querySelector('[data-project-card-list]');
        confirmedList.insertBefore(card, confirmedList.querySelector('[data-project-empty]'));
        card.setAttribute('data-project-current-stage', confirmedStage);
        if (isMobileProjectBoard()) setActiveMobileColumn(confirmedColumn);
        refreshColumnState();
      }
      if (data.project) renderCardDocuments(card, data.project);
      if (data.invoice_url && data.invoice_id) {
        // Keep an already-open invoice list in sync with the newly created invoice.
        broadcastWorkflowEvent('invoice-created', data.invoice_id);
      }
      if (historyAction === 'undo' && historyEntry) {
        undoStack.pop();
        redoStack.push(historyEntry);
      } else if (historyAction === 'redo' && historyEntry) {
        redoStack.pop();
        undoStack.push(historyEntry);
      } else if (data.can_undo) {
        undoStack.push({ projectId: Number(card.getAttribute('data-project-id')), from: previousStage, to: confirmedStage });
        if (undoStack.length > 50) undoStack.shift();
        redoStack = [];
      } else if (previousStage !== confirmedStage) {
        clearProjectHistory();
      }
      syncProjectHistoryButtons();
      showNotice(data.message, 'success');
      return true;
    }).catch(function (error) {
      card.classList.remove('is-saving');
      card.setAttribute('data-project-current-stage', previousStage);
      if (previousColumn) previousColumn.querySelector('[data-project-card-list]').insertBefore(card, previousColumn.querySelector('[data-project-empty]'));
      if (isMobileProjectBoard() && previousColumn) setActiveMobileColumn(previousColumn);
      refreshColumnState();
      showNotice(error.message, 'error');
      return false;
    }).finally(clearDragState);
  }

  board.addEventListener('click', function (event) {
    var undoControl = event.target.closest('[data-project-undo]');
    var redoControl = event.target.closest('[data-project-redo]');
    if (undoControl || redoControl) {
      event.preventDefault();
      var isUndo = Boolean(undoControl);
      var stack = isUndo ? undoStack : redoStack;
      var entry = stack[stack.length - 1];
      if (!entry || historyBusy) return;
      var historyCard = board.querySelector('[data-project-card][data-project-id="' + entry.projectId + '"]');
      var expectedStage = isUndo ? entry.to : entry.from;
      var targetStage = isUndo ? entry.from : entry.to;
      if (!historyCard || historyCard.getAttribute('data-project-current-stage') !== expectedStage) {
        clearProjectHistory();
        showNotice('مرحلهٔ این کارت از جای دیگری تغییر کرده؛ سابقهٔ واگرد پاک شد.', 'error');
        return;
      }
      var targetColumn = board.querySelector('[data-project-stage="' + targetStage + '"]');
      if (!targetColumn) {
        clearProjectHistory();
        return;
      }
      historyBusy = true;
      syncProjectHistoryButtons();
      moveCard(historyCard, targetColumn, isUndo ? 'undo' : 'redo', entry).finally(function () {
        historyBusy = false;
        syncProjectHistoryButtons();
      });
      return;
    }
    var removeDocumentButton = event.target.closest('[data-document-remove]');
    var newButton = event.target.closest('[data-project-new]');
    var archiveJump = event.target.closest('[data-project-archive-jump]');
    var closeButton = event.target.closest('[data-project-close]');
    var editButton = event.target.closest('[data-project-edit]');
    var closeAssignmentButton = event.target.closest('[data-close-active-assignment]');
    if (removeDocumentButton) {
      removeDocumentSelection(
        removeDocumentButton.getAttribute('data-document-remove'),
        removeDocumentButton.getAttribute('data-document-id')
      );
      return;
    }
    if (archiveJump) {
      var archive = board.querySelector('[data-project-archive]');
      if (archive) {
        archive.hidden = false;
        archive.scrollIntoView({ behavior: 'smooth', block: 'start' });
        loadArchiveRecords();
      }
      return;
    }
    if (closeAssignmentButton) {
      var activeProject = closeAssignmentButton.closest('[data-active-project]');
      var collaboratorColumn = closeAssignmentButton.closest('[data-active-collaborator]');
      if (!activeProject || !collaboratorColumn) return;
      showProjectConfirm({
        variant: 'archive',
        icon: '✓',
        title: 'کارکرد دریافت شده است؟',
        message: 'پروژه فقط از فهرست فعال این همکار خارج می‌شود و سوابق پروژه باقی می‌ماند.',
        confirmText: 'بله، از فعال‌ها خارج شود'
      }).then(function (confirmed) {
        if (!confirmed) return;
        closeAssignmentButton.disabled = true;
        closeAssignmentButton.textContent = 'در حال ثبت…';
        request('zigurat_close_workflow_partner_assignment', {
          project_id: activeProject.getAttribute('data-project-id'),
          assignment_key: collaboratorColumn.getAttribute('data-assignment-key')
        }).then(function (data) {
          activeProject.remove();
          if (!collaboratorColumn.querySelector('[data-active-project]')) collaboratorColumn.remove();
          refreshActiveCollaborators();
          showNotice(data.message, 'success');
        }).catch(function (error) {
          closeAssignmentButton.disabled = false;
          closeAssignmentButton.textContent = 'کارکرد گرفته شد؛ حذف از فعال‌ها';
          showNotice(error.message, 'error');
        });
      });
      return;
    }
    if (newButton) {
      openDialog(null);
      return;
    }
    if (closeButton) {
      closeDialog();
      return;
    }
    var addWorkButton = event.target.closest('[data-project-work-add]');
    if (addWorkButton) {
      var newRow = addWorkRow(null);
      newRow.querySelector('[data-work-field="title"]').focus();
      return;
    }
    var removeWorkButton = event.target.closest('[data-project-work-remove]');
    if (removeWorkButton) {
      removeWorkButton.closest('[data-project-work-row]').remove();
      updateWorkEmptyState();
      return;
    }
    if (editButton) {
      var card = editButton.closest('[data-project-card]');
      editButton.disabled = true;
      request('zigurat_get_workflow_project', { project_id: card.getAttribute('data-project-id') })
        .then(function (data) { openDialog(data.project); })
        .catch(function (error) { showNotice(error.message, 'error'); })
        .finally(function () { editButton.disabled = false; });
    }
  });

  form.addEventListener('dblclick', function (event) {
    var picker = event.target.closest('[data-document-picker]');
    if (!picker) return;
    event.preventDefault();
    var option = event.target.tagName === 'OPTION' ? event.target : picker.options[picker.selectedIndex];
    if (!option || !option.value) return;
    addDocumentSelection(picker.getAttribute('data-document-picker'), option.value);
    picker.selectedIndex = -1;
  });

  form.addEventListener('keydown', function (event) {
    var picker = event.target.closest('[data-document-picker]');
    if (!picker || event.key !== 'Enter') return;
    event.preventDefault();
    var option = picker.options[picker.selectedIndex];
    if (!option || !option.value) return;
    addDocumentSelection(picker.getAttribute('data-document-picker'), option.value);
    picker.selectedIndex = -1;
  });

  board.addEventListener('change', function (event) {
    if (event.target.matches('[data-partner-other-toggle]')) {
      var otherRow = event.target.closest('[data-project-work-row]');
      var otherInput = otherRow.querySelector('[data-partner-other-input]');
      otherInput.hidden = !event.target.checked;
      if (!event.target.checked) otherInput.value = '';
      else window.setTimeout(function () { otherInput.focus(); }, 0);
      updatePartnerSummary(otherRow);
      return;
    }
    if (!event.target.matches('[data-partner-checkbox]')) return;
    var row = event.target.closest('[data-project-work-row]');
    if (row) updatePartnerSummary(row);
  });

  board.addEventListener('input', function (event) {
    if (!event.target.matches('[data-partner-other-input]')) return;
    var row = event.target.closest('[data-project-work-row]');
    if (row) updatePartnerSummary(row);
  });

  document.addEventListener('click', function (event) {
    board.querySelectorAll('[data-partner-picker][open]').forEach(function (picker) {
      if (!picker.contains(event.target)) picker.removeAttribute('open');
    });
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;
    var values = new FormData(form);
    setBusy(true);
    showFormResult('در حال ذخیره پروژه…', 'success');
    request('zigurat_save_workflow_project', values)
      .then(function (data) {
        clearProjectHistory();
        showFormResult(data.message, 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      })
      .catch(function (error) {
        showFormResult(error.message, 'error');
        setBusy(false);
      });
  });

  function removeProjectFromBoard(projectId) {
    var card = board.querySelector('[data-project-card][data-project-id="' + projectId + '"]');
    if (card) card.remove();
    removeProjectFromActiveLists(projectId);
    refreshColumnState();
    setBusy(false);
    closeDialog();
  }

  archiveButton.addEventListener('click', function () {
    var projectId = form.elements.project_id.value;
    if (!projectId) return;
    showProjectConfirm({
      variant: 'archive',
      icon: '↙',
      title: 'انتقال پروژه به بایگانی',
      message: 'کارت از ستون‌های فعال حذف می‌شود اما سابقه و تایم‌لاین پروژه در بایگانی باقی می‌ماند.',
      confirmText: 'انتقال به بایگانی'
    }).then(function (confirmed) {
      if (!confirmed) return;
      setBusy(true);
      request('zigurat_archive_workflow_project', { project_id: projectId })
        .then(function (data) {
          removeProjectFromBoard(projectId);
          archiveLoaded = false;
          var archive = board.querySelector('section[data-project-archive]');
          if (archive && !archive.hidden) loadArchiveRecords();
          showNotice(data.message, 'success');
        })
        .catch(function (error) {
          showFormResult(error.message, 'error');
          setBusy(false);
        });
    });
  });

  deleteButton.addEventListener('click', function () {
    var projectId = form.elements.project_id.value;
    if (!projectId) return;
    showProjectConfirm({
      variant: 'delete',
      icon: '×',
      title: 'حذف دائمی کارت پروژه',
      message: 'این کارت و تمام اطلاعات مدیریتی آن برای همیشه حذف می‌شود و امکان بازگردانی وجود ندارد.',
      confirmText: 'حذف دائمی'
    }).then(function (confirmed) {
      if (!confirmed) return;
      setBusy(true);
      request('zigurat_delete_workflow_project', { project_id: projectId })
        .then(function (data) {
          removeProjectFromBoard(projectId);
          showNotice(data.message, 'success');
        })
        .catch(function (error) {
          showFormResult(error.message, 'error');
          setBusy(false);
        });
    });
  });

  board.addEventListener('dragstart', function (event) {
    var card = event.target.closest('[data-project-card]');
    if (!card || event.target.closest('a,button,input,select,textarea')) {
      event.preventDefault();
      return;
    }
    draggedCard = card;
    originalStage = card.getAttribute('data-project-current-stage');
    card.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', card.getAttribute('data-project-id'));
  });

  board.addEventListener('dragover', function (event) {
    var column = event.target.closest('[data-project-stage]');
    if (!draggedCard || !column) return;
    event.preventDefault();
    board.querySelectorAll('.is-drag-over').forEach(function (item) { if (item !== column) item.classList.remove('is-drag-over'); });
    column.classList.add('is-drag-over');
    event.dataTransfer.dropEffect = 'move';
  });

  board.addEventListener('drop', function (event) {
    var column = event.target.closest('[data-project-stage]');
    if (!draggedCard || !column) return;
    event.preventDefault();
    moveCard(draggedCard, column);
  });

  board.addEventListener('dragend', clearDragState);

  board.addEventListener('click', function (event) {
    if (Date.now() < suppressCardClickUntil && event.target.closest('[data-project-card]')) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  function touchTargetColumn(x, y) {
    var ghost = touchState && touchState.ghost;
    if (ghost) ghost.style.display = 'none';
    var element = document.elementFromPoint(x, y);
    if (ghost) ghost.style.display = '';
    return element ? element.closest('[data-project-stage]') : null;
  }

  function liftMobileCard(state) {
    if (!state || touchState !== state || state.mobileNavigating) return;
    var rect = state.card.getBoundingClientRect();
    var ghost = state.card.cloneNode(true);
    ghost.className = 'manager-project-card manager-project-card--touch-ghost is-mobile-lifted';
    ghost.removeAttribute('draggable');
    ghost.style.width = rect.width + 'px';
    ghost.style.left = state.currentX + 'px';
    ghost.style.top = state.currentY + 'px';
    document.body.appendChild(ghost);
    state.active = true;
    state.mobileDrag = true;
    state.ghost = ghost;
    state.card.classList.add('is-dragging');
    state.column.classList.add('is-drag-over');
    suppressCardClickUntil = Date.now() + 600;
    if (navigator.vibrate) navigator.vibrate(30);
  }

  function clearMobileEdgeMove(state) {
    if (!state) return;
    if (state.edgeTimer) window.clearTimeout(state.edgeTimer);
    state.edgeTimer = null;
  }

  function queueMobileEdgeMove(state, direction) {
    if (!state || (state.edgeDirection === direction && state.edgeTimer)) return;
    if (state.edgeDirection !== direction) clearMobileEdgeMove(state);
    state.edgeDirection = direction;
    var advanceOneColumn = function () {
      state.edgeTimer = null;
      if (touchState !== state || !state.mobileDrag) return;
      var nextColumn = adjacentProjectColumn(state.column, direction);
      if (!nextColumn) return;
      board.querySelectorAll('.is-drag-over').forEach(function (item) { item.classList.remove('is-drag-over'); });
      state.column = nextColumn;
      setActiveMobileColumn(nextColumn);
      nextColumn.classList.add('is-drag-over');
      if (navigator.vibrate) navigator.vibrate(12);
      var edgeSize = Math.max(54, window.innerWidth * 0.18);
      var stillAtSameEdge = direction > 0
        ? state.currentX <= edgeSize
        : state.currentX >= window.innerWidth - edgeSize;
      if (stillAtSameEdge) state.edgeTimer = window.setTimeout(advanceOneColumn, 1000);
    };
    state.edgeTimer = window.setTimeout(advanceOneColumn, 1000);
  }

  board.addEventListener('pointerdown', function (event) {
    if (event.pointerType === 'mouse' || event.button !== 0) return;
    var card = event.target.closest('[data-project-card]');
    if (isMobileProjectBoard()) {
      var columnsRoot = event.target.closest('[data-project-columns]');
      if (!columnsRoot) return;
      var interactive = event.target.closest('a,button,input,select,textarea');
      if (card && (!interactive || interactive.matches('[data-project-edit]'))) {
        var mobileCardState = {
          card: card,
          pointerId: event.pointerId,
          startX: event.clientX,
          startY: event.clientY,
          currentX: event.clientX,
          currentY: event.clientY,
          active: false,
          mobileCard: true,
          mobileNavigating: false,
          mobileDrag: false,
          ghost: null,
          edgeTimer: null,
          edgeDirection: 0,
          column: card.closest('[data-project-stage]'),
          originalColumn: card.closest('[data-project-stage]')
        };
        mobileCardState.timer = window.setTimeout(function () { liftMobileCard(mobileCardState); }, 360);
        touchState = mobileCardState;
        card.setPointerCapture(event.pointerId);
      } else if (!card && !interactive) {
        mobileBoardSwipe = {
          pointerId: event.pointerId,
          startX: event.clientX,
          startY: event.clientY,
          active: false
        };
        columnsRoot.setPointerCapture(event.pointerId);
      }
      return;
    }
    if (event.target.closest('a,button,input,select,textarea')) return;
    if (!card) return;
    var timer = window.setTimeout(function () {
      var rect = card.getBoundingClientRect();
      var ghost = card.cloneNode(true);
      ghost.className = 'manager-project-card manager-project-card--touch-ghost';
      ghost.removeAttribute('draggable');
      ghost.style.width = rect.width + 'px';
      document.body.appendChild(ghost);
      touchState.active = true;
      touchState.ghost = ghost;
      card.classList.add('is-dragging');
      if (navigator.vibrate) navigator.vibrate(25);
    }, 300);
    touchState = { card: card, pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, timer: timer, active: false, ghost: null, column: null };
    card.setPointerCapture(event.pointerId);
  });

  board.addEventListener('pointermove', function (event) {
    if (mobileBoardSwipe && mobileBoardSwipe.pointerId === event.pointerId) {
      var boardDx = event.clientX - mobileBoardSwipe.startX;
      var boardDy = event.clientY - mobileBoardSwipe.startY;
      if (Math.abs(boardDx) > 12 && Math.abs(boardDx) > Math.abs(boardDy) * 1.15) {
        mobileBoardSwipe.active = true;
        event.preventDefault();
        previewMobileStage(adjacentProjectColumn(mobileActiveColumn, boardDx < 0 ? -1 : 1));
      }
      return;
    }
    if (!touchState || touchState.pointerId !== event.pointerId) return;
    if (touchState.mobileCard) {
      var mobileDx = event.clientX - touchState.startX;
      var mobileDy = event.clientY - touchState.startY;
      touchState.currentX = event.clientX;
      touchState.currentY = event.clientY;
      if (!touchState.active) {
        if (Math.abs(mobileDx) > 10 && Math.abs(mobileDx) > Math.abs(mobileDy) * 1.15) {
          window.clearTimeout(touchState.timer);
          touchState.mobileNavigating = true;
          event.preventDefault();
          previewMobileStage(adjacentProjectColumn(touchState.column, mobileDx < 0 ? -1 : 1));
        } else if (Math.abs(mobileDy) > 10) {
          window.clearTimeout(touchState.timer);
          touchState = null;
        }
        return;
      }
      event.preventDefault();
      touchState.ghost.style.left = event.clientX + 'px';
      touchState.ghost.style.top = event.clientY + 'px';
      var edgeSize = Math.max(54, window.innerWidth * 0.18);
      var edgeDirection = event.clientX <= edgeSize ? 1 : (event.clientX >= window.innerWidth - edgeSize ? -1 : 0);
      if (edgeDirection) queueMobileEdgeMove(touchState, edgeDirection);
      else {
        clearMobileEdgeMove(touchState);
        touchState.edgeDirection = 0;
      }
      return;
    }
    var dx = Math.abs(event.clientX - touchState.startX);
    var dy = Math.abs(event.clientY - touchState.startY);
    if (!touchState.active && (dx > 9 || dy > 9)) {
      window.clearTimeout(touchState.timer);
      touchState = null;
      return;
    }
    if (!touchState.active) return;
    event.preventDefault();
    touchState.ghost.style.left = event.clientX + 'px';
    touchState.ghost.style.top = event.clientY + 'px';
    var column = touchTargetColumn(event.clientX, event.clientY);
    board.querySelectorAll('.is-drag-over').forEach(function (item) { item.classList.toggle('is-drag-over', item === column); });
    touchState.column = column;
  });

  function endTouch(event) {
    if (mobileBoardSwipe && mobileBoardSwipe.pointerId === event.pointerId) {
      var boardState = mobileBoardSwipe;
      mobileBoardSwipe = null;
      var boardDx = event.clientX - boardState.startX;
      var boardDy = event.clientY - boardState.startY;
      previewMobileStage(null);
      if (boardState.active && Math.abs(boardDx) >= 55 && Math.abs(boardDx) > Math.abs(boardDy) * 1.15) {
        var destination = adjacentProjectColumn(mobileActiveColumn, boardDx < 0 ? -1 : 1);
        if (destination) setActiveMobileColumn(destination);
      }
      return;
    }
    if (!touchState || touchState.pointerId !== event.pointerId) return;
    if (touchState.mobileCard) {
      var mobileState = touchState;
      touchState = null;
      window.clearTimeout(mobileState.timer);
      var mobileDx = event.clientX - mobileState.startX;
      var mobileDy = event.clientY - mobileState.startY;
      clearMobileEdgeMove(mobileState);
      if (mobileState.ghost) mobileState.ghost.remove();
      mobileState.card.classList.remove('is-dragging');
      board.querySelectorAll('.is-drag-over').forEach(function (item) { item.classList.remove('is-drag-over'); });
      previewMobileStage(null);
      if (mobileState.mobileDrag) {
        suppressCardClickUntil = Date.now() + 500;
        if (mobileState.column !== mobileState.originalColumn) moveCard(mobileState.card, mobileState.column);
      } else if (mobileState.mobileNavigating && Math.abs(mobileDx) >= 55 && Math.abs(mobileDx) > Math.abs(mobileDy) * 1.15) {
        suppressCardClickUntil = Date.now() + 500;
        var mobileDestination = adjacentProjectColumn(mobileState.column, mobileDx < 0 ? -1 : 1);
        if (mobileDestination) setActiveMobileColumn(mobileDestination);
      }
      return;
    }
    window.clearTimeout(touchState.timer);
    var state = touchState;
    touchState = null;
    clearMobileEdgeMove(state);
    if (state.ghost) state.ghost.remove();
    state.card.classList.remove('is-dragging');
    board.querySelectorAll('.is-drag-over').forEach(function (item) { item.classList.remove('is-drag-over'); });
    if (state.active && state.column) moveCard(state.card, state.column);
  }

  function cancelTouch(event) {
    if (mobileBoardSwipe && mobileBoardSwipe.pointerId === event.pointerId) {
      mobileBoardSwipe = null;
      previewMobileStage(null);
    }
    if (!touchState || touchState.pointerId !== event.pointerId) return;
    window.clearTimeout(touchState.timer);
    var state = touchState;
    touchState = null;
    clearMobileEdgeMove(state);
    if (state.ghost) state.ghost.remove();
    if (state.card) {
      state.card.classList.remove('is-dragging');
      state.card.style.transform = '';
    }
    previewMobileStage(null);
    board.querySelectorAll('.is-drag-over').forEach(function (item) { item.classList.remove('is-drag-over'); });
  }

  board.addEventListener('pointerup', endTouch);
  board.addEventListener('pointercancel', cancelTouch);
  var archiveLoaded = false;
  var archiveLoading = false;

  function renderArchiveRecords(records) {
    var archive = board.querySelector('[data-project-archive]');
    var content = archive && archive.querySelector('[data-project-archive-content]');
    if (!archive || !content) return;
    content.replaceChildren();
    var count = archive.querySelector('[data-project-archive-count]');
    if (count) count.textContent = Number(records.length).toLocaleString('fa-IR') + ' پروژه بایگانی‌شده';
    if (!records.length) {
      var empty = document.createElement('p');
      empty.className = 'manager-project-archive__empty';
      empty.textContent = 'هنوز پروژه‌ی بایگانی‌شده‌ای وجود ندارد.';
      content.appendChild(empty);
      return;
    }
    var chart = document.createElement('div');
    chart.className = 'manager-project-archive__chart';
    chart.setAttribute('data-project-archive-chart', '');
    var axis = document.createElement('div');
    axis.className = 'manager-project-archive__axis';
    axis.setAttribute('data-project-archive-axis', '');
    chart.appendChild(axis);
    var list = document.createElement('div');
    list.className = 'manager-project-archive__list';
    records.forEach(function (item) {
      var row = document.createElement('article');
      row.className = 'manager-project-archive__item';
      row.setAttribute('data-project-archive-row', '');
      row.dataset.startTs = item.timeline_start_ts || 0;
      row.dataset.endTs = item.timeline_end_ts || 0;
      row.innerHTML = '<div class="manager-project-archive__item-head"><strong></strong><span></span></div>' +
        '<div class="manager-project-archive__timeline"><div class="manager-project-archive__track"><span data-project-archive-bar><b></b></span></div><small class="manager-project-archive__dates"><span></span><span></span></small></div>';
      row.querySelector('.manager-project-archive__item-head strong').textContent = item.title || '';
      row.querySelector('.manager-project-archive__item-head span').textContent = item.client || 'بدون نام مشتری';
      row.querySelector('[data-project-archive-bar] b').textContent = item.title || '';
      row.querySelector('.manager-project-archive__dates span:first-child').textContent = item.created_at || '';
      row.querySelector('.manager-project-archive__dates span:last-child').textContent = item.archived_at || '';
      (item.timeline_events || []).forEach(function (entry) {
        var event = document.createElement('i');
        event.className = 'manager-project-archive__event is-' + (entry.stage || 'settled');
        event.setAttribute('data-project-archive-event', '');
        event.dataset.eventTs = entry.ts || 0;
        event.title = entry.label || '';
        row.querySelector('.manager-project-archive__track').appendChild(event);
      });
      list.appendChild(row);
    });
    chart.appendChild(list);
    content.appendChild(chart);
  }

  function loadArchiveRecords() {
    if (archiveLoaded || archiveLoading) { renderArchiveTimeline(); return; }
    archiveLoading = true;
    var content = board.querySelector('[data-project-archive-content]');
    request('zigurat_load_workflow_archive', {}).then(function (data) {
      var records = data && Array.isArray(data.records) ? data.records : [];
      renderArchiveRecords(records);
      archiveLoaded = true;
      renderArchiveTimeline();
    }).catch(function (error) {
      if (content) content.textContent = error.message;
    }).finally(function () { archiveLoading = false; });
  }

  function renderArchiveTimeline() {
    var archive = board.querySelector('[data-project-archive]');
    if (!archive) return;
    var rows = Array.prototype.slice.call(archive.querySelectorAll('[data-project-archive-row]'));
    var rangeSelect = archive.querySelector('[data-project-archive-range]');
    var now = Math.floor(Date.now() / 1000);
    var allStart = rows.reduce(function (value, row) { return Math.min(value, Number(row.getAttribute('data-start-ts')) || value); }, now);
    var allEnd = rows.reduce(function (value, row) { return Math.max(value, Number(row.getAttribute('data-end-ts')) || value); }, 0) || now;
    var selected = rangeSelect ? rangeSelect.value : '10d';
    var start = allStart;
    var end = allEnd;
    if (selected === '10d') start = now - 10 * 86400;
    if (selected === '30d') start = now - 30 * 86400;
    if (selected === '3m') start = now - 90 * 86400;
    if (selected === '6m') start = now - 180 * 86400;
    if (selected === 'year') start = new Date(new Date().getFullYear(), 0, 1).getTime() / 1000;
    end = Math.max(end, now);
    if (end <= start) end = start + 86400;
    var span = end - start;
    var axis = archive.querySelector('[data-project-archive-axis]');
    if (axis) {
      axis.replaceChildren();
      for (var tick = 0; tick <= 6; tick += 1) {
        var tickDate = new Date((start + span * tick / 6) * 1000);
        var label = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { month: '2-digit', day: '2-digit' }).format(tickDate);
        var tickNode = document.createElement('span');
        tickNode.textContent = label;
        axis.appendChild(tickNode);
      }
    }
    rows.forEach(function (row) {
      var rowStart = Number(row.getAttribute('data-start-ts')) || start;
      var rowEnd = Number(row.getAttribute('data-end-ts')) || end;
      var visible = rowEnd >= start && rowStart <= end;
      row.hidden = !visible;
      if (!visible) return;
      var bar = row.querySelector('[data-project-archive-bar]');
      var left = Math.max(0, Math.min(100, ((Math.max(rowStart, start) - start) / span) * 100));
      var right = Math.max(0, Math.min(100, ((Math.min(rowEnd, end) - start) / span) * 100));
      if (bar) { bar.style.left = left + '%'; bar.style.width = Math.max(1, right - left) + '%'; }
      row.querySelectorAll('[data-project-archive-event]').forEach(function (eventNode) {
        var eventTs = Number(eventNode.getAttribute('data-event-ts')) || rowStart;
        eventNode.style.left = Math.max(0, Math.min(100, ((eventTs - start) / span) * 100)) + '%';
      });
    });
    var visibleRows = rows.filter(function (row) { return !row.hidden; }).sort(function (first, second) {
      return (Number(first.getAttribute('data-start-ts')) || 0) - (Number(second.getAttribute('data-start-ts')) || 0);
    });
    var laneEnds = [];
    visibleRows.forEach(function (row) {
      var rowStart = Number(row.getAttribute('data-start-ts')) || start;
      var rowEnd = Number(row.getAttribute('data-end-ts')) || end;
      var lane = laneEnds.findIndex(function (laneEnd) { return rowStart >= laneEnd; });
      if (lane < 0) { lane = laneEnds.length; laneEnds.push(rowEnd); } else { laneEnds[lane] = rowEnd; }
      row.style.position = 'absolute';
      row.style.right = '0';
      row.style.left = '0';
      row.style.top = (lane * 56) + 'px';
    });
    var list = archive.querySelector('.manager-project-archive__list');
    if (list) {
      list.style.position = 'relative';
      list.style.height = Math.max(1, laneEnds.length) * 56 + 'px';
    }
  }
  var archiveRange = board.querySelector('[data-project-archive-range]');
  if (archiveRange) archiveRange.addEventListener('change', function () {
    if (!archiveLoaded) loadArchiveRecords();
    else renderArchiveTimeline();
  });
  renderArchiveTimeline();
  // Browsers may restore the board from the back/forward cache after the
  // invoice was paid in the same tab. Force one fresh read in that case too.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
  });
  initMobileProjectBoard();
  refreshColumnState();
  refreshActiveCollaborators();
}());
