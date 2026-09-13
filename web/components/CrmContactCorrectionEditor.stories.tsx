import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import {
  CrmContactCorrectionEditor,
  type EditableCrmContact,
} from './CrmContactCorrectionEditor';
import type { CrmOrganization } from '@/lib/types';

const organization: CrmOrganization = {
  key: 'acme-consulting',
  name: 'ACME Consulting',
  sourceName: 'ACME Consulting SAS',
  roles: ['COMPANY'],
  offerCount: 3,
  applicationCount: 1,
  positioningCount: 0,
  messageCount: 2,
  contactCount: 1,
  applicationStatuses: {},
  positioningStatuses: {},
  contacts: [],
  latestOffers: [],
};

const contact: EditableCrmContact = {
  key: 'contact-recruiter-1',
  name: 'Sarah Martin',
  email: 'sarah.martin@example.com',
  phone: '+33 6 12 34 56 78',
  sourceName: 'S. Martin',
  sourceEmail: 'recrutement@example.com',
  sourcePhone: '+33 1 44 55 66 77',
  roles: ['RECRUITER'],
  messageCount: 2,
  correction: {
    correctedName: 'Sarah Martin',
    correctedEmail: 'sarah.martin@example.com',
    correctedPhone: '+33 6 12 34 56 78',
    updatedAt: '2026-09-12T18:00:00+02:00',
  },
};

const meta = {
  title: 'CRM/CrmContactCorrectionEditor',
  component: CrmContactCorrectionEditor,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component:
          'Local CRM correction editor. Source values stay visible and unchanged while the user edits the local overlay; clearing all fields removes only that local correction.',
      },
    },
  },
  tags: ['autodocs'],
  args: {
    organization,
    contact,
    onClose: () => undefined,
    onSave: async () => undefined,
  },
} satisfies Meta<typeof CrmContactCorrectionEditor>;

export default meta;
type Story = StoryObj<typeof meta>;

export const ExistingCorrection: Story = {
  name: 'Existing local correction',
  parameters: {
    docs: {
      description: {
        story:
          'The editable local values are primary, while the immutable source values remain available as provenance. Saving this story does not call a backend API.',
      },
    },
  },
};

export const SourceOnlyContact: Story = {
  name: 'Source-only contact',
  args: {
    contact: {
      ...contact,
      name: 'S. Martin',
      email: 'recrutement@example.com',
      phone: '+33 1 44 55 66 77',
      correction: null,
    },
  },
  parameters: {
    docs: {
      description: {
        story:
          'A contact without a local correction starts from the source-backed displayed values, keeping the same explicit save and cancel actions.',
      },
    },
  },
};
