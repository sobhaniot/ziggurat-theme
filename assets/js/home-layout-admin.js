(function ($) {
  'use strict';

  var list = $('[data-home-layout-list]');
  if (!list.length) return;

  function refresh() {
    var items = list.children('li');
    items.each(function (index) {
      $(this).find('[data-home-layout-number]').text((index + 1).toLocaleString('fa-IR'));
    });
    items.find('[data-home-layout-up]').prop('disabled', false);
    items.find('[data-home-layout-down]').prop('disabled', false);
    items.first().find('[data-home-layout-up]').prop('disabled', true);
    items.last().find('[data-home-layout-down]').prop('disabled', true);
  }

  list.sortable({
    axis: 'y',
    handle: '.zigurat-home-layout-handle',
    placeholder: 'zigurat-home-layout-placeholder',
    update: refresh
  });

  list.on('click', '[data-home-layout-up], [data-home-layout-down]', function () {
    var item = $(this).closest('li');
    if ($(this).is('[data-home-layout-up]')) {
      item.prev().before(item);
    } else {
      item.next().after(item);
    }
    refresh();
  });

  refresh();
})(jQuery);
