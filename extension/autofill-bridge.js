chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type !== 'AUTOFILL_GENERIC_PAGE') return;

  const engine = globalThis.JobPilotAutofillEngine;
  const detector = globalThis.JobPilotFormDetector;
  if (!engine || typeof engine.fill !== 'function' || !detector) {
    sendResponse({ ok: false, error: 'JobPilot autofill engine is unavailable.' });
    return;
  }

  engine.fill(document, message.context || {}, detector)
    .then(report => sendResponse({ ok: true, report }))
    .catch(error => sendResponse({ ok: false, error: error instanceof Error ? error.message : String(error) }));

  return true;
});
