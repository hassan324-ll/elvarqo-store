import type { MetadataRoute } from 'next';
import { headers } from 'next/headers';
import { indexEnabled } from '../lib/catalogue-server';
import {
  excludedPaths,
  previewContentPaths,
  productionHost,
} from '../lib/indexing-policy';
export const dynamic = 'force-dynamic';
export default async function robots(): Promise<MetadataRoute.Robots> {
  const host = (await headers()).get('host') || '';
  if (!indexEnabled || !productionHost(host))
    return { rules: { userAgent: '*', disallow: '/' } };
  return {
    sitemap: 'https://elvarqo.uk/sitemap.xml',
    rules: {
      userAgent: '*',
      allow: '/',
      disallow: [...excludedPaths, ...previewContentPaths],
    },
  };
}
