import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionDirectory = path.resolve(testDirectory, '../../../extension');

function read(name: string): string {
  return fs.readFileSync(path.join(extensionDirectory, name), 'utf8');
}

describe('JobPilot extension security contract', () => {
  it('keeps permanent Chrome permissions minimal', () => {
    const manifest = JSON.parse(read('manifest.json')) as {
      permissions?: string[];
      host_permissions?: string[];
      content_scripts?: unknown;
      content_security_policy?: { extension_pages?: string };
    };

    expect(manifest.permissions).toEqual(['activeTab', 'storage', 'scripting']);
    expect(manifest.host_permissions).toEqual([
      'http://localhost:8080/*',
      'http://127.0.0.1:8080/*',
    ]);
    expect(JSON.stringify(manifest)).not.toContain('<all_urls>');
    expect(manifest.content_scripts).toBeUndefined();
    expect(manifest.content_security_policy?.extension_pages).toBe("script-src 'self'; object-src 'self'");
  });

  it('injects only after an explicit popup action', () => {
    const popup = read('popup.js');
    const popupHtml = read('popup.html');
    const plan = read('injection-plan.js');

    expect(popupHtml.indexOf('injection-plan.js')).toBeGreaterThanOrEqual(0);
    expect(popupHtml.indexOf('injection-plan.js')).toBeLessThan(popupHtml.indexOf('popup.js'));
    expect(popup).toContain('chrome.scripting.executeScript');
    expect(popup).toContain("ensureInjected(tab, 'import')");
    expect(popup).toContain("ensureInjected(tab, 'autofill')");
    expect(plan).toContain("scripts: ['import-ready.js', 'content.js']");
    expect(plan).toContain("'correction-learning.js'");
    expect(plan).toContain("'question-assistant.js'");
  });

  it('never falls back to persistent local storage for tab context', () => {
    const background = read('background.js');

    expect(background).toContain('chrome.storage?.session');
    expect(background).toContain('volatileTabContexts');
    expect(background).not.toContain('chrome.storage.local');
  });
});
