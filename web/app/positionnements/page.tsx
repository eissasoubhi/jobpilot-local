'use client';

import { FormEvent, useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Positioning } from '@/lib/types';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';

const initial={finalClient:'',agency:'',recruiterName:'',recruiterEmail:'',recruiterPhone:'',missionTitle:'',description:'',callForTenderReference:'',advertisedTjmMin:'',advertisedTjmMax:'',advertisedTjmFixed:'',proposedTjm:'',acceptedTjm:'',startDate:'',location:'',remotePolicy:'Hybride',agreementGivenAt:'',proofEmailId:'',status:'MISSION_DETECTED'};

export default function PositioningsPage(){
  const [items,setItems]=useState<Positioning[]|null>(null); const [form,setForm]=useState<any>(initial); const [show,setShow]=useState(false); const [error,setError]=useState(''); const [warning,setWarning]=useState<any>(null);
  const load = (): void => {
  void api<Positioning[]>('/positionings')
    .then(setItems)
    .catch((error: Error) => setError(error.message));
};

useEffect(() => {
  load();
}, []);
  const payload=()=>{const p:any={...form};['advertisedTjmMin','advertisedTjmMax','advertisedTjmFixed','proposedTjm','acceptedTjm'].forEach(k=>p[k]=p[k]===''?null:Number(p[k]));return p};
  const check=async()=>{try{const result=await api<any>('/positionings/check-duplicate',{method:'POST',body:JSON.stringify(payload())});setWarning(result.duplicate?result:null);return result;}catch(e:any){setError(e.message)}};
  const submit=async(e:FormEvent,force=false)=>{e.preventDefault();setError('');try{const p=payload();p.force=force;await api('/positionings',{method:'POST',body:JSON.stringify(p)});setForm(initial);setWarning(null);setShow(false);load();}catch(err:any){setError(err.message)}};
  return <><PageHeader title="Positionnements" description="Suivi des soumissions par client final, agence et commercial." actions={<button className="btn" onClick={()=>setShow(true)}>Nouveau positionnement</button>}/>{error&&<ErrorBox message={error}/>}<Card>{!items?<Loading/>:items.length===0?<Empty>Aucun positionnement enregistré.</Empty>:items.map(p=><div className="list-row" key={p.id}><div><div className="actions"><Badge tone={['AGREEMENT_GIVEN','SUBMITTED_TO_CLIENT','WAITING_CLIENT'].includes(p.status)?'warn':'blue'}>{p.status}</Badge>{p.callForTenderReference&&<Badge>Réf. {p.callForTenderReference}</Badge>}{p.proposedTjm&&<Badge tone="good">{p.proposedTjm} €</Badge>}</div><h3>{p.missionTitle}</h3><div className="muted small">Client : {p.finalClient} · Agence : {p.agency} · Commercial : {p.recruiterName}</div><div className="actions" style={{marginTop:9}}>{p.mailtoUrl&&<a className="btn secondary small" href={p.mailtoUrl}>Préparer l’e-mail d’accord</a>}{p.agreementEmailBody&&<button className="btn secondary small" onClick={()=>navigator.clipboard.writeText(p.agreementEmailBody||'')}>Copier le message</button>}</div></div></div>)}</Card>
  {show&&<div className="modal-backdrop" onMouseDown={()=>setShow(false)}><div className="modal" onMouseDown={e=>e.stopPropagation()}><PageHeader title="Nouveau positionnement" actions={<button className="btn secondary" onClick={()=>setShow(false)}>Fermer</button>}/>{warning&&<div className="notice warning"><strong>Risque de double positionnement.</strong>{warning.matches?.map((m:any)=><div key={m.positioning.id} className="small" style={{marginTop:8}}>Similarité {m.score}% — {m.positioning.finalClient} / {m.positioning.agency} / {m.positioning.callForTenderReference||'sans référence'}</div>)}</div>}<div style={{height:12}}/><form className="form-grid" onSubmit={submit}>
    <label>Client final<input required value={form.finalClient} onBlur={check} onChange={e=>setForm({...form,finalClient:e.target.value})}/></label><label>Agence / ESN<input required value={form.agency} onChange={e=>setForm({...form,agency:e.target.value})}/></label>
    <label>Commercial<input required value={form.recruiterName} onChange={e=>setForm({...form,recruiterName:e.target.value})}/></label><label>E-mail commercial<input type="email" value={form.recruiterEmail} onChange={e=>setForm({...form,recruiterEmail:e.target.value})}/></label>
    <label>Téléphone commercial<input value={form.recruiterPhone} onChange={e=>setForm({...form,recruiterPhone:e.target.value})}/></label><label>Référence appel d’offres<input value={form.callForTenderReference} onBlur={check} onChange={e=>setForm({...form,callForTenderReference:e.target.value})}/></label>
    <label className="full">Intitulé de la mission<input required value={form.missionTitle} onBlur={check} onChange={e=>setForm({...form,missionTitle:e.target.value})}/></label><label className="full">Description<textarea value={form.description} onChange={e=>setForm({...form,description:e.target.value})}/></label>
    <label>TJM minimum<input type="number" value={form.advertisedTjmMin} onChange={e=>setForm({...form,advertisedTjmMin:e.target.value})}/></label><label>TJM maximum<input type="number" value={form.advertisedTjmMax} onChange={e=>setForm({...form,advertisedTjmMax:e.target.value})}/></label>
    <label>TJM fixe<input type="number" value={form.advertisedTjmFixed} onChange={e=>setForm({...form,advertisedTjmFixed:e.target.value})}/></label><label>TJM proposé<input type="number" placeholder="Calcul automatique si vide" value={form.proposedTjm} onChange={e=>setForm({...form,proposedTjm:e.target.value})}/></label>
    <label>TJM accepté<input type="number" value={form.acceptedTjm} onChange={e=>setForm({...form,acceptedTjm:e.target.value})}/></label><label>Date de démarrage<input type="date" value={form.startDate} onChange={e=>setForm({...form,startDate:e.target.value})}/></label>
    <label>Lieu<input value={form.location} onChange={e=>setForm({...form,location:e.target.value})}/></label><label>Télétravail<input value={form.remotePolicy} onChange={e=>setForm({...form,remotePolicy:e.target.value})}/></label>
    <label>Date de l’accord<input type="datetime-local" value={form.agreementGivenAt} onChange={e=>setForm({...form,agreementGivenAt:e.target.value})}/></label><label>ID / objet de l’e-mail preuve<input value={form.proofEmailId} onChange={e=>setForm({...form,proofEmailId:e.target.value})}/></label>
    <label>Statut<select value={form.status} onChange={e=>setForm({...form,status:e.target.value})}><option value="MISSION_DETECTED">Mission détectée</option><option value="CONTACT_RECRUITER">Contact commercial</option><option value="AGREEMENT_REQUESTED">Accord demandé</option><option value="AGREEMENT_GIVEN">Accord donné</option><option value="SUBMITTED_TO_CLIENT">Soumis au client</option><option value="WAITING_CLIENT">En attente client</option><option value="INTERVIEW_SCHEDULED">Entretien planifié</option><option value="REJECTED">Refusé</option><option value="ACCEPTED">Accepté</option><option value="CANCELLED">Annulé</option><option value="ON_HOLD">En pause</option></select></label>
    <div className="actions full"><button type="button" className="btn secondary" onClick={check}>Vérifier le doublon</button><button className="btn">Enregistrer</button>{warning&&<button type="button" className="btn danger" onClick={e=>submit(e as any,true)}>Forcer après vérification</button>}</div>
  </form></div></div>}
  </>;
}
