const API = 'http://localhost:8080/api';

async function apiJson(path, options) {
  const response = await fetch(`${API}${path}`, options);
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
  return data;
}

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

  if (message.type === 'GET_PROFILE') {
    Promise.all([
      apiJson('/profile'),
      apiJson('/settings'),
    ]).then(([profile, settings]) => sendResponse({ ok: true, profile, settings }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }

  if (message.type === 'GET_AUTOFILL_CONTEXT') {
    Promise.all([
      apiJson('/profile/autofill'),
      apiJson('/reusable-answers/resolved'),
    ]).then(([profile, answerPayload]) => sendResponse({
      ok: true,
      context: {
        schemaVersion: 1,
        profile,
        answers: Array.isArray(answerPayload.answers) ? answerPayload.answers : [],
        minimumConfidence: 0.72,
      },
    })).catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }
});
