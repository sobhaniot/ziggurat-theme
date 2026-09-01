(function () {
  'use strict';

  /**
   * قانون سراسری ورود عدد:
   * - تمام inputهای type=number هنگام ورود کاربر آماده تایپ می‌شوند.
   * - برای فیلدهای متنی/فرمت‌شده نیز می‌توان data-clear-on-focus گذاشت.
   * - با data-keep-value-on-focus می‌توان یک فیلد عددی خاص را مستثنا کرد.
   * - اگر کاربر چیزی ننویسد، مقدار قبلی برمی‌گردد تا داده ناخواسته پاک نشود.
   *
   * استفاده از delegation باعث می‌شود فیلدهای جدید و محتوای AJAX نیز خودکار
   * همین رفتار را داشته باشند و به راه‌اندازی دوباره اسکریپت نیاز نباشد.
   */
  function isClearableField(element) {
    return element instanceof HTMLInputElement
      && !element.disabled
      && !element.readOnly
      && !element.matches('[data-keep-value-on-focus]')
      && element.matches('[data-clear-on-focus], input[type="number"]');
  }

  document.addEventListener('focus', function (event) {
    var input = event.target;
    if (!isClearableField(input) || input.dataset.clearOnFocusActive === '1') return;
    input.dataset.clearOnFocusActive = '1';
    input.dataset.clearOnFocusPreviousValue = input.value;
    input.value = '';
  }, true);

  document.addEventListener('blur', function (event) {
    var input = event.target;
    if (!isClearableField(input) || input.dataset.clearOnFocusActive !== '1') return;
    if (String(input.value || '').trim() === '') {
      input.value = input.dataset.clearOnFocusPreviousValue || '';
    }
    delete input.dataset.clearOnFocusActive;
    delete input.dataset.clearOnFocusPreviousValue;
  }, true);
}());
