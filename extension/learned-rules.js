const learning = globalThis.JobPilotCorrectionLearning;
const rulesRoot = document.getElementById('rules');
const count = document.getElementById('count');
const status = document.getElementById('status');
const clearDisabled = document.getElementById('clearDisabled');

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function setStatus(message) {
  status.textContent = message;
  if (message) setTimeout(() => { if (status.textContent === message) status.textContent = ''; }, 2500);
}

async function render() {
  const rules = await learning.loadRules();
  count.textContent = `${rules.length} règle${rules.length > 1 ? 's' : ''}`;

  if (!rules.length) {
    rulesRoot.innerHTML = '<div class="empty">Aucune correction approuvée pour le moment.</div>';
    return;
  }

  rulesRoot.innerHTML = rules.map((rule, index) => `
    <article class="rule" data-index="${index}">
      <div>
        <strong>${escapeHtml(rule.domain)}</strong>
        <small>${escapeHtml(rule.controlKind || 'champ')}</small>
      </div>
      <div>
        <strong>${escapeHtml(rule.label || rule.key)}</strong>
        <small>${escapeHtml(rule.key)}</small>
      </div>
      <label>
        <input data-role="value" type="text" value="${escapeHtml(rule.value)}" maxlength="1500" aria-label="Valeur pour ${escapeHtml(rule.label || rule.key)}">
      </label>
      <div class="actions">
        <label><input data-role="enabled" type="checkbox" ${rule.enabled === false ? '' : 'checked'}> Active</label>
        <button data-role="save" class="secondary" type="button">Enregistrer</button>
        <button data-role="delete" class="danger" type="button">Supprimer</button>
      </div>
    </article>
  `).join('');

  rulesRoot.querySelectorAll('.rule').forEach(node => {
    const index = Number(node.dataset.index);
    node.querySelector('[data-role="save"]').addEventListener('click', async () => {
      const current = await learning.loadRules();
      const rule = current[index];
      if (!rule) return render();
      const value = node.querySelector('[data-role="value"]').value.trim();
      if (!value) {
        setStatus('La valeur ne peut pas être vide.');
        return;
      }
      current[index] = {
        ...rule,
        value,
        enabled: node.querySelector('[data-role="enabled"]').checked,
        updatedAt: new Date().toISOString(),
      };
      await learning.saveRules(current);
      setStatus('Règle mise à jour.');
      await render();
    });

    node.querySelector('[data-role="delete"]').addEventListener('click', async () => {
      const current = await learning.loadRules();
      current.splice(index, 1);
      await learning.saveRules(current);
      setStatus('Règle supprimée.');
      await render();
    });
  });
}

clearDisabled.addEventListener('click', async () => {
  const rules = await learning.loadRules();
  const active = rules.filter(rule => rule.enabled !== false);
  await learning.saveRules(active);
  setStatus(`${rules.length - active.length} règle(s) désactivée(s) supprimée(s).`);
  await render();
});

void render();
