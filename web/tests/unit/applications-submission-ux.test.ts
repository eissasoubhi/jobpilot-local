import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

describe('application submission tracking UX', () => {
  it('marks an application as submitted without a browser confirmation dialog', () => {
    const pageSource = readFileSync(resolve(process.cwd(), 'app/candidatures/page.tsx'), 'utf8');

    expect(pageSource).not.toContain('window.confirm');
    expect(pageSource).toContain("await save(\n      'SUBMITTED'");
    expect(pageSource).toContain('met immédiatement à jour le suivi dans JobPilot sans ouvrir de confirmation');
  });
});
