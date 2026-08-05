import { describe, expect, it } from 'vitest';

import { connectorRoadmap } from '../../lib/connector-roadmap';

describe('connector roadmap catalog', () => {
  it('uses stable unique source codes', () => {
    const codes = connectorRoadmap.map((connector) => connector.code);

    expect(new Set(codes).size).toBe(codes.length);
    expect(codes).toContain('france-travail');
    expect(codes).toContain('linkedin');
    expect(codes).toContain('indeed');
  });

  it('does not present roadmap entries as operational connectors', () => {
    expect(connectorRoadmap.every((connector) => (
      connector.status === 'PLANNED'
      || connector.status === 'UNDER_REVIEW'
      || connector.status === 'EMAIL_OR_EXTENSION_ONLY'
    ))).toBe(true);
  });

  it('keeps LinkedIn and Indeed restricted to user-authorized channels', () => {
    for (const code of ['linkedin', 'indeed']) {
      const connector = connectorRoadmap.find((entry) => entry.code === code);

      expect(connector).toBeDefined();
      expect(connector?.status).toBe('EMAIL_OR_EXTENSION_ONLY');
      expect(connector?.modes).toEqual(['GMAIL', 'EXTENSION']);
    }
  });

  it('keeps France Travail gated behind official API access', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'france-travail');

    expect(connector).toMatchObject({
      status: 'PLANNED',
      modes: ['API'],
    });
  });
});
