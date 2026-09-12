import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import { ButtonLink, Card, Empty, Loading } from './UI';

const meta = {
  title: 'Feedback/EmptyAndLoading',
  parameters: {
    docs: {
      description: {
        component:
          'Baseline feedback states for JobPilot. Empty states explain what is missing and may provide a next useful action; Loading exposes one polite busy status without adding competing announcements.',
      },
    },
  },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

export const EmptyState: Story = {
  name: 'Empty state',
  render: () => (
    <Card>
      <Empty>
        <div style={{ display: 'grid', gap: '0.75rem', justifyItems: 'start' }}>
          <div>
            <strong>Aucune candidature à traiter</strong>
            <p style={{ marginBottom: 0 }}>
              Les candidatures prêtes apparaîtront ici après préparation.
            </p>
          </div>
          <ButtonLink href="/offres" variant="secondary" size="small">
            Voir les offres
          </ButtonLink>
        </div>
      </Empty>
    </Card>
  ),
};

export const EmptyWithoutAction: Story = {
  name: 'Empty without action',
  render: () => (
    <Card>
      <Empty>Aucun résultat pour ces filtres.</Empty>
    </Card>
  ),
};

export const LoadingState: Story = {
  name: 'Loading state',
  render: () => (
    <Card>
      <Loading />
    </Card>
  ),
};
