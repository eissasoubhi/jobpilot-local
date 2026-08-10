import assert from 'node:assert/strict';
import test from 'node:test';

import {
  assertPublicHostname,
  isPrivateIp,
  normalizeAllowedDomain,
  normalizeRenderRequest,
  shouldBlockRequest,
  validateNavigationUrl,
} from '../src/policy.mjs';

test('normalizes and validates the authorized HTTPS domain', () => {
  assert.equal(normalizeAllowedDomain(' Jobs.Example.TEST. '), 'jobs.example.test');
  assert.equal(
    validateNavigationUrl('https://jobs.example.test/offres?page=2', 'jobs.example.test').toString(),
    'https://jobs.example.test/offres?page=2',
  );
  assert.throws(() => validateNavigationUrl('http://jobs.example.test/offres', 'jobs.example.test'), /HTTPS/);
  assert.throws(() => validateNavigationUrl('https://other.example.test/offres', 'jobs.example.test'), /authorized domain/);
});

test('requires explicit authorization and robots approval from the API caller', () => {
  assert.throws(() => normalizeRenderRequest({
    url: 'https://jobs.example.test/offres',
    allowedDomain: 'jobs.example.test',
    authorizationApproved: true,
    robotsApproved: false,
  }), /robots approval/);

  const request = normalizeRenderRequest({
    sourceCode: 'custom-scraper-12',
    url: 'https://jobs.example.test/offres',
    allowedDomain: 'jobs.example.test',
    authorizationApproved: true,
    robotsApproved: true,
    timeoutMs: 60_000,
    settleMs: 10_000,
    maxHtmlBytes: 50_000_000,
  });

  assert.equal(request.timeoutMs, 15_000);
  assert.equal(request.settleMs, 2_000);
  assert.equal(request.maxHtmlBytes, 3_000_000);
});

test('blocks write requests, heavy resources and cross-domain top-level navigation', () => {
  assert.deepEqual(shouldBlockRequest({
    url: 'https://jobs.example.test/api/search',
    method: 'POST',
    resourceType: 'fetch',
    isNavigationRequest: false,
    frameIsMain: false,
  }, 'jobs.example.test'), { block: true, reason: 'NON_READ_METHOD' });

  assert.deepEqual(shouldBlockRequest({
    url: 'https://cdn.example.test/hero.jpg',
    method: 'GET',
    resourceType: 'image',
    isNavigationRequest: false,
    frameIsMain: false,
  }, 'jobs.example.test'), { block: true, reason: 'HEAVY_RESOURCE' });

  assert.deepEqual(shouldBlockRequest({
    url: 'https://login.example.net/',
    method: 'GET',
    resourceType: 'document',
    isNavigationRequest: true,
    frameIsMain: true,
  }, 'jobs.example.test'), { block: true, reason: 'CROSS_DOMAIN_NAVIGATION' });
});

test('recognizes private and reserved IP destinations', () => {
  for (const address of ['127.0.0.1', '10.20.30.40', '172.16.0.1', '192.168.1.1', '169.254.10.1', '::1', 'fd00::1', 'fe80::1', '2001:db8::1']) {
    assert.equal(isPrivateIp(address), true, address);
  }
  assert.equal(isPrivateIp('8.8.8.8'), false);
  assert.equal(isPrivateIp('2606:4700:4700::1111'), false);
});

test('rejects DNS names resolving to any private destination', async () => {
  await assert.rejects(
    assertPublicHostname('jobs.example.test', async () => [
      { address: '93.184.216.34', family: 4 },
      { address: '127.0.0.1', family: 4 },
    ]),
    /Private or reserved DNS destinations/,
  );

  await assert.doesNotReject(
    assertPublicHostname('jobs.example.test', async () => [
      { address: '93.184.216.34', family: 4 },
      { address: '2606:4700:4700::1111', family: 6 },
    ]),
  );
});
