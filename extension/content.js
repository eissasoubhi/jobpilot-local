function visibleText() {
  const clone = document.body.cloneNode(true);
  clone.querySelectorAll('script,style,noscript,svg,nav,footer').forEach(el => el.remove());
  return (clone.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 60000);
}

function guessCompany() {
  const candidates = [
    document.querySelector('[data-company-name]')?.textContent,
    document.querySelector('.company-name')?.textContent,
    document.querySelector('[class*="company"]')?.textContent,
    document.querySelector('meta[property="og:site_name"]')?.content,
  ];
  return candidates.find(Boolean)?.trim().slice(0, 255) || '';
}

function guessLocation(text) {
  const match = text.match(/(?:Paris|Cergy|Lyon|Lille|Nantes|Bordeaux|Toulouse|Marseille|France|Île-de-France|Remote|Télétravail)[^,.|]{0,50}/i);
  return match?.[0]?.trim() || '';
}

function guessContract(text) {
  const match = text.match(/\b(CDI|CDD|Freelance|Portage salarial|Sous-traitance|Contract)\b/i);
  return match?.[1] || '';
}

function setNativeValue(element, value) {
  const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
  const setter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
  setter ? setter.call(element, value) : (element.value = value);
  element.dispatchEvent(new Event('input', { bubbles: true }));
  element.dispatchEvent(new Event('change', { bubbles: true }));
}

function matchField(element, terms) {
  const text = [element.name, element.id, element.placeholder, element.getAttribute('aria-label'), element.autocomplete,
    element.labels ? [...element.labels].map(label => label.textContent).join(' ') : ''].filter(Boolean).join(' ').toLowerCase();
  return terms.some(term => text.includes(term));
}

function autofill(profile) {
  const values = [
    [['full name','fullname','nom complet','name'], profile.fullName],
    [['first name','firstname','prénom'], profile.fullName?.split(' ')[0]],
    [['last name','lastname','nom de famille','surname'], profile.fullName?.split(' ').slice(1).join(' ')],
    [['email','e-mail','courriel'], profile.email],
    [['phone','telephone','téléphone','mobile'], profile.phone],
    [['city','ville'], profile.city],
    [['postal','zip','code postal'], profile.postalCode],
    [['linkedin'], profile.linkedinUrl],
    [['github','portfolio','site web','website'], profile.portfolioUrl],
  ];
  let filled = 0;
  document.querySelectorAll('input:not([type="hidden"]):not([type="file"]), textarea').forEach(element => {
    if (element.disabled || element.readOnly || element.value) return;
    for (const [terms, value] of values) {
      if (value && matchField(element, terms)) { setNativeValue(element, value); filled++; break; }
    }
  });
  return filled;
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'EXTRACT_PAGE') {
    const text = visibleText();
    sendResponse({
      url: location.href, title: document.querySelector('h1')?.textContent?.trim() || document.title,
      company: guessCompany(), location: guessLocation(text), contractType: guessContract(text), text,
    });
  }
  if (message.type === 'AUTOFILL_PAGE') sendResponse({ filled: autofill(message.profile) });
});
