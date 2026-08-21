const hostForm = document.getElementById('host-form');
const hostInput = document.getElementById('host');
const status = document.getElementById('status');
const count = document.getElementById('count');
const empty = document.getElementById('empty');
const list = document.getElementById('corrections');
const template = document.getElementById('correction-template');

let activeHost = '';
let corrections = [];

function show(text, type = '') {
  status.textContent = text;
  status.className = type;
}

function normalizeHost(value) {
  const raw = String(value || '').trim().toLowerCase();
  if (!raw) return '';
  try {
    const url = new URL(raw.includes('://') ? raw : `https://${raw}`);
    return ['http:', 'https:'].includes(url.protocol) ? url.hostname.toLowerCase() : '';
  } catch (_) {
    return '';
  }
}

function runtimeMessage(message) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(message, response => {
      if (chrome.runtime.lastError) return reject(new Error(chrome.runtime.lastError.message));
      if (!response?.ok) return reject(new Error(response?.error || 'Action impossible.'));
      resolve(response);
    });
  });
}

function correctionLabel(correction) {
  return String(correction?.canonicalKey || 'Champ reconnu');
}

function render() {
  list.replaceChildren();
  count.textContent = corrections.length ? `${corrections.length} correction(s)` : '';
  empty.hidden = corrections.length !== 0;

  for (const correction of corrections) {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector('.correction');
    const toggle = fragment.querySelector('[data-toggle]');
    const remove = fragment.querySelector('[data-delete]');

    fragment.querySelector('[data-key]').textContent = correctionLabel(correction);
    fragment.querySelector('[data-kind]').textContent = `${correction.controlKind || 'contrôle'} · ${correction.enabled ? 'active' : 'désactivée'}`;
    fragment.querySelector('[data-value]').textContent = String(correction.correctedValue || '');
    toggle.textContent = correction.enabled ? 'Désactiver' : 'Réactiver';

    toggle.addEventListener('click', async () => {
      toggle.disabled = true;
      remove.disabled = true;
      try {
        const response = await runtimeMessage({
          type: 'SET_AUTOFILL_CORRECTION_ENABLED',
          id: correction.id,
          enabled: !correction.enabled,
        });
        corrections = corrections.map(item => item.id === correction.id ? response.correction : item);
        render();
        show('Correction mise à jour.', 'success');
      } catch (error) {
        toggle.disabled = false;
        remove.disabled = false;
        show(error instanceof Error ? error.message : 'Mise à jour impossible.', 'error');
      }
    });

    remove.addEventListener('click', async () => {
      if (!confirm(`Supprimer la correction « ${correctionLabel(correction)} » ?`)) return;
      toggle.disabled = true;
      remove.disabled = true;
      try {
        await runtimeMessage({ type: 'DELETE_AUTOFILL_CORRECTION', id: correction.id });
        corrections = corrections.filter(item => item.id !== correction.id);
        render();
        show('Correction supprimée.', 'success');
      } catch (error) {
        toggle.disabled = false;
        remove.disabled = false;
        show(error instanceof Error ? error.message : 'Suppression impossible.', 'error');
      }
    });

    row.dataset.correctionId = String(correction.id || '');
    list.append(fragment);
  }
}

async function loadCorrections(host) {
  show('Chargement…');
  const response = await runtimeMessage({ type: 'LIST_AUTOFILL_CORRECTIONS', host });
  activeHost = host;
  corrections = Array.isArray(response.corrections) ? response.corrections : [];
  hostInput.value = activeHost;
  render();
  show(corrections.length ? 'Corrections chargées.' : 'Aucune correction pour ce domaine.', 'success');
}

hostForm.addEventListener('submit', async event => {
  event.preventDefault();
  const host = normalizeHost(hostInput.value);
  if (!host) {
    show('Saisis un domaine ou une URL HTTP/HTTPS valide.', 'error');
    return;
  }

  try {
    await loadCorrections(host);
  } catch (error) {
    activeHost = '';
    corrections = [];
    render();
    show(error instanceof Error ? error.message : 'Chargement impossible.', 'error');
  }
});
