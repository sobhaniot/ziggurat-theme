(function () {
  'use strict';

  var controller = null;
  var digits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};

  function normalize(value) {
    return String(value || '')
      .replace(/[۰-۹٠-٩]/g, function (digit) { return digits[digit] || digit; })
      .replace(/[٬،,]/g, '')
      .replace(/×/g, '*')
      .replace(/÷/g, '/')
      .replace(/[−–]/g, '-');
  }

  function formatMoney(value) {
    return Math.round(value).toLocaleString('fa-IR') + ' ریال';
  }

  function evaluateExpression(expression) {
    var compact = normalize(expression).replace(/\s+/g, '');
    if (!compact) return 0;
    if (!/^[0-9.+\-*/%()]+$/.test(compact)) throw new Error('invalid');
    var tokens = compact.match(/\d+(?:\.\d+)?|[()+\-*/%]/g);
    if (!tokens || tokens.join('') !== compact) throw new Error('invalid');

    var output = [];
    var operators = [];
    var precedence = {'+':1, '-':1, '*':2, '/':2};
    var previous = 'start';

    tokens.forEach(function (token) {
      if (/^\d/.test(token)) {
        output.push(parseFloat(token));
        previous = 'number';
        return;
      }
      if (token === '%') {
        if (previous !== 'number' && previous !== 'close' && previous !== 'percent') throw new Error('invalid');
        output.push('%');
        previous = 'percent';
        return;
      }
      if (token === '(') {
        operators.push(token);
        previous = 'open';
        return;
      }
      if (token === ')') {
        if (previous !== 'number' && previous !== 'close' && previous !== 'percent') throw new Error('invalid');
        while (operators.length && operators[operators.length - 1] !== '(') output.push(operators.pop());
        if (operators.pop() !== '(') throw new Error('invalid');
        previous = 'close';
        return;
      }
      if (previous !== 'number' && previous !== 'close' && previous !== 'percent') throw new Error('invalid');
      while (operators.length && operators[operators.length - 1] !== '(' && precedence[operators[operators.length - 1]] >= precedence[token]) {
        output.push(operators.pop());
      }
      operators.push(token);
      previous = 'operator';
    });
    if (previous === 'operator' || previous === 'open') throw new Error('invalid');
    while (operators.length) {
      var operator = operators.pop();
      if (operator === '(') throw new Error('invalid');
      output.push(operator);
    }

    var stack = [];
    output.forEach(function (token) {
      if (typeof token === 'number') {
        stack.push(token);
      } else if (token === '%') {
        if (!stack.length) throw new Error('invalid');
        stack.push(stack.pop() / 100);
      } else {
        if (stack.length < 2) throw new Error('invalid');
        var right = stack.pop();
        var left = stack.pop();
        if (token === '+') stack.push(left + right);
        if (token === '-') stack.push(left - right);
        if (token === '*') stack.push(left * right);
        if (token === '/') {
          if (right === 0) throw new Error('divide');
          stack.push(left / right);
        }
      }
    });
    if (stack.length !== 1 || !Number.isFinite(stack[0])) throw new Error('invalid');
    return stack[0];
  }

  function initializeCalculator(root) {
    if (controller) controller.abort();
    controller = new AbortController();
    document.querySelectorAll('[data-invoice-calculator-root]').forEach(function (node) { node.remove(); });

    var editor = (root || document).querySelector('[data-invoice-editor]');
    if (!editor) return;

    var shell = document.createElement('div');
    shell.dataset.invoiceCalculatorRoot = '1';
    shell.innerHTML = '<div class="invoice-calculator-backdrop" data-calculator-backdrop hidden></div>' +
      '<section class="invoice-calculator" data-invoice-calculator role="dialog" aria-modal="true" aria-labelledby="invoice-calculator-title" hidden>' +
        '<header><strong id="invoice-calculator-title">ماشین‌حساب مبلغ واحد</strong><button type="button" data-calculator-close aria-label="بستن">×</button></header>' +
        '<label>محاسبه<input type="text" inputmode="decimal" dir="ltr" data-calculator-expression autocomplete="off" aria-describedby="invoice-calculator-help"></label>' +
        '<small id="invoice-calculator-help">برای درصد می‌توانید بنویسید: 1500000 × 10٪</small>' +
        '<output data-calculator-result>۰ ریال</output>' +
        '<div class="invoice-calculator__keys">' +
          '<button type="button" data-action="clear" class="is-command">C</button><button type="button" data-action="backspace" class="is-command">⌫</button><button type="button" data-value="%" class="is-operator">٪</button><button type="button" data-value="/" class="is-operator">÷</button>' +
          '<button type="button" data-value="7">۷</button><button type="button" data-value="8">۸</button><button type="button" data-value="9">۹</button><button type="button" data-value="*" class="is-operator">×</button>' +
          '<button type="button" data-value="4">۴</button><button type="button" data-value="5">۵</button><button type="button" data-value="6">۶</button><button type="button" data-value="-" class="is-operator">−</button>' +
          '<button type="button" data-value="1">۱</button><button type="button" data-value="2">۲</button><button type="button" data-value="3">۳</button><button type="button" data-value="+" class="is-operator">+</button>' +
          '<button type="button" data-value="000">۰۰۰</button><button type="button" data-value="0">۰</button><button type="button" data-value=".">.</button><button type="button" data-action="equals" class="is-equals">=</button>' +
        '</div>' +
        '<button type="button" class="invoice-calculator__apply" data-calculator-apply>قرار دادن در مبلغ واحد</button>' +
      '</section>';
    document.body.appendChild(shell);

    var panel = shell.querySelector('[data-invoice-calculator]');
    var backdrop = shell.querySelector('[data-calculator-backdrop]');
    var expression = shell.querySelector('[data-calculator-expression]');
    var result = shell.querySelector('[data-calculator-result]');
    var activeInput = null;
    var activeButton = null;
    var lastResult = 0;
    var justEvaluated = false;

    function refreshResult() {
      try {
        lastResult = evaluateExpression(expression.value);
        if (lastResult < 0) throw new Error('negative');
        result.textContent = formatMoney(lastResult);
        result.classList.remove('is-error');
        return true;
      } catch (error) {
        result.textContent = error.message === 'divide' ? 'تقسیم بر صفر ممکن نیست' : 'عبارت محاسباتی معتبر نیست';
        result.classList.add('is-error');
        return false;
      }
    }

    function positionPanel() {
      panel.style.left = '';
      panel.style.top = '';
      if (window.innerWidth <= 700 || !activeButton) return;
      var rect = activeButton.getBoundingClientRect();
      var width = panel.offsetWidth;
      var height = panel.offsetHeight;
      var left = Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12));
      var top = rect.bottom + 8;
      if (top + height > window.innerHeight - 12) top = Math.max(12, rect.top - height - 8);
      panel.style.left = left + 'px';
      panel.style.top = top + 'px';
    }

    function openCalculator(input, button) {
      activeInput = input;
      activeButton = button;
      var current = normalize(input.value).replace(/[^0-9]/g, '');
      expression.value = current === '0' ? '' : current;
      justEvaluated = false;
      panel.hidden = false;
      backdrop.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      refreshResult();
      positionPanel();
      expression.focus();
      expression.select();
    }

    function closeCalculator() {
      panel.hidden = true;
      backdrop.hidden = true;
      if (activeButton) {
        activeButton.setAttribute('aria-expanded', 'false');
        activeButton.focus({preventScroll:true});
      }
      activeInput = null;
      activeButton = null;
    }

    function appendValue(value) {
      var current = normalize(expression.value).replace(/[^0-9.+\-*/%()]/g, '');
      var isOperator = /^[+\-*/]$/.test(value);
      if (justEvaluated) {
        if (/^(?:\d|000|\.)$/.test(value)) current = '';
        justEvaluated = false;
      }
      if (isOperator && (!current || /[+\-*/.]$/.test(current))) {
        if (current && /[+\-*/]$/.test(current)) current = current.slice(0, -1) + value;
      } else if (value === '%' && (!current || /[+\-*/.%]$/.test(current))) {
        return;
      } else {
        current += value;
      }
      expression.value = current;
      expression.focus();
      refreshResult();
    }

    function applyResult() {
      if (!activeInput || !refreshResult()) return;
      activeInput.value = Math.round(lastResult).toLocaleString('fa-IR');
      activeInput.dispatchEvent(new Event('input', {bubbles:true}));
      closeCalculator();
    }

    function bindUnitPrice(input) {
      if (input.dataset.calculatorReady === '1') return;
      input.dataset.calculatorReady = '1';
      var wrapper = document.createElement('div');
      wrapper.className = 'invoice-unit-price-control';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'invoice-calculator-toggle';
      button.setAttribute('aria-label', 'باز کردن ماشین‌حساب مبلغ واحد');
      button.setAttribute('aria-expanded', 'false');
      button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="2.5" width="16" height="19" rx="2"/><path d="M7.5 6h9v3h-9zM8 13h1M12 13h1M16 13h1M8 17h1M12 17h1M16 17h1"/></svg>';
      wrapper.appendChild(button);
      button.addEventListener('click', function () { openCalculator(input, button); }, {signal:controller.signal});
    }

    function bindAllInputs() {
      editor.querySelectorAll('[name="item_unit_price[]"]').forEach(bindUnitPrice);
    }

    bindAllInputs();
    var observer = new MutationObserver(bindAllInputs);
    observer.observe(editor.querySelector('[data-invoice-items]'), {childList:true, subtree:true});
    controller.signal.addEventListener('abort', function () { observer.disconnect(); }, {once:true});

    expression.addEventListener('input', function () {
      justEvaluated = false;
      refreshResult();
    }, {signal:controller.signal});
    expression.addEventListener('keydown', function (event) {
      event.stopPropagation();
      if (event.key === 'Delete') {
        event.preventDefault();
        expression.value = '';
        justEvaluated = false;
        refreshResult();
        return;
      }
      if (justEvaluated && /^[0-9۰-۹٠-٩.]$/.test(event.key)) {
        expression.value = '';
        justEvaluated = false;
      }
      if (event.key === 'Enter') {
        event.preventDefault();
        if (refreshResult()) {
          expression.value = String(Math.round(lastResult));
          justEvaluated = true;
          expression.setSelectionRange(expression.value.length, expression.value.length);
        }
      }
      if (event.key === 'Escape') { event.preventDefault(); closeCalculator(); }
    }, {signal:controller.signal});
    panel.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button) return;
      if (button.matches('[data-calculator-close]')) closeCalculator();
      if (button.matches('[data-calculator-apply]')) applyResult();
      if (button.dataset.value) appendValue(button.dataset.value);
      if (button.dataset.action === 'clear') { expression.value = ''; justEvaluated = false; refreshResult(); expression.focus(); }
      if (button.dataset.action === 'backspace') { expression.value = expression.value.slice(0, -1); justEvaluated = false; refreshResult(); expression.focus(); }
      if (button.dataset.action === 'equals' && refreshResult()) {
        expression.value = String(Math.round(lastResult));
        justEvaluated = true;
      }
    }, {signal:controller.signal});
    backdrop.addEventListener('click', closeCalculator, {signal:controller.signal});
    window.addEventListener('resize', positionPanel, {signal:controller.signal});
    window.addEventListener('scroll', function () { if (!panel.hidden) positionPanel(); }, {signal:controller.signal, capture:true});
  }

  document.addEventListener('DOMContentLoaded', function () { initializeCalculator(document); });
  document.addEventListener('zigurat:panel-updated', function (event) {
    initializeCalculator(event.detail && event.detail.root ? event.detail.root : document);
  });
}());
