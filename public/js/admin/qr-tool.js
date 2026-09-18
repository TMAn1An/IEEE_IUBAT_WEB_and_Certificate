/**
 * Adapted from IEEEQRCODEGENERATOR-main/templates/index.html's inline
 * script. Same function names/behavior where practical; the one structural
 * difference is that role/conference options now round-trip through small
 * server endpoints (see routes/admin.php's qr.options.* routes) instead of
 * `localStorage`, so every admin sees the same persisted list. See
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool: old-tool-parity rebuild.
 */
(function () {
  var config = window.QR_TOOL_CONFIG;

  function jsonHeaders() {
    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': config.csrf,
    };
  }

  function post(url, body) {
    return fetch(url, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
      .then(function (response) { return response.json(); });
  }

  function populateSelect(select, options, placeholder) {
    var previous = select.value;
    select.innerHTML = '<option value="">' + placeholder + '</option>';
    options.forEach(function (value) {
      var option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });
    if (options.indexOf(previous) !== -1) {
      select.value = previous;
    }
  }

  function populateRoleSelect() {
    populateSelect(document.getElementById('role_select'), config.roleOptions, 'Select Role');
  }

  function findType(name) {
    return config.conferenceTypes.filter(function (t) { return t.name === name; })[0];
  }

  function populateTypeSelect() {
    var select = document.getElementById('conference_type');
    var previous = select.value;
    select.innerHTML = '<option value="">Select Type</option>';
    config.conferenceTypes.forEach(function (type) {
      var option = document.createElement('option');
      option.value = type.name;
      option.textContent = type.name;
      select.appendChild(option);
    });
    if (findType(previous)) {
      select.value = previous;
    }
  }

  window.toggleConferenceName = function () {
    var typeSelect = document.getElementById('conference_type');
    var nameWrap = document.getElementById('conferenceNameWrap');
    var nameSelect = document.getElementById('conference_select');
    var type = findType(typeSelect.value);

    populateSelect(nameSelect, type ? type.options : [], 'Select Name');
    nameWrap.style.display = typeSelect.value ? 'block' : 'none';
    nameSelect.required = Boolean(typeSelect.value);
  };

  window.toggleConferenceField = function () {
    var included = document.getElementById('include_conference').checked;
    var typeSelect = document.getElementById('conference_type');
    document.getElementById('conferenceField').style.display = included ? 'block' : 'none';
    typeSelect.required = included;
    if (!included) {
      typeSelect.value = '';
      window.toggleConferenceName();
    }
  };

  window.toggleSessionField = function () {
    var included = document.getElementById('include_session').checked;
    var session = document.getElementById('session');
    document.getElementById('sessionField').style.display = included ? 'block' : 'none';
    if (!included) session.value = '';
  };

  window.copyCodeword = function () {
    var code = document.getElementById('codeword');
    if (!code || !navigator.clipboard) return;
    navigator.clipboard.writeText(code.innerText);
  };

  window.addRoleOption = function () {
    var input = document.getElementById('role_option_add');
    var value = input.value.trim();
    if (!value) return;

    post(config.urls.addRole, { value: value }).then(function (data) {
      config.roleOptions = data.options;
      populateRoleSelect();
      document.getElementById('role_select').value = value;
      input.value = '';
    });
  };

  window.removeRoleOption = function () {
    var select = document.getElementById('role_select');
    var value = select.value;
    if (!value) return;

    post(config.urls.removeRole, { value: value }).then(function (data) {
      config.roleOptions = data.options;
      populateRoleSelect();
    });
  };

  window.addConferenceType = function () {
    var input = document.getElementById('conference_type_add');
    var value = input.value.trim();
    if (!value) return;

    post(config.urls.addType, { value: value }).then(function (data) {
      config.conferenceTypes = data.types;
      populateTypeSelect();
      document.getElementById('conference_type').value = value;
      window.toggleConferenceName();
      input.value = '';
    });
  };

  window.removeConferenceType = function () {
    var select = document.getElementById('conference_type');
    var value = select.value;
    if (!value) return;

    post(config.urls.removeType, { value: value }).then(function (data) {
      config.conferenceTypes = data.types;
      populateTypeSelect();
      window.toggleConferenceName();
    });
  };

  window.addConferenceOption = function () {
    var typeSelect = document.getElementById('conference_type');
    var input = document.getElementById('conference_option_add');
    var value = input.value.trim();
    if (!value || !typeSelect.value) return;

    post(config.urls.addOption, { type: typeSelect.value, value: value }).then(function (data) {
      config.conferenceTypes = data.types;
      window.toggleConferenceName();
      document.getElementById('conference_select').value = value;
      input.value = '';
    });
  };

  window.removeConferenceOption = function () {
    var typeSelect = document.getElementById('conference_type');
    var nameSelect = document.getElementById('conference_select');
    var value = nameSelect.value;
    if (!value || !typeSelect.value) return;

    post(config.urls.removeOption, { type: typeSelect.value, value: value }).then(function (data) {
      config.conferenceTypes = data.types;
      window.toggleConferenceName();
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    populateRoleSelect();
    populateTypeSelect();
    window.toggleConferenceField();
    if (config.selected.conferenceType) {
      document.getElementById('conference_type').value = config.selected.conferenceType;
    }
    window.toggleConferenceName();
    if (config.selected.conferenceSelect) {
      document.getElementById('conference_select').value = config.selected.conferenceSelect;
    }
    if (config.selected.roleSelect) {
      document.getElementById('role_select').value = config.selected.roleSelect;
    }
    window.toggleSessionField();

    var copyLinkBtn = document.getElementById('copy-link-btn');
    if (copyLinkBtn) {
      copyLinkBtn.addEventListener('click', function () {
        var url = document.getElementById('verify-url').value;
        var status = document.getElementById('copy-status');
        navigator.clipboard.writeText(url).then(function () {
          status.textContent = 'Link copied.';
        }).catch(function () {
          status.textContent = 'Could not copy automatically -- select and copy the link field manually.';
        });
      });
    }

    var form = document.getElementById('generatorForm');
    form.addEventListener('submit', function () {
      var button = document.getElementById('generateBtn');
      button.disabled = true;
      button.textContent = 'Generating…';
    });
  });
})();
