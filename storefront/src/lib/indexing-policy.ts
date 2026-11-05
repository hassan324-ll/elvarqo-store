export function productionIndexing(
  setting: string | undefined,
  nodeEnv: string | undefined,
  siteUrl: string | undefined,
): boolean {
  return (
    setting === 'true' &&
    nodeEnv === 'production' &&
    siteUrl?.replace(/\/$/, '') === 'https://elvarqo.uk'
  );
}
export function productionHost(host: string): boolean {
  try {
    const url = new URL('https://' + host);
    return (
      !url.username &&
      !url.password &&
      ['elvarqo.uk', 'www.elvarqo.uk'].includes(
        url.hostname.toLowerCase().replace(/\.$/, ''),
      )
    );
  } catch {
    return false;
  }
}
export const excludedPaths = [
  '/api',
  '/admin',
  '/account',
  '/bag',
  '/checkout',
  '/pay-order',
  '/returns',
  '/track',
  '/wishlist',
  '/search',
  '/development',
  '/design-system',
];
export const previewContentPaths = [
  '/the-edit',
  '/our-story',
  '/help',
  '/delivery-returns',
  '/size-guide',
  '/contact',
  '/privacy',
  '/terms',
  '/cookies',
  '/accessibility',
];
export function excludeFromIndex(
  host: string,
  path: string,
  enabled: boolean,
  hasQuery = false,
): boolean {
  return (
    !enabled ||
    !productionHost(host) ||
    [...excludedPaths, ...previewContentPaths].some(
      (p) => path === p || path.startsWith(p + '/'),
    ) ||
    (hasQuery && (path === '/shop' || path.startsWith('/collections/')))
  );
}
