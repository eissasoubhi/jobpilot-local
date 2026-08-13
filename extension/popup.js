const status = document.getElementById('status');
function show(text, type=''){ status.textContent=text; status.className=type; }
async function activeTab(){ const [tab]=await chrome.tabs.query({active:true,currentWindow:true}); return tab; }

function runtimeMessage(message) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(message, response => {
      if (chrome.runtime.lastError) return reject(new Error(chrome.runtime.lastError.message));
      resolve(response);
    });
  });
}

function tabMessage(tabId, message) {
  return new Promise((resolve, reject) => {
    chrome.tabs.sendMessage(tabId, message, response => {
      if (chrome.runtime.lastError) return reject(new Error(chrome.runtime.lastError.message));
      resolve(response);
    });
  });
}

function injectableTab(tab) {
  return Boolean(tab && Number.isInteger(tab.id) && /^https?:\/\//i.test(String(tab.url || '')));
}

async function capabilityAvailable(tabId, pingType) {
  try {
    const response = await tabMessage(tabId, { type: pingType });
    return response?.ok === true;
  } catch (_) {
    return false;
  }
}

async function ensureInjected(tab, mode) {
  if (!injectableTab(tab)) {
    throw new Error('Cette page ne permet pas l’injection JobPilot. Ouvre une page web HTTP/HTTPS de candidature.');
  }

  const plan = globalThis.JobPilotInjectionPlan?.[mode];
  if (!plan || !Array.isArray(plan.scripts) || !plan.pingType) {
    throw new Error('Plan d’injection JobPilot indisponible.');
  }

  if (await capabilityAvailable(tab.id, plan.pingType)) return;

  await chrome.scripting.executeScript({
    target: { tabId: tab.id },
    files: plan.scripts,
  });

  if (!(await capabilityAvailable(tab.id, plan.pingType))) {
    throw new Error('Les scripts JobPilot n’ont pas pu être activés sur cette page.');
  }
}

document.getElementById('import').addEventListener('click', async () => {
  show('Analyse de la page…');
  try {
    const tab = await activeTab();
    await ensureInjected(tab, 'import');

    const payload = await tabMessage(tab.id, {type:'EXTRACT_PAGE'});
    const result = await runtimeMessage({type:'IMPORT_JOB', payload});
    if (!result?.ok) return show(result?.error || 'Import impossible.', 'error');

    await runtimeMessage({
      type: 'REMEMBER_JOB_CONTEXT',
      tabId: tab.id,
      jobOfferId: result.data?.id,
      sourceUrl: payload?.url || tab.url || '',
    });

    show(`Offre importée — score ${result.data.score}/100. Le contexte est conservé pour cet onglet.`, 'success');
  } catch (error) {
    show(error instanceof Error ? error.message : 'Import impossible.', 'error');
  }
});

document.getElementById('autofill').addEventListener('click', async () => {
  show('Analyse du formulaire, du profil et des documents…');

  try {
    const tab = await activeTab();
    await ensureInjected(tab, 'autofill');

    const result = await runtimeMessage({type:'GET_AUTOFILL_CONTEXT', url:tab.url || ''});
    if (!result?.ok) return show(result?.error || 'Contexte Autofill indisponible.', 'error');

    const response = await tabMessage(tab.id, {type:'AUTOFILL_GENERIC_PAGE', context:result.context});
    if (!response?.ok) return show(response?.error || 'Remplissage impossible.', 'error');

    const fieldReport = response.report || {};
    let documentSummary = 'documents non liés';

    const documents = await runtimeMessage({
      type: 'GET_APPLICATION_DOCUMENT_CONTEXT',
      tabId: tab.id,
      url: tab.url || '',
    });

    if (documents?.ok && documents.context) {
      const upload = await tabMessage(tab.id, {
        type: 'UPLOAD_APPLICATION_DOCUMENTS',
        context: documents.context,
      });

      if (upload?.ok) {
        const documentReport = upload.report || {};
        documentSummary = `${documentReport.uploaded || 0} document(s) joint(s), ${documentReport.textFilled || 0} lettre(s) saisie(s), ${documentReport.review || 0} document(s) à vérifier`;
      } else {
        documentSummary = 'documents à vérifier';
      }
    }

    show(
      `${fieldReport.filled || 0} champ(s) rempli(s) · ${fieldReport.review || 0} à vérifier · ${fieldReport.preserved || 0} conservé(s) · ${documentSummary}. Vérifie avant d’envoyer.`,
      'success',
    );
  } catch (error) {
    show(error instanceof Error ? error.message : 'Remplissage impossible.', 'error');
  }
});
