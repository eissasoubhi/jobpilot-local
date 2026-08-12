chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type !== 'UPLOAD_APPLICATION_DOCUMENTS') return;

  const uploader = globalThis.JobPilotDocumentUploader;
  if (!uploader || typeof uploader.upload !== 'function') {
    sendResponse({ ok: false, error: 'JobPilot document uploader is unavailable.' });
    return;
  }

  uploader.upload(document, message.context || {})
    .then(report => sendResponse({ ok: true, report }))
    .catch(error => sendResponse({ ok: false, error: error instanceof Error ? error.message : String(error) }));

  return true;
});
