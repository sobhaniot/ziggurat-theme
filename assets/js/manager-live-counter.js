(function () {
  'use strict';

  var counter = document.querySelector('[data-live-counter]');
  var config = window.ziguratManagerViewsConfig || {};
  if (!counter || !config.ajaxUrl || !config.nonce) return;

  var value = counter.querySelector('[data-live-counter-value]');
  var status = counter.querySelector('[data-live-counter-status]');
  var toast = counter.querySelector('[data-live-toast]');
  var total = Math.max(0, Number(counter.dataset.initialTotal) || 0);
  var timer = null;
  var fetching = false;
  var socket = null;

  function faNumber(number) { return Number(number || 0).toLocaleString('fa-IR'); }

  function update(next) {
    next = Math.max(0, Number(next) || 0);
    var increase = Math.max(0, next - total);
    if (value) value.textContent = faNumber(next);
    counter.dataset.initialTotal = String(next);
    if (increase && toast) {
      toast.textContent = '+' + faNumber(increase) + ' بازدید جدید';
      toast.hidden = false;
      toast.classList.add('is-visible');
      window.setTimeout(function () { toast.classList.remove('is-visible'); toast.hidden = true; }, 2800);
    }
    total = next;
  }

  function schedule() {
    window.clearTimeout(timer);
    if (socket && socket.readyState === WebSocket.OPEN) return;
    timer = window.setTimeout(fetchTotal, Math.max(10000, Number(config.pollInterval) || 30000));
  }

  function fetchTotal() {
    if (fetching || document.hidden) { schedule(); return; }
    fetching = true;
    var body = new URLSearchParams();
    body.set('action', 'zigurat_get_live_views');
    body.set('nonce', config.nonce);
    body.set('scope', 'counter');
    window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) { return response.json(); }).then(function (result) {
      if (!result || !result.success) throw new Error('Invalid response');
      update(result.data.total_views);
      if (status) status.textContent = 'به‌روز';
    }).catch(function () {
      if (status) status.textContent = 'تلاش دوباره…';
    }).finally(function () { fetching = false; schedule(); });
  }

  function connectSocket() {
    var url = String(config.websocketUrl || '').trim();
    if (!url || !window.WebSocket) { schedule(); return; }
    try { socket = new WebSocket(url); } catch (error) { schedule(); return; }
    socket.addEventListener('open', function () {
      window.clearTimeout(timer);
      if (status) status.textContent = 'زنده';
    });
    socket.addEventListener('message', function (event) {
      try {
        var message = JSON.parse(event.data);
        var payload = message && message.data && typeof message.data === 'object' ? message.data : message;
        if (payload && payload.total_views !== undefined) update(payload.total_views);
      } catch (error) {}
    });
    socket.addEventListener('close', schedule);
    socket.addEventListener('error', function () { if (socket) socket.close(); });
  }

  counter.addEventListener('mouseenter', function () {
    if (counter.classList.contains('is-relocating')) return;
    counter.classList.add('is-relocating');
    window.setTimeout(function () {
      counter.classList.toggle('is-left');
      window.setTimeout(function () { counter.classList.remove('is-relocating'); }, 70);
    }, 140);
  });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) fetchTotal(); });
  connectSocket();
}());
