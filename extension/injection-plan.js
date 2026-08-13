(function exposeJobPilotInjectionPlan(root, factory) {
  const plan = factory();
  root.JobPilotInjectionPlan = plan;
  if (typeof module !== 'undefined' && module.exports) module.exports = plan;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotInjectionPlan() {
  return {
    import: {
      pingType: 'JOBPILOT_IMPORT_PING',
      scripts: ['import-ready.js', 'content.js'],
    },
    autofill: {
      pingType: 'JOBPILOT_AUTOFILL_PING',
      scripts: [
        'form-detector.js',
        'ats-adapters.js',
        'complex-ats-adapters.js',
        'ats-aware-detector.js',
        'autofill-engine.js',
        'document-uploader.js',
        'correction-learning.js',
        'form-detector-bridge.js',
        'autofill-bridge.js',
        'document-upload-bridge.js',
        'question-assistant.js',
        'autofill-ready.js',
      ],
    },
  };
});
