'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';

type Message={id:number;sender:string;subject:string;snippet:string;receivedAt:string;category:string};
export default function MessagesPage(){
  const [items,setItems]=useState<Message[]|null>(null); const [connected,setConnected]=useState(false); const [error,setError]=useState(''); const [info,setInfo]=useState('');
const load = (): void => {
  void api<{ connected: boolean }>('/integrations/gmail/status')
    .then((result) => setConnected(result.connected))
    .catch((error: Error) => setError(error.message));

  void api<Message[]>('/integrations/gmail/messages')
    .then(setItems)
    .catch((error: Error) => setError(error.message));
};

useEffect(() => {
  load();
}, []);
  const sync=async()=>{try{const r=await api<{imported:number;found:number}>('/integrations/gmail/sync',{method:'POST'});setInfo(`${r.imported} nouveau(x) message(s) importé(s) sur ${r.found} trouvé(s).`);load();}catch(e:any){setError(e.message)}};
  return <><PageHeader title="Messagerie" description="Alertes d’offres, réponses recruteurs, confirmations et entretiens." actions={connected?<button className="btn" onClick={sync}>Synchroniser Gmail</button>:<a className="btn" href="http://localhost:8080/api/integrations/gmail/start">Connecter Gmail</a>}/>{info&&<div className="notice">{info}</div>}{error&&<ErrorBox message={error}/>}<div style={{height:14}}/><Card>{!items?<Loading/>:items.length===0?<Empty>{connected?'Aucun message importé. Lance une synchronisation.':'Connecte Gmail depuis les paramètres.'}</Empty>:items.map(m=><div className="list-row" key={m.id}><div><div className="actions"><Badge tone={m.category==='INTERVIEW_REQUEST'?'good':m.category==='REJECTION'?'bad':'blue'}>{m.category}</Badge><span className="muted small">{new Date(m.receivedAt).toLocaleString('fr-FR')}</span></div><h3>{m.subject||'(sans objet)'}</h3><div className="muted small">{m.sender}</div><p className="small">{m.snippet}</p></div></div>)}</Card></>;
}
