/* Buchlovská spíž – drobná vylepšení. Stránky fungují i bez JavaScriptu. */
(function () {
  'use strict';

  /* Plovoucí hlášky: křížek zavře, úspěšné samy zmizí (pauza při najetí myší / fokusu). */
  function dismiss(toast) {
    if (toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    var remove = function () {
      var box = toast.parentNode;
      toast.remove();
      if (box && !box.children.length) box.remove();
    };
    toast.addEventListener('animationend', remove);
    setTimeout(remove, 400); // pojistka, když se animace nespustí (omezený pohyb)
  }

  document.querySelectorAll('.toast').forEach(function (toast) {
    var close = toast.querySelector('.toast-close');
    if (close) {
      close.hidden = false;
      close.addEventListener('click', function () { dismiss(toast); });
    }

    var delay = parseInt(toast.getAttribute('data-autohide') || '', 10);
    if (!delay) return;
    var timer = null;
    var start = function () { timer = setTimeout(function () { dismiss(toast); }, delay); };
    var stop = function () { clearTimeout(timer); };
    toast.addEventListener('mouseenter', stop);
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', stop);
    toast.addEventListener('focusout', start);
    start();
  });
})();

/* Rozbalovací menu (details.dropdown): zavřít klikem mimo nebo klávesou Esc. */
(function () {
  'use strict';
  var menus = document.querySelectorAll('details[data-dropdown]');
  if (!menus.length) return;
  document.addEventListener('click', function (event) {
    menus.forEach(function (menu) { if (menu.open && !menu.contains(event.target)) menu.open = false; });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    menus.forEach(function (menu) {
      if (menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
    });
  });
})();

/* Na úzkém displeji kratší nápověda ve vyhledávání (např. jen „Hledat produkt“). */
(function () {
  'use strict';
  var inputs = document.querySelectorAll('input[data-placeholder-short]');
  if (!inputs.length || !window.matchMedia) return;
  var narrow = window.matchMedia('(max-width: 560px)');
  inputs.forEach(function (input) { input.setAttribute('data-placeholder-long', input.placeholder); });
  var apply = function () {
    inputs.forEach(function (input) {
      input.placeholder = input.getAttribute(narrow.matches ? 'data-placeholder-short' : 'data-placeholder-long');
    });
  };
  apply();
  if (narrow.addEventListener) narrow.addEventListener('change', apply); else narrow.addListener(apply);
})();
