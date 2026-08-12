(function exposeJobPilotAutofillEngine(root, factory) {
  const engine = factory();
  root.JobPilotAutofillEngine = engine;
  if (typeof module !== 'undefined' && module.exports) module.exports = engine;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotAutofillEngine() {
  const IGNORED_INPUT_TYPES = new Set(['hidden', 'password', 'submit', 'button', 'reset', 'image']);

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[’']/g, ' ')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function languageFor(documentRef, context) {
    const requested = String(context?.language || '').toLowerCase();
    if (requested.startsWith('en')) return 'en';
    if (requested.startsWith('fr')) return 'fr';
    return String(documentRef.documentElement?.lang || '').toLowerCase().startsWith('en') ? 'en' : 'fr';
  }

  function readPath(object, path) {
    if (!object || !path) return null;
    let value = object;
    for (const segment of String(path).split('.')) {
      if (!value || typeof value !== 'object' || !(segment in value)) return null;
      value = value[segment];
    }
    return value;
  }

  function hasValue(value) {
    if (value === null || value === undefined || value === '') return false;
    if (Array.isArray(value)) return value.length > 0;
    return true;
  }

  function resolvedAnswerValue(answer, language) {
    const resolved = answer?.resolved || {};
    return resolved[language] ?? resolved.fr ?? resolved.en ?? null;
  }

  function policyAnswerForKey(answers, key) {
    return (answers || []).find(answer => answer?.profilePath === key) || null;
  }

  function questionMatch(question, answers, language) {
    const normalizedQuestion = normalize(question);
    if (!normalizedQuestion) return null;

    let best = null;
    for (const answer of answers || []) {
      if (!answer?.enabled || !answer?.eligibleForAutomaticFill) continue;
      const patterns = answer.questionPatterns?.[language] || [];
      for (const pattern of patterns) {
        const normalizedPattern = normalize(pattern);
        if (!normalizedPattern) continue;

        let score = 0;
        if (normalizedQuestion === normalizedPattern) {
          score = 1;
        } else if (normalizedQuestion.includes(normalizedPattern) || normalizedPattern.includes(normalizedQuestion)) {
          const ratio = Math.min(normalizedQuestion.length, normalizedPattern.length) / Math.max(normalizedQuestion.length, normalizedPattern.length);
          if (ratio >= 0.72) score = 0.9;
        }

        if (score && (!best || score > best.score)) best = { answer, score, pattern };
      }
    }
    return best;
  }

  function desiredForField(field, context, language) {
    const answers = context?.answers || [];
    const key = field.classification?.key || null;

    if (key) {
      const policy = policyAnswerForKey(answers, key);
      if (policy) {
        if (!policy.enabled) return { status: 'blocked', reason: 'answer-disabled', sensitive: Boolean(policy.sensitive) };
        if (policy.sensitive && !policy.autoFillAllowed) return { status: 'blocked', reason: 'sensitive-review-required', sensitive: true };
        if (!policy.eligibleForAutomaticFill) return { status: 'missing', reason: 'answer-not-ready', sensitive: Boolean(policy.sensitive) };
        const value = resolvedAnswerValue(policy, language);
        if (!hasValue(value)) return { status: 'missing', reason: 'missing-value', sensitive: Boolean(policy.sensitive) };
        return { status: 'ready', value, source: `answer:${policy.key}`, sensitive: Boolean(policy.sensitive) };
      }

      const value = readPath(context?.profile, key);
      if (!hasValue(value)) return { status: 'missing', reason: 'missing-profile-value', sensitive: false };
      return { status: 'ready', value, source: `profile:${key}`, sensitive: false };
    }

    if (field.classification?.status === 'question' && field.classification.questionText) {
      const match = questionMatch(field.classification.questionText, answers, language);
      if (!match) return { status: 'missing', reason: 'unknown-question', sensitive: false };
      const value = resolvedAnswerValue(match.answer, language);
      if (!hasValue(value)) return { status: 'missing', reason: 'missing-answer-value', sensitive: Boolean(match.answer.sensitive) };
      return {
        status: 'ready',
        value,
        source: `question:${match.answer.key}`,
        sensitive: Boolean(match.answer.sensitive),
        matchScore: match.score,
      };
    }

    return { status: 'missing', reason: field.classification?.status || 'unrecognized', sensitive: false };
  }

  function eligibleElements(documentRef) {
    return [...documentRef.querySelectorAll('input, textarea, select, [role="combobox"]')].filter(element => {
      if (!element || element.disabled) return false;
      const tag = element.tagName?.toLowerCase();
      if (!['input', 'textarea', 'select'].includes(tag) && element.getAttribute?.('role') !== 'combobox') return false;
      if (tag === 'input' && IGNORED_INPUT_TYPES.has(normalize(element.getAttribute('type') || 'text'))) return false;
      return true;
    });
  }

  function dispatchValueEvents(element) {
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function setNativeTextValue(element, value) {
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    const setter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
    setter ? setter.call(element, value) : (element.value = value);
    dispatchValueEvents(element);
  }

  function splitValues(value) {
    if (Array.isArray(value)) return value.map(item => String(item).trim()).filter(Boolean);
    return String(value || '').split(/[,;\n|]+/).map(item => item.trim()).filter(Boolean);
  }

  function booleanValue(value) {
    if (typeof value === 'boolean') return value;
    const normalized = normalize(value);
    if (['true', 'yes', 'oui', '1', 'y', 'o'].includes(normalized)) return true;
    if (['false', 'no', 'non', '0', 'n'].includes(normalized)) return false;
    return null;
  }

  function optionMatch(options, value) {
    const desired = normalize(value);
    if (!desired) return null;

    const exact = options.filter(option => !option.disabled && [option.value, option.label].some(candidate => normalize(candidate) === desired));
    if (exact.length === 1) return exact[0];
    if (exact.length > 1) return null;

    const partial = options.filter(option => {
      if (option.disabled) return false;
      return [option.value, option.label].some(candidate => {
        const normalizedCandidate = normalize(candidate);
        if (!normalizedCandidate) return false;
        if (!(normalizedCandidate.includes(desired) || desired.includes(normalizedCandidate))) return false;
        const ratio = Math.min(normalizedCandidate.length, desired.length) / Math.max(normalizedCandidate.length, desired.length);
        return ratio >= 0.72;
      });
    });

    return partial.length === 1 ? partial[0] : null;
  }

  function currentValuePresent(element, kind) {
    if (kind === 'radio' || kind === 'checkbox') return Boolean(element.checked);
    if (kind === 'select' || kind === 'multi-select') return Boolean(element.value);
    return Boolean(String(element.value || '').trim());
  }

  function fillText(element, value, kind) {
    const text = Array.isArray(value) ? value.join(', ') : String(value);
    if (kind === 'number' && !Number.isFinite(Number(text.replace(',', '.')))) return { ok: false, reason: 'invalid-number' };
    if (kind === 'date') {
      const match = text.match(/^\d{4}-\d{2}-\d{2}/);
      if (!match) return { ok: false, reason: 'invalid-date' };
      setNativeTextValue(element, match[0]);
      return { ok: true };
    }
    setNativeTextValue(element, text);
    return { ok: true };
  }

  function fillSelect(element, value, multiple) {
    const values = splitValues(value);
    if (!values.length) return { ok: false, reason: 'missing-value' };
    const options = [...element.options].map(option => ({
      element: option,
      value: option.value,
      label: option.textContent || option.label || option.value,
      disabled: Boolean(option.disabled),
    }));

    if (multiple) {
      const matches = values.map(item => optionMatch(options, item));
      if (matches.some(match => !match)) return { ok: false, reason: 'option-not-found' };
      const selected = new Set(matches.map(match => match.element));
      options.forEach(option => { option.element.selected = selected.has(option.element); });
      dispatchValueEvents(element);
      return { ok: true };
    }

    const match = optionMatch(options, values[0]);
    if (!match) return { ok: false, reason: 'option-not-found' };
    element.value = match.value;
    dispatchValueEvents(element);
    return { ok: true };
  }

  function choiceLabel(element) {
    const labels = element.labels ? [...element.labels].map(label => label.textContent || '').join(' ') : '';
    return normalize(labels || element.getAttribute('aria-label') || element.value || '');
  }

  function fillRadio(element, value) {
    const desiredBoolean = booleanValue(value);
    const desiredValues = splitValues(value).map(normalize);
    const candidate = choiceLabel(element);
    const rawValue = normalize(element.value);

    let matches = desiredValues.includes(candidate) || desiredValues.includes(rawValue);
    if (desiredBoolean !== null) {
      const yes = ['yes', 'oui', 'true', '1'].some(term => candidate === term || rawValue === term || candidate.includes(term));
      const no = ['no', 'non', 'false', '0'].some(term => candidate === term || rawValue === term || candidate.includes(term));
      matches = desiredBoolean ? yes : no;
    }

    if (!matches) return { ok: false, reason: 'radio-option-not-matched' };
    element.checked = true;
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
    element.click?.();
    return { ok: true };
  }

  function fillCheckbox(element, value) {
    const desiredBoolean = booleanValue(value);
    const values = splitValues(value).map(normalize);
    const candidate = choiceLabel(element);
    const rawValue = normalize(element.value);

    let checked = desiredBoolean;
    if (desiredBoolean === null) checked = values.includes(candidate) || values.includes(rawValue);
    if (checked === null || checked === false) return { ok: false, reason: 'checkbox-not-selected' };

    element.checked = true;
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
    return { ok: true };
  }

  function wait(milliseconds) {
    return new Promise(resolve => setTimeout(resolve, milliseconds));
  }

  async function fillAutocomplete(element, value) {
    const text = Array.isArray(value) ? String(value[0] || '') : String(value || '');
    if (!text) return { ok: false, reason: 'missing-value' };
    const original = element.value || '';
    setNativeTextValue(element, text);
    await wait(80);

    const controls = element.getAttribute('aria-controls');
    const listbox = controls ? element.ownerDocument?.getElementById(controls) : null;
    const optionElements = listbox ? [...listbox.querySelectorAll('[role="option"]')] : [];
    const options = optionElements.map(option => ({
      element: option,
      value: option.getAttribute('data-value') || option.getAttribute('value') || option.textContent || '',
      label: option.textContent || '',
      disabled: option.getAttribute('aria-disabled') === 'true',
    }));
    const match = optionMatch(options, text);
    if (!match) {
      setNativeTextValue(element, original);
      return { ok: false, reason: 'autocomplete-option-not-found' };
    }

    match.element.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    match.element.click?.();
    match.element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    return { ok: true };
  }

  async function fillControl(element, field, value) {
    const kind = field.controlKind;
    if (currentValuePresent(element, kind)) return { ok: false, reason: 'already-filled' };
    if (element.readOnly) return { ok: false, reason: 'read-only' };

    if (['text', 'textarea', 'email', 'tel', 'url', 'number', 'date'].includes(kind)) return fillText(element, value, kind);
    if (kind === 'select') return fillSelect(element, value, false);
    if (kind === 'multi-select') return fillSelect(element, value, true);
    if (kind === 'radio') return fillRadio(element, value);
    if (kind === 'checkbox') return fillCheckbox(element, value);
    if (kind === 'autocomplete') return fillAutocomplete(element, value);
    return { ok: false, reason: kind === 'file' ? 'document-upload-separate' : 'unsupported-control' };
  }

  function preview(value, sensitive) {
    if (sensitive) return '••••';
    const text = Array.isArray(value) ? value.join(', ') : String(value ?? '');
    return text.length > 80 ? `${text.slice(0, 77)}…` : text;
  }

  async function fill(documentRef, context, detectorOverride) {
    const detector = detectorOverride || globalThis.JobPilotFormDetector;
    if (!detector || typeof detector.detect !== 'function') throw new Error('JobPilot form detector is unavailable.');

    const detection = detector.detect(documentRef);
    const elements = eligibleElements(documentRef);
    const language = languageFor(documentRef, context);
    const minimumConfidence = Number.isFinite(context?.minimumConfidence) ? context.minimumConfidence : 0.72;
    const fields = [];

    for (const field of detection.fields) {
      const element = elements[field.index];
      if (!element) {
        fields.push({ ...field, fillStatus: 'skipped', fillReason: 'element-not-found' });
        continue;
      }

      if (field.classification.status === 'ambiguous') {
        fields.push({ ...field, fillStatus: 'review', fillReason: 'ambiguous-field' });
        continue;
      }
      if (field.classification.status === 'recognized' && field.classification.confidence < minimumConfidence) {
        fields.push({ ...field, fillStatus: 'review', fillReason: 'low-confidence' });
        continue;
      }

      const desired = desiredForField(field, context, language);
      if (desired.status !== 'ready') {
        fields.push({ ...field, fillStatus: desired.status === 'blocked' ? 'review' : 'skipped', fillReason: desired.reason });
        continue;
      }

      const result = await fillControl(element, field, desired.value);
      fields.push({
        ...field,
        fillStatus: result.ok ? 'filled' : (result.reason === 'already-filled' ? 'preserved' : 'review'),
        fillReason: result.ok ? null : result.reason,
        fillSource: desired.source,
        valuePreview: preview(desired.value, desired.sensitive),
      });
    }

    return {
      schemaVersion: 1,
      language,
      detected: detection.fieldCount,
      filled: fields.filter(field => field.fillStatus === 'filled').length,
      preserved: fields.filter(field => field.fillStatus === 'preserved').length,
      review: fields.filter(field => field.fillStatus === 'review').length,
      skipped: fields.filter(field => field.fillStatus === 'skipped').length,
      fields,
    };
  }

  return {
    desiredForField,
    fill,
    normalize,
    optionMatch,
    questionMatch,
  };
});
