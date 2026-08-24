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

async function blobToDataUrl(blob: Blob): Promise<string> {
  const bytes = new Uint8Array(await blob.arrayBuffer());
  let binary = '';
  const chunkSize = 0x8000;

  for (let offset = 0; offset < bytes.length; offset += chunkSize) {
    binary += String.fromCharCode(...bytes.subarray(offset, offset + chunkSize));
  }

  const mimeType = blob.type.trim() || 'application/octet-stream';
  return `data:${mimeType};base64,${window.btoa(binary)}`;
}

/**
 * Downloads a small, same-origin generated export through a data: URL so Chromium
 * does not need to persist the internal JobPilot route as download provenance.
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
    triggerDownload(dataUrl, filename);

    return { privacyClean: true, fallbackUsed: false };
  } catch {
    triggerDownload(url, filename);

    return { privacyClean: false, fallbackUsed: true };
  }
}
