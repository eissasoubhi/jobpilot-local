'use client';

import { useEffect, useState } from 'react';

import { Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import type { ResolvedReusableAnswer, ResolvedReusableAnswerPayload } from '@/lib/autofill-types';
import { getErrorMessage } from '@/lib/errors';

type Draft = ResolvedReusableAnswer;

type CreateDraft = {
  key: string;
  label: string;
  category: string;
  answerFr: string;
  answerEn: string;
  questionPatternsFr: string;
  questionPatternsEn: string;
};

const emptyCreateDraft: CreateDraft = {
  key: '',
  label: '',
  category: 'CUSTOM',
  answerFr: '',
  answerEn: '',
  questionPatternsFr: '',
  questionPatternsEn: '',
};

export default function ReusableAnswersPage() {
  const [answers, setAnswers] = useState<Draft[] | null>(null);
  const [createDraft, setCreateDraft] = useState<CreateDraft>(emptyCreateDraft);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [savingId, setSavingId] = useState<number | null>(null);

  const load = async (): Promise<void> => {
    setError('');
    try {
      const payload = await api<ResolvedReusableAnswerPayload>('/reusable-answers/resolved');
      setAnswers(payload.answers);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const updateLocal = <K extends keyof Draft>(id: number, key: K, value: Draft[K]): void => {
    setAnswers((current) => current?.map((answer) => answer.id === id ? { ...answer, [key]: value } : answer) ?? null);
  };

  const updateSensitive = (id: number, sensitive: boolean): void => {
    setAnswers((current) => current?.map((answer) => answer.id === id
      ? { ...answer, sensitive, autoFillAllowed: sensitive ? false : answer.autoFillAllowed }
      : answer) ?? null);
  };

  const save = async (answer: Draft): Promise<void> => {
    setSavingId(answer.id);
    setError('');
    setMessage('');

    try {
      await api(`/reusable-answers/${answer.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          label: answer.label,
          category: answer.category,
          valueSource: answer.valueSource,
          profilePath: answer.profilePath,
          answerType: answer.answerType,
          answerFr: answer.answerFr,
          answerEn: answer.answerEn,
          questionPatterns: answer.questionPatterns,
          enabled: answer.enabled,
          sensitive: answer.sensitive,
          autoFillAllowed: answer.autoFillAllowed,
        }),
      });
      setMessage(`Réponse « ${answer.label} » enregistrée.`);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSavingId(null);
    }
  };

  const create = async (): Promise<void> => {
    setError('');
    setMessage('');

    try {
      await api('/reusable-answers', {
        method: 'POST',
        body: JSON.stringify({
          key: createDraft.key,
          label: createDraft.label,
          category: createDraft.category || 'CUSTOM',
          valueSource: 'STATIC',
          answerType: 'TEXT',
          answerFr: createDraft.answerFr,
          answerEn: createDraft.answerEn,
          questionPatterns: {
            fr: createDraft.questionPatternsFr.split('\n').map((value) => value.trim()).filter(Boolean),
            en: createDraft.questionPatternsEn.split('\n').map((value) => value.trim()).filter(Boolean),
          },
          enabled: true,
          sensitive: false,
          autoFillAllowed: true,
        }),
      });
      setCreateDraft(emptyCreateDraft);
      setMessage('Nouvelle réponse automatique ajoutée.');
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const remove = async (answer: Draft): Promise<void> => {
    if (!window.confirm(`Supprimer la réponse « ${answer.label} » ?`)) return;

    setError('');
    try {
      await api(`/reusable-answers/${answer.id}`, { method: 'DELETE' });
      setMessage(`Réponse « ${answer.label} » supprimée.`);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  if (answers === null) {
    return error !== '' ? <ErrorBox message={error} /> : <Loading />;
  }

  return (
    <>
      <PageHeader
        title="Réponses automatiques"
        description="Bibliothèque utilisée par JobPilot Autofill pour reconnaître les questions récurrentes et proposer la bonne réponse."
      />

      {message !== '' && <div className="notice">{message}</div>}
      {error !== '' && <ErrorBox message={error} />}

      <div style={{ height: 14 }} />
      <Card>
        <h2 style={{ marginTop: 0 }}>Ajouter une réponse personnalisée</h2>
        <p className="muted">Utilise une clé stable, puis ajoute plusieurs formulations possibles de la question, une par ligne.</p>
        <div className="form-grid">
          <label>Clé<input placeholder="ex: sponsorship" value={createDraft.key} onChange={(event) => setCreateDraft({ ...createDraft, key: event.target.value })} /></label>
          <label>Libellé<input placeholder="ex: Sponsorship" value={createDraft.label} onChange={(event) => setCreateDraft({ ...createDraft, label: event.target.value })} /></label>
          <label>Catégorie<input value={createDraft.category} onChange={(event) => setCreateDraft({ ...createDraft, category: event.target.value })} /></label>
          <label>Réponse FR<textarea value={createDraft.answerFr} onChange={(event) => setCreateDraft({ ...createDraft, answerFr: event.target.value })} /></label>
          <label>Réponse EN<textarea value={createDraft.answerEn} onChange={(event) => setCreateDraft({ ...createDraft, answerEn: event.target.value })} /></label>
          <label>Questions FR<textarea placeholder="Une formulation par ligne" value={createDraft.questionPatternsFr} onChange={(event) => setCreateDraft({ ...createDraft, questionPatternsFr: event.target.value })} /></label>
          <label>Questions EN<textarea placeholder="One wording per line" value={createDraft.questionPatternsEn} onChange={(event) => setCreateDraft({ ...createDraft, questionPatternsEn: event.target.value })} /></label>
        </div>
        <div style={{ marginTop: 12 }}>
          <button className="btn" type="button" onClick={() => void create()}>Ajouter</button>
        </div>
      </Card>

      <div style={{ height: 14 }} />
      <div style={{ display: 'grid', gap: 14 }}>
        {answers.map((answer) => (
          <Card key={answer.id}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, alignItems: 'flex-start' }}>
              <div>
                <h2 style={{ margin: 0 }}>{answer.label}</h2>
                <div className="muted" style={{ marginTop: 4 }}>
                  {answer.key} · {answer.category} · {answer.valueSource === 'PROFILE' ? `Profil: ${answer.profilePath}` : 'Réponse personnalisée'}
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <strong>{answer.eligibleForAutomaticFill ? 'Prête pour autofill' : 'Revue requise'}</strong>
                {answer.sensitive && <div className="muted">Donnée sensible</div>}
              </div>
            </div>

            <div className="form-grid" style={{ marginTop: 16 }}>
              <label>Libellé<input value={answer.label} onChange={(event) => updateLocal(answer.id, 'label', event.target.value)} /></label>
              <label>Catégorie<input value={answer.category} onChange={(event) => updateLocal(answer.id, 'category', event.target.value)} /></label>
              <label>Source
                <select value={answer.valueSource} onChange={(event) => updateLocal(answer.id, 'valueSource', event.target.value as Draft['valueSource'])}>
                  <option value="PROFILE">Profil candidat</option>
                  <option value="STATIC">Réponse personnalisée</option>
                </select>
              </label>
              <label>Type
                <select value={answer.answerType} onChange={(event) => updateLocal(answer.id, 'answerType', event.target.value as Draft['answerType'])}>
                  <option value="TEXT">Texte</option>
                  <option value="NUMBER">Nombre</option>
                  <option value="BOOLEAN">Oui / Non</option>
                  <option value="CHOICE">Choix unique</option>
                  <option value="MULTI_CHOICE">Choix multiple</option>
                </select>
              </label>
              {answer.valueSource === 'PROFILE' ? (
                <label className="full">Chemin dans le profil<input value={answer.profilePath ?? ''} onChange={(event) => updateLocal(answer.id, 'profilePath', event.target.value)} /></label>
              ) : (
                <>
                  <label>Réponse FR<textarea value={answer.answerFr ?? ''} onChange={(event) => updateLocal(answer.id, 'answerFr', event.target.value)} /></label>
                  <label>Réponse EN<textarea value={answer.answerEn ?? ''} onChange={(event) => updateLocal(answer.id, 'answerEn', event.target.value)} /></label>
                </>
              )}
              <label>Questions reconnues FR
                <textarea value={answer.questionPatterns.fr.join('\n')} onChange={(event) => updateLocal(answer.id, 'questionPatterns', { ...answer.questionPatterns, fr: event.target.value.split('\n').map((value) => value.trim()).filter(Boolean) })} />
              </label>
              <label>Questions reconnues EN
                <textarea value={answer.questionPatterns.en.join('\n')} onChange={(event) => updateLocal(answer.id, 'questionPatterns', { ...answer.questionPatterns, en: event.target.value.split('\n').map((value) => value.trim()).filter(Boolean) })} />
              </label>
            </div>

            <div style={{ marginTop: 12, padding: 12, border: '1px solid var(--border)', borderRadius: 10 }}>
              <strong>Valeur résolue</strong>
              <div className="muted" style={{ marginTop: 6 }}>FR : {answer.resolved.fr ?? '—'} · EN : {answer.resolved.en ?? '—'}</div>
            </div>

            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, marginTop: 14, alignItems: 'center' }}>
              <label><input type="checkbox" checked={answer.enabled} onChange={(event) => updateLocal(answer.id, 'enabled', event.target.checked)} /> Active</label>
              <label><input type="checkbox" checked={answer.sensitive} onChange={(event) => updateSensitive(answer.id, event.target.checked)} /> Sensible</label>
              <label><input type="checkbox" checked={answer.autoFillAllowed} onChange={(event) => updateLocal(answer.id, 'autoFillAllowed', event.target.checked)} /> Autoriser le remplissage automatique</label>
              <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
                <button className="btn secondary" type="button" onClick={() => void remove(answer)}>Supprimer</button>
                <button className="btn" type="button" disabled={savingId === answer.id} onClick={() => void save(answer)}>
                  {savingId === answer.id ? 'Enregistrement…' : 'Enregistrer'}
                </button>
              </div>
            </div>
          </Card>
        ))}
      </div>
    </>
  );
}
