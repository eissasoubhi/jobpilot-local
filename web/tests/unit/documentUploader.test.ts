import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it, vi } from 'vitest';

type DocumentMeta = { filename?: string; mimeType?: string; downloadUrl?: string; format?: string };
type UploadReport = {
  uploaded: number;
  textFilled: number;
  preserved: number;
  review: number;
  skipped: number;
  items: Array<{ role: string | null; status: string; reason?: string; filename?: string }>;
};
type Uploader = {
  acceptsDocument(accept: string, meta: DocumentMeta): boolean;
  chooseCoverVariant(context: Record<string, unknown>, accept: string): DocumentMeta | null;
  classifyCoverLetterTextField(element: Element): boolean;
  classifyFileField(element: Element): 'cv' | 'coverLetter' | null;
  upload(documentRef: Document, context: Record<string, unknown>, fetchDocument?: (meta: DocumentMeta) => Promise<Record<string, unknown>>): Promise<UploadReport>;
};

const require = createRequire(import.meta.url);
const uploader = require('../../../extension/document-uploader.js') as Uploader;

class FakeDataTransfer {
  files: File[] = [];
  items = {
    add: (file: File) => {
      this.files = [file];
      return file;
    },
  };
}

describe('JobPilot document uploader', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    Object.defineProperty(globalThis, 'DataTransfer', {
      configurable: true,
      value: FakeDataTransfer,
    });
  });

  it('distinguishes CV, cover letter and unknown file fields', () => {
    document.body.innerHTML = `
      <label for="cv">CV / Résumé</label><input id="cv" type="file" name="resume">
      <label for="letter">Lettre de motivation</label><input id="letter" type="file" name="cover_letter">
      <label for="other">Pièce complémentaire</label><input id="other" type="file" name="attachment">
    `;

    expect(uploader.classifyFileField(document.getElementById('cv')!)).toBe('cv');
    expect(uploader.classifyFileField(document.getElementById('letter')!)).toBe('coverLetter');
    expect(uploader.classifyFileField(document.getElementById('other')!)).toBeNull();
  });

  it('respects accept and picks the first compatible cover-letter format', () => {
    const context = {
      job: { company: 'Example', title: 'Senior Symfony' },
      coverLetter: {
        variants: [
          { format: 'pdf', mimeType: 'application/pdf', downloadUrl: '/api/letter/pdf' },
          { format: 'docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', downloadUrl: '/api/letter/docx' },
          { format: 'txt', mimeType: 'text/plain', downloadUrl: '/api/letter/txt' },
        ],
      },
    };

    expect(uploader.acceptsDocument('.pdf', { filename: 'cv.pdf', mimeType: 'application/pdf' })).toBe(true);
    expect(uploader.acceptsDocument('.docx', { filename: 'cv.pdf', mimeType: 'application/pdf' })).toBe(false);
    expect(uploader.chooseCoverVariant(context, '.docx')?.format).toBe('docx');
    expect(uploader.chooseCoverVariant(context, 'application/pdf')?.format).toBe('pdf');
    expect(uploader.chooseCoverVariant(context, '.odt')).toBeNull();
  });

  it('uploads the selected CV without guessing another document', async () => {
    document.body.innerHTML = '<label for="cv">CV</label><input id="cv" type="file" accept="application/pdf">';
    const input = document.getElementById('cv') as HTMLInputElement;
    Object.defineProperty(input, 'files', { configurable: true, writable: true, value: [] });

    const fetchDocument = vi.fn(async (meta: DocumentMeta) => ({
      bytes: [37, 80, 68, 70],
      filename: meta.filename,
      mimeType: meta.mimeType,
    }));

    const report = await uploader.upload(document, {
      cv: {
        filename: 'cv-symfony.pdf',
        mimeType: 'application/pdf',
        downloadUrl: '/api/cvs/1/download',
      },
      coverLetter: null,
    }, fetchDocument);

    expect(fetchDocument).toHaveBeenCalledTimes(1);
    expect(fetchDocument.mock.calls[0][0].downloadUrl).toBe('/api/cvs/1/download');
    expect((input.files as unknown as File[])[0].name).toBe('cv-symfony.pdf');
    expect(report.uploaded).toBe(1);
    expect(report.review).toBe(0);
  });

  it('refuses an incompatible CV and leaves an unknown attachment for review', async () => {
    document.body.innerHTML = `
      <label for="cv">CV</label><input id="cv" type="file" accept=".docx">
      <label for="other">Other attachment</label><input id="other" type="file">
    `;

    const report = await uploader.upload(document, {
      cv: {
        filename: 'cv.pdf',
        mimeType: 'application/pdf',
        downloadUrl: '/api/cvs/1/download',
      },
    }, vi.fn());

    expect(report.uploaded).toBe(0);
    expect(report.review).toBe(2);
    expect(report.items.some(item => item.reason === 'cv-format-not-accepted')).toBe(true);
    expect(report.items.some(item => item.reason === 'unknown-document-field')).toBe(true);
  });

  it('fills an empty cover-letter textarea and preserves existing text', async () => {
    document.body.innerHTML = `
      <label for="letter">Cover letter</label><textarea id="letter"></textarea>
      <label for="existing">Lettre de motivation</label><textarea id="existing">Texte déjà saisi</textarea>
    `;

    const report = await uploader.upload(document, {
      coverLetter: {
        text: 'Dear hiring team, this is my tailored cover letter.',
        variants: [],
      },
    }, vi.fn());

    expect((document.getElementById('letter') as HTMLTextAreaElement).value).toContain('Dear hiring team');
    expect((document.getElementById('existing') as HTMLTextAreaElement).value).toBe('Texte déjà saisi');
    expect(report.textFilled).toBe(1);
    expect(report.preserved).toBe(1);
  });
});
