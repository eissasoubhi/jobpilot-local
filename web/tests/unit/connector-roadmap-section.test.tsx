import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ConnectorRoadmapSection } from '@/components/ConnectorRoadmapSection';
import { connectorRoadmap } from '@/lib/connector-roadmap';

describe('ConnectorRoadmapSection', () => {
  it('renders the complete platform acquisition matrix', () => {
    const { container } = render(<ConnectorRoadmapSection />);

    expect(screen.getByRole('heading', { name: 'Matrice des plateformes suivies' })).toBeInTheDocument();
    expect(container.querySelectorAll('[data-roadmap-connector]')).toHaveLength(connectorRoadmap.length);

    for (const entry of connectorRoadmap) {
      expect(screen.getByTestId(`roadmap-connector-${entry.code}`)).toHaveTextContent(entry.name);
    }

    expect(screen.getByTestId('roadmap-connector-france-travail')).toHaveTextContent('Opérationnel');
    expect(screen.getByTestId('roadmap-connector-adzuna')).toHaveTextContent('Opérationnel');
    expect(screen.getByTestId('roadmap-connector-smartrecruiters')).toHaveTextContent('Opérationnel');
  });

  it('shows official, restricted and review states without matrix actions', () => {
    render(<ConnectorRoadmapSection />);

    const linkedin = within(screen.getByTestId('roadmap-connector-linkedin'));
    expect(linkedin.getByText('Gmail ou import assisté uniquement')).toBeInTheDocument();
    expect(linkedin.getByText('Alertes Gmail')).toBeInTheDocument();
    expect(linkedin.getByText('Import assisté')).toBeInTheDocument();

    const freeWork = within(screen.getByTestId('roadmap-connector-free-work'));
    expect(freeWork.getByText(/n’autorise pas une extraction planifiée de la base/i)).toBeInTheDocument();

    const smartRecruiters = within(screen.getByTestId('roadmap-connector-smartrecruiters'));
    expect(smartRecruiters.getByText('Opérationnel')).toBeInTheDocument();
    expect(smartRecruiters.getByText('API officielle')).toBeInTheDocument();
    expect(smartRecruiters.getByText(/configuration requise/i)).toBeInTheDocument();

    const eures = within(screen.getByTestId('roadmap-connector-eures'));
    expect(eures.getByText('Canal en revue')).toBeInTheDocument();

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.queryByText('Tester maintenant')).not.toBeInTheDocument();
    expect(screen.queryByText('Activer')).not.toBeInTheDocument();
  });
});
