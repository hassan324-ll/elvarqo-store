import { indexEnabled } from '../../lib/catalogue-server';
import { productionHost } from '../../lib/indexing-policy';
import {
  catalogueSitemap,
  sitemapXml,
  type SitemapPage,
} from '../../lib/sitemap';
import { publicApi } from '../../lib/catalogue-types';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';
const responseHeaders = {
  'Content-Type': 'application/xml; charset=utf-8',
  'Cache-Control': 'no-store, max-age=0',
  'X-Content-Type-Options': 'nosniff',
};
export async function GET(request: Request) {
  if (
    !indexEnabled ||
    !productionHost(request.headers.get('host') || new URL(request.url).host)
  )
    return new Response(
      '<?xml version="1.0"?><error>Sitemap unavailable</error>',
      { status: 404, headers: responseHeaders },
    );
  try {
    const base = (process.env.CATALOGUE_API_URL || publicApi).replace(
      /\/$/,
      '',
    );
    const entries = await catalogueSitemap(
      async (page): Promise<SitemapPage> => {
        const response = await fetch(base + '/catalogue/sitemap?page=' + page, {
          cache: 'no-store',
          redirect: 'error',
          headers: { Accept: 'application/json' },
          signal: AbortSignal.timeout(10000),
        });
        if (!response.ok) throw new Error('Sitemap catalogue unavailable');
        return (await response.json()).data;
      },
    );
    return new Response(sitemapXml(entries), { headers: responseHeaders });
  } catch {
    // A transient failure must not become a cached empty/partial successful sitemap.
    return new Response(
      '<?xml version="1.0"?><error>Catalogue temporarily unavailable</error>',
      { status: 503, headers: { ...responseHeaders, 'Retry-After': '60' } },
    );
  }
}
