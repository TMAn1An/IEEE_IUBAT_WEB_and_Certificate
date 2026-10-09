/* =========================================================================
   Dynamic forms — conditional visibility evaluator (browser side).
   MUST stay semantically identical to App\Services\Forms\FormVisibilityResolver
   (the server re-evaluates every submission and is the authority; this file
   only drives what the visitor/admin sees). Shared by the public form
   runtime and the admin builder's live preview.
   See docs/FORM_BUILDER.md §Conditional logic.
   ========================================================================= */
(function (global) {
  'use strict';

  function normalize(value) {
    if (value === null || value === undefined || typeof value === 'object') return '';
    return String(value).trim().toLowerCase();
  }

  function isEmpty(value) {
    if (Array.isArray(value)) return value.every(function (v) { return normalize(v) === ''; });
    return normalize(value) === '';
  }

  function equals(actual, target) {
    if (Array.isArray(actual)) return actual.map(normalize).indexOf(target) !== -1;
    return normalize(actual) === target;
  }

  function contains(actual, target) {
    if (Array.isArray(actual)) return equals(actual, target);
    return target === '' || normalize(actual).indexOf(target) !== -1;
  }

  function conditionMatches(condition, values) {
    var actual = Object.prototype.hasOwnProperty.call(values, condition.field) ? values[condition.field] : null;
    var target = normalize(condition.value || '');
    switch (condition.operator) {
      case 'is_empty': return isEmpty(actual);
      case 'is_not_empty': return !isEmpty(actual);
      case 'equals': return equals(actual, target);
      case 'not_equals': return !equals(actual, target);
      case 'contains': return contains(actual, target);
      default: return false;
    }
  }

  function rulesPass(rules, values) {
    var conditions = (rules && Array.isArray(rules.conditions)) ? rules.conditions : [];
    if (conditions.length === 0) return true;
    var results = conditions.map(function (c) { return conditionMatches(c, values); });
    var matched = rules.match === 'any'
      ? results.indexOf(true) !== -1
      : results.indexOf(false) === -1;
    return rules.action === 'hide' ? !matched : matched;
  }

  /**
   * @param fields   [{key, type, collectsValue, rules}] in form order (active only)
   * @param getValue function(field) -> current value (string | string[] | null)
   * @returns {Object<string, boolean>} key -> visible
   */
  function resolve(fields, getValue) {
    var visible = {};
    var effective = {};
    var sectionVisible = true;

    fields.forEach(function (field) {
      var own = rulesPass(field.rules, effective);
      var isVisible;
      if (field.type === 'section') {
        sectionVisible = own;
        isVisible = own;
      } else {
        isVisible = sectionVisible && own;
      }
      visible[field.key] = isVisible;
      if (field.collectsValue) {
        effective[field.key] = isVisible ? getValue(field) : null;
      }
    });

    return visible;
  }

  global.FormLogic = { resolve: resolve, rulesPass: rulesPass, operators: ['equals', 'not_equals', 'contains', 'is_empty', 'is_not_empty'] };
})(window);
