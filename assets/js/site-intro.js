(function () {
  'use strict';

  var root = document.documentElement;
  var finished = false;

  function clearFailsafe() {
    if (window.ziguratIntroFailsafe) {
      window.clearTimeout(window.ziguratIntroFailsafe);
      window.ziguratIntroFailsafe = null;
    }
  }

  function finishIntro(intro, immediate) {
    if (finished) return;
    finished = true;
    clearFailsafe();
    if (!intro || immediate) {
      root.classList.remove('zigurat-intro-pending', 'zigurat-intro-finishing');
      if (intro) intro.remove();
      return;
    }
    root.classList.add('zigurat-intro-finishing');
    root.classList.remove('zigurat-intro-pending');
    window.setTimeout(function () {
      root.classList.remove('zigurat-intro-finishing');
      intro.remove();
    }, 430);
  }

  function landInHeader(intro, stage) {
    intro.classList.add('is-landing');
    var target = document.querySelector('#main-header .logo img');
    if (!target || !stage.animate) {
      window.setTimeout(function () { finishIntro(intro, false); }, 420);
      return;
    }
    var stageRect = stage.getBoundingClientRect();
    var targetRect = target.getBoundingClientRect();
    var dx = targetRect.left + targetRect.width / 2 - (stageRect.left + stageRect.width / 2);
    var dy = targetRect.top + targetRect.height / 2 - (stageRect.top + stageRect.height / 2);
    var targetScale = Math.min(targetRect.width / stageRect.width, targetRect.height / stageRect.height);
    var firstScale = 1 - ((1 - targetScale) / 3);
    var secondScale = 1 - ((1 - targetScale) * 2 / 3);
    var duration = 1400;
    var movement = stage.animate([
      { transform: 'translate3d(0,0,0) scale(1)', opacity: 1 },
      { transform: 'translate3d(' + (dx / 3) + 'px,' + (dy / 3) + 'px,0) scale(' + firstScale + ')', offset: 1 / 3, opacity: 1 },
      { transform: 'translate3d(' + (dx * 2 / 3) + 'px,' + (dy * 2 / 3) + 'px,0) scale(' + secondScale + ')', offset: 2 / 3, opacity: 1 },
      { transform: 'translate3d(' + dx + 'px,' + dy + 'px,0) scale(' + targetScale + ')', opacity: 1 }
    ], {
      duration: duration,
      easing: 'linear',
      fill: 'forwards'
    });
    var logo = stage.querySelector('svg');
    var rotation = logo && logo.animate ? logo.animate([
      { transform: 'rotateY(0deg)' },
      { transform: 'rotateY(360deg)', offset: 1 / 3 },
      { transform: 'rotateY(720deg)', offset: 2 / 3 },
      { transform: 'rotateY(1080deg)' }
    ], {
      duration: duration,
      easing: 'linear',
      fill: 'forwards'
    }) : null;
    var animations = [movement.finished];
    if (rotation) animations.push(rotation.finished);
    Promise.all(animations).then(function () { finishIntro(intro, false); }).catch(function () { finishIntro(intro, false); });
  }

  function colorAndSpin(intro, stage) {
    intro.classList.add('is-coloring');
    window.setTimeout(function () {
      if (finished) return;
      landInHeader(intro, stage);
    }, 620);
  }

  function start() {
    var intro = document.getElementById('zigurat-site-intro');
    if (!intro || !root.classList.contains('zigurat-intro-pending')) {
      finishIntro(intro, true);
      return;
    }
    var stage = intro.querySelector('.zigurat-site-intro__stage');
    var logo = stage && stage.querySelector('svg');
    var skip = intro.querySelector('[data-intro-skip]');
    function skipIntro() { finishIntro(intro, true); }
    if (skip) skip.addEventListener('click', skipIntro);
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') skipIntro(); });
    if (!stage || !logo) {
      finishIntro(intro, true);
      return;
    }
    intro.classList.add('is-drawing');
    window.setTimeout(function () { colorAndSpin(intro, stage); }, 1750);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
}());
