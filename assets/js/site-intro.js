(function () {
  'use strict';

  var root = document.documentElement;
  var finished = false;
  var drawingFallback = null;

  function clearFailsafe() {
    if (window.ziguratIntroFailsafe) {
      window.clearTimeout(window.ziguratIntroFailsafe);
      window.ziguratIntroFailsafe = null;
    }
    if (drawingFallback) {
      window.clearTimeout(drawingFallback);
      drawingFallback = null;
    }
  }

  function armRuntimeFailsafe(intro) {
    clearFailsafe();
    window.ziguratIntroFailsafe = window.setTimeout(function () {
      finishIntro(intro, false);
    }, 7200);
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

  function animationFinished(animation, duration) {
    return new Promise(function (resolve) {
      var settled = false;
      var fallback = window.setTimeout(done, duration + 180);
      function done() {
        if (settled) return;
        settled = true;
        window.clearTimeout(fallback);
        resolve();
      }
      if (!animation) {
        done();
        return;
      }
      if (animation.finished && typeof animation.finished.then === 'function') {
        animation.finished.then(done).catch(done);
      } else if (typeof animation.addEventListener === 'function') {
        animation.addEventListener('finish', done, { once: true });
        animation.addEventListener('cancel', done, { once: true });
      }
    });
  }

  function landInHeader(intro, stage, target) {
    intro.classList.add('is-landing');
    if (!target || !stage.animate) {
      window.setTimeout(function () { finishIntro(intro, false); }, 420);
      return;
    }
    var stageRect = stage.getBoundingClientRect();
    var targetRect = target.getBoundingClientRect();
    if (stageRect.width < 1 || stageRect.height < 1 || targetRect.width < 1 || targetRect.height < 1) {
      finishIntro(intro, false);
      return;
    }
    var dx = targetRect.left + targetRect.width / 2 - (stageRect.left + stageRect.width / 2);
    var dy = targetRect.top + targetRect.height / 2 - (stageRect.top + stageRect.height / 2);
    var targetScale = Math.min(1, targetRect.width / stageRect.width, targetRect.height / stageRect.height);
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
    var animations = [animationFinished(movement, duration)];
    if (rotation) animations.push(animationFinished(rotation, duration));
    Promise.all(animations).then(function () { finishIntro(intro, false); }).catch(function () { finishIntro(intro, false); });
  }

  function waitForHeaderLogo(callback) {
    var startedAt = window.performance && performance.now ? performance.now() : Date.now();
    function check() {
      if (finished) return;
      var target = document.querySelector('#main-header .logo img');
      var rect = target ? target.getBoundingClientRect() : null;
      if (target && rect && rect.width > 1 && rect.height > 1) {
        callback(target);
        return;
      }
      var now = window.performance && performance.now ? performance.now() : Date.now();
      if (now - startedAt < 1600) {
        window.requestAnimationFrame(check);
      } else {
        var logoBox = document.querySelector('#main-header .logo');
        var logoBoxRect = logoBox ? logoBox.getBoundingClientRect() : null;
        callback(logoBoxRect && logoBoxRect.width > 1 && logoBoxRect.height > 1 ? logoBox : target);
      }
    }
    check();
  }

  function colorAndSpin(intro, stage) {
    intro.classList.add('is-coloring');
    window.setTimeout(function () {
      if (finished) return;
      waitForHeaderLogo(function (target) {
        if (!finished) landInHeader(intro, stage, target);
      });
    }, 620);
  }

  function drawLogo(intro, logo) {
    var traces = Array.prototype.slice.call(logo.querySelectorAll('.zigurat-logo__trace'));
    if (!traces.length) {
      intro.classList.add('is-drawing');
      colorAndSpin(intro, intro.querySelector('.zigurat-site-intro__stage'));
      return;
    }
    var remaining = traces.length;
    var drawingFinished = false;
    function completeDrawing() {
      if (drawingFinished || finished) return;
      drawingFinished = true;
      if (drawingFallback) {
        window.clearTimeout(drawingFallback);
        drawingFallback = null;
      }
      colorAndSpin(intro, intro.querySelector('.zigurat-site-intro__stage'));
    }
    function traceFinished() {
      remaining -= 1;
      if (remaining <= 0) completeDrawing();
    }
    traces.forEach(function (trace) {
      trace.addEventListener('animationend', traceFinished, { once: true });
      trace.addEventListener('animationcancel', traceFinished, { once: true });
    });
    drawingFallback = window.setTimeout(completeDrawing, 2600);
    window.requestAnimationFrame(function () {
      if (!finished) intro.classList.add('is-drawing');
    });
  }

  function start() {
    var intro = document.getElementById('zigurat-site-intro');
    if (!intro || !root.classList.contains('zigurat-intro-pending')) {
      finishIntro(intro, true);
      return;
    }
    armRuntimeFailsafe(intro);
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
    drawLogo(intro, logo);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
}());
