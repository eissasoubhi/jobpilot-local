(function exposeJobPilotDocumentUploader(root, factory) {
  const uploader = factory();
  root.JobPilotDocumentUploader = uploader;
  if (typeof module !== 'undefined' && module.exports) module.exports = uploader;
})(typeof globalThis !== 'undefined' ? globalThis : this, function createJobPilotDocumentUploader() {
  const CV_TERMS = ['cv', 'resume', 'résumé', 'curriculum vitae'];
  const COVER_TERMS = ['cover letter', 'motivation letter', 'lettre de motivation', 'lettre motivation'];

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[’']/g, ' ')
      .replace(/[^a-z0-9.]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function fieldText(element) {
    const labels = element.labels ? [...element.labels].map(label => label.textContent || '').join(' ') : '';
    const labelledBy = String(element.getAttribute?.('aria-labelledby') || '')
      .split(/\s+/)
      .map(id => element.ownerDocument?.getElementById(id)?.textContent || '')
      .join(' ');
    const legend = element.closest?.('fieldset')?.querySelector(':scope > legend')?.textContent || '';

    return normalize([
      labels,
      labelledBy,
      element.getAttribute?.('aria-label'),
      element.getAttribute?.('name'),
      element.id,
      element.getAttribute?.('placeholder'),
      legend,
    ].filter(Boolean).join(' '));
  }

  function containsTerm(text, terms) {
    return terms.some(term => {
      const normalized = normalize(term);
      return text === normalized || ` ${text} `.includes(` ${normalized} `) || text.includes(normalized);
    });
  }

  function classifyFileField(element) {
    const text = fieldText(element);
    const cv = containsTerm(text, CV_TERMS);
    const coverLetter = containsTerm(text, COVER_TERMS);
    if (cv === coverLetter) return null;
    return cv ? 'cv' : 'coverLetter';
  }

  function extensionOf(filename) {
    const match = String(filename || '').toLowerCase().match(/\.([a-z0-9]+)$/);
    return match ? `.${match[1]}` : '';
  }

  function acceptTokens(accept) {
    return String(accept || '')
      .split(',')
      .map(token => token.trim().toLowerCase())
      .filter(Boolean);
  }

  function acceptsDocument(accept, documentMeta) {
    const tokens = acceptTokens(accept);
    if (tokens.length === 0) return true;

    const mimeType = String(documentMeta?.mimeType || '').toLowerCase();
    const extension = extensionOf(documentMeta?.filename || '');

    return tokens.some(token => {
      if (token.startsWith('.')) return token === extension;
      if (token.endsWith('/*')) return mimeType.startsWith(token.slice(0, -1));
      return token === mimeType;
    });
  }

  function coverFilename(context, variant) {
    const job = context?.job || {};
    const raw = ['Lettre-motivation', job.company, job.title].filter(Boolean).join('_');
    const safe = String(raw || 'Lettre-motivation')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^A-Za-z0-9._-]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 150) || 'Lettre-motivation';
    return `${safe}.${variant.format}`;
  }

  function chooseCoverVariant(context, accept) {
    const variants = context?.coverLetter?.variants || [];
    const ordered = ['pdf', 'docx', 'txt']
      .map(format => variants.find(variant => variant.format === format))
      .filter(Boolean);

    for (const variant of ordered) {
      const documentMeta = {
        ...variant,
        filename: coverFilename(context, variant),
      };
      if (acceptsDocument(accept, documentMeta)) return documentMeta;
    }

    return null;
  }

  function runtimeFetchDocument(meta) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage({
        type: 'FETCH_APPLICATION_DOCUMENT',
        downloadUrl: meta.downloadUrl,
        filename: meta.filename,
        mimeType: meta.mimeType,
      }, response => {
        if (chrome.runtime.lastError) {
          reject(new Error(chrome.runtime.lastError.message));
          return;
        }
        if (!response?.ok) {
          reject(new Error(response?.error || 'Document indisponible.'));
          return;
        }
        resolve(response.document);
      });
    });
  }

  function assignFile(input, payload, DataTransferClass = globalThis.DataTransfer) {
    if (!DataTransferClass) throw new Error('DataTransfer indisponible dans ce navigateur.');
    const bytes = new Uint8Array(Array.isArray(payload.bytes) ? payload.bytes : []);
    if (bytes.length === 0) throw new Error('Le document téléchargé est vide.');

    const file = new File([bytes], payload.filename || 'document', {
      type: payload.mimeType || 'application/octet-stream',
      lastModified: Date.now(),
    });
    const transfer = new DataTransferClass();
    transfer.items.add(file);
    input.files = transfer.files;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));

    return file;
  }

  function setNativeTextValue(element, value) {
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    const setter = Object.getOwnPropertyDescriptor(prototype, 'value')?.set;
    setter ? setter.call(element, value) : (element.value = value);
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function classifyCoverLetterTextField(element) {
    if (!['textarea', 'input'].includes(element.tagName?.toLowerCase())) return false;
    if (element.tagName?.toLowerCase() === 'input' && !['', 'text'].includes(normalize(element.getAttribute('type') || 'text'))) return false;
    return containsTerm(fieldText(element), COVER_TERMS);
  }

  async function upload(documentRef, context, fetchDocument = runtimeFetchDocument) {
    const report = [];

    for (const input of [...documentRef.querySelectorAll('input[type="file"]')]) {
      const role = classifyFileField(input);
      if (input.disabled) {
        report.push({ role, status: 'skipped', reason: 'disabled' });
        continue;
      }
      if (input.files?.length) {
        report.push({ role, status: 'preserved', reason: 'already-selected' });
        continue;
      }
      if (role === null) {
        report.push({ role: null, status: 'review', reason: 'unknown-document-field' });
        continue;
      }

      let meta = null;
      if (role === 'cv' && context?.cv) {
        meta = {
          filename: context.cv.filename,
          mimeType: context.cv.mimeType,
          downloadUrl: context.cv.downloadUrl,
        };
        if (!acceptsDocument(input.accept, meta)) {
          report.push({ role, status: 'review', reason: 'cv-format-not-accepted', filename: meta.filename });
          continue;
        }
      }
      if (role === 'coverLetter') {
        meta = chooseCoverVariant(context, input.accept);
      }

      if (!meta) {
        report.push({ role, status: 'skipped', reason: role === 'cv' ? 'missing-cv' : 'missing-compatible-cover-letter' });
        continue;
      }

      try {
        const payload = await fetchDocument(meta);
        const file = assignFile(input, payload);
        report.push({ role, status: 'uploaded', filename: file.name, mimeType: file.type });
      } catch (error) {
        report.push({
          role,
          status: 'review',
          reason: error instanceof Error ? error.message : String(error),
          filename: meta.filename,
        });
      }
    }

    const coverLetterText = String(context?.coverLetter?.text || '').trim();
    if (coverLetterText !== '') {
      for (const element of [...documentRef.querySelectorAll('textarea, input[type="text"]')]) {
        if (!classifyCoverLetterTextField(element)) continue;
        if (element.disabled || element.readOnly) {
          report.push({ role: 'coverLetterText', status: 'skipped', reason: 'disabled-or-read-only' });
          continue;
        }
        if (String(element.value || '').trim() !== '') {
          report.push({ role: 'coverLetterText', status: 'preserved', reason: 'already-filled' });
          continue;
        }
        setNativeTextValue(element, coverLetterText);
        report.push({ role: 'coverLetterText', status: 'filled' });
      }
    }

    return {
      schemaVersion: 1,
      uploaded: report.filter(item => item.status === 'uploaded').length,
      textFilled: report.filter(item => item.status === 'filled').length,
      preserved: report.filter(item => item.status === 'preserved').length,
      review: report.filter(item => item.status === 'review').length,
      skipped: report.filter(item => item.status === 'skipped').length,
      items: report,
    };
  }

  return {
    acceptsDocument,
    chooseCoverVariant,
    classifyCoverLetterTextField,
    classifyFileField,
    upload,
  };
});
