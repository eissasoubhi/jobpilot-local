import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

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
});
