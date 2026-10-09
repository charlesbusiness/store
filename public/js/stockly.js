/* Stockly -- the two behaviours the original handled with inline functions:
   the toast and the light/dark toggle. Loaded deferred, so it is safe to call
   from markup regardless of script order. */
window.Stockly = (function () {
  var TIMEOUT = 2400;

  function toast(message) {
    var el = document.getElementById('t');
    if (!el) return;

    el.textContent = message;
    el.style.display = 'block';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(function () {
      el.style.display = 'none';
    }, TIMEOUT);
  }

  function toggleTheme(button) {
    var root = document.documentElement;
    var isDark =
      root.getAttribute('data-theme') === 'dark' ||
      (!root.hasAttribute('data-theme') &&
        window.matchMedia('(prefers-color-scheme: dark)').matches);

    var next = isDark ? 'light' : 'dark';
    root.setAttribute('data-theme', next);

    try {
      localStorage.setItem('stockly_theme', next);
    } catch (e) {
      /* Private browsing: the theme just will not persist. */
    }

    if (button) {
      button.textContent = next === 'dark' ? 'Light' : 'Dark';
    }
  }

  /**
   * Confirm before a destructive submit, using the one <dialog> in the layout.
   *
   * The submit is held rather than cancelled, and replayed with the native
   * form.submit() on accept. That deliberately skips validation and the submit
   * event, which also means it cannot loop back in here a second time. The
   * delete buttons carry no name, so nothing is lost by replaying.
   *
   * The wording comes from the form's data-confirm-text, not from an argument,
   * so the markup never has to splice user-supplied text into JS source. Called
   * from an inline onsubmit, so with JS unavailable Stockly is simply not there:
   * the handler throws, the default is not prevented, and the action goes through.
   */
  function confirmDanger(event, message) {
    var dialog = document.getElementById('stockly-confirm');

    if (!dialog) return true;

    // An engine too old for showModal submits straight through. Falling back to
    // the browser's own confirmation box here would undo the point of the change,
    // and it would not even work: preventDefault has already run, so returning
    // true from an inline handler would not bring the submit back.
    if (typeof dialog.showModal !== 'function') return true;

    event.preventDefault();

    var form = event.target;
    var wording = message || form.getAttribute('data-confirm-text') || 'Delete this line?';
    var acceptText = form.getAttribute('data-confirm-accept-text') || 'Confirm';

    dialog.querySelector('#stockly-confirm-body').textContent = wording;
    dialog.querySelector('[data-confirm-accept]').textContent = acceptText;
    pending = { form: form };
    dialog.showModal();
    dialog.querySelector('[data-confirm-accept]').focus();

    return false;
  }

  /** Set while the dialog is open, and cleared however the dialog closes. */
  var pending = null;

  function wireDialog() {
    var dialog = document.getElementById('stockly-confirm');

    if (!dialog) return;

    var accept = dialog.querySelector('[data-confirm-accept]');
    var cancel = dialog.querySelector('[data-confirm-cancel]');

    accept.addEventListener('click', function () {
      var form = pending && pending.form;
      dialog.close();

      if (form) form.submit();
    });

    cancel.addEventListener('click', function () {
      dialog.close();
    });

    // Esc, or anything else that closes it, abandons the submit.
    dialog.addEventListener('close', function () {
      pending = null;
    });
  }

  /**
   * Link a measurement <select> to a unit <select> that carries optgroups.
   *
   * Marks a form with data-unit-picker, and it holds a [data-unit-group] whose
   * option values are family codes, plus a [data-unit-value] whose optgroups
   * carry data-unit-group-body="<that code>". Choosing a measurement reveals
   * only that group and drops a unit that no longer belongs to it.
   *
   * Progressive: the unit select is server-rendered with every group visible and
   * its option values already the submitted unit codes, so a form without this
   * still posts a valid unit. The family is read off that unit on the server, so
   * the measurement box is a filter rather than a source of truth.
   */
  function wireUnitPicker(form) {
    var group = form.querySelector('[data-unit-group]');
    var value = form.querySelector('[data-unit-value]');

    if (!group || !value) return;

    var bodies = value.querySelectorAll('[data-unit-group-body]');

    function apply() {
      var wanted = group.value;

      Array.prototype.forEach.call(bodies, function (body) {
        body.hidden = wanted !== '' && body.getAttribute('data-unit-group-body') !== wanted;
      });

      // A unit left over from the previously chosen measurement is no longer
      // reachable, so clear it rather than post a unit the box cannot show.
      if (value.selectedOptions.length === 1 && value.selectedOptions[0].value) {
        var option = value.selectedOptions[0];
        var owner = option.parentNode && option.parentNode.getAttribute('data-unit-group-body');

        if (owner && owner !== wanted) value.value = '';
      }
    }

    group.addEventListener('change', apply);
    apply();
  }

  function wireUnitPickers() {
    var forms = document.querySelectorAll('[data-unit-picker]');

    Array.prototype.forEach.call(forms, function (form) {
      wireUnitPicker(form);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    wireDialog();
    wireUnitPickers();
  });

  return {
    toast: toast,
    toggleTheme: toggleTheme,
    confirmDanger: confirmDanger,
    wireUnitPicker: wireUnitPicker,
  };
})();
