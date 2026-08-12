(function exposeJobPilotComplexAtsAdapters(root, factory) {
  const adapters = factory();
  root.JobPilotComplexAtsAdapters = adapters;
  if (typeof module !== 'undefined' && module.exports) module.exports = adapters;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotComplexAtsAdapters() {
  const IGNORED_INPUT_TYPES = new Set(['hidden', 'password', 'submit', 'button', 'reset', 'image']);

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function aliases(key, values) {
    return { key, values: values.map(normalize) };
  }

  const PLATFORMS = [
    {
      id: 'workday',
      label: 'Workday',
      hosts: [/(^|\.)myworkdayjobs\.com$/i],
      markers: [
        'form[action*="myworkdayjobs.com"]',
        'script[src*="myworkdayjobs.com"]',
        '[data-automation-id="jobPostingPage"]',
      ],
      aliases: [
        aliases('identity.firstName', [
          'legalNameSection_firstName',
          'legalNameSection.firstName',
          'firstName',
        ]),
        aliases('identity.lastName', [
          'legalNameSection_lastName',
          'legalNameSection.lastName',
          'lastName',
        ]),
        aliases('identity.email', [
          'email',
          'emailAddress',
          'emailSection_email',
        ]),
        aliases('identity.phone', [
          'phone-number',
          'phoneNumber',
          'phoneSection_phoneNumber',
        ]),
        aliases('address.line1', [
          'addressSection_addressLine1',
          'addressLine1',
        ]),
        aliases('address.city', [
          'addressSection_city',
          'city',
        ]),
        aliases('address.postalCode', [
          'addressSection_postalCode',
          'postalCode',
        ]),
        aliases('address.region', [
          'addressSection_region',
          'region',
          'state',
        ]),
        aliases('address.country', [
          'addressSection_countryRegion',
          'countryRegion',
          'country',
        ]),
      ],
    },
    {
      id: 'ashby',
      label: 'Ashby',
      hosts: [/(^|\.)ashbyhq\.com$/i],
      markers: [
        'form[action*="ashbyhq.com"]',
        'script[src*="ashbyhq.com"]',
        '[name="_systemfield_name"]',
        '[name="_systemfield_email"]',
        '[data-field-path^="_systemfield_"]',
      ],
      aliases: [
        aliases('identity.fullName', [
          '_systemfield_name',
          'systemfield_name',
        ]),
        aliases('identity.email', [
          '_systemfield_email',
          'systemfield_email',
        ]),
        aliases('identity.phone', [
          '_systemfield_phone',
          '_systemfield_phoneNumber',
          'systemfield_phone',
          'systemfield_phoneNumber',
        ]),
      ],
    },
  ];

  function hostname(locationLike) {
    try {
      if (locationLike?.hostname) return String(locationLike.hostname).toLowerCase();
      if (locationLike?.href) return new URL(locationLike.href).hostname.toLowerCase();
      if (typeof locationLike === 'string') return new URL(locationLike).hostname.toLowerCase();
    } catch (_) {
      return '';
    }
    return '';
  }

  function hasMarker(documentRef, selectors) {
    for (const selector of selectors) {
      try {
        if (documentRef.querySelector(selector)) return selector;
      } catch (_) {
        // Keep detection conservative if a selector is unsupported.
      }
    }
    return null;
  }

  function detectPlatform(documentRef = document, locationLike = documentRef.location) {
    const host = hostname(locationLike);
    const matches = [];

    for (const platform of PLATFORMS) {
      const hostMatch = platform.hosts.some(pattern => pattern.test(host));
      const marker = hasMarker(documentRef, platform.markers);
      if (!hostMatch && !marker) continue;
      matches.push({
        id: platform.id,
        label: platform.label,
        confidence: hostMatch ? (marker ? 1 : 0.98) : 0.9,
        reason: hostMatch && marker ? 'host+marker' : (hostMatch ? 'host' : `marker:${marker}`),
      });
    }

    matches.sort((left, right) => right.confidence - left.confidence);
    if (matches.length === 0) return { id: 'generic', label: 'Generic', confidence: 0, reason: 'no-complex-ats-marker' };
    if (matches.length > 1 && matches[0].confidence === matches[1].confidence) {
      return { id: 'generic', label: 'Generic', confidence: 0, reason: 'ambiguous-complex-ats-markers' };
    }
    return matches[0];
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

  function platformConfig(id) {
    return PLATFORMS.find(platform => platform.id === id) || null;
  }

  function identifiers(field, element) {
    return [
      field.name,
      field.id,
      element?.getAttribute?.('name'),
      element?.id,
      element?.getAttribute?.('data-automation-id'),
      element?.getAttribute?.('data-field-path'),
      element?.getAttribute?.('data-path'),
      element?.getAttribute?.('data-testid'),
    ]
      .map(normalize)
      .filter(Boolean);
  }

  function mappingFor(platform, field, element) {
    const candidates = identifiers(field, element);
    for (const mapping of platform.aliases) {
      const matched = candidates.find(candidate => mapping.values.includes(candidate));
      if (matched) return { key: mapping.key, identifier: matched };
    }
    return null;
  }

  function enhanceField(field, platform, element) {
    const mapping = mappingFor(platform, field, element);
    if (!mapping) return { ...field, atsComplex: { platform: platform.id, mapped: false } };

    const current = field.classification || {};
    const currentStrong = current.status === 'recognized' && Number(current.confidence || 0) >= 0.9;
    const sameKey = current.key === mapping.key;
    const marker = `ats:${platform.id}:${mapping.identifier}`;
    const evidence = [...new Set([...(current.evidence || []), marker])];

    if (currentStrong && current.key && !sameKey) {
      return {
        ...field,
        classification: {
          ...current,
          key: null,
          status: 'ambiguous',
          confidence: Math.min(Number(current.confidence || 0), 0.84),
          evidence,
          candidates: [
            ...(current.candidates || []),
            { key: mapping.key, confidence: 0.995, evidence: [marker] },
          ]
            .sort((left, right) => Number(right.confidence || 0) - Number(left.confidence || 0))
            .slice(0, 3),
        },
        atsComplex: { platform: platform.id, mapped: false, conflict: mapping.key },
      };
    }

    return {
      ...field,
      classification: {
        ...current,
        key: mapping.key,
        status: 'recognized',
        confidence: Math.max(Number(current.confidence || 0), 0.995),
        evidence,
        candidates: [
          { key: mapping.key, confidence: 0.995, evidence: [marker] },
          ...(current.candidates || []).filter(candidate => candidate.key !== mapping.key),
        ].slice(0, 3),
      },
      atsComplex: { platform: platform.id, mapped: true, identifier: mapping.identifier },
    };
  }

  function enhanceDetection(detection, documentRef = document, locationLike = documentRef.location) {
    const detected = detectPlatform(documentRef, locationLike);
    if (detected.id === 'generic') return detection;

    const platform = platformConfig(detected.id);
    if (!platform) return detection;

    // Do not let two unrelated ATS adapters claim the same page.
    if (detection.ats?.id && detection.ats.id !== 'generic' && detection.ats.id !== detected.id) {
      return {
        ...detection,
        ats: { id: 'generic', label: 'Generic', confidence: 0, reason: 'conflicting-ats-adapters' },
      };
    }

    const elements = eligibleElements(documentRef);
    const fields = (detection.fields || []).map((field, index) => enhanceField(field, platform, elements[index]));

    return {
      ...detection,
      ats: detected,
      recognizedCount: fields.filter(field => field.classification?.status === 'recognized').length,
      ambiguousCount: fields.filter(field => field.classification?.status === 'ambiguous').length,
      questionCount: fields.filter(field => field.classification?.status === 'question').length,
      unknownCount: fields.filter(field => field.classification?.status === 'unknown').length,
      fields,
    };
  }

  return {
    detectPlatform,
    enhanceDetection,
    platforms: PLATFORMS.map(({ id, label }) => ({ id, label })),
  };
});
