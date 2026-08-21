import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionDirectory = path.resolve(testDirectory, '../../../extension');

function read(name: string): string {
  return fs.readFileSync(path.join(extensionDirectory, name), 'utf8');
}

describe('Autofill correction management', () => {
  it('exposes a discoverable options page without widening Chrome permissions', () => {
    const manifest = JSON.parse(read('manifest.json')) as {
      options_page?: string;
      permissions?: string[];
      host_permissions?: string[];
    };
    const popup = read('popup.js');

    expect(manifest.options_page).toBe('learned-corrections.html');
    expect(manifest.permissions).toEqual(['activeTab', 'storage', 'scripting']);
    expect(manifest.host_permissions).toEqual([
      'http://localhost:8080/*',
      'http://127.0.0.1:8080/*',
    ]);
    expect(popup).toContain('chrome.runtime.openOptionsPage');
  });

  it('manages server corrections through background messages only', () => {
    const manager = read('learned-corrections.js');
    const background = read('background.js');

    expect(manager).toContain("type: 'LIST_AUTOFILL_CORRECTIONS'");
    expect(manager).toContain("type: 'SET_AUTOFILL_CORRECTION_ENABLED'");
    expect(manager).toContain("type: 'DELETE_AUTOFILL_CORRECTION'");
    expect(manager).not.toContain('chrome.storage.local');
    expect(manager).not.toContain('localStorage');

    expect(background).toContain('includeDisabled=1');
    expect(background).toContain("message.type === 'LIST_AUTOFILL_CORRECTIONS'");
    expect(background).toContain("message.type === 'SET_AUTOFILL_CORRECTION_ENABLED'");
    expect(background).toContain("message.type === 'DELETE_AUTOFILL_CORRECTION'");
  });

  it('keeps the options UI focused on explicit domain-scoped corrections', () => {
    const html = read('learned-corrections.html');

    expect(html).toContain('Domaine du site de candidature');
    expect(html).toContain('Corrections apprises');
    expect(html).toContain('désactive ou supprime');
    expect(html).not.toContain('type="submit" data-delete');
  });
});
