import type { NextConfig } from 'next';

const apiInternalUrl =
  process.env.API_INTERNAL_URL ?? 'http://api:8080';

const nextConfig: NextConfig = {
  reactStrictMode: true,
  allowedDevOrigins: ['jobpost.test'],

  // Manual job discovery can legitimately take longer than Next.js' default
  // external rewrite proxy timeout because connectors are queried serially and
  // each connector is isolated from upstream failures. Keep the local proxy
  // alive long enough for the API to return its aggregated result instead of
  // turning a healthy long-running sync into a frontend HTTP 500.
  experimental: {
    proxyTimeout: 180_000,
  },

  async rewrites() {
    return [
      {
        source: '/api/:path*',
        destination: `${apiInternalUrl}/api/:path*`,
      },
    ];
  },
};

export default nextConfig;
