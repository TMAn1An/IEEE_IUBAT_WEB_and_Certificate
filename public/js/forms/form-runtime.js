/* =========================================================================
   Dynamic forms — public page runtime.
   Applies conditional visibility as the visitor types: hidden fields are
   hidden AND their inputs disabled (so they're neither validated by the
   browser nor submitted). Purely a convenience — the server re-applies the
   same rules (FormVisibilityResolver) and ignores hidden fields regardless.
   Requires form-logic.js. No custom admin JavaScript is ever executed here
   (see docs/FORM_BUILDER.md §Custom code security).
   ========================================================================= */
(function () {
  'use strict';

  function readValue(formEl, field) {
    var inputs = formEl.querySelectorAll('[name="' + CSS.escape(field.key) + '"], [name="' + CSS.escape(field.key + '[]') + '"]');
    if (inputs.length === 0) return null;

    if (field.type === 'checkbox_group') {
      return Array.prototype.filter.call(inputs, function (i) { return i.checked; }).map(function (i) { return i.value; });
    }
    if (field.type === 'radio') {
      var checked = Array.prototype.find.call(inputs, function (i) { return i.checked; });
      return checked ? checked.value : null;
    }
    if (field.type === 'checkbox') {
      return inputs[0].checked ? '1' : null;
    }
    return inputs[0].value;
  }

  function init(root) {
    var logicEl = root.querySelector('script[data-ff-logic]');
    var formEl = root.querySelector('form');
    if (!logicEl || !formEl) return;

    var fields;
    try { fields = JSON.parse(logicEl.textContent); } catch (e) { return; }

    function apply() {
      var visible = window.FormLogic.resolve(fields, function (f) { return readValue(formEl, f); });
      Object.keys(visible).forEach(function (key) {
        var wrapper = root.querySelector('[data-ff-key="' + CSS.escape(key) + '"]');
        if (!wrapper) return;
        wrapper.hidden = !visible[key];
        wrapper.querySelectorAll('input, select, textarea').forEach(function (input) {
          // Fields the admin marked "disabled" stay disabled whatever happens.
          input.disabled = !visible[key] || input.hasAttribute('data-ff-disabled');
        });
      });
    }

    formEl.addEventListener('input', apply);
    formEl.addEventListener('change', apply);
    formEl.addEventListener('submit', function () {
      var button = formEl.querySelector('.ff-button');
      if (button) { window.setTimeout(function () { button.disabled = true; }, 0); }
    });
    apply();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ff-form').forEach(init);
  });
})();
