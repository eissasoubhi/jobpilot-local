(function exposeJobPilotQuestionAssistant(root, factory) {
  const assistant = factory(root);
  root.JobPilotQuestionAssistant = assistant;
  if (typeof module !== 'undefined' && module.exports) module.exports = assistant;

  if (root.chrome?.runtime?.sendMessage && root.document) {
    assistant.start(root.document);
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotQuestionAssistant(root) {
  const MARKER = 'data-jobpilot-question-assistant';
  let observer = null;
  let refreshTimer = null;

  function eligibleElements(documentRef) {
    const ignored = new Set(['hidden', 'password', 'submit', 'button', 'reset', 'image']);
    return [...documentRef.querySelectorAll('input, textarea, select, [role="combobox"]')].filter(element => {
      if (!element || element.disabled) return false;
      const tag = element.tagName?.toLowerCase();
      if (!['input', 'textarea', 'select'].includes(tag) && element.getAttribute?.('role') !== 'combobox') return false;
      if (tag === 'input' && ignored.has(String(element.getAttribute('type') || 'text').toLowerCase())) return false;
      return true;
    });
  }

  function isSupportedFreeText(field, element) {
    if (field?.classification?.status !== 'question' || !field?.classification?.questionText) return false;
    if (!['textarea', 'text'].includes(field.controlKind)) return false;
    if (!element || element.disabled || element.readOnly) return false;
    return true;
  }

  function language(documentRef) {
    return String(documentRef.documentElement?.lang || '').toLowerCase().startsWith('en') ? 'en' : 'fr';
  }

  function maxLength(element) {
    const configured = Number(element.maxLength);
    if (Number.isFinite(configured) && configured >= 80 && configured <= 1500) return configured;
    return 600;
  }

  function runtimeSuggestion(question, lang, limit) {
    return new Promise((resolve, reject) => {
      root.chrome.runtime.sendMessage({
        type: 'GET_APPLICATION_QUESTION_SUGGESTION',
        question,
        language: lang,
        maxLength: limit,
      }, response => {
        if (root.chrome.runtime.lastError) {
          reject(new Error(root.chrome.runtime.lastError.message));
          return;
        }
        if (!response?.ok) {
          reject(new Error(response?.error || 'Suggestion indisponible.'));
          return;
        }
        resolve(response.suggestion);
      });
    });
  }

  function setNativeValue(element, value) {
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    const setter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
    setter ? setter.call(element, value) : (element.value = value);
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function ensureStyles(documentRef) {
    if (documentRef.getElementById('jobpilot-question-assistant-style')) return;
    const style = documentRef.createElement('style');
    style.id = 'jobpilot-question-assistant-style';
    style.textContent = `
      .jobpilot-question-assistant { margin-top: 6px; font: 13px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
      .jobpilot-question-trigger { border: 1px solid #bbb; border-radius: 7px; background: #fff; color: #222; padding: 5px 9px; cursor: pointer; }
      .jobpilot-question-panel { margin-top: 7px; padding: 10px; border: 1px solid #d5d5d5; border-radius: 9px; background: #fff; color: #222; box-shadow: 0 2px 9px rgba(0,0,0,.08); }
      .jobpilot-question-panel textarea { box-sizing: border-box; width: 100%; min-height: 92px; margin-top: 7px; padding: 8px; border: 1px solid #bbb; border-radius: 6px; font: inherit; }
      .jobpilot-question-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
      .jobpilot-question-actions button { border: 1px solid #aaa; border-radius: 6px; padding: 5px 9px; background: #fff; cursor: pointer; }
      .jobpilot-question-meta { color: #666; font-size: 12px; margin-top: 5px; }
      .jobpilot-question-error { color: #9f1c1c; }
    `;
    documentRef.documentElement.appendChild(style);
  }

  function clearPanel(container) {
    container.querySelector('.jobpilot-question-panel')?.remove();
  }

  function renderMessage(container, text, isError = false) {
    clearPanel(container);
    const panel = container.ownerDocument.createElement('div');
    panel.className = 'jobpilot-question-panel';
    const message = container.ownerDocument.createElement('div');
    message.className = isError ? 'jobpilot-question-error' : '';
    message.textContent = text;
    panel.appendChild(message);
    container.appendChild(panel);
    return panel;
  }

  function renderSuggestion(container, element, question, lang, limit, result, requestSuggestion) {
    clearPanel(container);
    const documentRef = container.ownerDocument;
    const panel = documentRef.createElement('div');
    panel.className = 'jobpilot-question-panel';

    if (result?.status !== 'SUGGESTED' || !String(result?.suggestion || '').trim()) {
      const message = documentRef.createElement('div');
      message.textContent = result?.message || 'Aucune suggestion fiable disponible. Réponds manuellement à cette question.';
      panel.appendChild(message);

      const meta = documentRef.createElement('div');
      meta.className = 'jobpilot-question-meta';
      meta.textContent = result?.status ? `Statut : ${result.status}` : 'Réponse manuelle requise';
      panel.appendChild(meta);

      const actions = documentRef.createElement('div');
      actions.className = 'jobpilot-question-actions';
      const close = documentRef.createElement('button');
      close.type = 'button';
      close.textContent = 'Fermer';
      close.addEventListener('click', () => panel.remove());
      actions.appendChild(close);
      panel.appendChild(actions);
      container.appendChild(panel);
      return;
    }

    const textarea = documentRef.createElement('textarea');
    textarea.value = String(result.suggestion);
    textarea.maxLength = limit;
    textarea.setAttribute('aria-label', 'Suggestion JobPilot modifiable');
    panel.appendChild(textarea);

    const meta = documentRef.createElement('div');
    meta.className = 'jobpilot-question-meta';
    const confidence = Number(result.confidence);
    const confidenceLabel = Number.isFinite(confidence) ? ` · confiance ${Math.round(confidence * 100)} %` : '';
    meta.textContent = `Source : ${result.source === 'ai' ? 'IA JobPilot' : 'réponse enregistrée'}${confidenceLabel}. Vérifie avant insertion.`;
    panel.appendChild(meta);

    if (Array.isArray(result.usedFacts) && result.usedFacts.length > 0) {
      const facts = documentRef.createElement('div');
      facts.className = 'jobpilot-question-meta';
      facts.textContent = `Éléments utilisés : ${result.usedFacts.slice(0, 4).join(' · ')}`;
      panel.appendChild(facts);
    }

    const actions = documentRef.createElement('div');
    actions.className = 'jobpilot-question-actions';

    const insert = documentRef.createElement('button');
    insert.type = 'button';
    insert.textContent = 'Insérer';
    insert.addEventListener('click', () => {
      const value = textarea.value.trim();
      if (value !== '') setNativeValue(element, value);
      panel.remove();
    });

    const regenerate = documentRef.createElement('button');
    regenerate.type = 'button';
    regenerate.textContent = 'Régénérer';
    regenerate.addEventListener('click', () => void requestSuggestion(true));

    const close = documentRef.createElement('button');
    close.type = 'button';
    close.textContent = 'Fermer';
    close.addEventListener('click', () => panel.remove());

    actions.append(insert, regenerate, close);
    panel.appendChild(actions);
    container.appendChild(panel);
  }

  function attachField(element, field, documentRef, request = runtimeSuggestion) {
    if (!isSupportedFreeText(field, element)) return null;
    if (element.getAttribute(MARKER) === '1') return null;

    ensureStyles(documentRef);
    element.setAttribute(MARKER, '1');

    const container = documentRef.createElement('div');
    container.className = 'jobpilot-question-assistant';
    container.setAttribute('data-jobpilot-ui', 'question-assistant');

    const trigger = documentRef.createElement('button');
    trigger.type = 'button';
    trigger.className = 'jobpilot-question-trigger';
    trigger.textContent = '✨ Proposer avec JobPilot';
    container.appendChild(trigger);
    element.insertAdjacentElement('afterend', container);

    const question = String(field.classification.questionText);
    const lang = language(documentRef);
    const limit = maxLength(element);

    const requestSuggestion = async () => {
      renderMessage(container, lang === 'en' ? 'Generating a grounded suggestion…' : 'Génération d’une suggestion basée sur ton profil…');
      try {
        const result = await request(question, lang, limit);
        renderSuggestion(container, element, question, lang, limit, result, requestSuggestion);
      } catch (error) {
        renderMessage(container, error instanceof Error ? error.message : 'Suggestion indisponible.', true);
      }
    };

    trigger.addEventListener('click', () => void requestSuggestion());
    return container;
  }

  function enhance(documentRef = document, detectorOverride, request = runtimeSuggestion) {
    const detector = detectorOverride || root.JobPilotAtsAwareDetector || root.JobPilotFormDetector;
    if (!detector || typeof detector.detect !== 'function') return 0;

    const detection = detector.detect(documentRef);
    const elements = eligibleElements(documentRef);
    let attached = 0;
    for (const field of detection.fields || []) {
      const element = elements[field.index];
      if (attachField(element, field, documentRef, request)) attached += 1;
    }
    return attached;
  }

  function start(documentRef = document) {
    if (!documentRef?.documentElement) return;
    enhance(documentRef);
    if (observer) return;

    observer = new MutationObserver(() => {
      if (refreshTimer) clearTimeout(refreshTimer);
      refreshTimer = setTimeout(() => enhance(documentRef), 250);
    });
    observer.observe(documentRef.documentElement, { childList: true, subtree: true });
  }

  function stop() {
    observer?.disconnect();
    observer = null;
    if (refreshTimer) clearTimeout(refreshTimer);
    refreshTimer = null;
  }

  return {
    attachField,
    enhance,
    setNativeValue,
    start,
    stop,
  };
});
