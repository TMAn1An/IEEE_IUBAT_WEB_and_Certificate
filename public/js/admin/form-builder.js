/* =========================================================================
   Form builder — admin UI. Plain vanilla JS, no build step (same convention
   as template-designer.js / qr-tool.js; see docs/ARCHITECTURE.md).

   Data flow: boots from the JSON in #form-builder-data (built by
   App\Services\Forms\FormBuilderState), keeps the whole definition in
   `state`, renders a live preview with the SAME markup/classes/CSS as the
   public renderer (resources/views/forms/_renderer.blade.php +
   public/css/forms.css), and saves the complete definition as one JSON PUT.
   The server re-validates everything (FormDefinitionValidator) — nothing
   here is a security boundary. Admin-entered text is only ever inserted
   with textContent; the only innerHTML is HTML the server has already
   sanitized (FormHtmlSanitizer, via the /sanitize endpoint).
   See docs/FORM_BUILDER.md §Builder architecture.
   ========================================================================= */
(function () {
  'use strict';

  var root = document.getElementById('form-builder');
  var dataEl = document.getElementById('form-builder-data');
  if (!root || !dataEl) return;

  var boot = JSON.parse(dataEl.textContent);
  var META = boot.meta;
  var TYPES = META.types;
  var URLS = boot.urls;
  var PERMS = boot.permissions;
  var canEdit = !!PERMS.canEdit;
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var CSRF = csrfMeta ? csrfMeta.content : '';

  var els = {
    canvas: document.getElementById('fb-canvas'),
    palette: document.getElementById('fb-palette'),
    panel: document.getElementById('fb-panel'),
    tabs: document.getElementById('fb-tabs'),
    archived: document.getElementById('fb-archived'),
    errors: document.getElementById('fb-errors'),
    saveState: document.getElementById('fb-save-state'),
    statusBadge: document.getElementById('fb-status-badge'),
    save: document.getElementById('fb-save'),
    publish: document.getElementById('fb-publish'),
    showHidden: document.getElementById('fb-show-hidden'),
  };

  var ICONS = {
    text: 'Aa', long_text: '≡', email: '@', number: '#', phone: '☏', date: 'D', time: 'T', datetime: 'DT',
    select: '▾', radio: '◉', checkbox_group: '☑', checkbox: '✓', hidden: '∅',
    heading: 'H', paragraph: '¶', divider: '—', section: '§', html: '</>',
  };
  var GROUPS = [['input', 'Input fields'], ['choice', 'Choices'], ['layout', 'Layout & content']];
  var TABS = [['field', 'Field'], ['design', 'Design'], ['settings', 'Settings'], ['code', 'Custom code']];
  var ALIGNS = [['left', 'Left'], ['center', 'Center'], ['right', 'Right']];

  var uidSeq = 0;
  var dragPayload = null;
  var sanitizeTimers = {};
  var autoValueOptions = new WeakSet(); // options whose stored value still follows their label

  var state = {
    form: null,
    fields: [],
    rendered: null,
    selected: null,
    tab: 'field',
    version: 0,
    changeSeq: 0,
    savedSeq: 0,
    saving: false,
    pendingIntent: null,
    errors: {},
    fieldErrors: {},
    showHidden: true,
    device: 'desktop',
    touched: new Set(),
    autosaveTimer: null,
  };

  /* ================================================================ state */

  function plain(o) {
    return (o && typeof o === 'object' && !Array.isArray(o)) ? JSON.parse(JSON.stringify(o)) : {};
  }

  function fromServerField(f) {
    return {
      id: f.id,
      uid: 'f' + f.id,
      type: f.type,
      label: f.label,
      key: f.key,
      required: !!f.required,
      is_active: !!f.is_active,
      settings: plain(f.settings),
      style_settings: plain(f.style_settings),
      conditional_rules: f.conditional_rules ? JSON.parse(JSON.stringify(f.conditional_rules)) : null,
      has_submissions: !!f.has_submissions,
      keyEdited: true,
    };
  }

  function loadState(s) {
    state.form = s.form;
    state.form.settings = plain(s.form.settings);
    state.form.style_settings = plain(s.form.style_settings);
    state.version = s.form.version;
    state.fields = s.fields.map(fromServerField);
    state.rendered = { css: s.rendered.css || '', html_before: s.rendered.html_before || '', html_after: s.rendered.html_after || '', fields: {} };
    Object.keys(s.rendered.fields || {}).forEach(function (id) { state.rendered.fields['f' + id] = s.rendered.fields[id]; });
  }

  function byUid(uid) { return state.fields.find(function (f) { return f.uid === uid; }) || null; }
  function selectedField() { var f = byUid(state.selected); return f && f.is_active ? f : null; }
  function activeFields() { return state.fields.filter(function (f) { return f.is_active; }); }
  function isDirty() { return state.changeSeq !== state.savedSeq; }
  function formTitle() { return (state.form.settings.title || '').trim() || state.form.name; }

  /* ============================================================== helpers */

  function h(tag, attrs) {
    var el = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') el.className = v;
        else if (k === 'text') el.textContent = v;
        else if (k === 'style') el.style.cssText = v;
        else if (k.indexOf('on') === 0) el.addEventListener(k.slice(2), v);
        else if (v === true) el.setAttribute(k, '');
        else el.setAttribute(k, v);
      });
    }
    for (var i = 2; i < arguments.length; i++) append(el, arguments[i]);
    return el;
  }

  function append(el, child) {
    if (child === null || child === undefined || child === false) return;
    if (Array.isArray(child)) { child.forEach(function (c) { append(el, c); }); return; }
    el.append(child instanceof Node ? child : String(child));
  }

  function sanitizedHtml(className, html) {
    var div = h('div', { class: className });
    div.innerHTML = html; // server-sanitized (FormHtmlSanitizer) — never raw admin input
    return div;
  }

  function toKey(label) {
    var k = String(label || '').toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 56);
    if (!/^[a-z]/.test(k)) k = ('field_' + k).replace(/_+$/, '');
    return k;
  }

  function uniqueKey(base, except) {
    var taken = new Set(state.fields.filter(function (f) { return f !== except; }).map(function (f) { return f.key; }));
    var key = base;
    var n = 2;
    while (taken.has(key)) { var suffix = '_' + n++; key = base.slice(0, 64 - suffix.length) + suffix; }
    return key;
  }

  function cssValue(def, value) {
    if (def.type === 'px') return value + 'px';
    if (def.type === 'enum') return (def.options[value] || def.options[def.default]).css;
    return value;
  }

  function isValidStyle(def, v) {
    if (v === null || v === undefined || v === '') return false;
    if (def.type === 'color') return typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v);
    if (def.type === 'px') { var n = Number(v); return Number.isInteger(n) && n >= def.min && n <= def.max; }
    return Object.prototype.hasOwnProperty.call(def.options, String(v));
  }

  function formStyleDeclarations() {
    var out = [];
    Object.keys(META.formStyle).forEach(function (group) {
      var props = META.formStyle[group].properties;
      Object.keys(props).forEach(function (key) {
        var def = props[key];
        var v = (state.form.style_settings[group] || {})[key];
        out.push(def.var + ':' + cssValue(def, isValidStyle(def, v) ? v : def.default));
      });
    });
    return out.join(';');
  }

  function fieldStyleDeclarations(f) {
    var out = [];
    Object.keys(META.fieldStyle).forEach(function (key) {
      var def = META.fieldStyle[key];
      var v = f.style_settings[key];
      if (isValidStyle(def, v)) out.push(def.var + ':' + cssValue(def, v));
    });
    return out.join(';');
  }

  function now() { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }

  /* ============================================================ mutations */

  function changed(opts) {
    opts = opts || {};
    state.changeSeq++;
    setSaveState('Unsaved changes', 'dirty');
    if (opts.panel) renderPanel();
    renderCanvas();
    scheduleAutosave();
  }

  function setSetting(f, key, value) {
    if (value === '' || value === null || value === undefined || value === false) delete f.settings[key];
    else f.settings[key] = value;
    if (key === 'default_value') state.touched.delete(f.key);
    changed();
  }

  function setFieldStyle(f, key, value) {
    if (value === '' || value === null || value === undefined) delete f.style_settings[key];
    else f.style_settings[key] = value;
    changed();
  }

  function setFormStyle(group, key, value) {
    state.form.style_settings[group] = state.form.style_settings[group] || {};
    state.form.style_settings[group][key] = value;
    changed();
  }

  function newField(type) {
    var meta = TYPES[type];
    var labels = { heading: 'Heading', paragraph: 'Paragraph', divider: 'Divider', section: 'New section', html: 'HTML block', checkbox: 'I agree to the terms' };
    var label = labels[type] || meta.label;
    var settings = {};
    if (meta.hasOptions) {
      settings.options = [{ label: 'Option 1', value: 'option_1' }, { label: 'Option 2', value: 'option_2' }];
      settings.options.forEach(function (o) { autoValueOptions.add(o); });
    }
    if (type === 'heading') settings.content = 'Heading';
    if (type === 'paragraph') settings.content = 'Write your paragraph text here.';
    if (type === 'html') settings.content = '<p><strong>Notice:</strong> edit this HTML block.</p>';
    return {
      id: null,
      uid: 'n' + (++uidSeq),
      type: type,
      label: label,
      key: uniqueKey(toKey(type === 'checkbox' ? 'agreement' : label), null),
      required: false,
      is_active: true,
      settings: settings,
      style_settings: {},
      conditional_rules: null,
      has_submissions: false,
      keyEdited: false,
    };
  }

  /** Inserts relative to another field (or after the selection, or at the end). */
  function insertField(f, ref, position) {
    var anchor = ref ? byUid(ref) : selectedField();
    var index = anchor ? state.fields.indexOf(anchor) + (position === 'before' ? 0 : 1) : state.fields.length;
    state.fields.splice(index, 0, f);
  }

  function addField(type, ref, position) {
    if (!canEdit) return;
    var f = newField(type);
    insertField(f, ref, position);
    state.selected = f.uid;
    state.tab = 'field';
    if (type === 'html') scheduleSanitizeField(f);
    renderTabs();
    changed({ panel: true });
    focusItem(f.uid);
  }

  function moveField(uid, dir) {
    var f = byUid(uid);
    var actives = activeFields();
    var i = actives.indexOf(f);
    var target = actives[i + dir];
    if (!f || !target) return;
    state.fields.splice(state.fields.indexOf(f), 1);
    state.fields.splice(state.fields.indexOf(target) + (dir > 0 ? 1 : 0), 0, f);
    changed({ panel: true });
    focusItem(uid);
  }

  function moveFieldTo(uid, refUid, position) {
    var f = byUid(uid);
    if (!f || uid === refUid) return;
    state.fields.splice(state.fields.indexOf(f), 1);
    var ref = byUid(refUid);
    var index = ref ? state.fields.indexOf(ref) + (position === 'before' ? 0 : 1) : state.fields.length;
    state.fields.splice(index, 0, f);
    changed({ panel: true });
  }

  function duplicateField(uid) {
    var f = byUid(uid);
    if (!f) return;
    var copy = JSON.parse(JSON.stringify(f));
    copy.id = null;
    copy.uid = 'n' + (++uidSeq);
    copy.has_submissions = false;
    copy.label = f.label + ' (copy)';
    copy.key = uniqueKey(f.key.slice(0, 58) + '_copy', null);
    copy.keyEdited = true;
    insertField(copy, f.uid, 'after');
    if (state.rendered.fields[f.uid] !== undefined) state.rendered.fields[copy.uid] = state.rendered.fields[f.uid];
    state.selected = copy.uid;
    changed({ panel: true });
  }

  function dependentsOf(f) {
    return state.fields.filter(function (x) {
      return x !== f && x.conditional_rules && x.conditional_rules.conditions.some(function (c) { return c.field === f.key; });
    });
  }

  function removeField(uid) {
    var f = byUid(uid);
    if (!f) return;
    var dependents = dependentsOf(f);
    var msg = f.has_submissions
      ? 'This field already has submissions, so it will be ARCHIVED: hidden from the form but kept (with its answers) in submissions and Excel exports. Continue?'
      : 'Remove "' + f.label + '" from the form?';
    if (dependents.length) {
      msg += '\n\nConditions on ' + dependents.map(function (d) { return '"' + d.label + '"'; }).join(', ') + ' use this field and will be removed too.';
    }
    if (!window.confirm(msg)) return;

    dependents.forEach(function (d) {
      d.conditional_rules.conditions = d.conditional_rules.conditions.filter(function (c) { return c.field !== f.key; });
      if (!d.conditional_rules.conditions.length) d.conditional_rules = null;
    });
    if (f.has_submissions) f.is_active = false;
    else state.fields.splice(state.fields.indexOf(f), 1);
    if (state.selected === uid) state.selected = null;
    changed({ panel: true });
  }

  function restoreField(uid) {
    var f = byUid(uid);
    if (!f) return;
    f.is_active = true;
    changed({ panel: true });
  }

  function renameKey(f, newKey) {
    var old = f.key;
    f.key = newKey;
    f.keyEdited = true;
    state.fields.forEach(function (x) {
      if (!x.conditional_rules) return;
      x.conditional_rules.conditions.forEach(function (c) { if (c.field === old) c.field = newKey; });
    });
  }

  function select(uid) {
    state.selected = uid;
    state.tab = 'field';
    els.canvas.querySelectorAll('.fb-item').forEach(function (el) {
      el.classList.toggle('is-selected', el.dataset.uid === uid);
    });
    applyLogic();
    renderTabs();
    renderPanel();
  }

  function focusItem(uid) {
    var el = els.canvas.querySelector('.fb-item[data-uid="' + uid + '"]');
    if (el) el.focus({ preventScroll: false });
  }

  /* ============================================================ palette */

  function renderPalette() {
    if (!canEdit) {
      els.palette.replaceChildren(h('p', { class: 'fb-palette__hint', text: state.form.status === 'archived'
        ? 'This form is archived and read-only. A Super Admin can restore it from the Forms list.'
        : 'You can view this form but not edit it.' }));
      return;
    }
    var nodes = [];
    GROUPS.forEach(function (g) {
      nodes.push(h('h3', { text: g[1] }));
      Object.keys(TYPES).forEach(function (type) {
        if (TYPES[type].group !== g[0]) return;
        nodes.push(h('button', {
          type: 'button', class: 'fb-palette__item', draggable: 'true', 'data-type': type,
          title: 'Add ' + TYPES[type].label,
          onclick: function () { addField(type); },
          ondragstart: function (e) { dragPayload = { kind: 'new', type: type }; e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('text/plain', type); },
          ondragend: clearDropMarks,
        }, h('span', { class: 'fb-palette__icon', 'aria-hidden': 'true', text: ICONS[type] || '?' }), TYPES[type].label));
      });
    });
    nodes.push(h('p', { class: 'fb-palette__hint', text: 'Click to add below the selected field, or drag onto the form. Reorder with the ↑↓ buttons, Alt+↑/↓, or drag.' }));
    els.palette.replaceChildren.apply(els.palette, nodes);
  }

  /* ============================================================= canvas */

  function captureTouched() {
    var form = els.canvas.querySelector('form');
    var values = {};
    if (!form) return values;
    form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (i) {
      var key = i.name.replace(/\[\]$/, '');
      if (!state.touched.has(key)) return;
      if (i.type === 'checkbox' || i.type === 'radio') {
        values[i.name] = values[i.name] || { checked: [] };
        if (i.checked) values[i.name].checked.push(i.value);
      } else {
        values[i.name] = { value: i.value };
      }
    });
    return values;
  }

  function restoreTouched(values) {
    var form = els.canvas.querySelector('form');
    if (!form) return;
    form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (i) {
      var v = values[i.name];
      if (!v) return;
      if (v.checked) i.checked = v.checked.indexOf(i.value) !== -1;
      else i.value = v.value;
    });
  }

  function renderCanvas() {
    var touched = captureTouched();
    var wrapperId = 'ff-form-' + state.form.id;
    var style = h('style', { text: '#' + wrapperId + '{' + formStyleDeclarations() + '}\n' + (state.rendered.css || '') });

    var wrapper = h('div', { class: 'ff-form' + (state.device === 'mobile' ? ' ff-form--narrow' : ''), id: wrapperId });
    wrapper.append(h('h1', { class: 'ff-title', text: formTitle() }));
    if (state.form.description) wrapper.append(h('p', { class: 'ff-description', text: state.form.description }));
    if (state.rendered.html_before) wrapper.append(sanitizedHtml('ff-custom-html ff-custom-html--before ff-html', state.rendered.html_before));

    var grid = h('div', { class: 'ff-grid' });
    var actives = activeFields();
    if (!actives.length) {
      grid.append(h('div', { class: 'ff-field fb-empty', text: canEdit
        ? 'This form is empty. Click a field type on the left, or drag one here.'
        : 'This form has no fields.' }));
    }
    actives.forEach(function (f) { grid.append(renderItem(f)); });

    wrapper.append(h('form', { onsubmit: function (e) { e.preventDefault(); }, novalidate: true },
      grid,
      h('div', { class: 'ff-actions' }, h('button', { type: 'button', class: 'ff-button', text: state.form.settings.submit_label || 'Submit' }))));
    if (state.rendered.html_after) wrapper.append(sanitizedHtml('ff-custom-html ff-custom-html--after ff-html', state.rendered.html_after));

    els.canvas.replaceChildren(style, wrapper);
    els.canvas.classList.toggle('is-mobile', state.device === 'mobile');
    restoreTouched(touched);
    applyLogic();
    renderArchived();
  }

  function renderItem(f) {
    var meta = TYPES[f.type];
    var s = f.settings;
    var classes = ['ff-field', 'ff-field--' + f.type, 'fb-item'];
    if (s.width && Number(s.width) !== 100) classes.push('ff-w-' + Number(s.width));
    if (s.css_class) classes.push(String(s.css_class));
    if (f.uid === state.selected) classes.push('is-selected');
    if (state.fieldErrors[f.uid]) classes.push('has-fb-error', 'has-error');

    var el = h('div', {
      class: classes.join(' '), 'data-uid': f.uid, 'data-ff-key': f.key, tabindex: '0',
      draggable: canEdit ? 'true' : null, style: fieldStyleDeclarations(f) || null,
      'aria-label': meta.label + ': ' + f.label,
    });
    append(el, renderFieldBody(f));

    var flags = h('div', { class: 'fb-item__flags' },
      f.conditional_rules ? h('span', { class: 'fb-flag', text: 'Conditional' }) : null,
      h('span', { class: 'fb-flag fb-flag--hidden fb-flag--cond', text: 'Hidden by condition', hidden: true }),
      s.disabled ? h('span', { class: 'fb-flag fb-flag--hidden', text: 'Disabled' }) : null,
      s.read_only ? h('span', { class: 'fb-flag fb-flag--hidden', text: 'Read-only' }) : null,
      state.fieldErrors[f.uid] ? h('span', { class: 'fb-flag fb-flag--error', text: 'Needs fixing' }) : null);
    el.append(flags);

    if (canEdit) {
      var stop = function (fn) { return function (e) { e.stopPropagation(); fn(); }; };
      el.append(h('div', { class: 'fb-item__bar' },
        h('span', { text: meta.label }),
        h('button', { type: 'button', title: 'Move up', 'aria-label': 'Move ' + f.label + ' up', text: '↑', onclick: stop(function () { moveField(f.uid, -1); }) }),
        h('button', { type: 'button', title: 'Move down', 'aria-label': 'Move ' + f.label + ' down', text: '↓', onclick: stop(function () { moveField(f.uid, 1); }) }),
        h('button', { type: 'button', title: 'Duplicate', 'aria-label': 'Duplicate ' + f.label, text: '⧉', onclick: stop(function () { duplicateField(f.uid); }) }),
        h('button', { type: 'button', title: f.has_submissions ? 'Archive' : 'Remove', 'aria-label': (f.has_submissions ? 'Archive ' : 'Remove ') + f.label, text: '✕', onclick: stop(function () { removeField(f.uid); }) })));
    }
    return el;
  }

  function requiredMark(f) {
    return f.required ? h('span', { class: 'ff-req', 'aria-hidden': 'true', text: '*' }) : null;
  }

  function renderFieldBody(f) {
    var s = f.settings;
    var meta = TYPES[f.type];
    var id = 'fbp-' + f.uid;
    var align = ['left', 'center', 'right'].indexOf(s.text_align) !== -1 ? 'text-align:' + s.text_align : null;
    var disabledAttrs = s.disabled ? { disabled: true, 'data-ff-disabled': true } : {};
    var nodes = [];

    function input(attrs) { return h('input', Object.assign(attrs, disabledAttrs)); }

    switch (f.type) {
      case 'heading':
        nodes.push(h(['h2', 'h3', 'h4'].indexOf(s.heading_level) !== -1 ? s.heading_level : 'h3', { class: 'ff-heading', style: align, text: s.content || f.label }));
        break;
      case 'paragraph':
        nodes.push(h('p', { class: 'ff-paragraph', style: align, text: s.content || '' }));
        break;
      case 'divider':
        nodes.push(h('hr', { class: 'ff-divider' }));
        break;
      case 'section':
        nodes.push(h('div', { class: 'ff-section' },
          h('h2', { class: 'ff-section__title', text: f.label }),
          s.content ? h('p', { class: 'ff-section__desc', text: s.content }) : null));
        break;
      case 'html':
        nodes.push(state.rendered.fields[f.uid] !== undefined
          ? sanitizedHtml('ff-html', state.rendered.fields[f.uid])
          : h('div', { class: 'ff-html', text: 'Rendering preview…' }));
        break;
      case 'hidden':
        nodes.push(h('div', { class: 'fb-hidden-chip', text: 'Hidden field · ' + f.key + ' = ' + (s.default_value ? '"' + s.default_value + '"' : '(empty)') + ' — not shown to visitors' }));
        break;
      case 'radio':
      case 'checkbox_group':
        nodes.push(h('fieldset', { class: 'ff-choices' + (s.options_layout === 'inline' ? ' ff-choices--inline' : '') },
          h('legend', { class: 'ff-label' }, f.label, requiredMark(f)),
          h('div', { class: 'ff-choices__list' }, (s.options || []).map(function (o) {
            return h('label', { class: 'ff-choice' },
              input({ type: f.type === 'radio' ? 'radio' : 'checkbox', name: f.type === 'radio' ? f.key : f.key + '[]', value: o.value,
                checked: f.type === 'radio' && s.default_value === o.value }),
              h('span', { text: o.label }));
          }))));
        break;
      case 'checkbox':
        if (s.checkbox_text) nodes.push(h('span', { class: 'ff-label' }, f.label, requiredMark(f)));
        nodes.push(h('label', { class: 'ff-choice' },
          input({ type: 'checkbox', name: f.key, value: '1', checked: !!s.default_value }),
          h('span', null, s.checkbox_text || f.label, s.checkbox_text ? null : requiredMark(f))));
        break;
      default:
        nodes.push(h('label', { class: 'ff-label', for: id }, f.label, requiredMark(f)));
        if (f.type === 'long_text') {
          nodes.push(h('textarea', Object.assign({ class: 'ff-input', id: id, name: f.key, rows: String(s.rows || 4), placeholder: s.placeholder || null, readonly: !!s.read_only, text: s.default_value || '' }, disabledAttrs)));
        } else if (f.type === 'select') {
          nodes.push(h('select', Object.assign({ class: 'ff-input', id: id, name: f.key }, disabledAttrs),
            h('option', { value: '', text: s.placeholder || 'Select…' }),
            (s.options || []).map(function (o) { return h('option', { value: o.value, selected: s.default_value === o.value, text: o.label }); })));
        } else {
          nodes.push(input({ class: 'ff-input', id: id, type: meta.htmlInputType || 'text', name: f.key, value: s.default_value || null,
            placeholder: s.placeholder || null, readonly: !!s.read_only, min: s.min || null, max: s.max || null, step: s.step || (f.type === 'number' ? 'any' : null) }));
        }
    }

    if (meta.acceptsUserInput && s.help_text) nodes.push(h('p', { class: 'ff-help', text: s.help_text }));
    if (state.fieldErrors[f.uid]) nodes.push(h('p', { class: 'ff-error', text: state.fieldErrors[f.uid][0] }));
    return nodes;
  }

  /** Value a condition sees, mirroring FormVisibilityResolver::conditionValue(). */
  function previewValue(form, f) {
    if (!f) return null;
    var s = f.settings;
    if (f.type === 'hidden' || s.read_only || s.disabled) {
      if (f.type === 'checkbox') return s.default_value ? '1' : null;
      return s.default_value === undefined ? null : s.default_value;
    }
    var inputs = Array.prototype.filter.call(form.querySelectorAll('input[name], select[name], textarea[name]'), function (i) {
      return i.name === f.key || i.name === f.key + '[]';
    });
    if (!inputs.length) return null;
    if (f.type === 'checkbox_group') return inputs.filter(function (i) { return i.checked; }).map(function (i) { return i.value; });
    if (f.type === 'radio') { var c = inputs.find(function (i) { return i.checked; }); return c ? c.value : null; }
    if (f.type === 'checkbox') return inputs[0].checked ? '1' : null;
    return inputs[0].value;
  }

  function applyLogic() {
    var form = els.canvas.querySelector('form');
    if (!form || !window.FormLogic) return;
    var actives = activeFields();
    var byKey = {};
    actives.forEach(function (f) { if (!byKey[f.key]) byKey[f.key] = f; });
    var visible = window.FormLogic.resolve(actives.map(function (f) {
      return { key: f.key, type: f.type, collectsValue: TYPES[f.type].collectsValue, rules: f.conditional_rules };
    }), function (lf) { return previewValue(form, byKey[lf.key]); });

    actives.forEach(function (f) {
      var el = form.querySelector('.fb-item[data-uid="' + f.uid + '"]');
      if (!el) return;
      var hiddenByRule = visible[f.key] === false;
      el.classList.toggle('is-cond-hidden', hiddenByRule && state.showHidden);
      el.hidden = hiddenByRule && !state.showHidden && f.uid !== state.selected;
      var flag = el.querySelector('.fb-flag--cond');
      if (flag) flag.hidden = !hiddenByRule;
    });
  }

  function renderArchived() {
    var archived = state.fields.filter(function (f) { return !f.is_active; });
    if (!archived.length) { els.archived.replaceChildren(); return; }
    els.archived.replaceChildren(h('details', null,
      h('summary', { text: 'Archived fields (' + archived.length + ') — hidden from the form, kept in submissions and exports' }),
      h('ul', null, archived.map(function (f) {
        return h('li', null,
          h('span', null, f.label + ' ', h('code', { text: f.key })),
          canEdit ? h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: 'Restore', onclick: function () { restoreField(f.uid); } }) : null);
      }))));
  }

  /* ========================================================== drag & drop */

  function clearDropMarks() {
    els.canvas.querySelectorAll('.is-drop-before, .is-drop-after, .is-dragging').forEach(function (el) {
      el.classList.remove('is-drop-before', 'is-drop-after', 'is-dragging');
    });
  }

  function dropTarget(e) {
    var item = e.target.closest ? e.target.closest('.fb-item') : null;
    if (!item) return null;
    var rect = item.getBoundingClientRect();
    return { uid: item.dataset.uid, el: item, position: e.clientY < rect.top + rect.height / 2 ? 'before' : 'after' };
  }

  els.canvas.addEventListener('dragstart', function (e) {
    var item = e.target.closest && e.target.closest('.fb-item');
    if (!item || !canEdit || /^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)) return;
    dragPayload = { kind: 'move', uid: item.dataset.uid };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', item.dataset.uid);
    item.classList.add('is-dragging');
  });
  els.canvas.addEventListener('dragover', function (e) {
    if (!dragPayload) return;
    e.preventDefault();
    clearDropMarks();
    var t = dropTarget(e);
    if (t) t.el.classList.add(t.position === 'before' ? 'is-drop-before' : 'is-drop-after');
  });
  els.canvas.addEventListener('drop', function (e) {
    if (!dragPayload) return;
    e.preventDefault();
    var t = dropTarget(e);
    var payload = dragPayload;
    dragPayload = null;
    clearDropMarks();
    if (payload.kind === 'new') addField(payload.type, t ? t.uid : (activeFields().slice(-1)[0] || {}).uid, t ? t.position : 'after');
    else if (payload.kind === 'move' && (!t || t.uid !== payload.uid)) moveFieldTo(payload.uid, t ? t.uid : null, t ? t.position : 'after');
  });
  els.canvas.addEventListener('dragend', function () { dragPayload = null; clearDropMarks(); });

  /* ====================================================== canvas events */

  els.canvas.addEventListener('click', function (e) {
    var item = e.target.closest('.fb-item');
    if (item && item.dataset.uid !== state.selected) select(item.dataset.uid);
  });
  els.canvas.addEventListener('keydown', function (e) {
    var item = e.target.classList && e.target.classList.contains('fb-item') ? e.target : null;
    if (!item) return;
    var uid = item.dataset.uid;
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(uid); }
    else if (canEdit && e.altKey && e.key === 'ArrowUp') { e.preventDefault(); moveField(uid, -1); }
    else if (canEdit && e.altKey && e.key === 'ArrowDown') { e.preventDefault(); moveField(uid, 1); }
    else if (canEdit && e.key === 'Delete') { e.preventDefault(); removeField(uid); }
  });
  ['input', 'change'].forEach(function (type) {
    els.canvas.addEventListener(type, function (e) {
      if (e.target.name) state.touched.add(e.target.name.replace(/\[\]$/, ''));
      applyLogic();
    });
  });

  els.showHidden.addEventListener('change', function () { state.showHidden = els.showHidden.checked; applyLogic(); });
  document.querySelectorAll('[data-fb-device]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.device = btn.dataset.fbDevice;
      document.querySelectorAll('[data-fb-device]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
      renderCanvas();
    });
  });

  /* ======================================================= panel controls */

  function control(label, inputEl, opts) {
    opts = opts || {};
    return h('div', { class: 'fb-control' },
      label ? h('label', { for: inputEl.id || null, text: label }) : null,
      inputEl,
      opts.help ? h('p', { class: 'help', text: opts.help }) : null,
      opts.error ? h('p', { class: 'error', text: opts.error }) : null);
  }

  var ctlSeq = 0;
  function ctlId() { return 'fbc-' + (++ctlSeq); }

  function textCtl(label, value, onInput, opts) {
    opts = opts || {};
    var el = h('input', { type: opts.type || 'text', id: ctlId(), value: value === undefined || value === null ? '' : String(value),
      placeholder: opts.placeholder || null, maxlength: opts.maxlength || null, disabled: !!opts.disabled,
      min: opts.min !== undefined ? String(opts.min) : null, max: opts.max !== undefined ? String(opts.max) : null, step: opts.step || null,
      oninput: function () { onInput(el.value); }, onchange: opts.onchange ? function () { opts.onchange(el); } : null });
    return control(label, el, opts);
  }

  function textareaCtl(label, value, onInput, opts) {
    opts = opts || {};
    var el = h('textarea', { id: ctlId(), rows: String(opts.rows || 3), class: opts.code ? 'is-code' : null, spellcheck: opts.code ? 'false' : null,
      maxlength: opts.maxlength || null, placeholder: opts.placeholder || null, text: value || '', oninput: function () { onInput(el.value); } });
    return control(label, el, opts);
  }

  function selectCtl(label, value, options, onChange, opts) {
    opts = opts || {};
    var el = h('select', { id: ctlId(), disabled: !!opts.disabled, onchange: function () { onChange(el.value); } },
      options.map(function (o) { return h('option', { value: o[0], selected: String(value) === String(o[0]), text: o[1] }); }));
    return control(label, el, opts);
  }

  function checkCtl(label, checked, onChange, opts) {
    opts = opts || {};
    var el = h('input', { type: 'checkbox', checked: !!checked, disabled: !!opts.disabled, onchange: function () { onChange(el.checked); } });
    return h('div', { class: 'fb-control fb-control--check' },
      h('label', null, el, label),
      opts.help ? h('p', { class: 'help', text: opts.help }) : null,
      opts.error ? h('p', { class: 'error', text: opts.error }) : null);
  }

  function colorCtl(label, value, onChange, opts) {
    opts = opts || {};
    var valid = function (v) { return /^#[0-9a-fA-F]{6}$/.test(v); };
    var picker = h('input', { type: 'color', value: valid(value || '') ? value : (opts.fallback || '#000000'), 'aria-label': label });
    var text = h('input', { type: 'text', id: ctlId(), value: value || '', maxlength: '7', placeholder: opts.allowEmpty ? 'inherit' : '#rrggbb' });
    picker.addEventListener('input', function () { text.value = picker.value; onChange(picker.value); });
    text.addEventListener('input', function () {
      var v = text.value.trim();
      if (valid(v)) { picker.value = v; onChange(v.toLowerCase()); } else if (v === '' && opts.allowEmpty) { onChange(null); }
    });
    var row = h('div', { class: 'fb-color' }, picker, text,
      opts.allowEmpty ? h('button', { type: 'button', class: 'fb-link-btn', text: 'inherit', title: 'Use the form design value',
        onclick: function () { text.value = ''; onChange(null); } }) : null);
    return h('div', { class: 'fb-control' }, h('label', { for: text.id, text: label }), row,
      opts.error ? h('p', { class: 'error', text: opts.error }) : null);
  }

  /** px input: live-updates while in range, clamps on blur. */
  function pxCtl(label, value, def, onChange, opts) {
    opts = opts || {};
    return textCtl(label + ' (' + def.min + '–' + def.max + 'px)', value, function (v) {
      if (v === '') { if (opts.allowEmpty) onChange(null); return; }
      var n = Number(v);
      if (Number.isInteger(n) && n >= def.min && n <= def.max) onChange(n);
    }, {
      type: 'number', min: def.min, max: def.max, step: '1', placeholder: opts.allowEmpty ? 'inherit' : null, error: opts.error,
      onchange: function (el) {
        if (el.value === '') return;
        var n = Math.round(Number(el.value));
        if (!Number.isFinite(n)) return;
        n = Math.min(def.max, Math.max(def.min, n));
        el.value = String(n);
        onChange(n);
      },
    });
  }

  function err(path) { return state.errors[path] ? state.errors[path][0] : null; }

  /* ========================================================= field panel */

  function renderFieldPanel() {
    var f = selectedField();
    if (!f) {
      return [h('p', { class: 'fb-panel__empty', text: canEdit
        ? 'Select a field on the form to edit it, or add one from the left.'
        : 'Select a field to inspect its settings.' })];
    }
    var meta = TYPES[f.type];
    var index = state.fields.indexOf(f);
    var p = 'fields.' + index;
    var nodes = [];

    if (state.fieldErrors[f.uid]) {
      nodes.push(h('div', { class: 'fb-callout fb-callout--danger' }, h('strong', { text: 'Fix before saving:' }),
        h('ul', { style: 'margin:4px 0 0;padding-left:16px' }, state.fieldErrors[f.uid].map(function (m) { return h('li', { text: m }); }))));
    }

    // --- basics
    var isContent = !meta.collectsValue;
    var keyCtl;
    var basics = h('div', { class: 'fb-group' }, h('h4', { text: meta.label }));
    basics.append(textCtl(f.type === 'section' ? 'Section title' : (isContent ? 'Admin label' : 'Label'), f.label, function (v) {
      f.label = v;
      if (!f.keyEdited && f.id === null) {
        renameKey(f, uniqueKey(toKey(v), f));
        f.keyEdited = false;
        if (keyCtl) keyCtl.querySelector('input').value = f.key;
      }
      changed();
    }, { maxlength: 255, help: isContent && f.type !== 'section' ? 'Only shown in the builder.' : null }));

    keyCtl = textCtl('Key', f.key, function (v) {
      renameKey(f, v.trim());
      changed();
    }, {
      maxlength: 64, disabled: f.has_submissions,
      help: f.has_submissions
        ? 'Locked: submissions are stored under this key.'
        : 'Stable machine name used in submissions and exports (a-z, 0-9, _). Generated from the label until you edit it.',
    });
    basics.append(keyCtl);

    if (meta.supportsRequired) {
      basics.append(checkCtl('Required', f.required, function (v) { f.required = v; changed(); },
        { help: f.conditional_rules ? 'Only enforced while the field is visible.' : null }));
    }
    nodes.push(basics);

    // --- type-specific settings
    var settings = h('div', { class: 'fb-group' }, h('h4', { text: 'Settings' }));
    meta.settings.forEach(function (key) {
      var ctl = settingControl(f, key, p);
      if (ctl) settings.append(ctl);
    });
    if (settings.childElementCount > 1) nodes.push(settings);

    // --- style overrides
    var applies = f.type === 'hidden' ? [] : (meta.collectsValue ? ['input', 'all'] : ['content', 'all']);
    var styleKeys = Object.keys(META.fieldStyle).filter(function (k) { return applies.indexOf(META.fieldStyle[k].applies_to) !== -1; });
    if (styleKeys.length) {
      var styleGroup = h('details', { class: 'fb-group', open: Object.keys(f.style_settings).length ? true : null },
        h('summary', { text: 'Style overrides' }),
        h('p', { class: 'help', style: 'font-size:.74rem;color:var(--muted);margin:0 0 8px', text: 'Leave empty to inherit the form’s Design settings.' }));
      styleKeys.forEach(function (key) {
        var def = META.fieldStyle[key];
        styleGroup.append(def.type === 'color'
          ? colorCtl(def.label, f.style_settings[key], function (v) { setFieldStyle(f, key, v); }, { allowEmpty: true })
          : pxCtl(def.label, f.style_settings[key], def, function (v) { setFieldStyle(f, key, v); }, { allowEmpty: true }));
      });
      nodes.push(styleGroup);
    }

    nodes.push(conditionsGroup(f));

    if (canEdit) {
      nodes.push(h('div', { class: 'fb-panel__actions' },
        h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: 'Duplicate', onclick: function () { duplicateField(f.uid); } }),
        h('button', { type: 'button', class: 'btn btn--danger btn--sm', text: f.has_submissions ? 'Archive field' : 'Remove field', onclick: function () { removeField(f.uid); } })));
    }
    return nodes;
  }

  function boundInputType(f) {
    return { number: 'number', date: 'date', time: 'time', datetime: 'datetime-local' }[f.type] || 'text';
  }

  function settingControl(f, key, p) {
    var s = f.settings;
    var e = function (k) { return err(p + '.settings.' + k); };
    var set = function (k) { return function (v) { setSetting(f, k, v); }; };

    switch (key) {
      case 'placeholder':
        return textCtl(f.type === 'select' ? 'Empty-choice text' : 'Placeholder', s.placeholder, set('placeholder'), { maxlength: 255, error: e('placeholder') });
      case 'help_text':
        return textareaCtl('Help text', s.help_text, set('help_text'), { rows: 2, maxlength: 1000, error: e('help_text') });
      case 'default_value':
        return defaultValueCtl(f, e('default_value'));
      case 'width':
        return selectCtl('Width', String(s.width || 100), META.widths.map(function (w) { return [String(w), w + '%']; }), function (v) {
          setSetting(f, 'width', Number(v) === 100 ? null : Number(v));
        }, { help: 'Fields narrower than 100% sit side by side. All fields stack on phones.' });
      case 'css_class':
        return textCtl('CSS class', s.css_class, set('css_class'), { maxlength: 100, error: e('css_class'), help: 'Optional class names for custom CSS (letters, numbers, - and _).' });
      case 'min':
        var t = boundInputType(f);
        var minMaxLabel = f.type === 'number' ? ['Minimum', 'Maximum'] : ['Earliest', 'Latest'];
        return h('div', { class: 'fb-control--inline' },
          textCtl(minMaxLabel[0], s.min, set('min'), { type: t, error: e('min') }),
          textCtl(minMaxLabel[1], s.max, set('max'), { type: t, error: e('max') }));
      case 'max':
        return null; // rendered together with 'min'
      case 'step':
        return textCtl('Step', s.step, set('step'), { type: 'number', min: 0, step: 'any', error: e('step'), help: 'e.g. 1 for whole numbers, 0.01 for money.' });
      case 'max_length':
        return textCtl('Maximum length (characters)', s.max_length, function (v) { setSetting(f, 'max_length', v === '' ? null : Number(v)); },
          { type: 'number', min: 1, max: 65535, error: e('max_length') });
      case 'pattern':
        return textCtl('Pattern (regular expression)', s.pattern, set('pattern'), { maxlength: 255, error: e('pattern'),
          help: 'The whole answer must match, e.g. [0-9]{8,10} for an 8–10 digit ID. Checked on the server.' });
      case 'pattern_message':
        return s.pattern ? textCtl('Pattern error message', s.pattern_message, set('pattern_message'), { maxlength: 255, error: e('pattern_message') }) : null;
      case 'rows':
        return textCtl('Visible rows', s.rows, function (v) { setSetting(f, 'rows', v === '' ? null : Number(v)); }, { type: 'number', min: 2, max: 20, error: e('rows') });
      case 'options':
        return optionsEditor(f, e('options'));
      case 'options_layout':
        return selectCtl('Option layout', s.options_layout || 'stacked', [['stacked', 'Stacked'], ['inline', 'Side by side']], set('options_layout'));
      case 'checkbox_text':
        return textareaCtl('Checkbox text', s.checkbox_text, set('checkbox_text'), { rows: 2, maxlength: 500, error: e('checkbox_text'),
          help: 'Shown beside the box (the label then appears above). Leave empty to use the label beside the box.' });
      case 'content':
        return contentControl(f, e('content'));
      case 'heading_level':
        return selectCtl('Heading size', s.heading_level || 'h3', [['h2', 'Large (H2)'], ['h3', 'Medium (H3)'], ['h4', 'Small (H4)']], set('heading_level'));
      case 'text_align':
        return selectCtl('Alignment', s.text_align || 'left', ALIGNS, function (v) { setSetting(f, 'text_align', v === 'left' ? null : v); });
      case 'show_in_list':
        return checkCtl('Show in submissions list', s.show_in_list, set('show_in_list'), { help: 'Adds this field as a column on the Submissions page (up to 3).' });
      case 'read_only':
        return checkCtl('Read-only', s.read_only, set('read_only'), { help: 'Visitors see the default value but can’t change it; the server always stores the default.' });
      case 'disabled':
        return checkCtl('Disabled', s.disabled, set('disabled'), { help: 'Shown greyed out and never submitted or stored.' });
      default:
        return null;
    }
  }

  function defaultValueCtl(f, error) {
    var s = f.settings;
    var set = function (v) { setSetting(f, 'default_value', v); };
    if (f.type === 'checkbox') return checkCtl('Ticked by default', !!s.default_value, function (v) { setSetting(f, 'default_value', v ? true : null); });
    if (f.type === 'select' || f.type === 'radio') {
      return selectCtl('Default choice', s.default_value || '', [['', '(none)']].concat((s.options || []).map(function (o) { return [o.value, o.label]; })), set, { error: error });
    }
    if (f.type === 'long_text') return textareaCtl('Default value', s.default_value, set, { rows: 2, maxlength: 1000, error: error });
    var help = f.type === 'hidden' ? 'Stored with every submission. Visitors can’t change it (the server ignores any posted value).' : null;
    return textCtl(f.type === 'hidden' ? 'Value' : 'Default value', s.default_value, set, { type: boundInputType(f), maxlength: 1000, error: error, help: help });
  }

  function contentControl(f, error) {
    var s = f.settings;
    if (f.type === 'heading') return textCtl('Heading text', s.content, function (v) { setSetting(f, 'content', v); }, { maxlength: 255, error: error });
    if (f.type === 'html') {
      return textareaCtl('HTML', s.content, function (v) {
        if (v === '') delete s.content; else s.content = v;
        changed();
        scheduleSanitizeField(f);
      }, { rows: 8, code: true, maxlength: 20000, error: error,
        help: 'Sanitized on save and on display: scripts, iframes, forms, inline styles, event handlers (onclick…) and javascript: links are removed. The preview shows the sanitized result.' });
    }
    return textareaCtl(f.type === 'section' ? 'Description (optional)' : 'Text', s.content, function (v) { setSetting(f, 'content', v); }, { rows: 4, maxlength: 5000, error: error });
  }

  function optionValue(label, f, except) {
    var base = toKey(label).replace(/^field_/, '') || 'option';
    var taken = new Set((f.settings.options || []).filter(function (o) { return o !== except; }).map(function (o) { return o.value; }));
    var v = base;
    var n = 2;
    while (taken.has(v)) v = base + '_' + n++;
    return v;
  }

  function optionsEditor(f, error) {
    var opts = f.settings.options = f.settings.options || [];
    var wrap = h('div', { class: 'fb-control fb-options' },
      h('span', { class: 'fb-control__label', text: 'Options' }),
      h('div', { class: 'fb-options__head' }, h('span', { text: 'Label (shown)' }), h('span', { text: 'Value (stored)' }), h('span')));

    opts.forEach(function (o, i) {
      var valueInput = h('input', { type: 'text', value: o.value, maxlength: '255', 'aria-label': 'Option ' + (i + 1) + ' value',
        oninput: function () { o.value = valueInput.value; autoValueOptions.delete(o); changed(); } });
      var labelInput = h('input', { type: 'text', value: o.label, maxlength: '255', 'aria-label': 'Option ' + (i + 1) + ' label',
        oninput: function () {
          o.label = labelInput.value;
          if (autoValueOptions.has(o)) { o.value = optionValue(o.label, f, o); valueInput.value = o.value; }
          changed();
        } });
      var move = function (d) { return function () { var j = i + d; if (j < 0 || j >= opts.length) return; opts.splice(j, 0, opts.splice(i, 1)[0]); changed({ panel: true }); }; };
      wrap.append(h('div', { class: 'fb-options__row' }, labelInput, valueInput,
        h('span', { class: 'fb-mini' },
          h('button', { type: 'button', title: 'Move up', text: '↑', onclick: move(-1) }),
          h('button', { type: 'button', title: 'Move down', text: '↓', onclick: move(1) }),
          h('button', { type: 'button', title: 'Remove option', text: '✕', onclick: function () {
            if (opts.length <= 1) { window.alert('A choice field needs at least one option.'); return; }
            opts.splice(i, 1);
            if (f.settings.default_value === o.value) delete f.settings.default_value;
            changed({ panel: true });
          } }))));
    });

    wrap.append(h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: '+ Add option', onclick: function () {
      var o = { label: 'Option ' + (opts.length + 1), value: '' };
      o.value = optionValue(o.label, f, o);
      autoValueOptions.add(o);
      opts.push(o);
      changed({ panel: true });
    } }));
    if (f.has_submissions) wrap.append(h('p', { class: 'help', text: 'Past submissions keep the option labels they were submitted with, even if you edit or remove options.' }));
    if (error) wrap.append(h('p', { class: 'error', text: error }));
    return wrap;
  }

  function conditionsGroup(f) {
    var actives = activeFields();
    var idx = actives.indexOf(f);
    var candidates = actives.slice(0, idx).filter(function (x) { return TYPES[x.type].canDriveConditions; });
    var group = h('div', { class: 'fb-group' }, h('h4', { text: 'Conditional visibility' }));

    if (!f.conditional_rules) {
      if (!candidates.length) {
        group.append(h('p', { class: 'fb-panel__empty', style: 'font-size:.8rem', text: 'To show or hide this field based on an answer, place it below the field it depends on.' }));
      } else if (canEdit) {
        group.append(h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: '+ Add a condition', onclick: function () {
          f.conditional_rules = { action: 'show', match: 'all', conditions: [{ field: candidates[candidates.length - 1].key, operator: 'equals', value: '' }] };
          changed({ panel: true });
        } }));
      }
      return group;
    }

    var r = f.conditional_rules;
    group.append(h('div', { class: 'fb-rule__head' },
      h('select', { 'aria-label': 'Show or hide', onchange: function (e) { r.action = e.target.value; changed(); } },
        h('option', { value: 'show', selected: r.action === 'show', text: 'Show' }),
        h('option', { value: 'hide', selected: r.action === 'hide', text: 'Hide' })),
      ' this field when ',
      h('select', { 'aria-label': 'Match all or any', onchange: function (e) { r.match = e.target.value; changed(); } },
        h('option', { value: 'all', selected: r.match !== 'any', text: 'all' }),
        h('option', { value: 'any', selected: r.match === 'any', text: 'any' })),
      ' of these match:'));

    r.conditions.forEach(function (c, i) {
      var ref = candidates.find(function (x) { return x.key === c.field; });
      var fieldOptions = candidates.map(function (x) { return h('option', { value: x.key, selected: x.key === c.field, text: x.label + ' (' + x.key + ')' }); });
      if (!ref) fieldOptions.unshift(h('option', { value: c.field, selected: true, text: '⚠ ' + c.field + ' (not available above)' }));

      var valueCtl = null;
      if (c.operator !== 'is_empty' && c.operator !== 'is_not_empty') {
        if (ref && TYPES[ref.type].hasOptions) {
          valueCtl = h('select', { 'aria-label': 'Value', onchange: function (e) { c.value = e.target.value; changed(); } },
            h('option', { value: '', text: '(choose an option)' }),
            (ref.settings.options || []).map(function (o) { return h('option', { value: o.value, selected: o.value === c.value, text: o.label }); }));
        } else if (ref && ref.type === 'checkbox') {
          c.value = '1';
          valueCtl = h('span', { style: 'font-size:.78rem;color:var(--muted)', text: '= ticked' });
        } else {
          valueCtl = h('input', { type: 'text', value: c.value || '', placeholder: 'Value', 'aria-label': 'Value', maxlength: '255',
            oninput: function (e) { c.value = e.target.value; changed(); } });
        }
      }

      group.append(h('div', { class: 'fb-rule' },
        h('select', { 'aria-label': 'Field', onchange: function (e) { c.field = e.target.value; c.value = ''; changed({ panel: true }); } }, fieldOptions),
        h('select', { 'aria-label': 'Operator', onchange: function (e) { c.operator = e.target.value; changed({ panel: true }); } },
          Object.keys(META.operators).map(function (op) { return h('option', { value: op, selected: op === c.operator, text: META.operators[op] }); })),
        valueCtl,
        canEdit ? h('button', { type: 'button', class: 'fb-link-btn', style: 'justify-self:start', text: 'Remove condition', onclick: function () {
          r.conditions.splice(i, 1);
          if (!r.conditions.length) f.conditional_rules = null;
          changed({ panel: true });
        } }) : null));
    });

    if (canEdit) {
      group.append(h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: '+ Add condition', disabled: r.conditions.length >= 10, onclick: function () {
        r.conditions.push({ field: candidates.length ? candidates[candidates.length - 1].key : '', operator: 'equals', value: '' });
        changed({ panel: true });
      } }));
    }
    group.append(h('p', { class: 'help', style: 'font-size:.74rem;color:var(--muted);margin:8px 0 0',
      text: 'Conditions can only use fields placed above this one. A hidden field is never required and its answer is not stored. The server re-checks all of this on submit.' }));
    return group;
  }

  /* ======================================================= design panel */

  function renderDesignPanel() {
    var nodes = [];
    Object.keys(META.formStyle).forEach(function (group) {
      var g = META.formStyle[group];
      var box = h('div', { class: 'fb-group' }, h('h4', { text: g.label }));
      if (group === 'button') {
        box.append(textCtl('Button text', state.form.settings.submit_label, function (v) { state.form.settings.submit_label = v; changed(); },
          { maxlength: 80, error: err('settings.submit_label') }));
      }
      Object.keys(g.properties).forEach(function (key) {
        var def = g.properties[key];
        var current = (state.form.style_settings[group] || {})[key];
        var path = 'style_settings.' + group + '.' + key;
        var set = function (v) { setFormStyle(group, key, v); };
        if (def.type === 'color') box.append(colorCtl(def.label, current, set, { error: err(path) }));
        else if (def.type === 'px') box.append(pxCtl(def.label, current, def, set, { error: err(path) }));
        else box.append(selectCtl(def.label, current, Object.keys(def.options).map(function (k) { return [k, def.options[k].label]; }), set, { error: err(path) }));
      });
      nodes.push(box);
    });
    if (canEdit) {
      nodes.push(h('div', { class: 'fb-panel__actions' }, h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: 'Reset design to defaults', onclick: function () {
        if (!window.confirm('Reset every design setting to its default?')) return;
        var defaults = {};
        Object.keys(META.formStyle).forEach(function (group) {
          defaults[group] = {};
          Object.keys(META.formStyle[group].properties).forEach(function (key) { defaults[group][key] = META.formStyle[group].properties[key].default; });
        });
        state.form.style_settings = defaults;
        changed({ panel: true });
      } })));
    }
    return nodes;
  }

  /* ===================================================== settings panel */

  function renderSettingsPanel() {
    var st = state.form.settings;
    var setS = function (k) { return function (v) { st[k] = v === '' ? null : v; changed(); }; };
    var nodes = [];

    nodes.push(h('div', { class: 'fb-group' }, h('h4', { text: 'Form' }),
      textCtl('Internal name', state.form.name, function (v) { state.form.name = v; changed(); }, { maxlength: 150, error: err('name'), help: 'Shown in the admin.' }),
      textCtl('Public title', st.title, setS('title'), { maxlength: 200, placeholder: state.form.name, error: err('settings.title'), help: 'Shown at the top of the form. Defaults to the internal name.' }),
      textareaCtl('Description', state.form.description, function (v) { state.form.description = v; changed(); }, { rows: 3, maxlength: 2000, error: err('description') }),
      textCtl('URL slug', state.form.slug, function (v) { state.form.slug = v.trim(); changed(); }, {
        maxlength: 100, error: err('slug'),
        help: URLS.publicBase + (state.form.slug || '') + (state.form.status === 'active' ? '  — this form is live: changing the slug breaks links already shared.' : ''),
      })));

    nodes.push(h('div', { class: 'fb-group' }, h('h4', { text: 'After submitting' }),
      textareaCtl('Success message', st.success_message, setS('success_message'), { rows: 3, maxlength: 2000, error: err('settings.success_message') }),
      textCtl('Redirect URL (optional)', st.redirect_url, setS('redirect_url'), { type: 'url', maxlength: 2000, placeholder: 'https://…', error: err('settings.redirect_url'),
        help: 'If set, visitors are sent here instead of seeing the success message. http(s) only.' })));

    nodes.push(h('div', { class: 'fb-group' }, h('h4', { text: 'Access & limits' }),
      selectCtl('Visibility', st.visibility || 'public', [['public', 'Public — anyone with the link'], ['private', 'Private — signed-in admin users only']], setS('visibility'),
        { error: err('settings.visibility') }),
      checkCtl('Allow multiple submissions per person', st.allow_multiple_submissions !== false, function (v) { st.allow_multiple_submissions = v; changed(); },
        { help: 'When off, a browser session (or signed-in account) can submit once. Not a hard identity check.' }),
      textCtl('Submission limit (optional)', st.submission_limit, function (v) { st.submission_limit = v === '' ? null : Number(v); changed(); },
        { type: 'number', min: 1, max: 1000000, error: err('settings.submission_limit'), help: 'The form closes automatically once this many submissions are stored.' }),
      h('div', { class: 'fb-control--inline' },
        textCtl('Opens at', st.opens_at, setS('opens_at'), { type: 'datetime-local', error: err('settings.opens_at') }),
        textCtl('Closes at', st.closes_at, setS('closes_at'), { type: 'datetime-local', error: err('settings.closes_at') }))));

    return nodes;
  }

  /* ======================================================= code panel */

  function renderCodePanel() {
    if (!PERMS.canManageCustomCode) {
      return [h('div', { class: 'fb-callout' }, 'Only a Super Admin can view or edit custom code.' +
        (state.form.has_custom_code ? ' This form has custom code; its sanitized/scoped result is applied in the preview.' : ''))];
    }
    var f = state.form;
    var scope = '#ff-form-' + f.id;
    return [
      h('div', { class: 'fb-callout fb-callout--warn', text: 'Super Admin only. Custom CSS is scoped to this form and custom HTML is sanitized, but both still change what visitors see — preview before publishing.' }),
      h('div', { class: 'fb-group' }, h('h4', { text: 'Custom CSS' }),
        textareaCtl(null, f.custom_css, function (v) {
          f.custom_css = v;
          changed();
          scheduleSanitize('css', 'css', v, function (r) { state.rendered.css = r; });
        }, { rows: 9, code: true, maxlength: 20000, placeholder: '.ff-label { text-transform: uppercase; }', error: err('custom_css'),
          help: 'Every selector is automatically prefixed with ' + scope + ', so it can only style this form (html/body/:root become the form wrapper). @import, @font-face and other at-rules except @media/@supports/@container/@keyframes are removed.' })),
      h('div', { class: 'fb-group' }, h('h4', { text: 'Custom HTML' }),
        textareaCtl('Before the form', f.custom_html_before, function (v) {
          f.custom_html_before = v;
          changed();
          scheduleSanitize('html_before', 'html', v, function (r) { state.rendered.html_before = r; });
        }, { rows: 5, code: true, maxlength: 20000, error: err('custom_html_before') }),
        textareaCtl('After the form', f.custom_html_after, function (v) {
          f.custom_html_after = v;
          changed();
          scheduleSanitize('html_after', 'html', v, function (r) { state.rendered.html_after = r; });
        }, { rows: 5, code: true, maxlength: 20000, error: err('custom_html_after'), help: 'Sanitized like HTML blocks: formatting tags only.' })),
      h('div', { class: 'fb-group' }, h('h4', { text: 'Custom JavaScript' }),
        h('div', { class: 'fb-callout fb-callout--danger', text: 'Stored only — never executed. Running custom JavaScript is disabled until a later, security-reviewed phase; nothing entered here runs on the public form or in this builder.' }),
        textareaCtl(null, f.custom_js, function (v) { f.custom_js = v; changed(); }, { rows: 6, code: true, maxlength: 20000, error: err('custom_js') })),
    ];
  }

  /* ======================================================= panel shell */

  function renderTabs() {
    els.tabs.replaceChildren.apply(els.tabs, TABS.map(function (t) {
      var hasError = Object.keys(state.errors).some(function (p) { return tabForPath(p) === t[0]; });
      return h('button', { type: 'button', role: 'tab', 'aria-selected': state.tab === t[0] ? 'true' : 'false', class: state.tab === t[0] ? 'is-active' : null,
        text: t[1] + (hasError ? ' ●' : ''), onclick: function () { state.tab = t[0]; renderTabs(); renderPanel(); } });
    }));
  }

  function renderPanel() {
    var scroller = els.panel.parentElement;
    var top = scroller.scrollTop;
    var nodes = { field: renderFieldPanel, design: renderDesignPanel, settings: renderSettingsPanel, code: renderCodePanel }[state.tab]();
    // A disabled <fieldset> natively locks every control for read-only users.
    var fieldset = h('fieldset', { disabled: !canEdit, style: 'border:0;margin:0;padding:0;min-width:0' });
    append(fieldset, nodes);
    els.panel.replaceChildren(fieldset);
    scroller.scrollTop = top;
  }

  function tabForPath(path) {
    if (/^fields\./.test(path) || path === 'fields') return 'field';
    if (/^style_settings\./.test(path)) return 'design';
    if (/^custom_/.test(path)) return 'code';
    return 'settings';
  }

  /* ====================================================== server calls */

  function api(method, url, body) {
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body),
    }).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (data) { return { ok: res.ok, status: res.status, data: data }; });
    });
  }

  function scheduleSanitize(slot, kind, content, apply) {
    clearTimeout(sanitizeTimers[slot]);
    sanitizeTimers[slot] = setTimeout(function () {
      api('POST', URLS.sanitize, { kind: kind, content: content || '' }).then(function (res) {
        if (res.ok && res.data) { apply(res.data.result || ''); renderCanvas(); }
      }).catch(function () {});
    }, 350);
  }

  function scheduleSanitizeField(f) {
    scheduleSanitize('field-' + f.uid, 'html', f.settings.content || '', function (r) { state.rendered.fields[f.uid] = r; });
  }

  function buildPayload(intent, autosave) {
    var p = {
      version: state.version,
      intent: intent,
      autosave: !!autosave,
      name: state.form.name,
      slug: state.form.slug,
      description: state.form.description || null,
      settings: state.form.settings,
      style_settings: state.form.style_settings,
      fields: state.fields.map(function (f) {
        var settings = Object.assign({}, f.settings);
        if (settings.options) settings.options = settings.options.map(function (o) { return { label: o.label, value: o.value }; });
        return {
          id: f.id, type: f.type, label: f.label, key: f.key, required: f.required, is_active: f.is_active,
          settings: settings, style_settings: f.style_settings, conditional_rules: f.conditional_rules,
        };
      }),
    };
    if (PERMS.canManageCustomCode) {
      p.custom_css = state.form.custom_css || null;
      p.custom_html_before = state.form.custom_html_before || null;
      p.custom_html_after = state.form.custom_html_after || null;
      p.custom_js = state.form.custom_js || null;
    }
    return p;
  }

  function setSaveState(text, kind) {
    els.saveState.textContent = text;
    els.saveState.className = 'fb-save-state' + (kind === 'dirty' ? ' is-dirty' : kind === 'error' ? ' is-error' : '');
  }

  function scheduleAutosave() {
    clearTimeout(state.autosaveTimer);
    // Drafts only: an autosave must never silently change a live form.
    if (!canEdit || state.form.status !== 'draft') return;
    state.autosaveTimer = setTimeout(function () { if (isDirty()) save('save', true); }, 5000);
  }

  function save(intent, autosave) {
    if (!canEdit) return;
    if (state.saving) { if (!autosave) state.pendingIntent = intent; return; }
    clearTimeout(state.autosaveTimer);
    state.saving = true;
    var seq = state.changeSeq;
    els.save.disabled = els.publish.disabled = true;
    setSaveState(autosave ? 'Autosaving…' : 'Saving…');

    api('PUT', URLS.save, buildPayload(intent, autosave)).then(function (res) {
      if (res.ok) {
        applySavedState(res.data.state, seq);
        clearErrors();
        state.savedSeq = seq;
        var label = intent === 'publish' ? 'Published' : (autosave ? 'Autosaved' : 'Saved');
        setSaveState(isDirty() ? label + ' at ' + now() + ' — newer changes not saved yet' : label + ' at ' + now(), isDirty() ? 'dirty' : null);
        if (isDirty()) scheduleAutosave();
      } else if (res.status === 422) {
        showErrors((res.data && res.data.errors) || {});
        setSaveState(autosave ? 'Autosave paused — fix the problems listed above.' : 'Not saved — fix the problems listed above.', 'error');
      } else if (res.status === 409) {
        showBanner([(res.data && res.data.message) || 'This form changed somewhere else.'], true);
        setSaveState('Not saved — newer version exists.', 'error');
      } else if (res.status === 419) {
        showBanner(['Your session expired. Copy any text you need, then reload the page and sign in again.'], true);
        setSaveState('Not saved — session expired.', 'error');
      } else if (res.status === 403) {
        showBanner(['You don’t have permission to change this form (or it was archived).'], true);
        setSaveState('Not saved.', 'error');
      } else {
        setSaveState('Save failed (HTTP ' + res.status + '). Your changes are still here — try again.', 'error');
      }
    }).catch(function () {
      setSaveState('Could not reach the server. Your changes are still here — try again.', 'error');
    }).then(function () {
      state.saving = false;
      els.save.disabled = els.publish.disabled = false;
      if (state.pendingIntent) { var next = state.pendingIntent; state.pendingIntent = null; save(next, false); }
    });
  }

  /**
   * Applies the server's copy after a save. If nothing changed locally
   * while the request was in flight, the server copy simply replaces local
   * state. Otherwise only ids/version/status are merged in, so newer local
   * edits are kept (and stay "unsaved").
   */
  function applySavedState(s, seq) {
    var selectedKey = (byUid(state.selected) || {}).key;

    if (state.changeSeq === seq) {
      loadState(s);
      var sel = state.fields.find(function (f) { return f.key === selectedKey; });
      state.selected = sel ? sel.uid : null;
    } else {
      state.version = s.form.version;
      ['status', 'status_label', 'version', 'has_custom_code'].forEach(function (k) { state.form[k] = s.form[k]; });
      s.fields.forEach(function (sf) {
        var local = state.fields.find(function (f) { return f.id === sf.id; })
          || state.fields.find(function (f) { return f.id === null && f.key === sf.key; });
        if (local) {
          local.id = sf.id;
          local.has_submissions = sf.has_submissions;
        } else if (!sf.is_active) {
          state.fields.push(fromServerField(sf));
        }
      });
    }
    renderToolbar();
    renderTabs();
    renderPanel();
    renderCanvas();
  }

  function clearErrors() {
    state.errors = {};
    state.fieldErrors = {};
    els.errors.hidden = true;
    els.errors.replaceChildren();
  }

  function showErrors(errors) {
    state.errors = errors;
    state.fieldErrors = {};
    var items = [];
    Object.keys(errors).forEach(function (path) {
      var m = path.match(/^fields\.(\d+)(\.|$)/);
      var f = m ? state.fields[Number(m[1])] : null;
      if (f) {
        state.fieldErrors[f.uid] = (state.fieldErrors[f.uid] || []).concat(errors[path]);
        items.push({ text: (f.label || f.key) + ': ' + errors[path][0], uid: f.uid });
      } else {
        items.push({ text: errors[path][0], tab: tabForPath(path) });
      }
    });
    showBanner(items, false);
    renderTabs();
    renderPanel();
    renderCanvas();
  }

  function showBanner(items, blocking) {
    var list = h('ul', null, items.map(function (item) {
      if (typeof item === 'string') return h('li', { text: item });
      return h('li', null, h('button', { type: 'button', text: item.text, onclick: function () {
        if (item.uid) { var f = byUid(item.uid); if (f && f.is_active) select(item.uid); else { state.tab = 'field'; renderTabs(); renderPanel(); } }
        else { state.tab = item.tab; renderTabs(); renderPanel(); }
      } }));
    }));
    els.errors.replaceChildren(h('strong', { text: blocking ? 'Not saved.' : 'This form could not be saved:' }), list,
      blocking ? h('button', { type: 'button', text: 'Reload the builder', onclick: function () { window.location.reload(); } }) : null);
    els.errors.hidden = false;
  }

  /* ============================================================ toolbar */

  function renderToolbar() {
    var st = state.form.status;
    var badge = { active: 'badge--active', draft: 'badge--draft', inactive: 'badge--inactive', archived: 'badge--archived' }[st] || '';
    els.statusBadge.className = 'badge ' + badge;
    els.statusBadge.textContent = state.form.status_label;
    els.save.hidden = !canEdit;
    els.publish.hidden = !canEdit || st === 'active';
    if (st === 'active') {
      els.save.textContent = 'Save changes';
      els.save.className = 'btn btn--primary btn--sm';
      els.save.title = 'Saves straight to the live form';
    } else {
      els.save.textContent = st === 'draft' ? 'Save draft' : 'Save';
      els.save.className = 'btn btn--ghost btn--sm';
      els.save.title = '';
    }
  }

  els.save.addEventListener('click', function () { save('save', false); });
  els.publish.addEventListener('click', function () {
    if (!window.confirm('Publish this form? It will accept submissions at ' + URLS.publicBase + state.form.slug)) return;
    save('publish', false);
  });

  document.querySelectorAll('[data-fb-guard]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (isDirty() && !window.confirm('You have unsaved changes in the builder that will be lost. Continue?')) e.preventDefault();
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
  });

  /* =============================================================== boot */

  loadState(boot);
  renderToolbar();
  renderPalette();
  renderTabs();
  renderPanel();
  renderCanvas();
  setSaveState(state.form.status === 'draft' && canEdit ? 'Drafts autosave as you work.' : '');
})();
