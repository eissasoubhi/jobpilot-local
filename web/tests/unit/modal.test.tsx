import { createRef, useState } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Modal } from '@/components/Modal';

function ModalHarness() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button type="button" onClick={() => setOpen(true)}>Modifier la fiche</button>
      {open && (
        <Modal ariaLabel="Modifier la fiche CRM" onClose={() => setOpen(false)}>
          <button type="button">Fermer</button>
          <input aria-label="Nom affiché" />
          <button type="button">Enregistrer</button>
        </Modal>
      )}
    </>
  );
}

describe('Modal', () => {
  it('moves focus inside, closes with Escape, then restores trigger focus', async () => {
    const user = userEvent.setup();
    render(<ModalHarness />);

    const trigger = screen.getByRole('button', { name: 'Modifier la fiche' });
    await user.click(trigger);

    const dialog = screen.getByRole('dialog', { name: 'Modifier la fiche CRM' });
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(screen.getByRole('button', { name: 'Fermer' })).toHaveFocus();

    await user.keyboard('{Escape}');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('keeps Tab navigation inside the dialog boundaries', async () => {
    const user = userEvent.setup();
    render(<ModalHarness />);

    await user.click(screen.getByRole('button', { name: 'Modifier la fiche' }));

    const first = screen.getByRole('button', { name: 'Fermer' });
    const last = screen.getByRole('button', { name: 'Enregistrer' });

    last.focus();
    await user.tab();
    expect(first).toHaveFocus();

    await user.tab({ shift: true });
    expect(last).toHaveFocus();
  });

  it('returns programmatic focus escapes to the dialog', () => {
    render(
      <>
        <button type="button">Action extérieure</button>
        <Modal ariaLabel="Candidature" onClose={vi.fn()}>
          <button type="button">Fermer</button>
          <button type="button">Enregistrer</button>
        </Modal>
      </>,
    );

    const first = screen.getByRole('button', { name: 'Fermer' });
    const outside = screen.getByRole('button', { name: 'Action extérieure' });

    expect(first).toHaveFocus();
    outside.focus();
    expect(first).toHaveFocus();
  });

  it('keeps hidden inputs out of the modal focus order', async () => {
    const user = userEvent.setup();

    render(
      <Modal ariaLabel="Configurer le connecteur" onClose={vi.fn()}>
        <input type="hidden" name="connector-id" value="gmail" readOnly />
        <button type="button">Annuler</button>
        <button type="button">Enregistrer</button>
      </Modal>,
    );

    const first = screen.getByRole('button', { name: 'Annuler' });
    const last = screen.getByRole('button', { name: 'Enregistrer' });

    expect(first).toHaveFocus();

    last.focus();
    await user.tab();
    expect(first).toHaveFocus();
  });

  it('supports heading-based labelling and an explicit initial focus target', () => {
    const focusRef = createRef<HTMLInputElement>();

    render(
      <Modal ariaLabelledBy="modal-title" initialFocusRef={focusRef} onClose={vi.fn()}>
        <h2 id="modal-title">Modifier la candidature</h2>
        <button type="button">Fermer</button>
        <input ref={focusRef} aria-label="Référence" />
      </Modal>,
    );

    expect(screen.getByRole('dialog', { name: 'Modifier la candidature' })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Référence' })).toHaveFocus();
  });

  it('locks background scrolling while open and restores it after close', () => {
    document.body.style.overflow = 'auto';
    const { unmount } = render(
      <Modal ariaLabel="Candidature" onClose={vi.fn()}>
        <button type="button">Fermer</button>
      </Modal>,
    );

    expect(document.body.style.overflow).toBe('hidden');
    unmount();
    expect(document.body.style.overflow).toBe('auto');
    document.body.style.overflow = '';
  });

  it('can keep backdrop clicks non-destructive when requested', () => {
    const onClose = vi.fn();
    const { container } = render(
      <Modal ariaLabel="Candidature" closeOnBackdrop={false} onClose={onClose}>
        <button type="button">Fermer</button>
      </Modal>,
    );

    fireEvent.mouseDown(container.firstElementChild as HTMLElement);
    expect(onClose).not.toHaveBeenCalled();
  });
});