import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it, vi } from 'vitest';

type QuestionAssistant = {
  attachField(
    element: HTMLElement,
    field: Record<string, unknown>,
    documentRef: Document,
    request: (question: string, language: string, maxLength: number) => Promise<Record<string, unknown>>,
  ): HTMLElement | null;
};

const require = createRequire(import.meta.url);
const assistant = require('../../../extension/question-assistant.js') as QuestionAssistant;

const questionField = {
  controlKind: 'textarea',
  classification: {
    status: 'question',
    key: null,
    questionText: 'Why do you want to join our company?',
  },
};

async function flush(): Promise<void> {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('JobPilot question assistant', () => {
  beforeEach(() => {
    document.documentElement.lang = 'en';
    document.head.innerHTML = '';
    document.body.innerHTML = '<label for="motivation">Why do you want to join our company?</label><textarea id="motivation"></textarea>';
  });

  it('shows an editable suggestion without inserting it automatically', async () => {
    const field = document.getElementById('motivation') as HTMLTextAreaElement;
    const request = vi.fn(async () => ({
      status: 'SUGGESTED',
      source: 'ai',
      suggestion: 'Initial grounded suggestion.',
      confidence: 0.9,
      usedFacts: ['Symfony experience'],
      requiresExplicitInsert: true,
    }));

    const container = assistant.attachField(field, questionField, document, request);
    expect(container).not.toBeNull();

    (container!.querySelector('.jobpilot-question-trigger') as HTMLButtonElement).click();
    await flush();

    expect(field.value).toBe('');
    const editor = container!.querySelector('.jobpilot-question-panel textarea') as HTMLTextAreaElement;
    expect(editor.value).toBe('Initial grounded suggestion.');

    editor.value = 'Edited by the candidate before insertion.';
    const insert = [...container!.querySelectorAll('button')].find((button) => button.textContent === 'Insérer') as HTMLButtonElement;
    insert.click();

    expect(field.value).toBe('Edited by the candidate before insertion.');
    expect(container!.querySelector('.jobpilot-question-panel')).toBeNull();
  });

  it('regenerates on explicit request and still does not overwrite the application field', async () => {
    const field = document.getElementById('motivation') as HTMLTextAreaElement;
    let call = 0;
    const request = vi.fn(async () => {
      call += 1;
      return {
        status: 'SUGGESTED',
        source: 'ai',
        suggestion: call === 1 ? 'First draft.' : 'Second draft.',
        confidence: 0.85,
        usedFacts: [],
        requiresExplicitInsert: true,
      };
    });

    const container = assistant.attachField(field, questionField, document, request)!;
    (container.querySelector('.jobpilot-question-trigger') as HTMLButtonElement).click();
    await flush();

    const regenerate = [...container.querySelectorAll('button')].find((button) => button.textContent === 'Régénérer') as HTMLButtonElement;
    regenerate.click();
    await flush();

    expect(request).toHaveBeenCalledTimes(2);
    expect((container.querySelector('.jobpilot-question-panel textarea') as HTMLTextAreaElement).value).toBe('Second draft.');
    expect(field.value).toBe('');
  });

  it('shows manual-review status without offering an insert action', async () => {
    const field = document.getElementById('motivation') as HTMLTextAreaElement;
    const request = vi.fn(async () => ({
      status: 'MANUAL_REVIEW',
      source: 'policy',
      message: 'Answer this question manually.',
    }));

    const container = assistant.attachField(field, questionField, document, request)!;
    (container.querySelector('.jobpilot-question-trigger') as HTMLButtonElement).click();
    await flush();

    expect(container.textContent).toContain('Answer this question manually.');
    expect([...container.querySelectorAll('button')].some((button) => button.textContent === 'Insérer')).toBe(false);
    expect(field.value).toBe('');
  });

  it('does not attach to recognized profile fields or non-text controls', () => {
    const field = document.getElementById('motivation') as HTMLTextAreaElement;

    expect(assistant.attachField(field, {
      controlKind: 'textarea',
      classification: { status: 'recognized', key: 'preferences.availability', questionText: null },
    }, document, vi.fn())).toBeNull();

    field.removeAttribute('data-jobpilot-question-assistant');
    expect(assistant.attachField(field, {
      controlKind: 'radio',
      classification: { status: 'question', key: null, questionText: 'Would you relocate?' },
    }, document, vi.fn())).toBeNull();
  });
});
