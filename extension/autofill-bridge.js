chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type !== 'AUTOFILL_GENERIC_PAGE') return;

  const engine = globalThis.JobPilotAutofillEngine;
  const detector = globalThis.JobPilotAtsAwareDetector || globalThis.JobPilotFormDetector;
  if (!engine || typeof engine.fill !== 'function' || !detector) {
    sendResponse({ ok: false, error: 'JobPilot autofill engine is unavailable.' });
    return;
  }

  engine.fill(document, message.context || {}, detector)
    .then(report => {
      const learning = globalThis.JobPilotCorrectionLearning;
      const enhancedReport = learning && typeof learning.applyAndTrack === 'function'
        ? learning.applyAndTrack(document, report, message.context || {}, engine)
        : report;
      sendResponse({ ok: true, report: enhancedReport });
    })
    .catch(error => sendResponse({ ok: false, error: error instanceof Error ? error.message : String(error) }));

  return true;
});
