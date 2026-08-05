function cleanText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function visibleText(root = document.body) {
  const clone = root.cloneNode(true);
  clone.querySelectorAll('script,style,noscript,svg,nav,footer,header,[aria-hidden="true"]').forEach(element => element.remove());
  return cleanText(clone.innerText || '').slice(0, 60000);
}

function findJobPosting(value) {
  if (!value || typeof value !== 'object') return null;
  if (Array.isArray(value)) {
    for (const item of value) {
      const found = findJobPosting(item);
      if (found) return found;
    }
    return null;
  }

  const type = value['@type'];
  if (type === 'JobPosting' || (Array.isArray(type) && type.includes('JobPosting'))) return value;

  for (const child of Object.values(value)) {
    const found = findJobPosting(child);
    if (found) return found;
  }

  return null;
}

function jobPostingSchema() {
  for (const script of document.querySelectorAll('script[type="application/ld+json"]')) {
    try {
      const found = findJobPosting(JSON.parse(script.textContent || 'null'));
      if (found) return found;
    } catch (_) {
      // A malformed analytics block must not prevent the visible-page fallback.
    }
  }
  return null;
}

function htmlToText(value) {
  if (!value) return '';
  const documentFragment = new DOMParser().parseFromString(String(value), 'text/html');
  return cleanText(documentFragment.body?.innerText || documentFragment.body?.textContent || '');
}

function firstString(value) {
  if (typeof value === 'string') return cleanText(value);
  if (Array.isArray(value)) {
    for (const item of value) {
      const found = firstString(item);
      if (found) return found;
    }
  }
  return '';
}

function schemaCompany(schema) {
  const organization = schema?.hiringOrganization;
  if (typeof organization === 'string') return cleanText(organization);
  if (organization && typeof organization === 'object') return cleanText(organization.name || organization.legalName || '');
  return '';
}

function schemaLocation(schema) {
  const locations = Array.isArray(schema?.jobLocation) ? schema.jobLocation : [schema?.jobLocation];
  for (const location of locations) {
    const address = location?.address || location;
    if (!address || typeof address !== 'object') continue;
    const parts = [address.addressLocality, address.addressRegion, address.addressCountry]
      .map(cleanText)
      .filter(Boolean);
    if (parts.length) return [...new Set(parts)].join(', ');
  }

  return cleanText(schema?.applicantLocationRequirements?.name || '');
}

