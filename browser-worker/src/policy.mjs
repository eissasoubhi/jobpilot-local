import dns from 'node:dns/promises';
import net from 'node:net';

const PRIVATE_IPV4_RANGES = [
  ['10.0.0.0', 8],
  ['100.64.0.0', 10],
  ['127.0.0.0', 8],
  ['169.254.0.0', 16],
  ['172.16.0.0', 12],
  ['192.0.0.0', 24],
  ['192.0.2.0', 24],
  ['192.168.0.0', 16],
  ['198.18.0.0', 15],
  ['198.51.100.0', 24],
  ['203.0.113.0', 24],
  ['224.0.0.0', 4],
  ['240.0.0.0', 4],
];

const BLOCKED_RESOURCE_TYPES = new Set(['image', 'media', 'font']);
const SAFE_METHODS = new Set(['GET', 'HEAD']);

export function normalizeAllowedDomain(value) {
  const domain = String(value ?? '').trim().toLowerCase().replace(/\.$/, '');
  if (domain === '' || domain.includes('/') || domain.includes(':') || domain === 'localhost' || domain.endsWith('.local')) {
    throw new Error('allowedDomain must be a public DNS hostname.');
  }
  return domain;
}

export function validateNavigationUrl(value, allowedDomain) {
  const domain = normalizeAllowedDomain(allowedDomain);
  let url;
  try {
    url = new URL(String(value ?? '').trim());
  } catch {
    throw new Error('A valid HTTPS URL is required.');
  }

  if (url.protocol !== 'https:') {
    throw new Error('Only HTTPS navigation is allowed.');
  }
  if (url.username !== '' || url.password !== '' || url.hash !== '') {
    throw new Error('Credentials and fragments are not allowed in navigation URLs.');
  }
  if (url.hostname.toLowerCase().replace(/\.$/, '') !== domain) {
    throw new Error('Top-level navigation must stay on the authorized domain.');
  }

  return url;
}

export async function assertPublicHostname(hostname, lookup = dns.lookup) {
  const normalized = String(hostname ?? '').trim().toLowerCase().replace(/\.$/, '');
  if (normalized === '' || normalized === 'localhost' || normalized.endsWith('.local')) {
    throw new Error('Local hostnames are blocked.');
  }

  if (net.isIP(normalized) !== 0) {
    if (isPrivateIp(normalized)) throw new Error('Private or reserved IP addresses are blocked.');
    return;
  }

  const addresses = await lookup(normalized, { all: true, verbatim: true });
  if (!Array.isArray(addresses) || addresses.length === 0) {
    throw new Error('The hostname did not resolve to a public address.');
  }
  for (const entry of addresses) {
    const address = typeof entry === 'string' ? entry : entry?.address;
    if (!address || isPrivateIp(address)) {
      throw new Error('Private or reserved DNS destinations are blocked.');
    }
  }
}

export function shouldBlockRequest(request, allowedDomain) {
  const method = String(request.method ?? 'GET').toUpperCase();
  if (!SAFE_METHODS.has(method)) {
    return { block: true, reason: 'NON_READ_METHOD' };
  }

  const resourceType = String(request.resourceType ?? '').toLowerCase();
  if (BLOCKED_RESOURCE_TYPES.has(resourceType)) {
    return { block: true, reason: 'HEAVY_RESOURCE' };
  }

  let url;
  try {
    url = new URL(String(request.url ?? ''));
  } catch {
    return { block: true, reason: 'INVALID_URL' };
  }

  if (!['https:', 'http:'].includes(url.protocol)) {
    return { block: true, reason: 'UNSAFE_SCHEME' };
  }

  if (request.isNavigationRequest === true && request.frameIsMain === true) {
    if (url.protocol !== 'https:' || url.hostname.toLowerCase().replace(/\.$/, '') !== normalizeAllowedDomain(allowedDomain)) {
      return { block: true, reason: 'CROSS_DOMAIN_NAVIGATION' };
    }
  }

  return { block: false, reason: null };
}

export function normalizeRenderRequest(payload) {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw new Error('A JSON object is required.');
  }
  if (payload.authorizationApproved !== true || payload.robotsApproved !== true) {
    throw new Error('Authorization and robots approval must be confirmed by the caller.');
  }

  const allowedDomain = normalizeAllowedDomain(payload.allowedDomain);
  const url = validateNavigationUrl(payload.url, allowedDomain);
  const timeoutMs = Math.min(15_000, Math.max(2_000, Number(payload.timeoutMs ?? 10_000) || 10_000));
  const settleMs = Math.min(2_000, Math.max(0, Number(payload.settleMs ?? 800) || 0));
  const maxHtmlBytes = Math.min(3_000_000, Math.max(100_000, Number(payload.maxHtmlBytes ?? 3_000_000) || 3_000_000));

  return {
    sourceCode: String(payload.sourceCode ?? '').trim().slice(0, 120),
    url: url.toString(),
    allowedDomain,
    timeoutMs,
    settleMs,
    maxHtmlBytes,
  };
}

export function isPrivateIp(address) {
  const family = net.isIP(address);
  if (family === 4) return isPrivateIpv4(address);
  if (family === 6) return isPrivateIpv6(address);
  return true;
}

function isPrivateIpv4(address) {
  const value = ipv4ToInt(address);
  return PRIVATE_IPV4_RANGES.some(([base, bits]) => {
    const baseValue = ipv4ToInt(base);
    const mask = bits === 0 ? 0 : (0xffffffff << (32 - bits)) >>> 0;
    return (value & mask) === (baseValue & mask);
  });
}

function isPrivateIpv6(address) {
  const normalized = address.toLowerCase();
  if (normalized === '::' || normalized === '::1') return true;
  if (normalized.startsWith('fc') || normalized.startsWith('fd')) return true;
  if (/^fe[89ab]/.test(normalized)) return true;
  if (normalized.startsWith('ff')) return true;
  if (normalized.startsWith('2001:db8:')) return true;
  if (normalized.startsWith('::ffff:')) {
    const ipv4 = normalized.slice('::ffff:'.length);
    return net.isIP(ipv4) === 4 ? isPrivateIpv4(ipv4) : true;
  }
  return false;
}

function ipv4ToInt(address) {
  return address.split('.').reduce((value, octet) => ((value << 8) | Number(octet)) >>> 0, 0);
}
