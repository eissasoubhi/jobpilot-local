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
  show('Chargement du profil…');
  chrome.runtime.sendMessage({type:'GET_PROFILE'}, async result => {
    if (!result?.ok) return show(result?.error || 'Profil indisponible.', 'error');
    const tab = await activeTab();
    chrome.tabs.sendMessage(tab.id, {type:'AUTOFILL_PAGE', profile:result.profile}, response => {
      if (chrome.runtime.lastError) return show('Impossible de remplir cette page.', 'error');
      show(`${response?.filled || 0} champ(s) prérempli(s). Vérifie avant d’envoyer.`, 'success');
    });
  });
});
