chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type !== 'AUTOFILL_GENERIC_PAGE') return;

  const engine = globalThis.JobPilotAutofillEngine;
  const detector = globalThis.JobPilotAtsAwareDetector || globalThis.JobPilotFormDetector;
  const learning = globalThis.JobPilotCorrectionLearning;
  if (!engine || typeof engine.fill !== 'function' || !detector) {
    sendResponse({ ok: false, error: 'JobPilot autofill engine is unavailable.' });
    return;
  }

  (async () => {
    let learned = { applied: 0, matched: 0, appliedFingerprints: [] };
    if (learning && typeof learning.loadRules === 'function' && typeof learning.applyRules === 'function') {
      const rules = await learning.loadRules();
      learned = learning.applyRules(document, detector, rules);
    }

    const report = await engine.fill(document, message.context || {}, detector);
    if (learning && typeof learning.watchCorrections === 'function') {
      globalThis.__jobPilotCorrectionLearningCleanup?.();
      globalThis.__jobPilotCorrectionLearningCleanup = learning.watchCorrections(
        document,
        report,
        globalThis.confirm,
        learned.appliedFingerprints || [],
      );
    }

    return { ...report, learnedRulesApplied: learned.applied };
  })()
    .then(report => sendResponse({ ok: true, report }))
    .catch(error => sendResponse({ ok: false, error: error instanceof Error ? error.message : String(error) }));

  return true;
});
