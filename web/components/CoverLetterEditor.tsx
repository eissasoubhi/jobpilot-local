'use client';

import { useState } from 'react';

interface CoverLetterEditorProps {
  value: string;
  onChange: (value: string) => void;
  onCopy: (value: string) => void | Promise<void>;
}

export function CoverLetterEditor({ value, onChange, onCopy }: CoverLetterEditorProps) {
  const [manualEditorOpen, setManualEditorOpen] = useState(false);
  const hasCoverLetter = value.trim() !== '';
  const editorVisible = hasCoverLetter || manualEditorOpen;

  if (!editorVisible) {
    return (
      <section aria-labelledby="cover-letter-title">
        <strong className="small" id="cover-letter-title">Lettre de motivation</strong>
        <div className="notice" style={{ marginTop: 7 }}>
          <strong>Aucune lettre demandée par l’offre.</strong>{' '}
          JobPilot a conservé uniquement le message de candidature concis.
        </div>
        <button
          className="btn secondary small"
          type="button"
          style={{ marginTop: 8 }}
          onClick={() => setManualEditorOpen(true)}
        >
          Ajouter une lettre manuellement
        </button>
      </section>
    );
  }

  return (
    <section aria-labelledby="cover-letter-title">
      <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center', marginBottom: 7 }}>
        <div>
          <strong className="small" id="cover-letter-title">Lettre de motivation</strong>
          {!hasCoverLetter && (
            <div className="small muted">Ajout manuel : l’offre ne demandait pas de lettre.</div>
          )}
        </div>
        <button
          className="btn secondary small"
          type="button"
          disabled={!hasCoverLetter}
          onClick={() => void onCopy(value)}
        >
          Copier la lettre
        </button>
      </div>
      <textarea
        aria-label="Lettre de motivation"
        style={{ minHeight: 200 }}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </section>
  );
}
