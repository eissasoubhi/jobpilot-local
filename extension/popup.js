const status = document.getElementById('status');
function show(text, type=''){ status.textContent=text; status.className=type; }
async function activeTab(){ const [tab]=await chrome.tabs.query({active:true,currentWindow:true}); return tab; }

document.getElementById('import').addEventListener('click', async () => {
  show('Analyse de la page…');
  const tab = await activeTab();
  chrome.tabs.sendMessage(tab.id, {type:'EXTRACT_PAGE'}, payload => {
    if (chrome.runtime.lastError) return show('Impossible de lire cette page.', 'error');
    chrome.runtime.sendMessage({type:'IMPORT_JOB', payload}, result => {
      if (!result?.ok) return show(result?.error || 'Import impossible.', 'error');
      show(`Offre importée — score ${result.data.score}/100.`, 'success');
    });
  });
});

document.getElementById('autofill').addEventListener('click', async () => {
  show('Analyse du formulaire et chargement du profil…');
  chrome.runtime.sendMessage({type:'GET_AUTOFILL_CONTEXT'}, async result => {
    if (!result?.ok) return show(result?.error || 'Contexte Autofill indisponible.', 'error');

    const tab = await activeTab();
    chrome.tabs.sendMessage(tab.id, {type:'AUTOFILL_GENERIC_PAGE', context:result.context}, response => {
      if (chrome.runtime.lastError) return show('Impossible de remplir cette page.', 'error');
      if (!response?.ok) return show(response?.error || 'Remplissage impossible.', 'error');

      const report = response.report || {};
      const filled = report.filled || 0;
      const review = report.review || 0;
      const preserved = report.preserved || 0;
      const skipped = report.skipped || 0;
      show(`${filled} rempli(s) · ${review} à vérifier · ${preserved} conservé(s) · ${skipped} ignoré(s). Vérifie avant d’envoyer.`, 'success');
    });
  });
});
