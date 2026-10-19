document.addEventListener('DOMContentLoaded', function () {
  // menu button on small screens
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.querySelector('.main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
    });
  }

  // adds a Show/Hide button to every password box so people can check what they typed
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    var wrap = document.createElement('div');
    wrap.className = 'password-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'password-toggle';
    btn.textContent = 'Show';
    btn.setAttribute('aria-label', 'Show password');
    btn.setAttribute('aria-pressed', 'false');
    btn.addEventListener('click', function () {
      var isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.textContent = isHidden ? 'Hide' : 'Show';
      btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
    });
    wrap.appendChild(btn);
  });

  // flash messages fade away after a few seconds
  document.querySelectorAll('.flash').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 0.4s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4500);
  });

  // ask "are you sure?" before anything with data-confirm
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // filter bars submit as soon as a dropdown changes
  document.querySelectorAll('.filter-bar[data-autosubmit]').forEach(function (form) {
    form.querySelectorAll('select').forEach(function (sel) {
      sel.addEventListener('change', function () { form.submit(); });
    });
  });

  // skills page: add and remove skill chips before saving
  var skillForm = document.querySelector('#skills-form');
  if (skillForm) {
    var chosen = new Set(JSON.parse(skillForm.dataset.selected || '[]'));
    var chipWrap = document.querySelector('#skill-chips');
    var hiddenWrap = document.querySelector('#skill-hidden-inputs');
    var select = document.querySelector('#skill-select');

    function renderChips() {
      chipWrap.innerHTML = '';
      hiddenWrap.innerHTML = '';
      chosen.forEach(function (id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        var name = opt ? opt.textContent : id;
        var chip = document.createElement('span');
        chip.className = 'skill-chip';
        chip.innerHTML = '<span>' + name + '</span>';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '×';
        btn.addEventListener('click', function () {
          chosen.delete(id);
          renderChips();
        });
        chip.appendChild(btn);
        chipWrap.appendChild(chip);

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'skill_ids[]';
        hidden.value = id;
        hiddenWrap.appendChild(hidden);
      });
    }

    select.addEventListener('change', function () {
      if (select.value) {
        chosen.add(select.value);
        select.value = '';
        renderChips();
      }
    });

    renderChips();
  }

  // show the chosen file name under file inputs
  document.querySelectorAll('input[type="file"]').forEach(function (input) {
    var hint = input.parentElement.querySelector('.file-name-preview');
    if (!hint) return;
    input.addEventListener('change', function () {
      hint.textContent = input.files.length ? input.files[0].name : '';
    });
  });
});
