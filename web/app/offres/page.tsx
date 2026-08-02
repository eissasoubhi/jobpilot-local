'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import { api } from '@/lib/api';
import { Job } from '@/lib/types';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';

const initial = { source:'Manuel', sourceUrl:'', title:'', company:'', clientName:'', location:'', contractType:'CDI', workMode:'Hybride', description:'', publishedAt:'', salaryMin:'', salaryMax:'', tjmFixed:'', tjmMin:'', tjmMax:'' };

function tone(status:string): 'good'|'warn'|'bad'|'blue'|'neutral' { if(status==='PREPARED')return 'good'; if(status==='REJECTED_BY_FILTER')return 'bad'; if(status==='MATCHED')return 'blue'; return 'neutral'; }
function age(job:Job){ if(job.ageHours==null)return 'Date inconnue'; if(job.ageHours<24)return `Il y a ${job.ageHours} h`; const d=Math.floor(job.ageHours/24); return `Il y a ${d} j`; }

export default function JobsPage(){
  const [jobs,setJobs]=useState<Job[]|null>(null); const [form,setForm]=useState<any>(initial); const [error,setError]=useState(''); const [show,setShow]=useState(false); const [filter,setFilter]=useState('all');
  const load = (): void => {
  void api<Job[]>('/jobs')
    .then(setJobs)
    .catch((error: Error) => setError(error.message));
};

useEffect(() => {
  load();
}, []);
  const submit=async(e:FormEvent)=>{e.preventDefault();setError('');try{const payload:any={...form}; ['salaryMin','salaryMax','tjmFixed','tjmMin','tjmMax'].forEach(k=>payload[k]=payload[k]===''?null:Number(payload[k])); payload.publishedAt=payload.publishedAt||null; await api('/jobs',{method:'POST',body:JSON.stringify(payload)});setForm(initial);setShow(false);load();}catch(err:any){setError(err.message)}};
  const prepare=async(id:number)=>{try{await api(`/jobs/${id}/prepare`,{method:'POST'});load();}catch(e:any){setError(e.message)}};
  const displayed=useMemo(()=>jobs?.filter(j=>filter==='all'||j.status===filter)??[],[jobs,filter]);
  return <>
    <PageHeader title="Offres" description="Les offres les plus récentes sont prioritaires, puis triées par score." actions={<button className="btn" onClick={()=>setShow(true)}>Ajouter une offre</button>}/>
    {error&&<ErrorBox message={error}/>}<div className="tabs"><button className={filter==='all'?'active':''} onClick={()=>setFilter('all')}>Toutes</button><button className={filter==='PREPARED'?'active':''} onClick={()=>setFilter('PREPARED')}>Préparées</button><button className={filter==='MATCHED'?'active':''} onClick={()=>setFilter('MATCHED')}>À examiner</button><button className={filter==='REJECTED_BY_FILTER'?'active':''} onClick={()=>setFilter('REJECTED_BY_FILTER')}>Exclues</button></div>
    <Card>{!jobs?<Loading/>:displayed.length===0?<Empty>Aucune offre dans cette catégorie.</Empty>:displayed.map(job=><div className="list-row" key={job.id}><div style={{flex:1}}><div className="actions" style={{marginBottom:6}}><Badge tone={tone(job.status)}>{job.status}</Badge><Badge tone="blue">{job.language==='fr'?'FR':'EN'}</Badge><Badge>{job.contractType||'Contrat inconnu'}</Badge>{job.proposedTjm&&<Badge tone="good">TJM proposé : {job.proposedTjm} €</Badge>}{job.proposedSalary&&<Badge tone="good">Salaire proposé : {job.proposedSalary.toLocaleString('fr-FR')} €</Badge>}</div><h3>{job.title}</h3><div className="muted small">{job.company||'Entreprise non renseignée'} · {job.location||'Lieu non renseigné'} · {age(job)} · {job.source}</div>{job.recommendedCv&&<div className="small" style={{marginTop:7}}>CV conseillé : <strong>{job.recommendedCv.name}</strong></div>}<details style={{marginTop:8}}><summary className="small muted">Pourquoi ce score ?</summary><ul>{job.scoreReasons.map(r=><li key={r} className="small">{r}</li>)}</ul></details><div className="actions" style={{marginTop:10}}>{job.sourceUrl&&<a className="btn secondary small" href={job.sourceUrl} target="_blank">Ouvrir l’offre</a>}{job.status!=='PREPARED'&&job.status!=='REJECTED_BY_FILTER'&&<button className="btn small" onClick={()=>prepare(job.id)}>Préparer</button>}</div></div><div className="score">{job.score}</div></div>)}</Card>
    {show&&<div className="modal-backdrop" onMouseDown={()=>setShow(false)}><div className="modal" onMouseDown={e=>e.stopPropagation()}><PageHeader title="Ajouter une offre" actions={<button className="btn secondary" onClick={()=>setShow(false)}>Fermer</button>}/><form className="form-grid" onSubmit={submit}>
      <label>Source<input value={form.source} onChange={e=>setForm({...form,source:e.target.value})}/></label><label>URL<input value={form.sourceUrl} onChange={e=>setForm({...form,sourceUrl:e.target.value})}/></label>
      <label>Intitulé<input required value={form.title} onChange={e=>setForm({...form,title:e.target.value})}/></label><label>Entreprise<input value={form.company} onChange={e=>setForm({...form,company:e.target.value})}/></label>
      <label>Client final éventuel<input value={form.clientName} onChange={e=>setForm({...form,clientName:e.target.value})}/></label><label>Lieu<input value={form.location} onChange={e=>setForm({...form,location:e.target.value})}/></label>
      <label>Contrat<select value={form.contractType} onChange={e=>setForm({...form,contractType:e.target.value})}><option>CDI</option><option>CDD</option><option>Freelance</option><option>Portage salarial</option><option>Sous-traitance</option></select></label><label>Mode de travail<input value={form.workMode} onChange={e=>setForm({...form,workMode:e.target.value})}/></label>
      <label>Date de publication<input type="datetime-local" value={form.publishedAt} onChange={e=>setForm({...form,publishedAt:e.target.value})}/></label><label>Salaire min. annuel<input type="number" value={form.salaryMin} onChange={e=>setForm({...form,salaryMin:e.target.value})}/></label>
      <label>Salaire max. annuel<input type="number" value={form.salaryMax} onChange={e=>setForm({...form,salaryMax:e.target.value})}/></label><label>TJM fixe<input type="number" value={form.tjmFixed} onChange={e=>setForm({...form,tjmFixed:e.target.value})}/></label>
      <label>TJM minimum<input type="number" value={form.tjmMin} onChange={e=>setForm({...form,tjmMin:e.target.value})}/></label><label>TJM maximum<input type="number" value={form.tjmMax} onChange={e=>setForm({...form,tjmMax:e.target.value})}/></label>
      <label className="full">Description<textarea required value={form.description} onChange={e=>setForm({...form,description:e.target.value})}/></label><button className="btn full">Analyser et enregistrer</button>
    </form></div></div>}
  </>;
}
