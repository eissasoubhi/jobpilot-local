import { useState } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { CoverLetterDrawer } from '@/components/CoverLetterDrawer';
import type { Application } from '@/lib/types';

vi.mock('@/lib/api', () => ({
  API_URL: '/api',
  api: vi.fn(),
}));

vi.mock('@/lib/clipboard', () => ({
  copyTextToClipboard: vi.fn(),
}));

function application(): Application {
  return {
    id: 246,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Message court.',
    coverLetter: 'Lettre de motivation.',
    updatedAt: '2026-09-05T02:00:00+02:00',
    jobOffer: {
      id: 246,
      source: 'Manuel',
      sourceCode: 'manual',
      sourceUrl: 'https://example.com/jobs/246',
      title: 'Développeur Symfony',
      company: 'Example',
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Symfony PHP.',
      score: 90,
      scoreReasons: [],
      status: 'PREPARED',
      sources: [],
      sourceCount: 1,
    },
  } as Application;
}

function Harness() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button type="button" onClick={() => setOpen(true)}>Ouvrir la motivation</button>
      <CoverLetterDrawer application={application()} open={open} onClose={() => setOpen(false)} />
    </>
  );
}

describe('CoverLetterDrawer shared modal contract', () => {
  it('contains focus and restores it to the opener after Escape closes the drawer', async () => {
    render(<Harness />);

    const opener = screen.getByRole('button', { name: 'Ouvrir la motivation' });
    fireEvent.click(opener);

    const closeButton = screen.getByRole('button', { name: 'Fermer les contenus de motivation' });
    await waitFor(() => expect(closeButton).toHaveFocus());

    opener.focus();
    await waitFor(() => expect(closeButton).toHaveFocus());

    fireEvent.keyDown(closeButton, { key: 'Escape' });

    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Motivation' })).not.toBeInTheDocument());
    expect(opener).toHaveFocus();
  });
});
