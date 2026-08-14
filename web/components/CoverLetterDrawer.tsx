'use client';

import { useEffect, useMemo, useRef, useState } from 'react';

import styles from '@/components/CoverLetterDrawer.module.css';
import { API_URL, api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

type EditableApplication = Application & {
  coverLetterManuallyEdited?: boolean;
  coverLetterEditedAt?: string | null;
};

type CoverLetterDrawerProps = {
  application: Application;
  open: boolean;
  onClose: () => void;
  onApplicationUpdated?: (application: Application) => void;
};

function wordCount(value: string): number {
  const normalized = value.trim();
  return normalized === '' ? 0 : normalized.split(/\s+/).length;
}

function lengthLabel(words: number): string {
  if (words < 150) return 'Courte';
  if (words <= 220) return 'Longueur idéale';
  return 'Longue';
}

export function CoverLetterDrawer({
  application,
  open,
  onClose,
  onApplicationUpdated,
}: CoverLetterDrawerProps) {
  const [draft, setDraft] = useState(application.coverLetter);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [regenerating, setRegenerating] = useState(false);
  const [maxCharacters, setMaxCharacters] = useState(1_500);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const previousOpenRef = useRef(false);
  const previousApplicationIdRef = useRef(application.id);

  const editableApplication = application as EditableApplication;
  const displayedLetter = editing ? draft : application.coverLetter;
  const words = useMemo(() => wordCount(displayedLetter), [displayedLetter]);
  const characters = displayedLetter.length;
  const downloadBase = `${API_URL}/applications/${application.id}/cover-letter/download`;
  const editedAt = editableApplication.coverLetterEditedAt
    ? new Date(editableApplication.coverLetterEditedAt).toLocaleString('fr-FR')
    : null;

  useEffect(() => {
    const changedApplication = previousApplicationIdRef.current !== application.id;
    const justOpened = open && !previousOpenRef.current;

    if (open && (changedApplication || justOpened)) {
      setDraft(application.coverLetter);
      setEditing(false);
      setMaxCharacters(1_500);
      setNotice('');
      setError('');
    }

    previousApplicationIdRef.current = application.id;
    previousOpenRef.current = open;
  }, [application.coverLetter, application.id, open]);

  useEffect(() => {
    if (!editing) setDraft(application.coverLetter);
  }, [application.coverLetter, editing]);

  useEffect(() => {
    if (!open) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const frame = window.requestAnimationFrame(() => closeButtonRef.current?.focus());
    const handleKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') onClose();
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.cancelAnimationFrame(frame);
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [onClose, open]);

  if (!open) return null;

  const save = async (): Promise<void> => {
    if (saving || regenerating || draft.trim() === '') return;

    setSaving(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<EditableApplication>(`/applications/${application.id}/cover-letter`, {
        method: 'PATCH',
        body: JSON.stringify({ coverLetter: draft }),
      });
      setEditing(false);
      setDraft(updated.coverLetter);
      setNotice('Lettre de motivation enregistrée.');
      onApplicationUpdated?.(updated);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const regenerate = async (): Promise<void> => {
    if (saving || regenerating) return;
    if (maxCharacters < 200 || maxCharacters > 20_000) {
      setError('La longueur maximale doit être comprise entre 200 et 20 000 caractères.');
      return;
    }
    if (editableApplication.coverLetterManuallyEdited
      && !window.confirm('Régénérer la lettre remplacera la version modifiée manuellement. Continuer ?')) {
      return;
    }

    setRegenerating(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<EditableApplication>(`/applications/${application.id}/cover-letter/regenerate`, {
        method: 'POST',
        body: JSON.stringify({ maxCharacters }),
      });
      setEditing(false);
      setDraft(updated.coverLetter);
      setNotice(`Lettre régénérée avec une limite de ${maxCharacters} caractères.`);
      onApplicationUpdated?.(updated);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setRegenerating(false);
    }
  };

  const reset = async (): Promise<void> => {
    if (saving || regenerating) return;
    if (!window.confirm('Réinitialiser la lettre avec la dernière version générée par JobPilot ?')) return;

    setSaving(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<EditableApplication>(`/applications/${application.id}/cover-letter/reset`, {
        method: 'POST',
      });
      setEditing(false);
      setDraft(updated.coverLetter);
      setNotice('Lettre réinitialisée depuis la dernière version générée.');
      onApplicationUpdated?.(updated);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const copy = async (): Promise<void> => {
    setNotice('');
    setError('');

    try {
      await navigator.clipboard.writeText(application.coverLetter);
      setNotice('Lettre de motivation copiée.');
    } catch {
      setError('Impossible de copier la lettre de motivation dans le presse-papiers.');
    }
  };

  const startEditing = (): void => {
    setDraft(application.coverLetter);
    setEditing(true);
    setNotice('');
    setError('');
  };

  const cancelEditing = (): void => {
    setDraft(application.coverLetter);
    setEditing(false);
  };

  return (
    <div
      className={styles.backdrop}
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <aside
        className={styles.drawer}
        role="dialog"
        aria-modal="true"
        aria-labelledby={`cover-letter-drawer-title-${application.id}`}
      >
        <header className={styles.header}>
          <div className={styles.titleBlock}>
            <div className={styles.eyebrow}>Candidature</div>
            <h2 id={`cover-letter-drawer-title-${application.id}`}>Lettre de motivation</h2>
            <div className={styles.meta}>
              <span>{words} mots · {characters} caractères</span>
              <span className={styles.lengthBadge}>{lengthLabel(words)}</span>
              <span>
                {editableApplication.coverLetterManuallyEdited
                  ? `Modifiée manuellement${editedAt ? ` · ${editedAt}` : ''}`
                  : 'Générée automatiquement par JobPilot'}
              </span>
            </div>
          </div>
          <button
            ref={closeButtonRef}
            className={styles.closeButton}
            type="button"
            aria-label="Fermer la lettre de motivation"
            onClick={onClose}
          >
            ×
          </button>
        </header>

        <div className={styles.toolbar}>
          {!editing ? (
            <button className="btn small" type="button" disabled={regenerating} onClick={startEditing}>
              Modifier
            </button>
          ) : null}
          <button className="btn secondary small" type="button" disabled={regenerating} onClick={() => void copy()}>
            Copier
          </button>
          <details className={styles.downloadMenu}>
            <summary className="btn secondary small">Télécharger</summary>
            <div className={styles.downloadOptions}>
              <a href={`${downloadBase}/pdf`}>PDF</a>
              <a href={`${downloadBase}/docx`}>Word (.docx)</a>
            </div>
          </details>
          {editableApplication.coverLetterManuallyEdited && !editing ? (
            <button
              className="btn secondary small"
              type="button"
              disabled={saving || regenerating}
              onClick={() => void reset()}
            >
              Réinitialiser
            </button>
          ) : null}
          <div className={styles.regenerationControl}>
            <label htmlFor={`cover-letter-max-characters-${application.id}`}>Longueur max.</label>
            <input
              id={`cover-letter-max-characters-${application.id}`}
              aria-label="Longueur maximale de la lettre"
              type="number"
              min={200}
              max={20_000}
              step={50}
              value={maxCharacters}
              disabled={saving || regenerating}
              onChange={(event) => setMaxCharacters(Number(event.target.value))}
            />
            <span>caractères</span>
            <button
              className="btn secondary small"
              type="button"
              disabled={saving || regenerating || maxCharacters < 200 || maxCharacters > 20_000}
              onClick={() => void regenerate()}
            >
              {regenerating ? 'Régénération…' : 'Régénérer'}
            </button>
          </div>
        </div>

        {notice !== '' && <div className={`success-box ${styles.feedback}`} role="status">{notice}</div>}
        {error !== '' && <div className={`error-box ${styles.feedback}`} role="alert">{error}</div>}

        <div className={styles.body}>
          {editing ? (
            <div className={styles.editor}>
              <label htmlFor={`cover-letter-editor-${application.id}`}>Texte de la lettre</label>
              <textarea
                id={`cover-letter-editor-${application.id}`}
                value={draft}
                disabled={saving || regenerating}
                autoFocus
                onChange={(event) => setDraft(event.target.value)}
              />
              <div className={styles.editorFooter}>
                <span>Le PDF et le document Word utilisent la dernière version enregistrée.</span>
                <div className={styles.editorActions}>
                  <button
                    className="btn secondary small"
                    type="button"
                    disabled={saving || regenerating}
                    onClick={cancelEditing}
                  >
                    Annuler
                  </button>
                  {editableApplication.coverLetterManuallyEdited ? (
                    <button
                      className="btn secondary small"
                      type="button"
                      disabled={saving || regenerating}
                      onClick={() => void reset()}
                    >
                      Réinitialiser
                    </button>
                  ) : null}
                  <button
                    className="btn small"
                    type="button"
                    disabled={saving || regenerating || draft.trim() === ''}
                    onClick={() => void save()}
                  >
                    {saving ? 'Enregistrement…' : 'Enregistrer'}
                  </button>
                </div>
              </div>
            </div>
          ) : (
            <div className={styles.preview}>{application.coverLetter}</div>
          )}
        </div>
      </aside>
    </div>
  );
}
