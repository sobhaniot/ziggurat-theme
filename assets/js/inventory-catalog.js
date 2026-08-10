(function () {
  'use strict';
  function initializeInventory(root) {
  (root || document).querySelectorAll('[data-inventory-dependent]').forEach(function (group) {
    if (group.dataset.inventoryDependentReady === '1') return;
    group.dataset.inventoryDependentReady = '1';
    var category = group.querySelector('[data-inventory-category]');
    var product = group.querySelector('[data-inventory-product]');
    if (!category || !product) return;
    var options = Array.from(product.querySelectorAll('option[data-category-id]')).map(function (option) {
      return { value: option.value, label: option.textContent, category: option.dataset.categoryId, selected: option.selected };
    });
    function syncProducts() {
      var oldValue = product.value;
      var selectedFromServer = options.find(function (option) { return option.selected; });
      product.innerHTML = '';
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = category.value ? 'انتخاب کالا' : 'ابتدا دسته را انتخاب کنید';
      product.appendChild(placeholder);
      options.filter(function (option) { return option.category === category.value; }).forEach(function (item) {
        var option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.label;
        product.appendChild(option);
      });
      if (oldValue && product.querySelector('option[value="' + CSS.escape(oldValue) + '"]')) product.value = oldValue;
      else if (selectedFromServer && selectedFromServer.category === category.value) product.value = selectedFromServer.value;
      product.disabled = !category.value;
    }
    category.addEventListener('change', syncProducts);
    syncProducts();

    var action = group.querySelector('[data-inventory-action]');
    var project = group.querySelector('[data-inventory-project]');
    if (action && project) {
      function syncProject() {
        if (action.value === 'add') project.value = '';
        project.disabled = action.value === 'add';
      }
      action.addEventListener('change', syncProject);
      syncProject();
    }
  });
  }
  initializeInventory(document);
  document.addEventListener('zigurat:panel-updated', function (event) { initializeInventory(event.detail && event.detail.root ? event.detail.root : document); });
}());
