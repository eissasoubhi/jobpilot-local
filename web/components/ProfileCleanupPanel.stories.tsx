import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import { ProfileCleanupPanel } from './ProfileCleanupPanel';

const meta = {
  title: 'Settings/ProfileCleanupPanel',
  component: ProfileCleanupPanel,
  parameters: {
    docs: {
      description: {
        component:
          'Targeted catalog cleanup for JobPilot. The panel makes the destructive scope explicit, preserves tracked application history, and requires a confirmation dialog before any cleanup request can run.',
      },
    },
  },
} satisfies Meta<typeof ProfileCleanupPanel>;

export default meta;
type Story = StoryObj<typeof meta>;

export const SafeDefault: Story = {
  name: 'Safe default',
  parameters: {
    docs: {
      description: {
        story:
          'The destructive action starts in a safe state: its scope and preserved data are visible before the confirmation dialog is opened. Rendering this story performs no API request.',
      },
    },
  },
};
