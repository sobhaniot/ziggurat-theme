document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var links = Array.from(document.querySelectorAll('.glightbox'));
  if (!links.length) return;
  var current = 0;
  var overlay = document.createElement('div');
  overlay.className = 'zigurat-lightbox';
  overlay.setAttribute('aria-hidden', 'true');
  overlay.innerHTML = '<button class="zigurat-lightbox__close" type="button" aria-label="بستن">×</button><button class="zigurat-lightbox__next" type="button" aria-label="تصویر بعدی">‹</button><img alt=""><button class="zigurat-lightbox__prev" type="button" aria-label="تصویر قبلی">›</button>';
  document.body.appendChild(overlay);
  var image = overlay.querySelector('img');
  function show(index) {
    current = (index + links.length) % links.length;
    image.src = links[current].href;
    image.alt = links[current].querySelector('img') ? links[current].querySelector('img').alt : '';
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('lightbox-open');
  }
  function close() {
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('lightbox-open');
  }
  links.forEach(function (link, index) { link.addEventListener('click', function (event) { event.preventDefault(); show(index); }); });
  overlay.querySelector('.zigurat-lightbox__close').addEventListener('click', close);
  overlay.querySelector('.zigurat-lightbox__next').addEventListener('click', function () { show(current + 1); });
  overlay.querySelector('.zigurat-lightbox__prev').addEventListener('click', function () { show(current - 1); });
  overlay.addEventListener('click', function (event) { if (event.target === overlay) close(); });
  document.addEventListener('keydown', function (event) {
    if (!overlay.classList.contains('is-open')) return;
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowLeft') show(current + 1);
    if (event.key === 'ArrowRight') show(current - 1);
  });
});
