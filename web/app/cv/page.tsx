'use client';

import { FormEvent, useEffect, useState } from 'react';
import { api, API_URL } from '@/lib/api';
import { Cv } from '@/lib/types';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';

export default function CvPage(){
  const [items,setItems]=useState<Cv[]|null>(null); const [error,setError]=useState('');
const load = (): void => {
  void api<Cv[]>('/cvs')
    .then(setItems)
    .catch((error: Error) => setError(error.message));
};

useEffect(() => {
  load();
}, []);
  const upload=async(e:FormEvent<HTMLFormElement>)=>{e.preventDefault();setError('');const fd=new FormData(e.currentTarget);try{await api<Cv>('/cvs',{method:'POST',body:fd});e.currentTarget.reset();load();}catch(err:any){setError(err.message)}};
  const remove=async(id:number)=>{if(!confirm('Supprimer ce CV ?'))return;await api(`/cvs/${id}`,{method:'DELETE'});load();};
  return <><PageHeader title="Mes CV" description="L’application choisit le document adapté, sans modifier son contenu."/>{error&&<ErrorBox message={error}/>}<div className="grid cols-2">
    <Card><h2 className="section-title">Ajouter un CV</h2><form className="stack" onSubmit={upload}>
      <label>Nom du CV<input name="name" required placeholder="CV Full-Stack Symfony React"/></label>
      <label>Langue<select name="language"><option value="fr">Français</option><option value="en">Anglais</option></select></label>
      <label>Catégorie<input name="category" placeholder="Full-Stack, Backend, Frontend…"/></label>
      <label>Tags<input name="tags" placeholder="Symfony, React, PHP"/></label>
      <label>Fichier PDF ou Word<input name="file" type="file" accept=".pdf,.doc,.docx" required/></label>
      <label style={{display:'flex',gridTemplateColumns:'auto 1fr',alignItems:'center'}}><input style={{width:'auto'}} name="defaultForLanguage" type="checkbox" value="true"/> CV par défaut pour cette langue</label>
      <button className="btn">Téléverser</button>
    </form></Card>
    <Card><h2 className="section-title">Documents disponibles</h2>{!items?<Loading/>:items.length===0?<Empty>Aucun CV téléversé.</Empty>:items.map(cv=><div className="list-row" key={cv.id}><div><h3>{cv.name}</h3><div className="muted small">{cv.originalName} · {(cv.size/1024).toFixed(0)} Ko</div><div className="actions" style={{marginTop:8}}><Badge tone="blue">{cv.language==='fr'?'Français':'Anglais'}</Badge>{cv.defaultForLanguage&&<Badge tone="good">Par défaut</Badge>}{cv.tags.map(t=><Badge key={t}>{t}</Badge>)}</div></div><div className="actions"><a className="btn secondary small" href={`${API_URL.replace('/api','')}${cv.downloadUrl}`}>Télécharger</a><button className="btn danger small" onClick={()=>remove(cv.id)}>Supprimer</button></div></div>)}</Card>
  </div></>;
}
