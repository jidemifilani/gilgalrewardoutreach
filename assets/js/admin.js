/* Admin panel behaviour: sidebar toggle, "something else" selects,
   delete confirmation and live search. */
(function () {
  'use strict';

  /* Sidebar on small screens */
  var menuBtn = document.querySelector('.admin-menu-btn');
  var sidebar = document.getElementById('adminSidebar');

  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', function () {
      var open = sidebar.classList.toggle('is-open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* A select marked data-allow-other reveals its free-text companion input
     when "Something else…" is chosen. */
  document.querySelectorAll('select[data-allow-other="1"]').forEach(function (select) {
    var other = select.parentElement.querySelector('.other-input');
    if (!other) return;

    var sync = function () {
      var show = select.value === '__other__';
      other.hidden = !show;
      if (show) other.focus();
    };
    select.addEventListener('change', sync);
  });

  /* Deleting asks first, naming what is about to go. */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  /* Filter the list table as you type, without a round trip. The server-side
     search still works (and covers rows beyond the 200-row page) when JS is off. */
  var search = document.getElementById('tableSearch');
  var table = document.getElementById('listTable');

  if (search && table) {
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
    var empty = document.getElementById('tableEmpty');

    search.addEventListener('input', function () {
      var term = search.value.trim().toLowerCase();
      var shown = 0;

      rows.forEach(function (row) {
        var match = term === '' || row.textContent.toLowerCase().indexOf(term) !== -1;
        row.hidden = !match;
        if (match) shown++;
      });

      if (empty) empty.hidden = shown !== 0;
    });
  }
})();

/* Bulk selection: reveals the action bar once anything is ticked, keeps the
   header checkbox in step, and confirms before applying. */
(function () {
  'use strict';

  var form = document.getElementById('bulkForm');
  if (!form) return;

  var bar = document.getElementById('bulkBar');
  var count = document.getElementById('bulkCount');
  var checkAll = document.getElementById('checkAll');
  var rows = form.querySelectorAll('.row-check');

  if (!bar || !rows.length) return;

  var sync = function () {
    var selected = form.querySelectorAll('.row-check:checked').length;

    bar.hidden = selected === 0;
    if (count) count.textContent = selected;

    if (checkAll) {
      checkAll.checked = selected === rows.length && selected > 0;
      checkAll.indeterminate = selected > 0 && selected < rows.length;
    }
  };

  rows.forEach(function (box) { box.addEventListener('change', sync); });

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      rows.forEach(function (box) { box.checked = checkAll.checked; });
      sync();
    });
  }

  form.addEventListener('submit', function (e) {
    var action = form.querySelector('#bulkAction');
    var selected = form.querySelectorAll('.row-check:checked').length;

    if (!action || !action.value || selected === 0) {
      e.preventDefault();
      return;
    }

    var label = action.options[action.selectedIndex].text;
    var message = action.value === 'delete'
      ? 'Delete ' + selected + ' record(s) permanently? This cannot be undone.'
      : label + ' — apply to ' + selected + ' record(s)?';

    if (!window.confirm(message)) e.preventDefault();
  });

  sync();
})();

/* Keeps the colour swatch and its hex box in step. The text box is the field
   that actually submits, so it stays usable if the colour input is not
   supported or JS is off. */
(function () {
  'use strict';

  document.querySelectorAll('input[type="color"][data-color-for]').forEach(function (picker) {
    var hex = document.getElementById(picker.getAttribute('data-color-for'));
    if (!hex) return;

    picker.addEventListener('input', function () {
      hex.value = picker.value.toUpperCase();
    });

    hex.addEventListener('input', function () {
      var value = hex.value.trim();
      if (/^#?[0-9a-fA-F]{6}$/.test(value)) {
        picker.value = value.charAt(0) === '#' ? value : '#' + value;
      }
    });
  });
})();
