/* =========================================================================
   Certificate template designer — Phase 4.
   Plain vanilla JS, no build step, no framework — matches the rest of this
   project's admin UI (see docs/ARCHITECTURE.md §5). PDF.js renders the
   uploaded certificate PDF to a <canvas>; this file overlays draggable,
   resizable boxes for each dynamic field plus the two system elements
   (certificate_number, qr_code), and converts between on-screen pixels and
   real PDF points using PDF.js's own viewport transform — see
   docs/CERTIFICATE_SYSTEM.md §Coordinate system for why that matters (a
   real Canva-exported certificate's PDF page does not always start at
   MediaBox [0,0], so a hand-rolled flip formula is not safe to use here).
   ========================================================================= */
(function () {
  const config = window.DESIGNER_CONFIG;
  if (!config) return;

  const canvasWrap = document.getElementById('designer-canvas');
  const pdfCanvas = document.getElementById('pdf-canvas');
  const elementList = document.getElementById('element-list');
  const settingsEmpty = document.getElementById('settings-empty');
  const settingsForm = document.getElementById('settings-form');
  const layoutInput = document.getElementById('layout_json');
  const designerForm = document.getElementById('designer-form');
  const saveHint = document.getElementById('save-hint');

  let viewport = null; // the PDF.js viewport used for the current render (canvas-pixel <-> pdf-point conversion)
  let elements = {};   // keyed by element key ("field:<id>" or "system:certificate_number"/"system:qr_code")
  let selectedKey = null;
  let qrSampleDataUrl = null;

  /* ------------------------------------------------- 1. Sample preview values */
  // Preview-only heuristic keyed off field_type/field_key — never stored,
  // never affects saved data. See docs/CERTIFICATE_SYSTEM.md §Sample preview data.
  function sampleValueFor(field) {
    const key = (field.field_key || '').toLowerCase();
    if (field.field_type === 'dropdown') {
      return (field.options && field.options[0]) || 'Sample option';
    }
    if (key.includes('institution') || key.includes('organi')) return 'Example University';
    if (key.includes('title')) return 'A Sample Research Paper Title';
    if (key.includes('track')) return 'Sample Track';
    if (key.includes('role')) return 'Keynote Speaker';
    if (key.includes('session')) return 'Technical Session 1';
    if (key.includes('id')) return '1570000012';
    if (key.includes('name')) return 'John Doe';
    switch (field.field_type) {
      case 'long_text': return 'A Sample Research Paper Title';
      case 'number': return '1570000012';
      case 'date': return '04 September 2026';
      default: return field.label || 'Sample text';
    }
  }

  /* ------------------------------------------------- 2. Coordinate conversion */
  // box: {left, top, width, height} in canvas-PIXEL space (top-left origin) -> {x, y, width, height} in PDF-POINT space (bottom-left origin)
  function screenBoxToPdf(box) {
    const p1 = viewport.convertToPdfPoint(box.left, box.top);
    const p2 = viewport.convertToPdfPoint(box.left + box.width, box.top + box.height);
    const x = Math.min(p1[0], p2[0]);
    const y = Math.min(p1[1], p2[1]);
    return { x: x, y: y, width: Math.abs(p2[0] - p1[0]), height: Math.abs(p2[1] - p1[1]) };
  }

  // box: {x, y, width, height} in PDF-POINT space -> {left, top, width, height} in canvas-PIXEL space
  function pdfBoxToScreen(box) {
    const p1 = viewport.convertToViewportPoint(box.x, box.y);
    const p2 = viewport.convertToViewportPoint(box.x + box.width, box.y + box.height);
    const left = Math.min(p1[0], p2[0]);
    const top = Math.min(p1[1], p2[1]);
    return { left: left, top: top, width: Math.abs(p2[0] - p1[0]), height: Math.abs(p2[1] - p1[1]) };
  }

  /* ------------------------------------------------------- 3. PDF.js render */
  function renderPdf() {
    pdfjsLib.GlobalWorkerOptions.workerSrc = config.pdfWorkerSrc;

    return pdfjsLib.getDocument(config.pdfUrl).promise.then(function (doc) {
      return doc.getPage(1);
    }).then(function (page) {
      // True page size in PDF points, independent of how large we render it.
      const nativeViewport = page.getViewport({ scale: 1 });
      const pageWidthPt = nativeViewport.width;
      const pageHeightPt = nativeViewport.height;

      // Fit the render to a sensible width for the available canvas area.
      const maxWidth = Math.min(canvasWrap.clientWidth - 48, 1000);
      const scale = maxWidth > 0 ? maxWidth / pageWidthPt : 1;
      viewport = page.getViewport({ scale: scale });

      pdfCanvas.width = viewport.width;
      pdfCanvas.height = viewport.height;
      canvasWrap.style.width = viewport.width + 'px';
      canvasWrap.style.height = viewport.height + 'px';

      const ctx = pdfCanvas.getContext('2d');
      return page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () {
        return { pageWidthPt: pageWidthPt, pageHeightPt: pageHeightPt };
      });
    });
  }

  /* --------------------------------------------------------- 4. QR sample */
  function buildSampleQr() {
    try {
      const qr = qrcode(0, 'M');
      qr.addData(window.location.origin + '/verify/SAMPLE-CODE');
      qr.make();
      qrSampleDataUrl = qr.createDataURL(8, 4);
    } catch (e) {
      qrSampleDataUrl = null;
    }
  }

  /* ------------------------------------------------------ 5. Element model */
  function makeElement(kind, id, label, extra) {
    return Object.assign({
      kind: kind,        // 'field' | 'system'
      id: id,             // template_field id, or 'certificate_number' / 'qr_code'
      label: label,
      placed: false,
      box: null,           // {left, top, width, height} in canvas-pixel space, once placed
      style: {
        font_size: 24,
        font_weight: 'normal',
        alignment: 'left',
        line_height: 1.2,
        color: '#000000',
        wrap: true,
      },
    }, extra || {});
  }

  function elementKey(kind, id) {
    return kind + ':' + id;
  }

  function initElements() {
    config.fields.forEach(function (field) {
      const el = makeElement('field', field.id, field.label, {
        fieldType: field.field_type,
        fieldKey: field.field_key,
        options: field.options,
        sample: sampleValueFor(field),
      });
      if (field.style) el.style = Object.assign(el.style, field.style);
      if (field.position) {
        el.placed = true;
        el.box = pdfBoxToScreen(field.position);
      }
      elements[elementKey('field', field.id)] = el;
    });

    const certNum = makeElement('system', 'certificate_number', 'Certificate Number', {
      sample: config.sampleCertificateNumber,
    });
    certNum.style = Object.assign(certNum.style, { alignment: 'center', font_size: 20, font_weight: 'bold' });
    if (config.certificateNumber) {
      certNum.placed = true;
      if (config.certificateNumber.style) certNum.style = Object.assign(certNum.style, config.certificateNumber.style);
      certNum.box = pdfBoxToScreen(config.certificateNumber);
    }
    elements[elementKey('system', 'certificate_number')] = certNum;

    const qr = makeElement('system', 'qr_code', 'QR Code');
    if (config.qrCode) {
      qr.placed = true;
      qr.box = pdfBoxToScreen(config.qrCode);
    }
    elements[elementKey('system', 'qr_code')] = qr;
  }

  /* --------------------------------------------------------- 6. Rendering */
  function renderAll() {
    canvasWrap.querySelectorAll('.designer-element').forEach(function (n) { n.remove(); });
    Object.keys(elements).forEach(function (key) {
      const el = elements[key];
      if (el.placed) renderElement(key);
    });
    updateSidebarPlacedState();
  }

  function renderElement(key) {
    const el = elements[key];
    let node = canvasWrap.querySelector('[data-key="' + key + '"]');
    if (!node) {
      node = document.createElement('div');
      node.className = 'designer-element' + (el.kind === 'system' ? ' designer-element--system' : '');
      node.dataset.key = key;

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'designer-element__remove';
      remove.textContent = '×';
      remove.title = 'Remove from canvas';
      remove.addEventListener('click', function (e) {
        e.stopPropagation();
        el.placed = false;
        el.box = null;
        node.remove();
        if (selectedKey === key) selectElement(null);
        updateSidebarPlacedState();
      });
      node.appendChild(remove);

      if (el.id === 'qr_code') {
        const img = document.createElement('img');
        img.className = 'designer-element__qr-img';
        img.src = qrSampleDataUrl || '';
        img.alt = 'Sample QR';
        node.appendChild(img);
      } else {
        const text = document.createElement('div');
        text.className = 'designer-element__text';
        text.textContent = el.sample || el.label;
        node.appendChild(text);
      }

      const handle = document.createElement('div');
      handle.className = 'designer-element__handle';
      node.appendChild(handle);
      attachResize(node, handle, key);
      attachMove(node, key);
      node.addEventListener('pointerdown', function () { selectElement(key); });

      canvasWrap.appendChild(node);
    }

    applyBoxToNode(node, el.box);
    applyStyleToNode(node, el);
  }

  function applyBoxToNode(node, box) {
    node.style.left = box.left + 'px';
    node.style.top = box.top + 'px';
    node.style.width = box.width + 'px';
    node.style.height = box.height + 'px';
  }

  function applyStyleToNode(node, el) {
    const text = node.querySelector('.designer-element__text');
    if (!text) return;
    text.style.fontSize = el.style.font_size + 'px';
    text.style.fontWeight = el.style.font_weight === 'bold' ? '700' : '400';
    text.style.textAlign = el.style.alignment;
    text.style.lineHeight = String(el.style.line_height || 1.2);
    text.style.color = el.style.color || '#000';
    text.style.whiteSpace = el.style.wrap === false ? 'nowrap' : 'normal';
    // Screen font size is illustrative only (viewport scale != 1); saved
    // font_size is always the value in the settings panel, in PDF points.
  }

  function updateSidebarPlacedState() {
    elementList.querySelectorAll('.designer-elements__item').forEach(function (li) {
      const key = elementKey(li.dataset.kind, li.dataset.id);
      const el = elements[key];
      li.classList.toggle('is-placed', !!(el && el.placed));
    });
  }

  /* ------------------------------------------------- 7. Drag from sidebar */
  elementList.querySelectorAll('.designer-elements__item').forEach(function (li) {
    li.addEventListener('dragstart', function (e) {
      const key = elementKey(li.dataset.kind, li.dataset.id);
      if (elements[key] && elements[key].placed) { e.preventDefault(); return; }
      if (!config.canEdit) { e.preventDefault(); return; }
      e.dataTransfer.setData('text/plain', key);
    });
    li.setAttribute('draggable', 'true');
  });

  canvasWrap.addEventListener('dragover', function (e) {
    if (!config.canEdit) return;
    e.preventDefault();
    canvasWrap.classList.add('is-drag-over');
  });
  canvasWrap.addEventListener('dragleave', function () {
    canvasWrap.classList.remove('is-drag-over');
  });
  canvasWrap.addEventListener('drop', function (e) {
    e.preventDefault();
    canvasWrap.classList.remove('is-drag-over');
    if (!config.canEdit) return;

    const key = e.dataTransfer.getData('text/plain');
    const el = elements[key];
    if (!el || el.placed) return;

    const rect = canvasWrap.getBoundingClientRect();
    const defaultWidth = el.id === 'qr_code' ? 100 : 200;
    const defaultHeight = el.id === 'qr_code' ? 100 : 40;
    const left = clamp(e.clientX - rect.left - defaultWidth / 2, 0, viewport.width - defaultWidth);
    const top = clamp(e.clientY - rect.top - defaultHeight / 2, 0, viewport.height - defaultHeight);

    el.placed = true;
    el.box = { left: left, top: top, width: defaultWidth, height: defaultHeight };
    renderElement(key);
    selectElement(key);
    updateSidebarPlacedState();
  });

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  /* --------------------------------------------- 8. Move / resize (pointer) */
  function attachMove(node, key) {
    let startX, startY, startLeft, startTop, dragging = false;

    node.addEventListener('pointerdown', function (e) {
      if (!config.canEdit) return;
      if (e.target.classList.contains('designer-element__handle') || e.target.classList.contains('designer-element__remove')) return;
      dragging = true;
      startX = e.clientX;
      startY = e.clientY;
      const el = elements[key];
      startLeft = el.box.left;
      startTop = el.box.top;
      node.setPointerCapture(e.pointerId);
      e.preventDefault();
    });

    node.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      const el = elements[key];
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      el.box.left = clamp(startLeft + dx, -el.box.width / 2, viewport.width - el.box.width / 2);
      el.box.top = clamp(startTop + dy, -el.box.height / 2, viewport.height - el.box.height / 2);
      applyBoxToNode(node, el.box);
      if (selectedKey === key) syncSettingsFromElement(key);
    });

    node.addEventListener('pointerup', function () {
      dragging = false;
    });
  }

  function attachResize(node, handle, key) {
    let startX, startY, startWidth, startHeight, resizing = false;

    handle.addEventListener('pointerdown', function (e) {
      if (!config.canEdit) return;
      resizing = true;
      startX = e.clientX;
      startY = e.clientY;
      const el = elements[key];
      startWidth = el.box.width;
      startHeight = el.box.height;
      handle.setPointerCapture(e.pointerId);
      e.stopPropagation();
      e.preventDefault();
    });

    handle.addEventListener('pointermove', function (e) {
      if (!resizing) return;
      const el = elements[key];
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      let width = Math.max(16, startWidth + dx);
      let height = Math.max(16, startHeight + dy);
      if (el.id === 'qr_code') { height = width; } // square by default — see docs/CERTIFICATE_SYSTEM.md
      el.box.width = width;
      el.box.height = height;
      applyBoxToNode(node, el.box);
      if (selectedKey === key) syncSettingsFromElement(key);
    });

    handle.addEventListener('pointerup', function () {
      resizing = false;
    });
  }

  /* --------------------------------------------------- 9. Settings panel */
  function selectElement(key) {
    if (selectedKey) {
      const prevNode = canvasWrap.querySelector('[data-key="' + selectedKey + '"]');
      if (prevNode) prevNode.classList.remove('is-selected');
    }
    selectedKey = key;
    if (!key) {
      settingsEmpty.hidden = false;
      settingsForm.hidden = true;
      settingsForm.innerHTML = '';
      return;
    }
    const node = canvasWrap.querySelector('[data-key="' + key + '"]');
    if (node) node.classList.add('is-selected');

    settingsEmpty.hidden = true;
    settingsForm.hidden = false;
    buildSettingsForm(key);
  }

  function buildSettingsForm(key) {
    const el = elements[key];
    const pdfBox = screenBoxToPdf(el.box);
    const isQr = el.id === 'qr_code';
    const showTextStyle = !isQr;

    let html = '<p style="font-weight:600;margin:0 0 10px">' + escapeHtml(el.label) + '</p>';
    html += fieldRow('Position & size (pt)', [
      numberInput('x', pdfBox.x, 0),
      numberInput('y', pdfBox.y, 0),
    ]);
    html += fieldRow('', [
      numberInput('width', pdfBox.width, 0),
      numberInput('height', pdfBox.height, 0, isQr),
    ]);

    if (showTextStyle) {
      html += '<div class="field"><label for="set_font_size">Font size (pt)</label>'
        + '<input type="number" id="set_font_size" min="4" max="300" value="' + el.style.font_size + '"></div>';
      html += '<div class="field"><label for="set_font_weight">Font weight</label>'
        + '<select id="set_font_weight"><option value="normal"' + (el.style.font_weight !== 'bold' ? ' selected' : '') + '>Normal</option>'
        + '<option value="bold"' + (el.style.font_weight === 'bold' ? ' selected' : '') + '>Bold</option></select></div>';
      html += '<div class="field"><label for="set_alignment">Alignment</label><select id="set_alignment">'
        + ['left', 'center', 'right'].map(function (a) {
          return '<option value="' + a + '"' + (el.style.alignment === a ? ' selected' : '') + '>' + a + '</option>';
        }).join('') + '</select></div>';
      if (el.kind === 'field') {
        html += '<div class="field"><label for="set_line_height">Line height</label>'
          + '<input type="number" id="set_line_height" min="0.5" max="4" step="0.1" value="' + (el.style.line_height || 1.2) + '"></div>';
        html += '<div class="field field--checkbox"><label><input type="checkbox" id="set_wrap"' + (el.style.wrap !== false ? ' checked' : '') + '> Wrap text</label></div>';
      }
      html += '<div class="field"><label for="set_color">Text color</label>'
        + '<input type="color" id="set_color" value="' + (el.style.color || '#000000') + '"></div>';
    }

    settingsForm.innerHTML = html;

    ['x', 'y', 'width', 'height'].forEach(function (name) {
      const input = document.getElementById('set_' + name);
      if (input) input.addEventListener('input', function () { applySettingsToElement(key); });
    });
    ['font_size', 'font_weight', 'alignment', 'line_height', 'color'].forEach(function (name) {
      const input = document.getElementById('set_' + name);
      if (input) input.addEventListener('input', function () { applySettingsToElement(key); });
    });
    const wrapInput = document.getElementById('set_wrap');
    if (wrapInput) wrapInput.addEventListener('change', function () { applySettingsToElement(key); });
  }

  function applySettingsToElement(key) {
    const el = elements[key];
    const x = numberOr(document.getElementById('set_x'), 0);
    const y = numberOr(document.getElementById('set_y'), 0);
    let width = numberOr(document.getElementById('set_width'), 10);
    let height = numberOr(document.getElementById('set_height'), 10);
    if (el.id === 'qr_code') height = width;

    el.box = pdfBoxToScreen({ x: x, y: y, width: width, height: height });

    const fontSize = document.getElementById('set_font_size');
    if (fontSize) el.style.font_size = numberOr(fontSize, el.style.font_size);
    const fontWeight = document.getElementById('set_font_weight');
    if (fontWeight) el.style.font_weight = fontWeight.value;
    const alignment = document.getElementById('set_alignment');
    if (alignment) el.style.alignment = alignment.value;
    const lineHeight = document.getElementById('set_line_height');
    if (lineHeight) el.style.line_height = numberOr(lineHeight, el.style.line_height);
    const color = document.getElementById('set_color');
    if (color) el.style.color = color.value;
    const wrap = document.getElementById('set_wrap');
    if (wrap) el.style.wrap = wrap.checked;

    const node = canvasWrap.querySelector('[data-key="' + key + '"]');
    if (node) {
      applyBoxToNode(node, el.box);
      applyStyleToNode(node, el);
    }
  }

  function syncSettingsFromElement(key) {
    // Cheap re-render of the numeric position fields while dragging, without rebuilding the whole panel.
    const el = elements[key];
    const pdfBox = screenBoxToPdf(el.box);
    ['x', 'y', 'width', 'height'].forEach(function (name) {
      const input = document.getElementById('set_' + name);
      if (input) input.value = Math.round(pdfBox[name] * 100) / 100;
    });
  }

  function fieldRow(labelText, inputsHtml) {
    const label = labelText ? '<label>' + escapeHtml(labelText) + '</label>' : '';
    return '<div class="field">' + label + '<div class="designer-settings__row">' + inputsHtml.join('') + '</div></div>';
  }

  function numberInput(name, value, min, disabled) {
    return '<input type="number" id="set_' + name + '" value="' + (Math.round(value * 100) / 100) + '"'
      + (min !== undefined ? ' min="' + min + '"' : '') + (disabled ? ' disabled' : '') + '>';
  }

  function numberOr(input, fallback) {
    const n = input ? parseFloat(input.value) : NaN;
    return isNaN(n) ? fallback : n;
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /* --------------------------------------------------------------- 10. Save */
  designerForm.addEventListener('submit', function (e) {
    if (!config.canEdit) { e.preventDefault(); return; }

    const payload = {
      page_width: viewport.width / viewport.scale,
      page_height: viewport.height / viewport.scale,
      fields: [],
      certificate_number: null,
      qr_code: null,
    };

    Object.keys(elements).forEach(function (key) {
      const el = elements[key];
      if (!el.placed) return;
      const pdfBox = screenBoxToPdf(el.box);

      if (el.kind === 'field') {
        payload.fields.push({
          id: el.id,
          position: pdfBox,
          style: el.style,
        });
      } else if (el.id === 'certificate_number') {
        payload.certificate_number = Object.assign({}, pdfBox, { style: el.style });
      } else if (el.id === 'qr_code') {
        payload.qr_code = pdfBox;
      }
    });

    layoutInput.value = JSON.stringify(payload);
  });

  /* ------------------------------------------------------------- Boot */
  renderPdf().then(function () {
    buildSampleQr();
    initElements();
    renderAll();
    saveHint.textContent = '';
  }).catch(function (err) {
    saveHint.textContent = 'Could not load the certificate PDF for preview.';
    // eslint-disable-next-line no-console
    console.error(err);
  });

  canvasWrap.addEventListener('pointerdown', function (e) {
    if (e.target === canvasWrap || e.target === pdfCanvas) selectElement(null);
  });
})();
