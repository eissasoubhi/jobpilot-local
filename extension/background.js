const API = 'http://localhost:8080/api';
const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;
const volatileTabContexts = new Map();

async function apiJson(path, options) {
  const response = await fetch(`${API}${path}`, options);
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
  return data;
}

function tabContextKey(tabId) {
  return `jobpilot:tab:${tabId}`;
}

function sessionStorage() {
  return chrome.storage?.session && typeof chrome.storage.session.get === 'function'
    ? chrome.storage.session
    : null;
}

async function rememberTabContext(tabId, context) {
  if (!Number.isInteger(tabId) || tabId < 0) return;
  const value = {
    jobOfferId: Number(context?.jobOfferId) || null,
    sourceUrl: String(context?.sourceUrl || ''),
    rememberedAt: new Date().toISOString(),
  };
  const storage = sessionStorage();
  if (storage) {
    await storage.set({ [tabContextKey(tabId)]: value });
    return;
  }
  volatileTabContexts.set(tabId, value);
}

async function readTabContext(tabId) {
  if (!Number.isInteger(tabId) || tabId < 0) return null;
  const storage = sessionStorage();
  if (storage) {
    const key = tabContextKey(tabId);
    const stored = await storage.get(key);
    return stored?.[key] || null;
  }
  return volatileTabContexts.get(tabId) || null;
}

async function forgetTabContext(tabId) {
  if (!Number.isInteger(tabId) || tabId < 0) return;
  const storage = sessionStorage();
  if (storage) await storage.remove(tabContextKey(tabId));
  volatileTabContexts.delete(tabId);
}

function hostFromUrl(value) {
  try {
    const parsed = new URL(String(value || ''));
    if (!['http:', 'https:'].includes(parsed.protocol)) return '';
    return parsed.hostname.toLowerCase();
  } catch (_) {
    return '';
  }
}

async function autofillContext(message) {
  const host = hostFromUrl(message?.url);
  const correctionsPromise = host
    ? apiJson(`/autofill/corrections?host=${encodeURIComponent(host)}`)
    : Promise.resolve([]);

  const [profile, answerPayload, corrections] = await Promise.all([
    apiJson('/profile/autofill'),
    apiJson('/reusable-answers/resolved'),
    correctionsPromise,
  ]);

  return {
    schemaVersion: 1,
    profile,
    answers: Array.isArray(answerPayload.answers) ? answerPayload.answers : [],
    corrections: Array.isArray(corrections) ? corrections : [],
    minimumConfidence: 0.72,
  };
}

async function saveAutofillCorrection(message) {
  return apiJson('/autofill/corrections', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      host: String(message?.host || '').toLowerCase(),
      fieldFingerprint: String(message?.fieldFingerprint || ''),
      canonicalKey: String(message?.canonicalKey || ''),
      controlKind: String(message?.controlKind || ''),
      originalValue: String(message?.originalValue || ''),
      correctedValue: String(message?.correctedValue || ''),
    }),
  });
}

async function documentContext(message) {
  const tabId = Number(message?.tabId);
  const remembered = await readTabContext(tabId);
  const criteria = {
    applicationId: Number(message?.applicationId) || undefined,
    jobOfferId: Number(message?.jobOfferId) || Number(remembered?.jobOfferId) || undefined,
    url: String(message?.url || remembered?.sourceUrl || ''),
  };

  return apiJson('/extension/application-documents', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(criteria),
  });
}

async function questionSuggestion(message, sender) {
  const tabId = Number(message?.tabId) || Number(sender?.tab?.id);
  const remembered = await readTabContext(tabId);
  return apiJson('/extension/question-suggestion', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      applicationId: Number(message?.applicationId) || undefined,
      jobOfferId: Number(message?.jobOfferId) || Number(remembered?.jobOfferId) || undefined,
      url: String(message?.url || sender?.tab?.url || remembered?.sourceUrl || ''),
      question: String(message?.question || ''),
      language: String(message?.language || 'fr'),
      maxLength: Number(message?.maxLength) || 600,
    }),
  });
}

function documentFilename(response, fallback) {
  const disposition = response.headers.get('Content-Disposition') || '';
  const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
  if (encoded) {
    try { return decodeURIComponent(encoded); } catch (_) { /* fallback below */ }
  }
  const plain = disposition.match(/filename="?([^";]+)"?/i)?.[1];
  return plain || fallback || 'document';
}

async function fetchDocument(downloadUrl, fallbackFilename, fallbackMimeType) {
  const path = String(downloadUrl || '');
  if (!path.startsWith('/api/')) throw new Error('Chemin de document non autorisé.');

  const response = await fetch(`http://localhost:8080${path}`);
  if (!response.ok) throw new Error(`Téléchargement du document impossible (HTTP ${response.status}).`);

  const declaredLength = Number(response.headers.get('Content-Length') || 0);
  if (declaredLength > MAX_DOCUMENT_BYTES) throw new Error('Le document dépasse la taille maximale autorisée pour Autofill.');

  const buffer = await response.arrayBuffer();
  if (buffer.byteLength > MAX_DOCUMENT_BYTES) throw new Error('Le document dépasse la taille maximale autorisée pour Autofill.');

  return {
    filename: documentFilename(response, fallbackFilename),
    mimeType: response.headers.get('Content-Type')?.split(';')[0] || fallbackMimeType || 'application/octet-stream',
    bytes: Array.from(new Uint8Array(buffer)),
  };
}

chrome.tabs?.onRemoved?.addListener(tabId => {
  void forgetTabContext(tabId);
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'IMPORT_JOB') {
    apiJson('/extension/import-page', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(message.payload),
    }).then(data => sendResponse({ ok: true, data }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'REMEMBER_JOB_CONTEXT') {
    rememberTabContext(Number(message.tabId), {
      jobOfferId: message.jobOfferId,
      sourceUrl: message.sourceUrl,
    }).then(() => sendResponse({ ok: true }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'GET_PROFILE') {
    Promise.all([
      apiJson('/profile'),
      apiJson('/settings'),
    ]).then(([profile, settings]) => sendResponse({ ok: true, profile, settings }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'GET_AUTOFILL_CONTEXT') {
    autofillContext(message)
      .then(context => sendResponse({ ok: true, context }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'SAVE_AUTOFILL_CORRECTION') {
    saveAutofillCorrection(message)
      .then(correction => sendResponse({ ok: true, correction }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'GET_APPLICATION_DOCUMENT_CONTEXT') {
    documentContext(message)
      .then(context => sendResponse({ ok: true, context }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'FETCH_APPLICATION_DOCUMENT') {
    fetchDocument(message.downloadUrl, message.filename, message.mimeType)
      .then(document => sendResponse({ ok: true, document }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'GET_APPLICATION_QUESTION_SUGGESTION') {
    questionSuggestion(message, sender)
      .then(suggestion => sendResponse({ ok: true, suggestion }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }
});
