'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Application } from '@/lib/types';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';

export default function ApplicationsPage(){
  const [items,setItems]=useState<Application[]|null>(null); const [selected,setSelected]=useState<Application|null>(null); const [error,setError]=useState('');
  const load = (): void => {
  void api<Application[]>('/applications')
    .then(setItems)
    .catch((error: Error) => setError(error.message));
};

useEffect(() => {
  load();
}, []);
  const save=async()=>{if(!selected)return;try{const updated=await api<Application>(`/applications/${selected.id}`,{method:'PATCH',body:JSON.stringify({status:selected.status,message:selected.message,coverLetter:selected.coverLetter,compensationAnswer:selected.compensationAnswer,confirmationRef:selected.confirmationRef})});setSelected(updated);load();}catch(e:any){setError(e.message)}};
  return <><PageHeader title="Candidatures" description="Relis, ajuste puis confirme l’envoi depuis la plateforme concernée."/>{error&&<ErrorBox message={error}/>}<Card>{!items?<Loading/>:items.length===0?<Empty>Aucune candidature préparée.</Empty>:items.map(a=><div className="list-row" key={a.id}><div><h3>{a.jobOffer.title}</h3><div className="muted small">{a.jobOffer.company} · Score {a.jobOffer.score} · {a.jobOffer.language.toUpperCase()}</div><div className="actions" style={{marginTop:8}}><Badge tone={a.status==='SUBMITTED'?'good':'blue'}>{a.status}</Badge>{a.cvDocument&&<Badge>{a.cvDocument.name}</Badge>}{a.compensationAnswer&&<Badge tone="good">{a.compensationAnswer}</Badge>}</div></div><button className="btn secondary small" onClick={()=>setSelected(a)}>Ouvrir</button></div>)}</Card>
  {selected&&<div className="modal-backdrop" onMouseDown={()=>setSelected(null)}><div className="modal" onMouseDown={e=>e.stopPropagation()}><PageHeader title={selected.jobOffer.title} description={selected.jobOffer.company} actions={<button className="btn secondary" onClick={()=>setSelected(null)}>Fermer</button>}/><div className="stack">
    <label>Statut<select value={selected.status} onChange={e=>setSelected({...selected,status:e.target.value})}><option value="READY_TO_SUBMIT">Prête à envoyer</option><option value="SUBMITTED">Envoyée</option><option value="RECRUITER_REPLIED">Réponse recruteur</option><option value="INTERVIEW">Entretien</option><option value="REJECTED">Refusée</option><option value="OFFER_RECEIVED">Offre reçue</option></select></label>
    <label>Message<textarea value={selected.message} onChange={e=>setSelected({...selected,message:e.target.value})}/></label>
    <label>Lettre de motivation<textarea style={{minHeight:200}} value={selected.coverLetter} onChange={e=>setSelected({...selected,coverLetter:e.target.value})}/></label>
    <label>Réponse rémunération<input value={selected.compensationAnswer??''} onChange={e=>setSelected({...selected,compensationAnswer:e.target.value})}/></label>
    <label>Confirmation / référence<input value={selected.confirmationRef??''} onChange={e=>setSelected({...selected,confirmationRef:e.target.value})}/></label>
    <button className="btn" onClick={save}>Enregistrer</button>
  </div></div></div>}
  </>;
}
