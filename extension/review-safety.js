(function exposeJobPilotReviewSafety(root, factory) {
  const review = factory();
  root.JobPilotReviewSafety = review;
  if (typeof module !== 'undefined' && module.exports) module.exports = review;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotReviewSafety() {
  const PANEL_ID = 'jobpilot-autofill-review-panel';
  const AUDIT_KEY = 'jobpilotAutofillAudit';
  const SENSITIVE_KEYS = new Set([
    'screening.workAuthorisation',
    'screening.sponsorship',
    'preferences.desiredSalary',
    'preferences.desiredTjm',
  ]);

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function isSensitive(field) {
    const key = field?.classification?.key || '';
    if (SENSITIVE_KEYS.has(key)) return true;
    const text = normalize([field?.label, field?.name, field?.id, field?.placeholder].filter(Boolean).join(' '));
    return /\b(salary|salaire|tjm|visa|sponsorship|work authorization|autorisation travail|gender|genre|religion|disability|handicap|health|sante|criminal|judiciaire|veteran|ethnicity|ethnicite|nationality|nationalite|age)\b/.test(text);
  }

  function confidenceLevel(field) {
    if (isSensitive(field)) return 'sensitive';
    if (field?.fillStatus === 'review' || field?.classification?.status === 'ambiguous') return 'review';
    const confidence = Number(field?.classification?.confidence ?? 0);
    if (field?.fillStatus === 'filled' && confidence >= 0.9) return 'high';
    if (field?.fillStatus === 'filled' && confidence >= 0.72) return 'medium';
    if (field?.fillStatus === 'preserved') return 'preserved';
    return 'review';
  }

  function summarize(report) {
    const fields = Array.isArray(report?.fields) ? report.fields : [];
    const summary = {
      high: 0,
      medium: 0,
      preserved: 0,
      review: 0,
      sensitive: 0,
      total: fields.length,
      canSubmitAutomatically: false,
    };

    for (const field of fields) {
      summary[confidenceLevel(field)] += 1;
    }

    return summary;
  }

  function eligibleElements(documentRef) {
    const ignored = new Set(['hidden', 'password', 'submit', 'button', 'reset', 'image', 'file']);
    return [...documentRef.querySelectorAll('input, textarea, select, [role="combobox"]')].filter(element => {
      if (!element || element.disabled) return false;
      const tag = element.tagName?.toLowerCase();
      if (!['input', 'textarea', 'select'].includes(tag) && element.getAttribute?.('role') !== 'combobox') return false;
      if (tag === 'input' && ignored.has(normalize(element.getAttribute('type') || 'text'))) return false;
      return true;
    });
  }

  function labelFor(field) {
    return String(field?.label || field?.placeholder || field?.name || field?.id || field?.classification?.key || 'Champ').trim();
  }

  function statusCopy(level) {
    return {
      high: 'Confiance forte',
      medium: 'À vérifier rapidement',
      preserved: 'Valeur conservée',
      review: 'Vérification requise',
      sensitive: 'Sensible — manuel',
    }[level] || 'Vérification requise';
  }

  function focusField(documentRef, index) {
    const element = eligibleElements(documentRef)[index];
    if (!element) return false;
    element.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
    element.focus?.({ preventScroll: true });
    const previousOutline = element.style?.outline || '';
    if (element.style) {
      element.style.outline = '3px solid #7c3aed';
      setTimeout(() => { element.style.outline = previousOutline; }, 1800);
    }
    return true;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function render(documentRef, report) {
    documentRef.getElementById(PANEL_ID)?.remove();
    const summary = summarize(report);
    const fields = Array.isArray(report?.fields) ? report.fields : [];

    const host = documentRef.createElement('aside');
    host.id = PANEL_ID;
    host.setAttribute('aria-label', 'Vérification Autofill JobPilot');
    host.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:2147483647;width:min(420px,calc(100vw - 36px));max-height:min(72vh,680px);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;';
    const shadow = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;

    const priority = { sensitive: 0, review: 1, medium: 2 };
    const rows = fields
      .map((field, index) => ({ field, index, level: confidenceLevel(field) }))
      .filter(item => ['review', 'sensitive', 'medium'].includes(item.level))
      .sort((left, right) => (priority[left.level] ?? 99) - (priority[right.level] ?? 99));

    const style = documentRef.createElement('style');
    style.textContent = `
      *{box-sizing:border-box}.panel{overflow:hidden;border:1px solid #d9dce4;border-radius:16px;background:#fff;color:#20293a;box-shadow:0 18px 50px rgba(24,32,52,.22)}
      header{display:flex;justify-content:space-between;gap:12px;padding:16px 16px 12px;border-bottom:1px solid #eef0f4}.eyebrow{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6655c5}h2{margin:3px 0 0;font-size:17px}.close{border:0;background:#f2f4f7;border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:18px}
      .notice{margin:12px 16px;padding:10px 12px;border-radius:10px;background:#f7f5ff;color:#544a85;font-size:11px;line-height:1.45}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;padding:0 16px 12px}.metric{padding:8px;border:1px solid #eceef2;border-radius:9px}.metric strong{display:block;font-size:16px}.metric span{font-size:9px;color:#6c7586}
      .list{overflow:auto;max-height:360px;border-top:1px solid #eef0f4}.row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:11px 16px;border-bottom:1px solid #f0f1f4}.row strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px}.row small{display:block;margin-top:3px;font-size:9px}.review small{color:#a15c00}.sensitive small{color:#b42318}.medium small{color:#5f6fa8}.row button{align-self:center;border:1px solid #d8dce5;background:#fff;border-radius:8px;padding:6px 8px;cursor:pointer;font-size:10px;font-weight:700}.empty{padding:16px;color:#667085;font-size:11px}.footer{padding:11px 16px;color:#667085;font-size:10px;line-height:1.45;background:#fafbfc}
      @media(max-width:560px){.metrics{grid-template-columns:1fr 1fr}.panel{border-radius:12px}}
    `;

    const panel = documentRef.createElement('div');
    panel.className = 'panel';
    panel.innerHTML = `
      <header><div><span class="eyebrow">JobPilot Autofill</span><h2>Vérifie avant d’envoyer</h2></div><button class="close" type="button" aria-label="Fermer">×</button></header>
      <div class="notice">JobPilot ne clique jamais sur le bouton final de candidature. Les champs sensibles restent manuels et les éléments ci-dessous méritent une vérification avant envoi.</div>
      <div class="metrics">
        <div class="metric"><strong>${summary.high}</strong><span>forte confiance</span></div>
        <div class="metric"><strong>${summary.medium}</strong><span>à vérifier</span></div>
        <div class="metric"><strong>${summary.review}</strong><span>revue requise</span></div>
        <div class="metric"><strong>${summary.sensitive}</strong><span>sensibles</span></div>
      </div>
      <div class="list">${rows.length === 0 ? '<div class="empty">Aucun champ à risque détecté. Vérifie quand même le formulaire avant l’envoi manuel.</div>' : rows.map(item => `
        <div class="row ${item.level}" data-index="${item.index}"><div><strong>${escapeHtml(labelFor(item.field))}</strong><small>${statusCopy(item.level)}${item.field?.fillReason ? ` · ${escapeHtml(item.field.fillReason)}` : ''}</small></div><button type="button">Voir le champ</button></div>
      `).join('')}</div>
      <div class="footer">Journal local : uniquement des compteurs et statuts techniques, jamais les valeurs saisies, mots de passe, CV ou réponses sensibles.</div>
    `;

    shadow.append(style, panel);
    panel.querySelector('.close')?.addEventListener('click', () => host.remove());
    panel.querySelectorAll('.row button').forEach(button => {
      button.addEventListener('click', () => {
        const row = button.closest('.row');
        focusField(documentRef, Number(row?.getAttribute('data-index')));
      });
    });
    documentRef.body.appendChild(host);
    return summary;
  }

  async function recordAudit(report, url = globalThis.location?.href || '') {
    const storage = globalThis.chrome?.storage?.local;
    if (!storage) return false;
    const summary = summarize(report);
    const existing = await storage.get(AUDIT_KEY);
    const entries = Array.isArray(existing?.[AUDIT_KEY]) ? existing[AUDIT_KEY] : [];
    entries.unshift({
      at: new Date().toISOString(),
      domain: (() => { try { return new URL(url).hostname; } catch { return ''; } })(),
      detected: Number(report?.detected || 0),
      filled: Number(report?.filled || 0),
      preserved: Number(report?.preserved || 0),
      review: Number(report?.review || 0),
      skipped: Number(report?.skipped || 0),
      highConfidence: summary.high,
      mediumConfidence: summary.medium,
      sensitive: summary.sensitive,
      learnedRulesApplied: Number(report?.learnedRulesApplied || 0),
    });
    await storage.set({ [AUDIT_KEY]: entries.slice(0, 100) });
    return true;
  }

  return {
    AUDIT_KEY,
    PANEL_ID,
    confidenceLevel,
    focusField,
    isSensitive,
    recordAudit,
    render,
    summarize,
  };
});