import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import {
  Button,
  Card,
  DataList,
  DataListItem,
  DataToolbar,
  Empty,
  ErrorBox,
  FormField,
  InlineFeedback,
  ProgressBar,
} from './UI';

const meta = {
  title: 'Design System/Core contracts',
  parameters: {
    docs: {
      description: {
        component:
          'Shared JobPilot V1 primitives used by dense operational screens. Stories exercise semantic variants, loading/feedback states, accessible form relationships and responsive list/toolbar behavior.',
      },
    },
  },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

export const Buttons: Story = {
  render: () => (
    <Card>
      <DataToolbar actions={<Button variant="subtle">Action discrète</Button>}>
        <strong>Actions partagées</strong>
      </DataToolbar>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem', marginTop: '1rem' }}>
        <Button>Primaire</Button>
        <Button variant="secondary">Secondaire</Button>
        <Button variant="subtle">Subtile</Button>
        <Button variant="danger">Supprimer</Button>
        <Button loading>Chargement</Button>
        <Button disabled>Désactivée</Button>
      </div>
    </Card>
  ),
};

export const FeedbackStates: Story = {
  render: () => (
    <div style={{ display: 'grid', gap: '0.75rem', maxWidth: 720 }}>
      <InlineFeedback>Information contextuelle.</InlineFeedback>
      <InlineFeedback tone="success">Modification enregistrée.</InlineFeedback>
      <InlineFeedback tone="warning">Une vérification est nécessaire avant de continuer.</InlineFeedback>
      <ErrorBox message="Impossible de charger les données." />
      <Empty>Aucun élément à afficher.</Empty>
    </div>
  ),
};

export const DenseListAndToolbar: Story = {
  render: () => (
    <Card>
      <DataToolbar actions={<Button size="small">Actualiser</Button>}>
        <div>
          <strong>Candidatures</strong>
          <div>3 éléments à examiner</div>
        </div>
      </DataToolbar>
      <DataList aria-label="Candidatures à examiner" style={{ marginTop: '1rem' }}>
        <DataListItem>
          <strong>Senior PHP / Symfony</strong>
          <div>Entreprise Alpha · Paris</div>
        </DataListItem>
        <DataListItem>
          <strong>Full-stack React / PHP</strong>
          <div>Entreprise Beta · Remote</div>
        </DataListItem>
        <DataListItem>
          <strong>Software Engineer</strong>
          <div>Entreprise Gamma · Hybride</div>
        </DataListItem>
      </DataList>
    </Card>
  ),
};

export const AccessibleFormStates: Story = {
  render: () => (
    <div style={{ display: 'grid', gap: '1rem', maxWidth: 520 }}>
      <FormField label="Nom complet" hint="Utilisé dans les candidatures.">
        <input type="text" defaultValue="Aissa Soubhi" />
      </FormField>
      <FormField label="E-mail" error="Adresse e-mail invalide.">
        <input type="email" defaultValue="adresse-invalide" />
      </FormField>
      <FormField label="LinkedIn" success="Lien vérifié.">
        <input type="url" defaultValue="https://www.linkedin.com/in/example" />
      </FormField>
    </div>
  ),
};

export const ProgressStates: Story = {
  render: () => (
    <div style={{ display: 'grid', gap: '1rem', maxWidth: 620 }}>
      <ProgressBar value={25} label="Synchronisation" valueText="25 % terminé" />
      <ProgressBar value={64} label="Objectif candidatures" valueText="64 % atteint" tone="good" />
      <ProgressBar value={90} label="Quota" valueText="90 % utilisé" tone="warn" />
    </div>
  ),
};
