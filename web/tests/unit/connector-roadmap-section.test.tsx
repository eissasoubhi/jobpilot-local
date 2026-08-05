import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ConnectorRoadmapSection } from '@/components/ConnectorRoadmapSection';
import { connectorRoadmap } from '@/lib/connector-roadmap';

describe('ConnectorRoadmapSection', () => {
  it('renders every roadmap source separately from operational connectors', () => {
    const { container } = render(<ConnectorRoadmapSection />);

    expect(screen.getByRole('heading', { name: 'Sources prévues et restreintes' })).toBeInTheDocument();
    expect(container.querySelectorAll('[data-roadmap-connector]')).toHaveLength(connectorRoadmap.length);

    for (const entry of connectorRoadmap) {
      expect(screen.getByTestId(`roadmap-connector-${entry.code}`)).toHaveTextContent(entry.name);
    }

    expect(screen.queryByTestId('roadmap-connector-france-travail')).not.toBeInTheDocument();
  });

  it('shows the authorized channel restrictions without operational actions', () => {
    render(<ConnectorRoadmapSection />);

    const linkedin = within(screen.getByTestId('roadmap-connector-linkedin'));
    expect(linkedin.getByText('Gmail ou extension uniquement')).toBeInTheDocument();
    expect(linkedin.getByText('Alertes Gmail')).toBeInTheDocument();
    expect(linkedin.getByText('Import assisté')).toBeInTheDocument();

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.queryByText('Tester maintenant')).not.toBeInTheDocument();
    expect(screen.queryByText('Activer')).not.toBeInTheDocument();
  });
});
