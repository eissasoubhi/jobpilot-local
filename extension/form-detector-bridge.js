chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type !== 'DETECT_FORM_FIELDS') return;

  const detector = globalThis.JobPilotAtsAwareDetector || globalThis.JobPilotFormDetector;
  if (!detector || typeof detector.detect !== 'function') {
    sendResponse({
      schemaVersion: 1,
      error: 'JobPilot form detector is unavailable.',
      fieldCount: 0,
      recognizedCount: 0,
      ambiguousCount: 0,
      questionCount: 0,
      unknownCount: 0,
      fields: [],
    });
    return;
  }

  sendResponse(detector.detect(document));
});
