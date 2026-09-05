'use client';

import { useId } from 'react';

import { Modal } from './Modal';
import { Button } from './UI';

type ConfirmDialogProps = {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  cancelLabel?: string;
  confirmVariant?: 'primary' | 'danger';
  loading?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
};

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  cancelLabel = 'Annuler',
  confirmVariant = 'danger',
  loading = false,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const titleId = useId();
  const descriptionId = useId();

  if (!open) return null;

  return (
    <Modal
      ariaLabelledBy={titleId}
      ariaDescribedBy={descriptionId}
      closeOnBackdrop={!loading}
      onClose={() => {
        if (!loading) onCancel();
      }}
    >
      <div className="stack">
        <div>
          <h2 id={titleId} className="section-title">{title}</h2>
          <p id={descriptionId} className="muted">{description}</p>
        </div>
        <div className="actions">
          <Button variant="secondary" disabled={loading} onClick={onCancel}>
            {cancelLabel}
          </Button>
          <Button variant={confirmVariant} loading={loading} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </Modal>
  );
}
