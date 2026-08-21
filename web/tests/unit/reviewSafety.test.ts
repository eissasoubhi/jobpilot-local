import { beforeEach, describe, expect, it, vi } from 'vitest';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const safety = require('../../../extension/review-safety.js');

describe('JobPilot autofill review safety', () => {
  beforeEach(() => {
    document.body.innerHTML = '<label for="first">Prénom</label><input id="first" name="first_name"><label for="salary">Salaire</label><input id="salary" name="salary">';
  });

  it('separates high confidence, review and sensitive fields without ever authorizing submit', () => {
    const summary = safety.summarize({
      fields: [
        { fillStatus: 'filled', classification: { status: 'recognized', key: 'identity.firstName', confidence: 0.96 }, label: 'Prénom' },
        { fillStatus: 'review', classification: { status: 'ambiguous', key: null, confidence: 0.4 }, label: 'Question libre' },
        { fillStatus: 'review', classification: { status: 'recognized', key: 'preferences.desiredSalary', confidence: 0.99 }, label: 'Salaire souhaité' },
      ],
    });

    expect(summary.high).toBe(1);
    expect(summary.review).toBe(1);
    expect(summary.sensitive).toBe(1);
    expect(summary.canSubmitAutomatically).toBe(false);
  });

  it('renders a review panel with risky fields and no submit action', () => {
    const summary = safety.render(document, {
      fields: [
        { fillStatus: 'filled', classification: { status: 'recognized', key: 'identity.firstName', confidence: 0.8 }, label: 'Prénom' },
        { fillStatus: 'review', fillReason: 'sensitive-answer', classification: { status: 'recognized', key: 'preferences.desiredSalary', confidence: 0.99 }, label: 'Salaire souhaité' },
      ],
    });

    expect(summary.medium).toBe(1);
    expect(summary.sensitive).toBe(1);
    const host = document.getElementById(safety.PANEL_ID) as HTMLElement;
    expect(host).not.toBeNull();
    const root = host.shadowRoot || host;
    expect(root.textContent).toContain('Vérifie avant d’envoyer');
    expect(root.textContent).toContain('Salaire souhaité');
    expect(root.querySelector('button[type="submit"]')).toBeNull();
  });

  it('stores only technical counters and hostname in the audit log', async () => {
    let stored: Record<string, unknown> = {};
    const storage = {
      get: vi.fn().mockResolvedValue({}),
      set: vi.fn().mockImplementation(async (value: Record<string, unknown>) => { stored = value; }),
    };
    vi.stubGlobal('chrome', { storage: { local: storage } });

    await safety.recordAudit({
      detected: 2,
      filled: 1,
      review: 1,
      learnedRulesApplied: 1,
      fields: [
        { fillStatus: 'filled', valuePreview: 'Aissa', classification: { status: 'recognized', key: 'identity.firstName', confidence: 0.98 }, label: 'Prénom' },
        { fillStatus: 'review', valuePreview: '60000', classification: { status: 'recognized', key: 'preferences.desiredSalary', confidence: 0.99 }, label: 'Salaire souhaité' },
      ],
    }, 'https://jobs.example.com/apply?candidate=secret');

    const entries = stored[safety.AUDIT_KEY] as Array<Record<string, unknown>>;
    expect(entries).toHaveLength(1);
    expect(entries[0].domain).toBe('jobs.example.com');
    expect(entries[0].sensitive).toBe(1);
    expect(JSON.stringify(entries[0])).not.toContain('Aissa');
    expect(JSON.stringify(entries[0])).not.toContain('60000');
    expect(JSON.stringify(entries[0])).not.toContain('candidate=secret');
  });
});
