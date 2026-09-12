import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import { CatalogResetPanel } from './CatalogResetPanel';

const meta = {
  title: 'Settings/CatalogResetPanel',
  component: CatalogResetPanel,
  parameters: {
    layout: 'padded',
    docs: {
      description: {
        component:
          'Destructive catalog maintenance surface. The confirmation phrase keeps the action locked until the consequence has been deliberately acknowledged, while preserved and deleted data remain visible before the action is available.',
      },
    },
  },
} satisfies Meta<typeof CatalogResetPanel>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Locked: Story = {
  name: 'Locked destructive action',
  parameters: {
    docs: {
      description: {
        story:
          'Default state: the destructive action remains disabled until the required confirmation phrase is entered. No API request is made by this story.',
      },
    },
  },
};
