(function () {
  'use strict';

  var persianNumber = function (value) {
    return Number(value || 0).toLocaleString('fa-IR');
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-partner-map]').forEach(function (map) {
      var dataElement = map.querySelector('[data-partner-map-data]');
      var results = map.querySelector('[data-partner-map-results]');
      var title = map.querySelector('[data-partner-map-title]');
      var description = map.querySelector('[data-partner-map-description]');
      if (!dataElement || !results || !title || !description) return;

      var provinceData = {};
      try {
        provinceData = JSON.parse(dataElement.textContent || '{}');
      } catch (error) {
        console.error('Could not read partner map data.', error);
        return;
      }

      var makeInitials = function (name) {
        var words = String(name || 'ز').trim().split(/\s+/).filter(Boolean);
        return words.slice(0, 2).map(function (word) { return word.charAt(0); }).join('') || 'ز';
      };

      var createCard = function (partner) {
        var link = document.createElement('a');
        link.className = 'manager-partner-card';
        link.href = partner.url;
        link.target = '_blank';
        link.rel = 'noopener';

        var photo = document.createElement('div');
        photo.className = 'manager-partner-card__photo';
        if (partner.photo) {
          var image = document.createElement('img');
          image.src = partner.photo;
          image.alt = 'عکس ' + partner.name;
          image.loading = 'lazy';
          image.addEventListener('error', function () {
            photo.replaceChildren(document.createTextNode(makeInitials(partner.name)));
          }, { once: true });
          photo.appendChild(image);
        } else {
          photo.textContent = makeInitials(partner.name);
        }

        var content = document.createElement('div');
        content.className = 'manager-partner-card__content';
        var name = document.createElement('strong');
        name.textContent = partner.name || 'بدون نام';
        var profession = document.createElement('span');
        profession.textContent = partner.profession || 'زمینه فعالیت ثبت نشده';
        var meta = document.createElement('small');
        meta.textContent = [partner.city, partner.type].filter(Boolean).join(' • ');
        content.append(name, profession, meta);
        if (partner.person) {
          var person = document.createElement('small');
          person.textContent = 'نماینده: ' + partner.person;
          content.appendChild(person);
        }

        var arrow = document.createElement('span');
        arrow.className = 'manager-partner-card__arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '←';
        link.append(photo, content, arrow);
        return link;
      };

      var selectProvince = function (path) {
        var province = path.getAttribute('data-province') || '';
        var partners = Array.isArray(provinceData[province]) ? provinceData[province] : [];
        map.querySelectorAll('.manager-partner-map__province.is-selected').forEach(function (item) {
          item.classList.remove('is-selected');
          item.setAttribute('aria-pressed', 'false');
        });
        path.classList.add('is-selected');
        path.setAttribute('aria-pressed', 'true');
        title.textContent = province;
        description.textContent = partners.length
          ? persianNumber(partners.length) + ' همکار یا تأمین‌کننده ثبت‌شده'
          : 'هنوز فردی برای این استان ثبت نشده است.';
        results.replaceChildren();

        var heading = document.createElement('div');
        heading.className = 'manager-partner-map__results-heading';
        var headingTitle = document.createElement('strong');
        headingTitle.textContent = 'افراد ثبت‌شده در ' + province;
        var headingCount = document.createElement('span');
        headingCount.textContent = persianNumber(partners.length) + ' نفر';
        heading.append(headingTitle, headingCount);
        results.appendChild(heading);

        if (!partners.length) {
          var empty = document.createElement('div');
          empty.className = 'manager-partner-map__empty';
          empty.innerHTML = '<span aria-hidden="true">—</span><strong>همکاری ثبت نشده است</strong><p>در فهرست درخواست‌ها نیز می‌توانید استان را بررسی کنید.</p>';
          results.appendChild(empty);
          return;
        }

        var cards = document.createElement('div');
        cards.className = 'manager-partner-map__cards';
        partners.forEach(function (partner) { cards.appendChild(createCard(partner)); });
        results.appendChild(cards);
      };

      map.querySelectorAll('.manager-partner-map__province').forEach(function (path) {
        path.setAttribute('aria-pressed', 'false');
        path.addEventListener('pointerenter', function () {
          var count = parseInt(path.getAttribute('data-count') || '0', 10);
          title.textContent = path.getAttribute('data-province') || '';
          description.textContent = count
            ? persianNumber(count) + ' همکار یا تأمین‌کننده ثبت‌شده؛ برای مشاهده کلیک کنید.'
            : 'همکاری برای این استان ثبت نشده است.';
        });
        path.addEventListener('click', function () { selectProvince(path); });
        path.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selectProvince(path);
          }
        });
      });
    });
  });
}());
