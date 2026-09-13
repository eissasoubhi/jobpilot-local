import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import { CrmOrganizationAnnotationEditor } from './CrmOrganizationAnnotationEditor';
import type { CrmOrganization } from '@/lib/types';

const organization: CrmOrganization = {
  key: 'acme consulting',
  name: 'ACME Consulting France',
  sourceName: 'Acme Consulting',
  annotation: {
    displayName: 'ACME Consulting France',
    note: 'Relancer dans une semaine après le retour du client final.',
    updatedAt: '2026-09-12T18:00:00+02:00',
  },
  roles: ['AGENCY', 'COMPANY'],
  offerCount: 3,
  applicationCount: 1,
  positioningCount: 1,
  messageCount: 2,
  contactCount: 1,
  applicationStatuses: { INTERVIEW: 1 },
  positioningStatuses: { AGREEMENT_GIVEN: 1 },
  lastActivityAt: '2026-09-12T17:30:00+02:00',
  contacts: [],
  latestOffers: [],
};

const meta = {
  title: 'CRM/CrmOrganizationAnnotationEditor',
  component: CrmOrganizationAnnotationEditor,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component:
          'Local CRM organization editor. The displayed name and internal note are an editable overlay; the detected source name, stable key, offers, positionings, and messages remain unchanged.',
      },
    },
  },
  tags: ['autodocs'],
  args: {
    organization,
    onClose: () => undefined,
    onSave: async () => undefined,
  },
} satisfies Meta<typeof CrmOrganizationAnnotationEditor>;

export default meta;
type Story = StoryObj<typeof meta>;

export const ExistingAnnotation: Story = {
  name: 'Existing local annotation',
  parameters: {
    docs: {
      description: {
        story:
          'The local display name and note are editable while source provenance stays visible. Story actions are inert and do not call a backend API.',
      },
    },
  },
};

export const SourceOnlyOrganization: Story = {
  name: 'Source-only organization',
  args: {
    organization: {
      ...organization,
      name: 'Acme Consulting',
      annotation: null,
    },
  },
  parameters: {
    docs: {
      description: {
        story:
          'Without a local annotation, the editor starts empty, falls back to the detected source name, and keeps the destructive clear action disabled until an annotation exists.',
      },
    },
  },
};
