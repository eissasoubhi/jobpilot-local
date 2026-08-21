chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type !== 'AUTOFILL_GENERIC_PAGE') return;

  const engine = globalThis.JobPilotAutofillEngine;
  const detector = globalThis.JobPilotAtsAwareDetector || globalThis.JobPilotFormDetector;
  const safety = globalThis.JobPilotReviewSafety;
  if (!engine || typeof engine.fill !== 'function' || !detector) {
    sendResponse({ ok: false, error: 'JobPilot autofill engine is unavailable.' });
    return;
  }

  engine.fill(document, message.context || {}, detector)
    .then(async report => {
      const learning = globalThis.JobPilotCorrectionLearning;
      const enhancedReport = learning && typeof learning.applyAndTrack === 'function'
        ? learning.applyAndTrack(document, report, message.context || {}, engine)
        : report;
      const learnedCorrectionsApplied = Array.isArray(enhancedReport?.fields)
        ? enhancedReport.fields.filter(field => field?.learnedCorrectionApplied === true).length
        : 0;
      const finalReport = {
        ...enhancedReport,
        learnedCorrectionsApplied,
        learnedRulesApplied: learnedCorrectionsApplied,
      };

      if (safety && typeof safety.render === 'function') {
        finalReport.safetySummary = safety.render(document, finalReport);
        if (typeof safety.recordAudit === 'function') {
          await safety.recordAudit(finalReport, document.location?.href || '');
        }
      }

      return finalReport;
    })
    .then(report => sendResponse({ ok: true, report }))
    .catch(error => sendResponse({ ok: false, error: error instanceof Error ? error.message : String(error) }));

  return true;
});
