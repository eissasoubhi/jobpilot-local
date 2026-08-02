'use client';

import { type FormEvent, useCallback, useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Cv } from '@/lib/types';

export default function CvPage() {
  const [items, setItems] = useState<Cv[] | null>(null);
  const [error, setError] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      setItems(await api<Cv[]>('/cvs'));
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const upload = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    setError('');
    const form = event.currentTarget;

    try {
      await api<Cv>('/cvs', { method: 'POST', body: new FormData(form) });
      form.reset();
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const remove = async (id: number): Promise<void> => {
    if (!window.confirm('Supprimer ce CV ?')) return;

    try {
      await api(`/cvs/${id}`, { method: 'DELETE' });
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  return (
    <>
      <PageHeader
        title="Mes CV"
        description="L’application choisit le document adapté, sans modifier son contenu."
      />
      {error !== '' && <ErrorBox message={error} />}
      <div className="grid cols-2">
        <Card>
          <h2 className="section-title">Ajouter un CV</h2>
          <form className="stack" onSubmit={(event) => void upload(event)}>
            <label>
              Nom du CV
              <input name="name" required placeholder="CV Full-Stack Symfony React" />
            </label>
            <label>
              Langue
              <select name="language">
                <option value="fr">Français</option>
                <option value="en">Anglais</option>
              </select>
            </label>
            <label>
              Catégorie
              <input name="category" placeholder="Full-Stack, Backend, Frontend…" />
            </label>
            <label>
              Tags
              <input name="tags" placeholder="Symfony, React, PHP" />
            </label>
            <label>
              Fichier PDF ou Word
              <input name="file" type="file" accept=".pdf,.doc,.docx" required />
            </label>
            <label className="checkbox-label">
              <input name="defaultForLanguage" type="checkbox" value="true" />
              CV par défaut pour cette langue
            </label>
            <button className="btn" type="submit">Téléverser</button>
          </form>
        </Card>

        <Card>
          <h2 className="section-title">Documents disponibles</h2>
          {items === null ? (
            <Loading />
          ) : items.length === 0 ? (
            <Empty>Aucun CV téléversé.</Empty>
          ) : (
            items.map((cv) => (
              <div className="list-row" key={cv.id}>
                <div>
                  <h3>{cv.name}</h3>
                  <div className="muted small">
                    {cv.originalName} · {(cv.size / 1024).toFixed(0)} Ko
                  </div>
                  <div className="actions" style={{ marginTop: 8 }}>
                    <Badge tone="blue">{cv.language === 'fr' ? 'Français' : 'Anglais'}</Badge>
                    {cv.defaultForLanguage && <Badge tone="good">Par défaut</Badge>}
                    {cv.tags.map((tag) => <Badge key={tag}>{tag}</Badge>)}
                  </div>
                </div>
                <div className="actions">
                  <a className="btn secondary small" href={cv.downloadUrl}>Télécharger</a>
                  <button className="btn danger small" type="button" onClick={() => void remove(cv.id)}>
                    Supprimer
                  </button>
                </div>
              </div>
            ))
          )}
        </Card>
      </div>
    </>
  );
}
