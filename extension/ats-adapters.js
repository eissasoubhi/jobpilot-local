(function exposeJobPilotAtsAdapters(root, factory) {
  const adapters = factory();
  root.JobPilotAtsAdapters = adapters;
  if (typeof module !== 'undefined' && module.exports) module.exports = adapters;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotAtsAdapters() {
  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function normalizeIdentifier(value) {
    return normalize(String(value || '').replace(/[\[\]._-]+/g, ' '));
  }

  function alias(key, identifiers) {
    return { key, identifiers: identifiers.map(normalizeIdentifier) };
  }

  const PLATFORMS = [
    {
      id: 'smartrecruiters',
      label: 'SmartRecruiters',
      hostPatterns: [/(^|\.)smartrecruiters\.com$/i],
      markerSelectors: [
        'form[action*="smartrecruiters.com"]',
        'script[src*="smartrecruiters.com"]',
        'iframe[src*="smartrecruiters.com"]',
      ],
      aliases: [
        alias('identity.firstName', ['firstName', 'candidate.firstName', 'candidate_firstName']),
        alias('identity.lastName', ['lastName', 'candidate.lastName', 'candidate_lastName']),
        alias('identity.email', ['email', 'candidate.email']),
        alias('identity.phone', ['phoneNumber', 'phone', 'candidate.phoneNumber']),
        alias('professional.linkedinUrl', ['web.linkedin', 'linkedin', 'linkedinUrl']),
      ],
    },
    {
      id: 'greenhouse',
      label: 'Greenhouse',
      hostPatterns: [/(^|\.)greenhouse\.io$/i],
      markerSelectors: [
        'form[action*="greenhouse.io"]',
        'script[src*="greenhouse.io"]',
        'iframe[src*="greenhouse.io"]',
      ],
      aliases: [
        alias('identity.firstName', ['first_name']),
        alias('identity.lastName', ['last_name']),
        alias('identity.email', ['email']),
        alias('identity.phone', ['phone']),
        alias('address.line1', ['location']),
      ],
    },
    {
      id: 'lever',
      label: 'Lever',
      hostPatterns: [/(^|\.)lever\.co$/i],
      markerSelectors: [
        'form[action*="lever.co"]',
        'script[src*="lever.co"]',
        'iframe[src*="lever.co"]',
      ],
      aliases: [
        alias('identity.fullName', ['name']),
        alias('identity.email', ['email']),
        alias('identity.phone', ['phone']),
        alias('professional.linkedinUrl', ['urls.LinkedIn', 'urls[LinkedIn]', 'linkedin']),
        alias('professional.githubUrl', ['urls.GitHub', 'urls[GitHub]', 'github']),
        alias('professional.portfolioUrl', ['urls.Portfolio', 'urls[Portfolio]', 'urls.Website', 'urls[Website]', 'portfolio']),
      ],
    },
    {
      id: 'teamtailor',
      label: 'Teamtailor',
      hostPatterns: [/(^|\.)teamtailor\.com$/i],
      markerSelectors: [
        'meta[name="generator"][content*="Teamtailor" i]',
        'form[action*="teamtailor.com"]',
        'script[src*="teamtailor"]',
        'iframe[src*="teamtailor.com"]',
      ],
      aliases: [
        alias('identity.firstName', ['candidate.first_name', 'candidate.first-name', 'first_name']),
        alias('identity.lastName', ['candidate.last_name', 'candidate.last-name', 'last_name']),
        alias('identity.fullName', ['candidate.name', 'name']),
        alias('identity.email', ['candidate.email', 'email']),
        alias('identity.phone', ['candidate.phone', 'phone']),
        alias('professional.linkedinUrl', ['candidate.linkedin_url', 'linkedin_url', 'linkedin']),
      ],
    },
    {
      id: 'recruitee',
      label: 'Recruitee',
      hostPatterns: [/(^|\.)recruitee\.com$/i],
      markerSelectors: [
        'meta[name="generator"][content*="Recruitee" i]',
        'form[action*="recruitee.com"]',
        'script[src*="recruitee"]',
        'iframe[src*="recruitee.com"]',
      ],
      aliases: [
        alias('identity.fullName', ['candidate.name', 'candidate.full_name', 'full_name', 'name']),
        alias('identity.firstName', ['candidate.first_name', 'first_name']),
        alias('identity.lastName', ['candidate.last_name', 'last_name']),
        alias('identity.email', ['candidate.email', 'email']),
        alias('identity.phone', ['candidate.phone', 'phone']),
        alias('professional.currentJobTitle', ['candidate.professional_title', 'professional_title']),
        alias('professional.linkedinUrl', ['candidate.linkedin', 'linkedin']),
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
        // A browser without support for a selector extension should continue with
        // the remaining conservative markers instead of failing detection.
      }
    }
    return null;
  }

  function detectPlatform(documentRef = document, locationLike = documentRef.location) {
    const host = hostname(locationLike);
    const matches = [];

    for (const platform of PLATFORMS) {
      const hostMatch = platform.hostPatterns.some(pattern => pattern.test(host));
      const marker = hasMarker(documentRef, platform.markerSelectors);
      if (!hostMatch && !marker) continue;

      matches.push({
        id: platform.id,
        label: platform.label,
        confidence: hostMatch ? (marker ? 1 : 0.98) : 0.9,
        reason: hostMatch && marker ? 'host+marker' : (hostMatch ? 'host' : `marker:${marker}`),
      });
    }

    matches.sort((left, right) => right.confidence - left.confidence);
    if (matches.length === 0) return { id: 'generic', label: 'Generic', confidence: 0, reason: 'no-ats-marker' };
    if (matches.length > 1 && matches[0].confidence === matches[1].confidence) {
      return { id: 'generic', label: 'Generic', confidence: 0, reason: 'ambiguous-ats-markers' };
    }

    return matches[0];
  }

  function platformConfig(id) {
    return PLATFORMS.find(platform => platform.id === id) || null;
  }

  function aliasForField(platform, field) {
    const identifiers = [...new Set([field.name, field.id]
      .map(normalizeIdentifier)
      .filter(Boolean))];
    if (identifiers.length === 0) return null;

    for (const mapping of platform.aliases) {
      const identifier = identifiers.find(candidate => mapping.identifiers.includes(candidate));
      if (identifier) {
        return { key: mapping.key, identifier };
      }
    }

    return null;
  }

  function enhanceField(field, platform) {
    const mapping = aliasForField(platform, field);
    if (!mapping) return { ...field, ats: { platform: platform.id, mapped: false } };

    const current = field.classification || {};
    const strongestCandidate = Array.isArray(current.candidates) ? current.candidates[0] : null;
    const strongCurrentKey = current.status === 'recognized'
      && current.key
      && Number(current.confidence || 0) >= 0.9
      ? current.key
      : (current.status === 'ambiguous'
        && strongestCandidate?.key
        && Number(strongestCandidate.confidence || 0) >= 0.9
        ? strongestCandidate.key
        : null);
    const strongConflict = strongCurrentKey && strongCurrentKey !== mapping.key;
    const evidence = [...new Set([...(current.evidence || []), `ats:${platform.id}:${mapping.identifier}`])];

    if (strongConflict) {
      const candidates = [
        ...(current.candidates || []).filter(candidate => candidate.key !== mapping.key),
        { key: mapping.key, confidence: 0.995, evidence: [`ats:${platform.id}:${mapping.identifier}`] },
      ]
        .sort((left, right) => Number(right.confidence || 0) - Number(left.confidence || 0))
        .slice(0, 3);

      return {
        ...field,
        classification: {
          ...current,
          key: null,
          status: 'ambiguous',
          confidence: Math.min(Number(current.confidence || 0), 0.84),
          evidence,
          candidates,
        },
        ats: { platform: platform.id, mapped: false, conflict: mapping.key },
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
          { key: mapping.key, confidence: 0.995, evidence: [`ats:${platform.id}:${mapping.identifier}`] },
          ...(current.candidates || []).filter(candidate => candidate.key !== mapping.key),
        ].slice(0, 3),
      },
      ats: { platform: platform.id, mapped: true, identifier: mapping.identifier },
    };
  }

  function enhanceDetection(detection, documentRef = document, locationLike = documentRef.location) {
    const detected = detectPlatform(documentRef, locationLike);
    if (detected.id === 'generic') {
      return { ...detection, ats: detected };
    }

    const platform = platformConfig(detected.id);
    if (!platform) return { ...detection, ats: detected };

    const fields = (detection.fields || []).map(field => enhanceField(field, platform));

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
    normalizeIdentifier,
    platforms: PLATFORMS.map(({ id, label }) => ({ id, label })),
  };
});
