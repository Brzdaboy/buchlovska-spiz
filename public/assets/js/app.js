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
