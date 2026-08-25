import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { OfflineState } from '@/components/UI';

describe('OfflineState', () => {
  it('shows contextual recovery guidance without leading with raw technical errors', () => {
    render(
      <OfflineState
        title="Les offres sont temporairement indisponibles"
        message="JobPilot n’arrive pas à joindre l’API. Les données déjà enregistrées ne sont pas supprimées."
        technicalDetail="Failed to fetch"
        onRetry={() => undefined}
      />,
    );

    const state = screen.getByRole('status');
    expect(state).toHaveTextContent('Les offres sont temporairement indisponibles');
    expect(state).toHaveTextContent('JobPilot n’arrive pas à joindre l’API');
    expect(state).not.toHaveTextContent('⚠️ API indisponible');
    expect(screen.getByText('Détail technique : Failed to fetch')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
  });

  it('runs the retry action and can hide technical details', () => {
    const onRetry = vi.fn();

    render(
      <OfflineState
        title="Service indisponible"
        message="Réessaie dans un instant."
        onRetry={onRetry}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    expect(onRetry).toHaveBeenCalledTimes(1);
    expect(screen.queryByText(/Détail technique/)).not.toBeInTheDocument();
  });
});
