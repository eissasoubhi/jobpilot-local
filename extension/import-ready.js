(function registerJobPilotImportPing(root) {
  if (root.__jobpilotImportPingRegistered) return;
  root.__jobpilotImportPingRegistered = true;

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type !== 'JOBPILOT_IMPORT_PING') return;
    sendResponse({ ok: true, capability: 'import' });
  });
})(typeof globalThis !== 'undefined' ? globalThis : this);
