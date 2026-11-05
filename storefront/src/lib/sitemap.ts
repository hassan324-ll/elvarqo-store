export type SitemapEntry = { url: string; lastmod?: string };
export type SitemapPage = {
  products: { slug: string; lastmod?: string | null }[];
  groups: { slug: string }[];
  page: number;
  last_page: number;
  total: number;
};
export const sitemapOrigin = 'https://elvarqo.uk';
const publicContentPaths = ['/', '/shop', '/collections'];
const slugPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
function slug(value: string): string {
  if (
    typeof value !== 'string' ||
    value.length > 180 ||
    !slugPattern.test(value)
  )
    throw new Error('Invalid public catalogue slug');
  return value;
}
function lastmod(value: string | null | undefined): string | undefined {
  if (!value || !/^\d{4}-\d{2}-\d{2}T/.test(value)) return undefined;
  const time = Date.parse(value);
  return Number.isFinite(time) && time <= Date.now()
    ? new Date(time).toISOString()
    : undefined;
}
export async function catalogueSitemap(
  load: (page: number) => Promise<SitemapPage>,
): Promise<SitemapEntry[]> {
  const entries = new Map<string, SitemapEntry>();
  for (const path of publicContentPaths)
    entries.set(path, { url: sitemapOrigin + path });
  let pages = 1;
  for (let page = 1; page <= pages; page++) {
    const data = await load(page);
    if (
      data.page !== page ||
      !Number.isInteger(data.last_page) ||
      data.last_page < page ||
      data.last_page > 100 ||
      !Number.isInteger(data.total) ||
      data.total < 0 ||
      data.total > 50000 ||
      !Array.isArray(data.products) ||
      !Array.isArray(data.groups)
    )
      throw new Error('Invalid sitemap pagination');
    if (page === 1) pages = data.last_page;
    else if (data.last_page !== pages)
      throw new Error('Catalogue changed during sitemap generation; retry');
    for (const group of data.groups) {
      const path = '/collections/' + slug(group.slug);
      entries.set(path, { url: sitemapOrigin + path });
    }
    for (const product of data.products) {
      const path = '/products/' + slug(product.slug);
      entries.set(path, {
        url: sitemapOrigin + path,
        lastmod: lastmod(product.lastmod),
      });
    }
    if (entries.size > 50000) throw new Error('Sitemap needs splitting');
  }
  return [...entries.values()];
}
export function escapeXml(value: string): string {
  return value.replace(
    /[&<>"']/g,
    (char) =>
      ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&apos;',
      })[char]!,
  );
}
export function sitemapXml(entries: SitemapEntry[]): string {
  return (
    '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' +
    entries
      .map(
        (entry) =>
          '  <url><loc>' +
          escapeXml(entry.url) +
          '</loc>' +
          (entry.lastmod
            ? '<lastmod>' + escapeXml(entry.lastmod) + '</lastmod>'
            : '') +
          '</url>',
      )
      .join('\n') +
    '\n</urlset>\n'
  );
}
