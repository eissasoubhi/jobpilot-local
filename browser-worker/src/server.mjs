import http from 'node:http';
import { chromium } from 'playwright';

import {
  assertPublicHostname,
  normalizeRenderRequest,
  shouldBlockRequest,
  validateNavigationUrl,
} from './policy.mjs';

const HOST = process.env.BROWSER_WORKER_HOST || '0.0.0.0';
const PORT = Math.min(65535, Math.max(1, Number(process.env.BROWSER_WORKER_PORT || 3100)));
const TOKEN = String(process.env.JOBPILOT_BROWSER_WORKER_TOKEN || '').trim();
const USER_AGENT = 'JobPilotBrowserWorker/1.0 (+public-job-rendering; no-login; read-only)';

if (TOKEN.length < 24) {
  throw new Error('JOBPILOT_BROWSER_WORKER_TOKEN must contain at least 24 characters.');
}

const server = http.createServer(async (request, response) => {
  try {
    if (request.method === 'GET' && request.url === '/health') {
      return json(response, 200, { status: 'ok' });
    }
    if (request.method !== 'POST' || request.url !== '/render') {
      return json(response, 404, { error: 'Not found.' });
    }
    if (!authorized(request)) {
      return json(response, 401, { error: 'Unauthorized.' });
    }

    const payload = await readJson(request, 64 * 1024);
    const input = normalizeRenderRequest(payload);
    await assertPublicHostname(input.allowedDomain);

    const result = await render(input);
    return json(response, 200, result);
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Browser rendering failed.';
    return json(response, 422, { error: message });
  }
});

server.listen(PORT, HOST, () => {
  process.stdout.write(`JobPilot browser worker listening on ${HOST}:${PORT}\n`);
});

async function render(input) {
  const browser = await chromium.launch({
    headless: true,
    args: ['--disable-dev-shm-usage'],
  });

  try {
    const context = await browser.newContext({
      acceptDownloads: false,
      javaScriptEnabled: true,
      userAgent: USER_AGENT,
      serviceWorkers: 'block',
    });
    const page = await context.newPage();
    let blockedRequests = 0;
    let allowedRequests = 0;

    page.on('dialog', (dialog) => void dialog.dismiss());
    page.on('download', (download) => void download.cancel());

    await page.route('**/*', async (route) => {
      const request = route.request();
      const url = request.url();
      const decision = shouldBlockRequest({
        url,
        method: request.method(),
        resourceType: request.resourceType(),
        isNavigationRequest: request.isNavigationRequest(),
        frameIsMain: request.frame() === page.mainFrame(),
      }, input.allowedDomain);

      if (decision.block) {
        ++blockedRequests;
        return route.abort('blockedbyclient');
      }

      try {
        const parsed = new URL(url);
        if (['https:', 'http:'].includes(parsed.protocol)) {
          await assertPublicHostname(parsed.hostname);
        }
      } catch {
        ++blockedRequests;
        return route.abort('blockedbyclient');
      }

      ++allowedRequests;
      return route.continue();
    });

    const navigationResponse = await page.goto(input.url, {
      waitUntil: 'domcontentloaded',
      timeout: input.timeoutMs,
    });
    if (input.settleMs > 0) {
      await page.waitForTimeout(input.settleMs);
    }

    const finalUrl = page.url();
    validateNavigationUrl(finalUrl, input.allowedDomain);
    const html = await page.content();
    const htmlBytes = Buffer.byteLength(html, 'utf8');
    if (htmlBytes > input.maxHtmlBytes) {
      throw new Error(`Rendered HTML exceeds the ${input.maxHtmlBytes} byte limit.`);
    }

    return {
      sourceCode: input.sourceCode,
      requestedUrl: input.url,
      finalUrl,
      statusCode: navigationResponse?.status() ?? null,
      title: (await page.title()).slice(0, 500),
      html,
      htmlBytes,
      allowedRequests,
      blockedRequests,
    };
  } finally {
    await browser.close();
  }
}

function authorized(request) {
  const value = String(request.headers.authorization || '');
  return value === `Bearer ${TOKEN}`;
}

async function readJson(request, maxBytes) {
  const chunks = [];
  let total = 0;
  for await (const chunk of request) {
    total += chunk.length;
    if (total > maxBytes) throw new Error('Request body is too large.');
    chunks.push(chunk);
  }

  const body = Buffer.concat(chunks).toString('utf8');
  try {
    return JSON.parse(body || '{}');
  } catch {
    throw new Error('Invalid JSON request body.');
  }
}

function json(response, status, payload) {
  const body = JSON.stringify(payload);
  response.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(body),
    'cache-control': 'no-store',
  });
  response.end(body);
}
