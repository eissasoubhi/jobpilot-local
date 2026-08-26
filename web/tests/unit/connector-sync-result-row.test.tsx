import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ConnectorSyncResultRow } from '@/components/ConnectorSyncResultRow';

describe('ConnectorSyncResultRow', () => {
  it('keeps the connector result compact and exposes detailed counters on demand', () => {
    render(
      <ConnectorSyncResultRow
        name="Apec"
        state="success"
        result={{ received: 12, imported: 3, merged: 1, duplicates: 8, profileFiltered: 2, failed: 0, durationMs: 1340 }}
      />,
    );

    expect(screen.getByRole('region', { name: 'Synchronisation Apec' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Terminée');
    expect(screen.getByText('3 nouvelles offres · 8 déjà connues · 2 hors profil')).toBeInTheDocument();

    const details = screen.getByText('Voir le détail de Apec');
    expect(details.closest('details')).not.toHaveAttribute('open');
    fireEvent.click(details);
    expect(details.closest('details')).toHaveAttribute('open');
    expect(screen.getByText('12 offres récupérées')).toBeInTheDocument();
    expect(screen.getByText('1 source fusionnée')).toBeInTheDocument();
    expect(screen.getByText('2 offres hors profil')).toBeInTheDocument();
    expect(screen.getByText('Durée : 1,3 s')).toBeInTheDocument();
    expect(screen.getByText(/le filtre d’admission a confirmé une incompatibilité de profil/)).toBeInTheDocument();
    expect(screen.getByText(/Les offres écartées ne sont pas enregistrées/)).toBeInTheDocument();
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

    expect(screen.getByRole('region', { name: 'Synchronisation Gmail' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('En erreur');
    expect(screen.getByText('0 nouvelles offres · 0 déjà connues · 1 échec')).toBeInTheDocument();
    expect(screen.getByText(/23 emails trouvés/)).toBeInTheDocument();
    expect(screen.getByText(/19 déjà traités/)).toBeInTheDocument();
    expect(screen.getByText(/Erreur :/)).toBeInTheDocument();
    expect(screen.getByText(/Jeton expiré/)).toBeInTheDocument();
    expect(screen.queryByText(/Résultat vide/)).not.toBeInTheDocument();
  });

  it('explains a successful source run that returned zero offers', () => {
    render(
      <ConnectorSyncResultRow
        name="Apec"
        state="success"
        result={{ received: 0, imported: 0, duplicates: 0, profileFiltered: 0, failed: 0 }}
      />,
    );

    const disclosure = screen.getByText('Voir le détail de Apec');
    fireEvent.click(disclosure);
    expect(screen.getByText(/La source n’a renvoyé aucune offre/)).toBeInTheDocument();
    expect(screen.getByText(/distinct d’une erreur de connecteur/)).toBeInTheDocument();
  });

  it('explains Gmail zero-result runs from the available search diagnostics', () => {
    const { rerender } = render(
      <ConnectorSyncResultRow
        name="Gmail"
        state="success"
        result={{ received: 0, imported: 0, duplicates: 0, failed: 0 }}
        diagnostics={{ messagesMatched: 0, messagesImported: 0, offersExtracted: 0 }}
      />,
    );

    fireEvent.click(screen.getByText('Voir le détail de Gmail'));
    expect(screen.getByText(/Aucun email ne correspondait à la recherche Gmail/)).toBeInTheDocument();

    rerender(
      <ConnectorSyncResultRow
        name="Gmail"
        state="success"
        result={{ received: 0, imported: 0, duplicates: 0, failed: 0 }}
        diagnostics={{ messagesMatched: 4, messagesImported: 4, offersExtracted: 0 }}
      />,
    );

    expect(screen.getByText(/4 emails trouvés, mais aucune offre exploitable n’a été extraite/)).toBeInTheDocument();
  });

  it('keeps disclosure labels connector-specific for keyboard and screen-reader navigation', () => {
    render(
      <ConnectorSyncResultRow
        name="Adzuna"
        state="warning"
        result={{ received: 2, imported: 0, duplicates: 1, profileFiltered: 1, failed: 0 }}
      />,
    );

    const disclosure = screen.getByText('Voir le détail de Adzuna');
    expect(disclosure.closest('details')).not.toHaveAttribute('open');
    fireEvent.click(disclosure);
    expect(disclosure.closest('details')).toHaveAttribute('open');
    expect(screen.getByRole('status')).toHaveTextContent('Avec avertissement');
  });

  it('does not add an empty disclosure before a connector has run', () => {
    render(
      <ConnectorSyncResultRow
        name="Adzuna"
        state="waiting"
        result={{ received: 0, imported: 0, duplicates: 0, failed: 0 }}
      />,
    );

    expect(screen.getByRole('status')).toHaveTextContent('En attente');
    expect(screen.queryByText(/Voir le détail de/)).not.toBeInTheDocument();
  });
});
