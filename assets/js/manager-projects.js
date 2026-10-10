(function () {
  'use strict';

  var board = document.querySelector('[data-project-board]');
  var config = window.ziguratWorkflowProjects || {};
  if (!board || !config.ajaxUrl || !config.nonce) return;

  var dialog = board.querySelector('[data-project-dialog]');
  var form = board.querySelector('[data-project-form]');
  var notice = board.querySelector('[data-project-notice]');
  var archiveButton = board.querySelector('[data-project-archive]');
  var draggedCard = null;
  var originalStage = '';
  var touchState = null;

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

  function openDialog(project) {
    form.reset();
    showFormResult('', '');
    form.elements.project_id.value = project ? project.id : '';
    form.elements.title.value = project ? project.title : '';
    form.elements.client.value = project ? project.client : '';
    form.elements.phone.value = project ? project.phone : '';
    form.elements.stage.value = project ? project.stage : 'lead';
    form.elements.followup.value = project ? project.followup : '';
    form.elements.proforma_id.value = project ? String(project.proforma_id || 0) : '0';
    form.elements.invoice_id.value = project ? String(project.invoice_id || 0) : '0';
    form.elements.notes.value = project ? project.notes : '';
    renderWorkItems(project && Array.isArray(project.work_items) ? project.work_items : []);
    board.querySelector('[data-project-dialog-eyebrow]').textContent = project ? 'ویرایش پروژه' : 'پروژه جدید';
    board.querySelector('[data-project-dialog-title]').textContent = project ? project.title : 'ثبت پروژه';
    archiveButton.hidden = !project;
    var historyBox = board.querySelector('[data-project-history]');
    var historyList = board.querySelector('[data-project-history-list]');
    historyList.replaceChildren();
    var history = project && Array.isArray(project.history) ? project.history : [];
    historyBox.hidden = !history.length;
    history.forEach(function (entry) {
      var item = document.createElement('li');
      var description = entry.action === 'created'
        ? 'پروژه در مرحله «' + entry.to_label + '» ایجاد شد.'
        : 'مرحله از «' + entry.from_label + '» به «' + entry.to_label + '» تغییر کرد.';
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
    window.setTimeout(function () { form.elements.title.focus(); }, 30);
  }

  function closeDialog() {
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  }

  function refreshColumnState() {
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
    card.dataset.projectInvoiceId = record.invoice_id || 0;
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
      var initial = document.createElement('span');
      initial.textContent = String(record.client).trim().charAt(0);
      var clientName = document.createElement('b');
      clientName.textContent = record.client;
      client.append(initial, clientName);
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
    if (record.proforma_url || record.invoice_url) {
      var documents = document.createElement('div');
      documents.className = 'manager-project-card__documents';
      if (record.proforma_url) {
        var proforma = document.createElement('a');
        proforma.href = record.proforma_url;
        proforma.textContent = 'مشاهده پیش‌فاکتور';
        documents.appendChild(proforma);
      }
      if (record.invoice_url) {
        var invoice = document.createElement('a');
        invoice.href = record.invoice_url;
        invoice.textContent = 'مشاهده فاکتور';
        invoice.setAttribute('data-project-invoice-link', '');
        documents.appendChild(invoice);
      }
      card.appendChild(documents);
    }
    var archive = document.createElement('button');
    archive.type = 'button';
    archive.className = 'manager-project-card__archive';
    archive.setAttribute('data-project-quick-archive', '');
    archive.textContent = 'لغو و بایگانی';
    card.appendChild(archive);
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
      return String(Number(item.getAttribute('data-project-invoice-id') || 0)) === invoiceId
        || (Number(sourceId) > 0 && String(Number(item.getAttribute('data-project-proforma-id') || 0)) === sourceId)
        || (payload.documentType === 'proforma' && String(Number(item.getAttribute('data-project-proforma-id') || 0)) === invoiceId);
    });
    if (payload.paymentStatus === 'settled') {
      if (card) {
        var projectId = card.getAttribute('data-project-id');
        card.remove();
        removeProjectFromActiveLists(projectId);
        refreshColumnState();
        showNotice('فاکتور تسویه شد؛ پروژه به آرشیو منتقل شد.', 'success');
      }
      return;
    }
    if (payload.documentType === 'invoice' && card) {
      card.setAttribute('data-project-invoice-id', invoiceId);
      if (payload.invoiceUrl && !card.querySelector('[data-project-invoice-link]')) {
        var documents = card.querySelector('.manager-project-card__documents');
        if (!documents) {
          documents = document.createElement('div');
          documents.className = 'manager-project-card__documents';
          card.appendChild(documents);
        }
        var invoiceLink = document.createElement('a');
        invoiceLink.href = payload.invoiceUrl;
        invoiceLink.textContent = 'مشاهده فاکتور';
        invoiceLink.setAttribute('data-project-invoice-link', '');
        documents.appendChild(invoiceLink);
      }
      var currentStage = card.getAttribute('data-project-current-stage');
      if (currentStage !== 'payment') {
        var delivery = board.querySelector('[data-project-stage="delivery"] [data-project-card-list]');
        var empty = delivery && delivery.querySelector('[data-project-empty]');
        if (delivery) delivery.insertBefore(card, empty || null);
        card.setAttribute('data-project-current-stage', 'delivery');
      }
      refreshColumnState();
      showNotice('فاکتور ذخیره شد و کارت پروژه به‌روز شد.', 'success');
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

  function moveCard(card, column) {
    if (!card || !column) return;
    var stage = column.getAttribute('data-project-stage');
    var previousColumn = card.closest('[data-project-stage]');
    var previousStage = card.getAttribute('data-project-current-stage') || (previousColumn ? previousColumn.getAttribute('data-project-stage') : '');
    if (!stage || stage === previousStage) {
      clearDragState();
      return;
    }
    if (stage === 'delivery' && Number(card.getAttribute('data-project-invoice-id') || 0) === 0 && previousStage === 'proforma' && Number(card.getAttribute('data-project-proforma-id') || 0) === 0) {
        clearDragState();
        showNotice('این کارت به پیش‌فاکتوری متصل نیست؛ ابتدا پیش‌فاکتور را در ویرایش پروژه انتخاب کنید.', 'error');
        return;
    }
    column.querySelector('[data-project-card-list]').insertBefore(card, column.querySelector('[data-project-empty]'));
    card.setAttribute('data-project-current-stage', stage);
    card.classList.add('is-saving');
    refreshColumnState();
    request('zigurat_move_workflow_project', {
      project_id: card.getAttribute('data-project-id'),
      stage: stage
    }).then(function (data) {
      card.classList.remove('is-saving');
      if (data.invoice_id) {
        card.setAttribute('data-project-invoice-id', String(data.invoice_id));
        if (data.invoice_url && !card.querySelector('[data-project-invoice-link]')) {
          var documents = card.querySelector('.manager-project-card__documents');
          if (!documents) {
            documents = document.createElement('div');
            documents.className = 'manager-project-card__documents';
            card.appendChild(documents);
          }
          var invoiceLink = document.createElement('a');
          invoiceLink.href = data.invoice_url;
          invoiceLink.textContent = 'مشاهده فاکتور';
          invoiceLink.setAttribute('data-project-invoice-link', '');
          documents.appendChild(invoiceLink);
        }
        // Keep an already-open invoice list in sync with the newly created invoice.
        broadcastWorkflowEvent('invoice-created', data.invoice_id);
      }
      showNotice(data.message, 'success');
    }).catch(function (error) {
      card.classList.remove('is-saving');
      card.setAttribute('data-project-current-stage', previousStage);
      if (previousColumn) previousColumn.querySelector('[data-project-card-list]').insertBefore(card, previousColumn.querySelector('[data-project-empty]'));
      refreshColumnState();
      showNotice(error.message, 'error');
    }).finally(clearDragState);
  }

  board.addEventListener('click', function (event) {
    var newButton = event.target.closest('[data-project-new]');
    var archiveJump = event.target.closest('[data-project-archive-jump]');
    var closeButton = event.target.closest('[data-project-close]');
    var editButton = event.target.closest('[data-project-edit]');
    var quickArchive = event.target.closest('[data-project-quick-archive]');
    var closeAssignmentButton = event.target.closest('[data-close-active-assignment]');
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
      if (!activeProject || !collaboratorColumn || !window.confirm('کارکرد این همکار دریافت شده و پروژه از فهرست فعال او خارج شود؟')) return;
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
      return;
    }
    if (quickArchive) {
      var archiveCard = quickArchive.closest('[data-project-card]');
      if (!archiveCard || !window.confirm('این پروژه لغو و به آرشیو منتقل شود؟')) return;
      quickArchive.disabled = true;
      request('zigurat_archive_workflow_project', { project_id: archiveCard.getAttribute('data-project-id') })
        .then(function (data) {
          archiveCard.remove();
          removeProjectFromActiveLists(archiveCard.getAttribute('data-project-id'));
          refreshColumnState();
          showNotice(data.message, 'success');
        })
        .catch(function (error) {
          quickArchive.disabled = false;
          showNotice(error.message, 'error');
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
        showFormResult(data.message, 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      })
      .catch(function (error) {
        showFormResult(error.message, 'error');
        setBusy(false);
      });
  });

  archiveButton.addEventListener('click', function () {
    var projectId = form.elements.project_id.value;
    if (!projectId || !window.confirm('این پروژه بایگانی شود؟')) return;
    setBusy(true);
    request('zigurat_archive_workflow_project', { project_id: projectId })
      .then(function (data) {
        showFormResult(data.message, 'success');
        window.setTimeout(function () { window.location.reload(); }, 400);
      })
      .catch(function (error) {
        showFormResult(error.message, 'error');
        setBusy(false);
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

  function touchTargetColumn(x, y) {
    var ghost = touchState && touchState.ghost;
    if (ghost) ghost.style.display = 'none';
    var element = document.elementFromPoint(x, y);
    if (ghost) ghost.style.display = '';
    return element ? element.closest('[data-project-stage]') : null;
  }

  board.addEventListener('pointerdown', function (event) {
    if (event.pointerType === 'mouse' || event.button !== 0 || event.target.closest('a,button,input,select,textarea')) return;
    var card = event.target.closest('[data-project-card]');
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
    if (!touchState || touchState.pointerId !== event.pointerId) return;
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
    if (!touchState || touchState.pointerId !== event.pointerId) return;
    window.clearTimeout(touchState.timer);
    var state = touchState;
    touchState = null;
    if (state.ghost) state.ghost.remove();
    state.card.classList.remove('is-dragging');
    board.querySelectorAll('.is-drag-over').forEach(function (item) { item.classList.remove('is-drag-over'); });
    if (state.active && state.column) moveCard(state.card, state.column);
  }

  board.addEventListener('pointerup', endTouch);
  board.addEventListener('pointercancel', endTouch);
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
  refreshColumnState();
  refreshActiveCollaborators();
}());
