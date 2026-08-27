'use client';

import { useEffect, useMemo, useRef, useState } from 'react';

import styles from '@/components/CoverLetterDrawer.module.css';
import { API_URL, api } from '@/lib/api';
import { copyTextToClipboard } from '@/lib/clipboard';
import { getErrorMessage } from '@/lib/errors';
import { jobTargetCompany, targetCompanyMissingHint } from '@/lib/job-target-company';
import { downloadWithCleanProvenance } from '@/lib/privacy-download';
import type { Application } from '@/lib/types';

type EditableApplication = Application & {
  coverLetterManuallyEdited?: boolean;
  coverLetterEditedAt?: string | null;
};

type MotivationTab = 'coverLetter' | 'message';

type CoverLetterFormat = 'pdf' | 'docx';

type CoverLetterDrawerProps = {
  application: Application;
  open: boolean;
  onClose: () => void;
  onApplicationUpdated?: (application: Application) => void;
  initialTab?: MotivationTab;
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
  initialTab = 'coverLetter',
}: CoverLetterDrawerProps) {
  const [activeTab, setActiveTab] = useState<MotivationTab>(initialTab);
  const [draft, setDraft] = useState(application.coverLetter);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [regenerating, setRegenerating] = useState(false);
  const [regeneratingMessage, setRegeneratingMessage] = useState(false);
  const [maxCharacters, setMaxCharacters] = useState(1_500);
  const [messageMaxCharacters, setMessageMaxCharacters] = useState(400);
  const [targetCompany, setTargetCompany] = useState(jobTargetCompany(application.jobOffer));
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const previousOpenRef = useRef(false);
  const previousApplicationIdRef = useRef(application.id);

  const editableApplication = application as EditableApplication;
  const displayedLetter = editing ? draft : application.coverLetter;
  const words = useMemo(() => wordCount(displayedLetter), [displayedLetter]);
  const characters = displayedLetter.length;
  const messageCharacters = application.message.length;
  const messageOverCommonLimit = messageCharacters > 400;
  const messageOverSelectedLimit = messageCharacters > messageMaxCharacters;
  const hasMessage = application.message.trim() !== '';
  const downloadBase = `${API_URL}/applications/${application.id}/cover-letter/download`;
  const editedAt = editableApplication.coverLetterEditedAt
    ? new Date(editableApplication.coverLetterEditedAt).toLocaleString('fr-FR')
    : null;

  useEffect(() => {
    const changedApplication = previousApplicationIdRef.current !== application.id;
    const justOpened = open && !previousOpenRef.current;

    if (open && (changedApplication || justOpened)) {
      setActiveTab(initialTab);
      setDraft(application.coverLetter);
      setEditing(false);
      setMaxCharacters(1_500);
      setMessageMaxCharacters(400);
      setTargetCompany(jobTargetCompany(application.jobOffer));
      setNotice('');
      setError('');
    }

    previousApplicationIdRef.current = application.id;
    previousOpenRef.current = open;
  }, [application.coverLetter, application.id, application.jobOffer, initialTab, open]);

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

  const targetCompanyOverride = (): { targetCompany?: string } => {
    const resolved = jobTargetCompany(application.jobOffer).trim();
    const requested = targetCompany.trim();

    return requested === resolved ? {} : { targetCompany: requested };
  };

  const save = async (): Promise<void> => {
    if (saving || regenerating || regeneratingMessage || draft.trim() === '') return;

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
    if (saving || regenerating || regeneratingMessage) return;
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
        body: JSON.stringify({ maxCharacters, ...targetCompanyOverride() }),
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

  const regenerateMessage = async (): Promise<void> => {
    if (saving || regenerating || regeneratingMessage) return;
    if (messageMaxCharacters < 50 || messageMaxCharacters > 5_000) {
      setError('La longueur maximale du message doit être comprise entre 50 et 5 000 caractères.');
      return;
    }

    setRegeneratingMessage(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<Application>(`/applications/${application.id}/message/regenerate`, {
        method: 'POST',
        body: JSON.stringify({ maxCharacters: messageMaxCharacters, ...targetCompanyOverride() }),
      });
      setNotice(`Message court régénéré avec une limite de ${messageMaxCharacters} caractères.`);
      onApplicationUpdated?.(updated);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setRegeneratingMessage(false);
    }
  };

  const reset = async (): Promise<void> => {
    if (saving || regenerating || regeneratingMessage) return;
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

  const copyCoverLetter = async (): Promise<void> => {
    setNotice('');
    setError('');

    try {
      await copyTextToClipboard(application.coverLetter);
      setNotice('Lettre de motivation copiée.');
    } catch {
      setError('Impossible de copier la lettre de motivation dans le presse-papiers.');
    }
  };

  const copyMessage = async (): Promise<void> => {
    setNotice('');
    setError('');

    try {
      await copyTextToClipboard(application.message);
      setNotice('Message court copié.');
    } catch {
      setError('Impossible de copier le message court dans le presse-papiers.');
    }
  };

  const downloadCoverLetter = async (format: CoverLetterFormat): Promise<void> => {
    setNotice('');
    setError('');

    const result = await downloadWithCleanProvenance({
      url: `${downloadBase}/${format}`,
      filename: `lettre-motivation-${application.id}.${format}`,
    });

    setNotice(result.privacyClean
      ? `Téléchargement ${format.toUpperCase()} préparé sans provenance JobPilot.`
      : `Téléchargement ${format.toUpperCase()} lancé avec le mécanisme navigateur standard.`);
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

  const switchTab = (tab: MotivationTab): void => {
    setActiveTab(tab);
    setNotice('');
    setError('');
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
        aria-labelledby={`motivation-drawer-title-${application.id}`}
      >
        <header className={styles.header}>
          <div className={styles.titleBlock}>
            <div className={styles.eyebrow}>Candidature</div>
            <h2 id={`motivation-drawer-title-${application.id}`}>Motivation</h2>
            <div className={styles.meta}>
              {activeTab === 'coverLetter' ? (
                <>
                  <span>{words} mots · {characters} caractères</span>
                  <span className={styles.lengthBadge}>{lengthLabel(words)}</span>
                  <span>
                    {editableApplication.coverLetterManuallyEdited
                      ? `Modifiée manuellement${editedAt ? ` · ${editedAt}` : ''}`
                      : 'Générée automatiquement par JobPilot'}
                  </span>
                </>
              ) : (
                <>
                  <span>{messageCharacters} caractères</span>
                  <span className={`${styles.lengthBadge} ${messageOverCommonLimit ? styles.warningBadge : ''}`}>
                    {messageOverCommonLimit ? 'Dépasse 400' : 'Format court'}
                  </span>
                  <span>Pour les formulaires de candidature courts</span>
                </>
              )}
            </div>
          </div>
          <button
            ref={closeButtonRef}
            className={styles.closeButton}
            type="button"
            aria-label="Fermer les contenus de motivation"
            onClick={onClose}
          >
            ×
          </button>
        </header>

        <div className={styles.tabs} role="tablist" aria-label="Contenus de motivation">
          <button
            id={`cover-letter-tab-${application.id}`}
            className={`${styles.tab} ${activeTab === 'coverLetter' ? styles.tabActive : ''}`}
            type="button"
            role="tab"
            aria-selected={activeTab === 'coverLetter'}
            aria-controls={`cover-letter-panel-${application.id}`}
            onClick={() => switchTab('coverLetter')}
          >
            Lettre de motivation
          </button>
          <button
            id={`message-tab-${application.id}`}
            className={`${styles.tab} ${activeTab === 'message' ? styles.tabActive : ''}`}
            type="button"
            role="tab"
            aria-selected={activeTab === 'message'}
            aria-controls={`message-panel-${application.id}`}
            onClick={() => switchTab('message')}
          >
            Message court
          </button>
        </div>

        <div className={styles.toolbarArea}>
          <div className={styles.toolbar}>
            <div className={styles.regenerationControl}>
              <label htmlFor={`motivation-target-company-${application.id}`}>Entreprise ciblée</label>
              <input
                id={`motivation-target-company-${application.id}`}
                aria-label="Entreprise ciblée pour la motivation"
                type="text"
                maxLength={160}
                value={targetCompany}
                disabled={saving || regenerating || regeneratingMessage}
                style={{ width: 'min(240px, 46vw)' }}
                placeholder="Nom de l’entreprise"
                onChange={(event) => setTargetCompany(event.target.value)}
              />
            </div>
            {activeTab === 'coverLetter' ? (
              <>
                {!editing ? (
                  <button className="btn small" type="button" disabled={regenerating || regeneratingMessage} onClick={startEditing}>
                    Modifier
                  </button>
                ) : null}
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={regenerating || regeneratingMessage}
                  onClick={() => void copyCoverLetter()}
                >
                  Copier
                </button>
                <details className={styles.downloadMenu}>
                  <summary className="btn secondary small">Télécharger</summary>
                  <div className={styles.downloadOptions}>
                    <button type="button" onClick={() => void downloadCoverLetter('pdf')}>PDF</button>
                    <button type="button" onClick={() => void downloadCoverLetter('docx')}>Word (.docx)</button>
                  </div>
                </details>
                {editableApplication.coverLetterManuallyEdited && !editing ? (
                  <button
                    className="btn secondary small"
                    type="button"
                    disabled={saving || regenerating || regeneratingMessage}
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
                    disabled={saving || regenerating || regeneratingMessage}
                    onChange={(event) => setMaxCharacters(Number(event.target.value))}
                  />
                  <span>caractères</span>
                  <button
                    className="btn secondary small"
                    type="button"
                    disabled={saving || regenerating || regeneratingMessage || maxCharacters < 200 || maxCharacters > 20_000}
                    onClick={() => void regenerate()}
                  >
                    {regenerating ? 'Régénération…' : 'Régénérer'}
                  </button>
                </div>
              </>
            ) : (
              <>
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={!hasMessage || regenerating || regeneratingMessage}
                  onClick={() => void copyMessage()}
                >
                  Copier
                </button>
                <div className={styles.regenerationControl}>
                  <label htmlFor={`message-max-characters-${application.id}`}>Longueur max.</label>
                  <input
                    id={`message-max-characters-${application.id}`}
                    aria-label="Longueur maximale du message court"
                    type="number"
                    min={50}
                    max={5_000}
                    step={25}
                    value={messageMaxCharacters}
                    disabled={saving || regenerating || regeneratingMessage}
                    onChange={(event) => setMessageMaxCharacters(Number(event.target.value))}
                  />
                  <span>caractères</span>
                  <button
                    className="btn secondary small"
                    type="button"
                    disabled={saving
                      || regenerating
                      || regeneratingMessage
                      || messageMaxCharacters < 50
                      || messageMaxCharacters > 5_000}
                    onClick={() => void regenerateMessage()}
                  >
                    {regeneratingMessage
                      ? 'Régénération…'
                      : messageOverSelectedLimit
                        ? `Réduire à ${messageMaxCharacters}`
                        : 'Régénérer'}
                  </button>
                </div>
              </>
            )}
          </div>

          {targetCompany.trim() === '' && (
            <div className={`small muted ${styles.feedback}`} role="status">
              {targetCompanyMissingHint(application.jobOffer)}
            </div>
          )}
          {notice !== '' && <div className={`success-box ${styles.feedback}`} role="status">{notice}</div>}
          {error !== '' && <div className={`error-box ${styles.feedback}`} role="alert">{error}</div>}
        </div>

        <div className={styles.body}>
          {activeTab === 'coverLetter' ? (
            <div
              id={`cover-letter-panel-${application.id}`}
              role="tabpanel"
              aria-labelledby={`cover-letter-tab-${application.id}`}
            >
              {editing ? (
                <div className={styles.editor}>
                  <label htmlFor={`cover-letter-editor-${application.id}`}>Texte de la lettre</label>
                  <textarea
                    id={`cover-letter-editor-${application.id}`}
                    value={draft}
                    disabled={saving || regenerating || regeneratingMessage}
                    autoFocus
                    onChange={(event) => setDraft(event.target.value)}
                  />
                  <div className={styles.editorFooter}>
                    <span>Le PDF et le document Word utilisent la dernière version enregistrée.</span>
                    <div className={styles.editorActions}>
                      <button
                        className="btn secondary small"
                        type="button"
                        disabled={saving || regenerating || regeneratingMessage}
                        onClick={cancelEditing}
                      >
                        Annuler
                      </button>
                      {editableApplication.coverLetterManuallyEdited ? (
                        <button
                          className="btn secondary small"
                          type="button"
                          disabled={saving || regenerating || regeneratingMessage}
                          onClick={() => void reset()}
                        >
                          Réinitialiser
                        </button>
                      ) : null}
                      <button
                        className="btn small"
                        type="button"
                        disabled={saving || regenerating || regeneratingMessage || draft.trim() === ''}
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
          ) : (
            <div
              id={`message-panel-${application.id}`}
              role="tabpanel"
              aria-labelledby={`message-tab-${application.id}`}
              className={styles.messagePanel}
            >
              <p className={styles.messageHint}>
                Ce texte est prévu pour les formulaires qui demandent un message de motivation court. La limite courante est souvent de 400 caractères.
              </p>
              <div className={styles.preview}>{hasMessage ? application.message : 'Aucun message court préparé.'}</div>
              {messageOverSelectedLimit && (
                <div className={styles.messageWarning}>
                  Ce message fait {messageCharacters} caractères et dépasse la limite choisie de {messageMaxCharacters}. Utilise « Réduire à {messageMaxCharacters} » pour générer une version compatible.
                </div>
              )}
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}
