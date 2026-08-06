document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.querySelector('.manager-password-toggle');
  const password = document.getElementById('user_pass');
  if (!toggle || !password) {
    return;
  }

  toggle.addEventListener('click', function () {
    const shouldShow = password.type === 'password';
    password.type = shouldShow ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
    toggle.setAttribute('aria-label', shouldShow ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور');
    password.focus({ preventScroll: true });
  });
});
