(function registerJobPilotAutofillPing(root) {
  if (root.__jobpilotAutofillPingRegistered) return;
  root.__jobpilotAutofillPingRegistered = true;

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type !== 'JOBPILOT_AUTOFILL_PING') return;
    sendResponse({ ok: true, capability: 'autofill' });
  });
})(typeof globalThis !== 'undefined' ? globalThis : this);
