document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var button = document.getElementById('mobile-menu-btn');
  var menu = document.getElementById('main-menu');
  if (button && menu) {
    function closeMenu() {
      menu.classList.remove('active');
      button.classList.remove('open');
      button.setAttribute('aria-expanded', 'false');
    }
    button.addEventListener('click', function () {
      menu.classList.toggle('active');
      button.classList.toggle('open');
      button.setAttribute('aria-expanded', menu.classList.contains('active') ? 'true' : 'false');
    });
    menu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
    document.addEventListener('click', function (event) {
      if (!menu.classList.contains('active')) return;
      if (!menu.contains(event.target) && !button.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMenu();
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) closeMenu();
    });
  }
  var header = document.getElementById('main-header');
  function updateHeader() {
    if (header) header.classList.toggle('scrolled', window.scrollY > 50);
  }
  window.addEventListener('scroll', updateHeader, { passive: true });
  updateHeader();
});
