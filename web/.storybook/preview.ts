import type { Preview } from '@storybook/nextjs-vite';

import '../app/globals.css';
import '../app/design-tokens.css';
import '../app/accessibility.css';

const preview: Preview = {
  parameters: {
    a11y: {
      test: 'error',
    },
    controls: {
      expanded: true,
    },
    layout: 'padded',
  },
  tags: ['autodocs'],
};

export default preview;
