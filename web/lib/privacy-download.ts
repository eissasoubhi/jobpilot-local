export type PrivacyDownloadResult = {
  privacyClean: boolean;
  fallbackUsed: boolean;
};

type PrivacyDownloadOptions = {
  url: string;
  filename: string;
  fetchImpl?: typeof fetch;
};

function triggerDownload(href: string, filename: string): void {
  const anchor = document.createElement('a');
  anchor.href = href;
  anchor.download = filename;
  anchor.rel = 'noreferrer';
  anchor.referrerPolicy = 'no-referrer';
  anchor.style.display = 'none';
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
}

function bytesToBase64(bytes: Uint8Array): string {
  let binary = '';
  const chunkSize = 0x8000;

  for (let offset = 0; offset < bytes.length; offset += chunkSize) {
    binary += String.fromCharCode(...bytes.subarray(offset, offset + chunkSize));
  }

  return window.btoa(binary);
}

async function blobToDataUrl(blob: Blob): Promise<string> {
  const bytes = new Uint8Array(await blob.arrayBuffer());

  const mimeType = blob.type.trim() || 'application/octet-stream';
  return `data:${mimeType};base64,${bytesToBase64(bytes)}`;
}

function serializeForInlineScript(value: string): string {
  return JSON.stringify(value)
    .replaceAll('<', '\\u003c')
    .replaceAll('\u2028', '\\u2028')
    .replaceAll('\u2029', '\\u2029');
}

function triggerIsolatedDataDownload(href: string, filename: string): void {
  const iframe = document.createElement('iframe');
  const documentSource = [
    '<!doctype html>',
    '<meta name="referrer" content="no-referrer">',
    '<body>',
    '<script>',
    `const anchor = document.createElement('a');`,
    `anchor.href = ${serializeForInlineScript(href)};`,
    `anchor.download = ${serializeForInlineScript(filename)};`,
    `anchor.rel = 'noreferrer';`,
    `anchor.referrerPolicy = 'no-referrer';`,
    `document.body.appendChild(anchor);`,
    `anchor.click();`,
    '<\/script>',
    '</body>',
  ].join('');

  iframe.hidden = true;
  iframe.referrerPolicy = 'no-referrer';
  iframe.setAttribute('sandbox', 'allow-scripts allow-downloads');
  iframe.src = `data:text/html;charset=utf-8;base64,${bytesToBase64(new TextEncoder().encode(documentSource))}`;
  iframe.addEventListener('load', () => {
    window.setTimeout(() => iframe.remove(), 1_000);
  }, { once: true });
  document.body.appendChild(iframe);
}

/**
 * Downloads a small, same-origin generated export from a sandboxed data: document.
 * Both the downloaded URL and its initiating document therefore have no HTTP(S)
 * authority for Chromium to persist as macOS download provenance.
 *
 * This deliberately does not disable browser/macOS quarantine protections. If the
 * privacy-clean path is unavailable, it falls back to the normal URL download.
 */
export async function downloadWithCleanProvenance({
  url,
  filename,
  fetchImpl = fetch,
}: PrivacyDownloadOptions): Promise<PrivacyDownloadResult> {
  try {
    const response = await fetchImpl(url, {
      credentials: 'same-origin',
      referrerPolicy: 'no-referrer',
      headers: {
        Accept: 'application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,*/*',
      },
    });

    if (!response.ok) {
      throw new Error(`Téléchargement impossible (${response.status}).`);
    }

    const blob = await response.blob();
    const dataUrl = await blobToDataUrl(blob);
    triggerIsolatedDataDownload(dataUrl, filename);

    return { privacyClean: true, fallbackUsed: false };
  } catch {
    triggerDownload(url, filename);

    return { privacyClean: false, fallbackUsed: true };
  }
}
