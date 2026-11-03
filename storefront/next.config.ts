import type { NextConfig } from 'next';
const nextConfig: NextConfig = {
  distDir: process.env.ELVARQO_NEXT_DIST || '.next',
  cacheComponents: false,
  htmlLimitedBots: /.*/,
};
export default nextConfig;