function normalizedContract(value, pageText = '') {
  const text = cleanText(Array.isArray(value) ? value.join(' ') : value || pageText);
  if (/freelance|contractor|ind[ée]pendant|mission freelance/i.test(text)) return 'Freelance';
  if (/\bcdd\b|fixed[ -]?term|temporary/i.test(text)) return 'CDD';
  if (/portage salarial/i.test(text)) return 'Portage salarial';
  if (/sous[ -]?traitance/i.test(text)) return 'Sous-traitance';
  if (/\bcdi\b|full[ -]?time|permanent|offre d['’]emploi/i.test(text)) return 'CDI';
  return '';
}

function guessWorkMode(text, schema) {
  if (/TELECOMMUTE/i.test(cleanText(schema?.jobLocationType))) return 'Télétravail';
  if (/t[ée]l[ée]travail\s+(?:partiel|hybride)|hybride|hybrid/i.test(text)) return 'Hybride';
  if (/100\s*%\s*(?:remote|t[ée]l[ée]travail)|full(?:y)? remote|t[ée]l[ée]travail complet/i.test(text)) return 'Télétravail';
  if (/sur site|on[ -]?site|pr[ée]sence sur site/i.test(text)) return 'Sur site';
  return '';
}

function numberValue(value) {
  const normalized = cleanText(value).replace(/[\s  ]/g, '').replace(',', '.');
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? Math.round(parsed) : null;
}

function schemaCompensation(schema) {
  const baseSalary = schema?.baseSalary;
  const value = baseSalary?.value || baseSalary;
  if (!value || typeof value !== 'object') return {};

  const unit = cleanText(value.unitText || baseSalary?.unitText).toUpperCase();
  const min = numberValue(value.minValue ?? value.value);
  const max = numberValue(value.maxValue ?? value.value);

  if (unit.includes('DAY') || unit.includes('JOUR')) {
    return { tjmMin: min, tjmMax: max };
  }
  if (unit.includes('YEAR') || unit.includes('AN')) {
    return { salaryMin: min, salaryMax: max };
  }
  return {};
}

function textCompensation(text) {
  const result = {};
  const tjm = text.match(/(?:TJM\s*)?([0-9][0-9\s  ]{1,5})(?:\s*[-–]\s*([0-9][0-9\s  ]{1,5}))?\s*€\s*(?:\/|⁄)?\s*(?:j|jour)/i);
  if (tjm) {
    result.tjmMin = numberValue(tjm[1]);
    result.tjmMax = numberValue(tjm[2] || tjm[1]);
  }

  const salaryK = text.match(/([0-9]{2,3})\s*k(?:\s*[-–]\s*([0-9]{2,3})\s*k)?\s*€?\s*(?:\/|⁄)?\s*(?:an|annuel)/i);
  if (salaryK) {
    result.salaryMin = Number(salaryK[1]) * 1000;
    result.salaryMax = Number(salaryK[2] || salaryK[1]) * 1000;
  } else {
    const salary = text.match(/([0-9][0-9\s  ]{3,8})(?:\s*[-–]\s*([0-9][0-9\s  ]{3,8}))?\s*€\s*(?:\/|⁄)?\s*(?:an|annuel)/i);
    if (salary) {
      result.salaryMin = numberValue(salary[1]);
      result.salaryMax = numberValue(salary[2] || salary[1]);
    }
  }

  return result;
}

function isoDate(value) {
  const text = cleanText(value);
  if (!text) return null;

  const french = text.match(/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/);
  if (french) return `${french[3]}-${french[2].padStart(2, '0')}-${french[1].padStart(2, '0')}`;

  const date = new Date(text);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

function platform() {
  const host = location.hostname.toLowerCase().replace(/^www\./, '');
  const known = [
    [/free-work\.com$/, 'free-work', 'Free-Work'],
    [/linkedin\.com$/, 'linkedin', 'LinkedIn'],
    [/indeed\./, 'indeed', 'Indeed'],
    [/apec\.fr$/, 'apec', 'APEC'],
    [/hellowork\.com$/, 'hellowork', 'Hellowork'],
    [/welcometothejungle\.com$/, 'welcome-to-the-jungle', 'Welcome to the Jungle'],
    [/francetravail\.fr$/, 'france-travail', 'France Travail'],
  ];

  for (const [pattern, code, name] of known) {
    if (pattern.test(host)) return { code, name };
  }

  return { code: host.replace(/[^a-z0-9]+/g, '-'), name: host };
}

function externalId(sourceCode) {
  const canonical = document.querySelector('link[rel="canonical"]')?.href || location.href;
  try {
    const url = new URL(canonical, location.href);
    const lastSegment = url.pathname.split('/').filter(Boolean).at(-1);
    if (lastSegment) return `${sourceCode}-${lastSegment}`.slice(0, 180);
  } catch (_) {
    // The backend will derive a stable URL hash.
  }
  return '';
}

function nearbyCompany(locationHeading) {
  let sibling = locationHeading?.nextElementSibling || null;
  for (let index = 0; sibling && index < 5; index += 1, sibling = sibling.nextElementSibling) {
    const candidate = cleanText(sibling.innerText || sibling.textContent || '');
    if (candidate && candidate.length <= 120 && !/^(PHP|Symfony|Signaler|Publi[ée]e|Partager|Postuler)/i.test(candidate)) {
      return candidate;
    }
  }
  return '';
}

function guessCompany(schema, locationHeading) {
  const candidates = [
    schemaCompany(schema),
    document.querySelector('[itemprop="hiringOrganization"] [itemprop="name"]')?.textContent,
    document.querySelector('[itemprop="hiringOrganization"]')?.textContent,
    document.querySelector('[data-company-name]')?.textContent,
    document.querySelector('[data-testid*="company" i]')?.textContent,
    document.querySelector('.company-name')?.textContent,
    document.querySelector('[class*="company" i]')?.textContent,
    nearbyCompany(locationHeading),
  ];

  return cleanText(candidates.find(value => cleanText(value)) || '').slice(0, 255);
}

function guessLocation(text, schema, locationHeading) {
  const structured = schemaLocation(schema);
  if (structured) return structured;

  const heading = cleanText(locationHeading?.textContent || '');
  if (heading && heading.length <= 150 && !/profil recherch[ée]|environnement|d[ée]couvrir/i.test(heading)) return heading;

  const match = text.match(/(?:Paris|Cergy|Lyon|Lille|Nantes|Bordeaux|Toulouse|Marseille|Nice|Rennes|Montpellier|Strasbourg|Grenoble|Rouen|France|Île-de-France|Remote|Télétravail)[^,.|]{0,60}/i);
  return cleanText(match?.[0] || '');
}

function freeWorkTitle(rawTitle) {
  return cleanText(rawTitle)
    .replace(/^(?:Mission freelance|Offre d['’]emploi)\s+/i, '')
    .replace(/\s+\|\s+Free-?work.*$/i, '');
}

function extractPage() {
  const schema = jobPostingSchema();
  const pageText = visibleText();
  const source = platform();
  const h1 = document.querySelector('h1');
  const locationHeading = h1?.parentElement?.querySelector('h2') || document.querySelector('main h2, article h2');
  const isFreeWork = source.code === 'free-work';

  const schemaTitle = cleanText(schema?.title || '');
  const rawTitle = schemaTitle || cleanText(h1?.textContent || document.title);
  const title = isFreeWork ? freeWorkTitle(rawTitle) : rawTitle;
  const description = htmlToText(schema?.description) || pageText;
  const contractType = normalizedContract(schema?.employmentType, `${rawTitle} ${pageText}`);
  const compensation = {
    ...textCompensation(pageText),
    ...schemaCompensation(schema),
  };
  const publishedAt = isoDate(schema?.datePosted)
    || isoDate(pageText.match(/Publi[ée]e\s+le\s+(\d{1,2}\/\d{1,2}\/\d{4})/i)?.[1]);

  return {
    url: document.querySelector('link[rel="canonical"]')?.href || location.href,
    source: source.name,
    sourceCode: source.code,
    externalId: externalId(source.code),
    title,
    company: guessCompany(schema, locationHeading),
    location: guessLocation(pageText, schema, locationHeading),
    contractType,
    workMode: guessWorkMode(pageText, schema),
    text: description.slice(0, 60000),
    description: description.slice(0, 60000),
    publishedAt,
    extractionMethod: schema ? 'job-posting-json-ld' : (isFreeWork ? 'free-work-visible-page' : 'visible-page'),
    ...compensation,
  };
}

function setNativeValue(element, value) {
  const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
  const setter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
  setter ? setter.call(element, value) : (element.value = value);
  element.dispatchEvent(new Event('input', { bubbles: true }));
  element.dispatchEvent(new Event('change', { bubbles: true }));
}

function matchField(element, terms) {
  const text = [
    element.name,
    element.id,
    element.placeholder,
    element.getAttribute('aria-label'),
    element.autocomplete,
    element.labels ? [...element.labels].map(label => label.textContent).join(' ') : '',
  ].filter(Boolean).join(' ').toLowerCase();

  return terms.some(term => text.includes(term));
}

function autofill(profile) {
  const values = [
    [['full name', 'fullname', 'nom complet', 'name'], profile.fullName],
    [['first name', 'firstname', 'prénom'], profile.fullName?.split(' ')[0]],
    [['last name', 'lastname', 'nom de famille', 'surname'], profile.fullName?.split(' ').slice(1).join(' ')],
    [['email', 'e-mail', 'courriel'], profile.email],
    [['phone', 'telephone', 'téléphone', 'mobile'], profile.phone],
    [['city', 'ville'], profile.city],
    [['postal', 'zip', 'code postal'], profile.postalCode],
    [['linkedin'], profile.linkedinUrl],
    [['github', 'portfolio', 'site web', 'website'], profile.portfolioUrl],
  ];
  let filled = 0;

  document.querySelectorAll('input:not([type="hidden"]):not([type="file"]), textarea').forEach(element => {
    if (element.disabled || element.readOnly || element.value) return;
    for (const [terms, value] of values) {
      if (value && matchField(element, terms)) {
        setNativeValue(element, value);
        filled += 1;
        break;
      }
    }
  });

  return filled;
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'EXTRACT_PAGE') sendResponse(extractPage());
  if (message.type === 'AUTOFILL_PAGE') sendResponse({ filled: autofill(message.profile) });
});
