import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ConnectorSyncResultRow } from '@/components/ConnectorSyncResultRow';

describe('ConnectorSyncResultRow', () => {
  it('keeps the connector result compact and exposes detailed counters on demand', () => {
    render(
      <ConnectorSyncResultRow
        name="Apec"
        state="success"
        result={{ received: 12, imported: 3, merged: 1, duplicates: 8, profileFiltered: 2, failed: 0 }}
      />,
    );

    expect(screen.getByText('Terminée')).toBeInTheDocument();
    expect(screen.getByText('3 nouvelles offres · 8 déjà connues · 2 hors profil')).toBeInTheDocument();

    const details = screen.getByText('Voir le détail');
    expect(details.closest('details')).not.toHaveAttribute('open');
    fireEvent.click(details);
    expect(details.closest('details')).toHaveAttribute('open');
    expect(screen.getByText('12 offres récupérées')).toBeInTheDocument();
    expect(screen.getByText('1 source fusionnée')).toBeInTheDocument();
    expect(screen.getByText('2 offres hors profil')).toBeInTheDocument();
  });

  it('shows Gmail diagnostics and connector errors only in the expandable details', () => {
    render(
      <ConnectorSyncResultRow
        name="Gmail"
        state="error"
        result={{ received: 0, imported: 0, duplicates: 0, failed: 1 }}
        diagnostics={{
          messagesMatched: 23,
          messagesAlreadyKnown: 19,
          messagesImported: 3,
          offersExtracted: 1,
          messagesFailed: 1,
        }}
        error="Jeton expiré"
      />,
    );

    expect(screen.getByText('En erreur')).toBeInTheDocument();
    expect(screen.getByText('0 nouvelles offres · 0 déjà connues · 1 échec')).toBeInTheDocument();
    expect(screen.getByText(/23 emails trouvés/)).toBeInTheDocument();
    expect(screen.getByText(/19 déjà traités/)).toBeInTheDocument();
    expect(screen.getByText(/Erreur :/)).toBeInTheDocument();
    expect(screen.getByText(/Jeton expiré/)).toBeInTheDocument();
  });

  it('does not add an empty disclosure when there is no diagnostic detail', () => {
    render(
      <ConnectorSyncResultRow
        name="Adzuna"
        state="waiting"
        result={{ received: 0, imported: 0, duplicates: 0, failed: 0 }}
      />,
    );

    expect(screen.getByText('En attente')).toBeInTheDocument();
    expect(screen.queryByText('Voir le détail')).not.toBeInTheDocument();
  });
});
