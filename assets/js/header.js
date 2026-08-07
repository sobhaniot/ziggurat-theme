document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var button = document.getElementById('mobile-menu-btn');
  var menu = document.getElementById('main-menu');
  if (button && menu) {
    button.addEventListener('click', function () {
      menu.classList.toggle('active');
      button.classList.toggle('open');
    });
    menu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        menu.classList.remove('active');
        button.classList.remove('open');
      });
    });
  }
  var header = document.getElementById('main-header');
  function updateHeader() {
    if (header) header.classList.toggle('scrolled', window.scrollY > 50);
  }
  window.addEventListener('scroll', updateHeader, { passive: true });
  updateHeader();
});
