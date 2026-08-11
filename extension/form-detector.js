(function exposeJobPilotFormDetector(root, factory) {
  const detector = factory();
  root.JobPilotFormDetector = detector;
  if (typeof module !== 'undefined' && module.exports) module.exports = detector;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotFormDetector() {
  const IGNORED_INPUT_TYPES = new Set(['hidden', 'password', 'submit', 'button', 'reset', 'image']);

  const RULES = [
    rule('identity.fullName', ['full name', 'fullname', 'nom complet', 'nom et prénom', 'nom et prenom'], ['name']),
    rule('identity.firstName', ['first name', 'firstname', 'given name', 'prénom', 'prenom'], ['given-name']),
    rule('identity.lastName', ['last name', 'lastname', 'surname', 'family name', 'nom de famille'], ['family-name']),
    rule('identity.email', ['email', 'e-mail', 'mail', 'courriel'], ['email']),
    rule('identity.phone', ['phone', 'telephone', 'téléphone', 'mobile', 'portable'], ['tel']),
    rule('address.line1', ['address line 1', 'address 1', 'street address', 'adresse', 'adresse postale'], ['street-address', 'address-line1']),
    rule('address.line2', ['address line 2', 'address 2', 'complément adresse', 'complement adresse'], ['address-line2']),
    rule('address.city', ['city', 'ville', 'locality'], ['address-level2']),
    rule('address.postalCode', ['postal code', 'postcode', 'zip code', 'zip', 'code postal'], ['postal-code']),
    rule('address.region', ['region', 'région', 'state', 'province', 'département', 'departement'], ['address-level1']),
    rule('address.country', ['country', 'pays'], ['country', 'country-name']),
    rule('professional.currentJobTitle', ['current job title', 'job title', 'position title', 'titre du poste', 'poste actuel', 'intitulé du poste', 'intitule du poste'], ['organization-title']),
    rule('professional.linkedinUrl', ['linkedin', 'linkedin url', 'linkedin profile', 'profil linkedin'], []),
    rule('professional.githubUrl', ['github', 'github url', 'github profile', 'profil github'], []),
    rule('professional.portfolioUrl', ['portfolio', 'personal website', 'website', 'site web', 'site personnel'], ['url']),
    rule('preferences.availability', ['availability', 'available date', 'start date', 'date available', 'disponibilité', 'disponibilite', 'date de disponibilité', 'date de disponibilite'], []),
    rule('preferences.noticePeriod', ['notice period', 'préavis', 'preavis'], []),
    rule('preferences.desiredSalary', ['salary expectation', 'salary expectations', 'desired salary', 'expected salary', 'prétentions salariales', 'pretentions salariales', 'salaire souhaité', 'salaire souhaite'], []),
    rule('preferences.desiredTjm', ['daily rate', 'day rate', 'tjm', 'tarif journalier', 'taux journalier'], []),
    rule('professional.yearsOfExperience', ['years of experience', 'years experience', 'années expérience', 'annees experience', "années d'expérience", "annees d'experience"], []),
    rule('screening.workAuthorisation', ['work authorization', 'work authorisation', 'authorized to work', 'authorised to work', 'autorisation de travail', 'autorisé à travailler', 'autorise a travailler'], []),
    rule('screening.sponsorship', ['sponsorship', 'visa sponsorship', 'require sponsorship', 'need sponsorship', 'parrainage visa', 'sponsor visa'], []),
  ];

  function rule(key, terms, autocomplete) {
    return { key, terms: terms.map(normalize), autocomplete: autocomplete.map(normalize) };
  }

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

  function cleanText(value, maxLength = 500) {
    return String(value || '').replace(/\s+/g, ' ').trim().slice(0, maxLength);
  }

  function textFromIds(value, documentRef) {
    return String(value || '')
      .split(/\s+/)
      .map(id => cleanText(documentRef.getElementById(id)?.textContent || ''))
      .filter(Boolean)
      .join(' ');
  }

  function fieldsetLegend(element) {
    const fieldset = element.closest?.('fieldset');
    return cleanText(fieldset?.querySelector(':scope > legend')?.textContent || '');
  }

  function nearestGroupText(element) {
    const group = element.closest?.('[role="group"], [role="radiogroup"], .form-group, .field, .form-field');
    if (!group) return '';
    const clone = group.cloneNode(true);
    clone.querySelectorAll?.('input,textarea,select,button,option,script,style').forEach(node => node.remove());
    return cleanText(clone.textContent || '', 300);
  }

  function explicitLabel(element, documentRef) {
    const labels = element.labels ? [...element.labels].map(label => cleanText(label.textContent || '')).filter(Boolean) : [];
    if (labels.length) return labels.join(' ');

    const labelledBy = textFromIds(element.getAttribute?.('aria-labelledby'), documentRef);
    if (labelledBy) return labelledBy;

    const aria = cleanText(element.getAttribute?.('aria-label') || '');
    if (aria) return aria;

    const wrappingLabel = element.closest?.('label');
    return cleanText(wrappingLabel?.textContent || '');
  }

  function controlKind(element) {
    const tag = element.tagName?.toLowerCase() || '';
    const role = normalize(element.getAttribute?.('role'));
    const ariaAutocomplete = normalize(element.getAttribute?.('aria-autocomplete'));
    if (role === 'combobox' || ariaAutocomplete) return 'autocomplete';
    if (tag === 'textarea') return 'textarea';
    if (tag === 'select') return element.multiple ? 'multi-select' : 'select';

    const type = normalize(element.getAttribute?.('type') || 'text');
    if (type === 'radio') return 'radio';
    if (type === 'checkbox') return 'checkbox';
    if (type === 'file') return 'file';
    if (type === 'number' || type === 'range') return 'number';
    if (type === 'date' || type === 'datetime local' || type === 'month') return 'date';
    if (type === 'email') return 'email';
    if (type === 'tel') return 'tel';
    if (type === 'url') return 'url';
    return 'text';
  }

  function optionList(element) {
    if (element instanceof HTMLSelectElement) {
      return [...element.options].map(option => ({
        value: cleanText(option.value, 300),
        label: cleanText(option.textContent || option.label || option.value, 300),
        disabled: Boolean(option.disabled),
      }));
    }

    if (element.getAttribute?.('role') === 'combobox') {
      const controls = element.getAttribute('aria-controls');
      const listbox = controls ? element.ownerDocument?.getElementById(controls) : null;
      if (listbox) {
        return [...listbox.querySelectorAll('[role="option"]')].slice(0, 100).map(option => ({
          value: cleanText(option.getAttribute('data-value') || option.getAttribute('value') || option.textContent || '', 300),
          label: cleanText(option.textContent || '', 300),
          disabled: option.getAttribute('aria-disabled') === 'true',
        }));
      }
    }

    return [];
  }

  function signalsFor(element, documentRef) {
    const label = explicitLabel(element, documentRef);
    const placeholder = cleanText(element.getAttribute?.('placeholder') || '');
    const ariaDescription = textFromIds(element.getAttribute?.('aria-describedby'), documentRef);
    const legend = fieldsetLegend(element);
    const group = nearestGroupText(element);
    const name = cleanText(element.getAttribute?.('name') || '');
    const id = cleanText(element.id || '');
    const autocomplete = cleanText(element.getAttribute?.('autocomplete') || '');

    return {
      label,
      placeholder,
      ariaDescription,
      legend,
      group,
      name,
      id,
      autocomplete,
      normalized: {
        label: normalize(label),
        placeholder: normalize(placeholder),
        ariaDescription: normalize(ariaDescription),
        legend: normalize(legend),
        group: normalize(group),
        name: normalize(name.replace(/[_\-\[\].]+/g, ' ')),
        id: normalize(id.replace(/[_\-\[\].]+/g, ' ')),
        autocomplete: normalize(autocomplete),
      },
    };
  }

  function termScore(haystack, term) {
    if (!haystack || !term) return 0;
    if (haystack === term) return 1;
    if (` ${haystack} `.includes(` ${term} `)) return 0.96;
    if (haystack.includes(term)) return 0.84;
    return 0;
  }

  function classify(element, documentRef = document) {
    const signals = signalsFor(element, documentRef);
    const candidates = [];

    for (const candidateRule of RULES) {
      let score = 0;
      const evidence = [];

      for (const autocompleteTerm of candidateRule.autocomplete) {
        if (signals.normalized.autocomplete === autocompleteTerm) {
          score = Math.max(score, 0.99);
          evidence.push(`autocomplete:${autocompleteTerm}`);
        }
      }

      const inputType = normalize(element.getAttribute?.('type') || '');
      const inputTypeKey = inputType === 'email' ? 'identity.email' : (inputType === 'tel' ? 'identity.phone' : null);
      if (inputTypeKey === candidateRule.key) {
        score = Math.max(score, 0.9);
        evidence.push(`input-type:${inputType}`);
      }

      const sources = [
        ['label', signals.normalized.label, 0.94],
        ['name', signals.normalized.name, 0.9],
        ['id', signals.normalized.id, 0.88],
        ['placeholder', signals.normalized.placeholder, 0.82],
        ['legend', signals.normalized.legend, 0.78],
        ['aria-description', signals.normalized.ariaDescription, 0.72],
        ['group', signals.normalized.group, 0.64],
      ];

      for (const term of candidateRule.terms) {
        for (const [sourceName, sourceValue, weight] of sources) {
          const matched = termScore(sourceValue, term);
          const weighted = matched * weight;
          if (weighted > score) score = weighted;
          if (weighted >= 0.55) evidence.push(`${sourceName}:${term}`);
        }
      }

      if (score >= 0.55) candidates.push({ key: candidateRule.key, confidence: round(score), evidence: [...new Set(evidence)] });
    }

    candidates.sort((left, right) => right.confidence - left.confidence);
    const best = candidates[0] || null;
    const second = candidates[1] || null;
    const ambiguous = Boolean(best && second && best.confidence - second.confidence < 0.08);
    const kind = controlKind(element);
    const questionText = (kind === 'radio' || kind === 'checkbox')
      ? (signals.legend || signals.group || signals.label || signals.placeholder)
      : (signals.label || signals.legend || signals.group || signals.placeholder);
    const looksLikeQuestion = /\?$/.test(questionText) || /^(why|what|when|where|how|do|are|have|can|would|will|pourquoi|quel|quelle|quand|ou|où|comment|avez|etes|êtes|pouvez|souhaitez)/i.test(questionText);

    return {
      key: ambiguous ? null : best?.key || null,
      confidence: ambiguous ? round(Math.max(0, (best?.confidence || 0) - 0.15)) : best?.confidence || 0,
      status: ambiguous ? 'ambiguous' : (best ? 'recognized' : (looksLikeQuestion ? 'question' : 'unknown')),
      questionText: looksLikeQuestion ? cleanText(questionText, 1000) : null,
      evidence: best?.evidence || [],
      candidates: candidates.slice(0, 3),
    };
  }

  function round(value) {
    return Math.round(value * 1000) / 1000;
  }

  function selectorHint(element) {
    if (element.id) return `#${cssEscape(element.id)}`;
    const name = element.getAttribute?.('name');
    if (name) return `${element.tagName.toLowerCase()}[name="${cssEscape(name)}"]`;
    return element.tagName?.toLowerCase() || 'field';
  }

  function cssEscape(value) {
    if (typeof CSS !== 'undefined' && CSS.escape) return CSS.escape(String(value));
    return String(value).replace(/["\\]/g, '\\$&');
  }

  function isEligible(element) {
    if (!element || element.disabled) return false;
    const tag = element.tagName?.toLowerCase();
    if (!['input', 'textarea', 'select'].includes(tag) && element.getAttribute?.('role') !== 'combobox') return false;
    if (tag === 'input' && IGNORED_INPUT_TYPES.has(normalize(element.getAttribute('type') || 'text'))) return false;
    return true;
  }

  function describe(element, index, documentRef = document) {
    const classification = classify(element, documentRef);
    const signals = signalsFor(element, documentRef);

    return {
      index,
      selectorHint: selectorHint(element),
      tagName: element.tagName?.toLowerCase() || '',
      inputType: normalize(element.getAttribute?.('type') || ''),
      controlKind: controlKind(element),
      required: Boolean(element.required || element.getAttribute?.('aria-required') === 'true'),
      disabled: Boolean(element.disabled),
      readOnly: Boolean(element.readOnly),
      multiple: Boolean(element.multiple),
      label: signals.label || signals.legend || null,
      placeholder: signals.placeholder || null,
      name: signals.name || null,
      id: signals.id || null,
      autocomplete: signals.autocomplete || null,
      options: optionList(element),
      classification,
    };
  }

  function detect(documentRef = document) {
    const elements = [...documentRef.querySelectorAll('input, textarea, select, [role="combobox"]')]
      .filter(isEligible);
    const fields = elements.map((element, index) => describe(element, index, documentRef));

    return {
      schemaVersion: 1,
      url: documentRef.location?.href || '',
      title: cleanText(documentRef.title || '', 500),
      fieldCount: fields.length,
      recognizedCount: fields.filter(field => field.classification.status === 'recognized').length,
      ambiguousCount: fields.filter(field => field.classification.status === 'ambiguous').length,
      questionCount: fields.filter(field => field.classification.status === 'question').length,
      unknownCount: fields.filter(field => field.classification.status === 'unknown').length,
      fields,
    };
  }

  return {
    classify,
    controlKind,
    detect,
    describe,
    normalize,
  };
});
