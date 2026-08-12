(function exposeJobPilotCorrectionLearning(root, factory) {
  const learning = factory();
  root.JobPilotCorrectionLearning = learning;
  if (typeof module !== 'undefined' && module.exports) module.exports = learning;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotCorrectionLearning() {
  const STORAGE_KEY = 'jobpilotAutofillLearnedRules';
  const SENSITIVE_KEYS = new Set([
    'screening.workAuthorisation',
    'screening.sponsorship',
    'preferences.desiredSalary',
    'preferences.desiredTjm',
  ]);
  const LEARNABLE_CONTROLS = new Set(['text', 'textarea', 'email', 'tel', 'url', 'number', 'date', 'select', 'autocomplete']);

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function domainFor(locationRef) {
    return String(locationRef?.hostname || '').toLowerCase().replace(/^www\./, '');
  }

  function fieldFingerprint(field) {
    const parts = [
      field?.controlKind,
      field?.name,
      field?.id,
      field?.label,
      field?.placeholder,
      field?.selectorHint,
      field?.classification?.key,
    ].map(normalize).filter(Boolean);
    return parts.join('|').slice(0, 800);
  }

  function isLearnableField(field) {
    const classification = field?.classification || {};
    const key = classification.key || '';
    return classification.status === 'recognized'
      && Boolean(key)
      && !SENSITIVE_KEYS.has(key)
      && LEARNABLE_CONTROLS.has(field?.controlKind);
  }

  function eligibleElements(documentRef) {
    const ignored = new Set(['hidden', 'password', 'submit', 'button', 'reset', 'image', 'file']);
    return [...documentRef.querySelectorAll('input, textarea, select, [role="combobox"]')].filter(element => {
      if (!element || element.disabled) return false;
      const tag = element.tagName?.toLowerCase();
      if (!['input', 'textarea', 'select'].includes(tag) && element.getAttribute?.('role') !== 'combobox') return false;
      if (tag === 'input' && ignored.has(normalize(element.getAttribute('type') || 'text'))) return false;
      return true;
    });
  }

  function readValue(element, controlKind) {
    if (!element) return '';
    if (controlKind === 'select') {
      const selected = element.options?.[element.selectedIndex];
      return String(selected?.value || selected?.textContent || element.value || '').trim();
    }
    return String(element.value ?? '').trim();
  }

  function dispatchValueEvents(element) {
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function writeValue(element, field, value) {
    if (!element || element.disabled || element.readOnly) return false;
    const text = String(value ?? '');
    if (!text) return false;

    if (field.controlKind === 'select') {
      const desired = normalize(text);
      const matches = [...element.options].filter(option => !option.disabled && [option.value, option.textContent].some(candidate => normalize(candidate) === desired));
      if (matches.length !== 1) return false;
      element.value = matches[0].value;
      dispatchValueEvents(element);
      return true;
    }

    if (!LEARNABLE_CONTROLS.has(field.controlKind)) return false;
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    const setter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
    setter ? setter.call(element, text) : (element.value = text);
    dispatchValueEvents(element);
    return true;
  }

  function activeRulesForDomain(rules, domain) {
    const normalizedDomain = domainFor({ hostname: domain });
    return (Array.isArray(rules) ? rules : []).filter(rule => rule?.enabled !== false && rule?.domain === normalizedDomain && rule?.fingerprint && typeof rule.value === 'string');
  }

  function applyRules(documentRef, detector, rules) {
    if (!detector || typeof detector.detect !== 'function') return { applied: 0, matched: 0, appliedFingerprints: [] };
    const domain = domainFor(documentRef.location);
    const applicable = activeRulesForDomain(rules, domain);
    if (!applicable.length) return { applied: 0, matched: 0, appliedFingerprints: [] };

    const byFingerprint = new Map(applicable.map(rule => [rule.fingerprint, rule]));
    const detection = detector.detect(documentRef);
    const elements = eligibleElements(documentRef);
    const appliedFingerprints = [];
    let matched = 0;
    let applied = 0;

    for (const field of detection.fields || []) {
      if (!isLearnableField(field)) continue;
      const fingerprint = fieldFingerprint(field);
      const rule = byFingerprint.get(fingerprint);
      if (!rule || rule.key !== field.classification.key) continue;
      matched += 1;
      const element = elements[field.index];
      if (writeValue(element, field, rule.value)) {
        applied += 1;
        appliedFingerprints.push(fingerprint);
      }
    }

    return { applied, matched, appliedFingerprints };
  }

  function storageArea() {
    return globalThis.chrome?.storage?.local || null;
  }

  async function loadRules() {
    const storage = storageArea();
    if (!storage) return [];
    const payload = await storage.get(STORAGE_KEY);
    return Array.isArray(payload?.[STORAGE_KEY]) ? payload[STORAGE_KEY] : [];
  }

  async function saveRules(rules) {
    const storage = storageArea();
    if (!storage) return;
    await storage.set({ [STORAGE_KEY]: rules.slice(0, 250) });
  }

  function upsertRule(rules, nextRule) {
    const items = Array.isArray(rules) ? [...rules] : [];
    const index = items.findIndex(rule => rule.domain === nextRule.domain && rule.fingerprint === nextRule.fingerprint);
    if (index >= 0) items[index] = { ...items[index], ...nextRule };
    else items.unshift(nextRule);
    return items.slice(0, 250);
  }

  async function rememberCorrection(field, correctedValue, documentRef = document) {
    if (!isLearnableField(field)) return { saved: false, reason: 'field-not-learnable' };
    const value = String(correctedValue ?? '').trim();
    if (!value || value.length > 1500) return { saved: false, reason: 'invalid-value' };

    const domain = domainFor(documentRef.location);
    const fingerprint = fieldFingerprint(field);
    if (!domain || !fingerprint) return { saved: false, reason: 'missing-identity' };

    const rules = await loadRules();
    const next = upsertRule(rules, {
      id: `${domain}:${fingerprint}`,
      domain,
      fingerprint,
      key: field.classification.key,
      label: String(field.label || field.name || field.id || field.classification.key).slice(0, 180),
      value,
      controlKind: field.controlKind,
      enabled: true,
      updatedAt: new Date().toISOString(),
    });
    await saveRules(next);
    return { saved: true };
  }

  function watchCorrections(documentRef, report, confirmFn = globalThis.confirm, learnedFingerprints = []) {
    const fields = Array.isArray(report?.fields) ? report.fields : [];
    const elements = eligibleElements(documentRef);
    const learned = new Set(Array.isArray(learnedFingerprints) ? learnedFingerprints : []);
    const cleanups = [];

    for (const field of fields) {
      const fingerprint = fieldFingerprint(field);
      const wasFilledByJobPilot = field.fillStatus === 'filled' || (field.fillStatus === 'preserved' && learned.has(fingerprint));
      if (!wasFilledByJobPilot || !isLearnableField(field)) continue;
      const element = elements[field.index];
      if (!element) continue;
      let baseline = readValue(element, field.controlKind);
      let prompting = false;

      const handler = async event => {
        if (event?.isTrusted === false) return;
        const current = readValue(element, field.controlKind);
        if (!current || current === baseline || prompting) return;
        prompting = true;
        try {
          const accepted = typeof confirmFn === 'function'
            ? confirmFn(`JobPilot a détecté une correction pour « ${field.label || field.classification.key} ».\n\nL’enregistrer uniquement pour ${domainFor(documentRef.location)} ?`)
            : false;
          if (accepted) await rememberCorrection(field, current, documentRef);
          baseline = current;
        } finally {
          prompting = false;
        }
      };

      element.addEventListener('change', handler);
      element.addEventListener('blur', handler);
      cleanups.push(() => {
        element.removeEventListener('change', handler);
        element.removeEventListener('blur', handler);
      });
    }

    return () => cleanups.forEach(cleanup => cleanup());
  }

  return {
    STORAGE_KEY,
    activeRulesForDomain,
    applyRules,
    domainFor,
    fieldFingerprint,
    isLearnableField,
    loadRules,
    rememberCorrection,
    saveRules,
    upsertRule,
    watchCorrections,
  };
});
