import { NextResponse, type NextRequest } from 'next/server';
import { excludeFromIndex, productionIndexing } from './lib/indexing-policy';

export function proxy(request: NextRequest) {
  const response = NextResponse.next();
  const enabled = productionIndexing(
    process.env.STOREFRONT_INDEXING,
    process.env.NODE_ENV,
    process.env.NEXT_PUBLIC_SITE_URL,
  );
  if (
    excludeFromIndex(
      request.headers.get('host') || request.nextUrl.host,
      request.nextUrl.pathname,
      enabled,
      request.nextUrl.searchParams.size > 0,
    )
  )
    response.headers.set('X-Robots-Tag', 'noindex, nofollow');
  return response;
}
export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
