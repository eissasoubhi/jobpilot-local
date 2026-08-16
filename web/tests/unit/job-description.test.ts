import { describe, expect, it } from 'vitest';

import { jobDescriptionToPlainText } from '../../lib/job-description';

describe('jobDescriptionToPlainText', () => {
  it('turns HTML job-board descriptions into readable plain text', () => {
    const html = '<p class="lexical__paragraph"><strong>Join Proton and build a better internet where privacy is the default</strong></p>'
      + '<p>Founded in 2014 by scientists from CERN on a simple truth: <strong>privacy is a fundamental human right</strong>.</p>'
      + '<p>We move fast, keep hierarchy light, and prioritize impact over optics.</p>';

    const text = jobDescriptionToPlainText(html);

    expect(text).toContain('Join Proton and build a better internet where privacy is the default');
    expect(text).toContain('privacy is a fundamental human right');
    expect(text).toContain('\n');
    expect(text).not.toContain('<p');
    expect(text).not.toContain('<strong>');
  });

  it('preserves list structure and decodes common entities', () => {
    const html = '<p>Stack&nbsp;principale &amp; outils :</p><ul><li>PHP &gt; 8</li><li>React &#38; TypeScript</li></ul>';

    expect(jobDescriptionToPlainText(html)).toBe(
      'Stack principale & outils :\n• PHP > 8\n• React & TypeScript',
    );
  });

  it('removes non-content markup instead of exposing or rendering it', () => {
    const html = '<style>.hidden{display:none}</style><script>alert("xss")</script><p>Mission sûre</p>';
    const text = jobDescriptionToPlainText(html);

    expect(text).toBe('Mission sûre');
    expect(text).not.toContain('alert');
    expect(text).not.toContain('.hidden');
  });

  it('keeps ordinary plain text readable', () => {
    expect(jobDescriptionToPlainText('Backend Symfony\nReact et API REST')).toBe(
      'Backend Symfony\nReact et API REST',
    );
  });
});
