(function () {
  'use strict';

  var inFlight = false;
  var timer;

  async function renderPendingRubikaProjects() {
    var workflow = window.ziguratWorkflowProjects;
    if (inFlight || document.hidden || !window.zigguratRubikaBrowserRender
        || !window.zigguratPrepareRubikaRender || !workflow || !workflow.ajaxUrl
        || !workflow.nonce || !document.querySelector('[data-project-board]')) return;

    inFlight = true;
    try {
      var response = await fetch(workflow.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: new URLSearchParams({
          action: 'ziggurat_rubika_pending_render_projects',
          nonce: workflow.nonce
        })
      });
      var result = await response.json();
      if (!response.ok || !result.success || !Array.isArray(result.data)) return;

      for (var projectId of result.data) {
        await window.zigguratPrepareRubikaRender(projectId);
      }
    } catch (error) {
      console.warn('آماده‌سازی خودکار تصویر اعلان روبیکا انجام نشد.');
    } finally {
      inFlight = false;
    }
  }

  renderPendingRubikaProjects();
  timer = window.setInterval(renderPendingRubikaProjects, 15000);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) renderPendingRubikaProjects();
  });
  window.addEventListener('pagehide', function () {
    if (timer) window.clearInterval(timer);
  }, {once: true});
})();
