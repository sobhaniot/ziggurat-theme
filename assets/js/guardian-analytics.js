(function (w, d) {
  'use strict';
  if (w.zigguratGuardianTrackingInstalled) return;
  w.zigguratGuardianTrackingInstalled = true;
  var config = w.zigguratGuardianAnalytics || {};
  var recent = new WeakMap();
  var sentLead = false;
  function permitted() { return w.zigguratAnalyticsConsent === true && typeof w.gtag === 'function'; }
  function emit(name, method) {
    if (!permitted()) return false;
    // Never pass href, phone, email, form values, receipt or arbitrary DOM text.
    w.gtag('event', name, {contact_method: method});
    return true;
  }
  function lead() {
    var receipt = config.leadReceipt;
    if (sentLead || !/^[a-f0-9]{32}$/.test(receipt || '') || !permitted()) return;
    var key = 'ziggurat-lead-' + receipt;
    try { if (w.localStorage.getItem(key)) return; } catch (ignore) {}
    if (emit('generate_lead', 'consultation_form')) {
      sentLead = true;
      try { w.localStorage.setItem(key, String(Date.now())); } catch (ignore) {}
    }
  }
  d.addEventListener('click', function (event) {
    var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!link || !permitted()) return;
    var name, method, url;
    try { url = new URL(link.href, w.location.origin); } catch (ignore) { return; }
    if (url.protocol === 'tel:') { name = 'click_phone'; method = 'phone'; }
    else if (['wa.me', 'api.whatsapp.com', 'web.whatsapp.com'].indexOf(url.hostname) >= 0 && url.protocol === 'https:') { name = 'click_whatsapp'; method = 'whatsapp'; }
    else if (url.protocol === 'mailto:') { name = 'click_contact'; method = 'email'; }
    else if (url.origin === w.location.origin && /^\/contact\/?$/.test(url.pathname)) { name = 'click_contact'; method = 'contact_page'; }
    else return;
    var stamp = recent.get(link) || 0;
    if (Date.now() - stamp < 1000) return;
    if (emit(name, method)) recent.set(link, Date.now());
  });
  // A CMP can dispatch this event after updating zigguratAnalyticsConsent.
  w.addEventListener('ziggurat:analytics-consent', lead);
  lead();
})(window, document);
