(function exposeJobPilotCorrectionLearning(root, factory) {
  const learning = factory(root);
  root.JobPilotCorrectionLearning = learning;
  if (typeof module !== 'undefined' && module.exports) module.exports = learning;

  if (root.document && root.chrome?.runtime?.sendMessage) {
    learning.start(root.document);
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotCorrectionLearning(root) {
  const ALLOWED_KINDS = new Set(['select', 'multi-select', 'autocomplete']);
  const MARKER = 'jobpilotCorrectionTracked';

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function fieldFingerprint(field) {
    return [
      field?.controlKind,
      normalize(field?.name),
      normalize(field?.id),
      normalize(field?.label),
      normalize(field?.autocomplete),
    ].join('|').slice(0, 255);
  }

  function eligibleElements(documentRef) {
    const ignored = new Set(['hidden', 'password', 'submit', 'button', 'reset', 'image']);
    return [...documentRef.querySelectorAll('input, textarea, select, [role="combobox"]')].filter(element => {
      if (!element || element.disabled) return false;
      const tag = element.tagName?.toLowerCase();
      if (!['input', 'textarea', 'select'].includes(tag) && element.getAttribute?.('role') !== 'combobox') return false;
      if (tag === 'input' && ignored.has(String(element.getAttribute('type') || 'text').toLowerCase())) return false;
      return true;
    });
  }

  function readValue(element, kind) {
    if (kind === 'multi-select' && element instanceof HTMLSelectElement) {
      return [...element.selectedOptions].map(option => option.value || option.textContent || '').filter(Boolean).join('|');
    }
    return String(element.value || '').trim();
  }

  function dispatchValueEvents(element) {
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function setNativeValue(element, value) {
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    const setter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
    setter ? setter.call(element, value) : (element.value = value);
    dispatchValueEvents(element);
  }

  function correctionFor(field, corrections) {
    const key = field?.classification?.key;
    if (!key || !ALLOWED_KINDS.has(field.controlKind)) return null;
    const fingerprint = fieldFingerprint(field);
    return (corrections || []).find(correction =>
      correction?.enabled !== false
      && correction?.fieldFingerprint === fingerprint
      && correction?.canonicalKey === key
      && correction?.controlKind === field.controlKind,
    ) || null;
  }

  function applySelect(element, field, value, engine) {
    const desiredValues = String(value || '').split('|').map(item => item.trim()).filter(Boolean);
    if (!desiredValues.length || !(element instanceof HTMLSelectElement)) return false;

    const options = [...element.options].map(option => ({
      element: option,
      value: option.value,
      label: option.textContent || option.label || option.value,
      disabled: Boolean(option.disabled),
    }));

    if (field.controlKind === 'multi-select') {
      const matches = desiredValues.map(item => engine.optionMatch(options, item));
      if (matches.some(match => !match)) return false;
      const selected = new Set(matches.map(match => match.element));
      options.forEach(option => { option.element.selected = selected.has(option.element); });
      dispatchValueEvents(element);
      return true;
    }

    const match = engine.optionMatch(options, desiredValues[0]);
    if (!match) return false;
    element.value = match.value;
    dispatchValueEvents(element);
    return true;
  }

  function applyAutocomplete(element, value, engine) {
    const desired = String(value || '').trim();
    if (!desired) return false;

    const controls = element.getAttribute?.('aria-controls');
    const listbox = controls ? element.ownerDocument?.getElementById(controls) : null;
    const optionElements = listbox ? [...listbox.querySelectorAll('[role="option"]')] : [];
    const options = optionElements.map(option => ({
      element: option,
      value: option.getAttribute('data-value') || option.getAttribute('value') || option.textContent || '',
      label: option.textContent || '',
      disabled: option.getAttribute('aria-disabled') === 'true',
    }));
    const match = engine.optionMatch(options, desired);
    if (!match) return false;

    setNativeValue(element, desired);
    match.element.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    match.element.click?.();
    match.element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    return true;
  }

  function markTrackable(element, field) {
    if (!ALLOWED_KINDS.has(field.controlKind) || !field.classification?.key) return;
    if (field.classification.key.startsWith('screening.')) return;
    if (['preferences.desiredSalary', 'preferences.desiredTjm'].includes(field.classification.key)) return;

    element.dataset[MARKER] = '1';
    element.dataset.jobpilotFieldFingerprint = fieldFingerprint(field);
    element.dataset.jobpilotCanonicalKey = field.classification.key;
    element.dataset.jobpilotControlKind = field.controlKind;
    element.dataset.jobpilotAutofillSnapshot = readValue(element, field.controlKind);
  }

  function applyAndTrack(documentRef, report, context, engineOverride) {
    const engine = engineOverride || root.JobPilotAutofillEngine;
    if (!engine?.optionMatch) return report;

    const elements = eligibleElements(documentRef);
    const corrections = context?.corrections || [];
    const fields = (report?.fields || []).map(field => {
      const element = elements[field.index];
      if (!element || field.fillStatus !== 'filled') return field;

      const correction = correctionFor(field, corrections);
      let next = field;
      if (correction) {
        const applied = field.controlKind === 'autocomplete'
          ? applyAutocomplete(element, correction.correctedValue, engine)
          : applySelect(element, field, correction.correctedValue, engine);
        if (applied) {
          next = {
            ...field,
            fillSource: `correction:${correction.id}`,
            learnedCorrectionApplied: true,
            correctionId: correction.id,
          };
        }
      }

      markTrackable(element, next);
      return next;
    });

    return { ...report, fields };
  }

  function saveCorrection(payload) {
    return new Promise((resolve, reject) => {
      root.chrome.runtime.sendMessage({ type: 'SAVE_AUTOFILL_CORRECTION', ...payload }, response => {
        if (root.chrome.runtime.lastError) return reject(new Error(root.chrome.runtime.lastError.message));
        if (!response?.ok) return reject(new Error(response?.error || 'Correction non enregistrée.'));
        resolve(response.correction);
      });
    });
  }

  function removePrompt(element) {
    const id = element.dataset.jobpilotCorrectionPromptId;
    if (id) element.ownerDocument?.getElementById(id)?.remove();
    delete element.dataset.jobpilotCorrectionPromptId;
  }

  function promptForCorrection(element) {
    const kind = element.dataset.jobpilotControlKind;
    const canonicalKey = element.dataset.jobpilotCanonicalKey;
    const fingerprint = element.dataset.jobpilotFieldFingerprint;
    const originalValue = element.dataset.jobpilotAutofillSnapshot ?? '';
    const correctedValue = readValue(element, kind);

    if (!kind || !canonicalKey || !fingerprint || correctedValue === '' || correctedValue === originalValue) return;
    if (!ALLOWED_KINDS.has(kind) || canonicalKey.startsWith('screening.')) return;

    removePrompt(element);
    const documentRef = element.ownerDocument;
    const prompt = documentRef.createElement('div');
    const id = `jobpilot-correction-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    prompt.id = id;
    prompt.setAttribute('data-jobpilot-ui', 'correction-prompt');
    prompt.style.cssText = 'margin-top:6px;padding:8px;border:1px solid #c9c9c9;border-radius:7px;background:#fff;font:12px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#222;';
    prompt.textContent = 'JobPilot : mémoriser cette correction pour ce site ? ';

    const save = documentRef.createElement('button');
    save.type = 'button';
    save.textContent = 'Mémoriser';
    save.style.marginLeft = '6px';

    const dismiss = documentRef.createElement('button');
    dismiss.type = 'button';
    dismiss.textContent = 'Pas maintenant';
    dismiss.style.marginLeft = '6px';

    save.addEventListener('click', async () => {
      save.disabled = true;
      try {
        await saveCorrection({
          host: root.location?.hostname || documentRef.location?.hostname || '',
          fieldFingerprint: fingerprint,
          canonicalKey,
          controlKind: kind,
          originalValue,
          correctedValue,
        });
        element.dataset.jobpilotAutofillSnapshot = correctedValue;
        prompt.remove();
      } catch (error) {
        save.disabled = false;
        prompt.firstChild.textContent = error instanceof Error ? error.message : 'Correction non enregistrée. ';
      }
    });
    dismiss.addEventListener('click', () => prompt.remove());

    prompt.append(save, dismiss);
    element.insertAdjacentElement('afterend', prompt);
    element.dataset.jobpilotCorrectionPromptId = id;
  }

  function start(documentRef = document) {
    documentRef.addEventListener('change', event => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || target.dataset[MARKER] !== '1') return;
      promptForCorrection(target);
    }, true);
  }

  return {
    applyAndTrack,
    fieldFingerprint,
    promptForCorrection,
    readValue,
    start,
  };
});
