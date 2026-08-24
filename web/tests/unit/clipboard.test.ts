import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { copyTextToClipboard } from '@/lib/clipboard';

const originalClipboard = Object.getOwnPropertyDescriptor(navigator, 'clipboard');
const originalExecCommand = (document as Document & { execCommand?: (command: string) => boolean }).execCommand;

function setClipboard(value: { writeText: (text: string) => Promise<void> } | undefined): void {
  Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value,
  });
}

function setExecCommand(value: ((command: string) => boolean) | undefined): void {
  Object.defineProperty(document, 'execCommand', {
    configurable: true,
    writable: true,
    value,
  });
}

describe('copyTextToClipboard', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  afterEach(() => {
    vi.restoreAllMocks();
    if (originalClipboard) {
      Object.defineProperty(navigator, 'clipboard', originalClipboard);
    } else {
      setClipboard(undefined);
    }
    setExecCommand(originalExecCommand);
    document.body.innerHTML = '';
  });

  it('uses the modern Clipboard API when it is available', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    const execCommand = vi.fn(() => true);
    setClipboard({ writeText });
    setExecCommand(execCommand);

    await copyTextToClipboard('Bonjour');

    expect(writeText).toHaveBeenCalledWith('Bonjour');
    expect(execCommand).not.toHaveBeenCalled();
  });

  it('falls back to a temporary textarea when the Clipboard API rejects on local HTTP', async () => {
    const writeText = vi.fn().mockRejectedValue(new DOMException('Not allowed', 'NotAllowedError'));
    const execCommand = vi.fn(() => true);
    const previousFocus = document.createElement('button');
    document.body.appendChild(previousFocus);
    previousFocus.focus();
    setClipboard({ writeText });
    setExecCommand(execCommand);

    await copyTextToClipboard('Message court');

    expect(writeText).toHaveBeenCalledWith('Message court');
    expect(execCommand).toHaveBeenCalledWith('copy');
    expect(document.querySelector('textarea')).toBeNull();
    expect(previousFocus).toHaveFocus();
  });

  it('uses the fallback when navigator.clipboard is unavailable', async () => {
    const execCommand = vi.fn(() => true);
    setClipboard(undefined);
    setExecCommand(execCommand);

    await copyTextToClipboard('Fallback');

    expect(execCommand).toHaveBeenCalledWith('copy');
  });

  it('reports failure when neither clipboard path can copy', async () => {
    setClipboard(undefined);
    setExecCommand(vi.fn(() => false));

    await expect(copyTextToClipboard('Impossible')).rejects.toThrow('Clipboard copy fallback unavailable.');
    expect(document.querySelector('textarea')).toBeNull();
  });
});
