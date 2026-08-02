const API = 'http://localhost:8080/api';

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'IMPORT_JOB') {
    fetch(`${API}/extension/import-page`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(message.payload),
    }).then(async response => {
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
      sendResponse({ ok: true, data });
    }).catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }
  if (message.type === 'GET_PROFILE') {
    Promise.all([
      fetch(`${API}/profile`).then(r => r.json()),
      fetch(`${API}/settings`).then(r => r.json()),
    ]).then(([profile, settings]) => sendResponse({ ok: true, profile, settings }))
      .catch(error => sendResponse({ ok: false, error: error.message }));
    return true;
  }
});
