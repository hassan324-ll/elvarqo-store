import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
const base = (
  process.env.COMMERCE_API_URL ||
  process.env.CATALOGUE_API_URL ||
  process.env.NEXT_PUBLIC_API_URL ||
  'http://localhost:8000/api/v1'
).replace(/\/$/, '');
const headers = {
  'Cache-Control': 'private, no-store',
  'Referrer-Policy': 'no-referrer',
};
const cookie = 'elvarqo_tracking';
async function handle(
  request: NextRequest,
  context: { params: Promise<{ action: string }> },
) {
  const { action } = await context.params;
  const post = request.method === 'POST';
  if (
    !(
      post
        ? [
            'request',
            'redeem',
            'logout',
            'support',
            'payment-redeem',
            'returns',
          ]
        : ['order', 'returns']
    ).includes(action)
  )
    return NextResponse.json(
      { error: { message: 'Unknown tracking route.' } },
      { status: 404, headers },
    );
  const origin = process.env.STOREFRONT_ORIGIN || request.nextUrl.origin;
  if (
    post &&
    (request.headers.get('origin') !== origin ||
      !request.headers.get('content-type')?.startsWith('application/json'))
  )
    return NextResponse.json(
      { error: { message: 'A same-origin JSON request is required.' } },
      { status: 403, headers },
    );
  const jar = await cookies();
  const token = jar.get(cookie)?.value;
  if (['order', 'support', 'logout', 'returns'].includes(action) && !token)
    return NextResponse.json(
      { error: { message: 'Verify your tracking link to view this order.' } },
      { status: 401, headers },
    );
  try {
    const body = post ? await request.text() : undefined;
    if (body && body.length > 8192)
      return NextResponse.json(
        { error: { message: 'Request too large.' } },
        { status: 413, headers },
      );
    const response = await fetch(`${base}/tracking/${action}`, {
      method: request.method,
      cache: 'no-store',
      redirect: 'error',
      signal: AbortSignal.timeout(15000),
      headers: {
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(post ? { 'Content-Type': 'application/json', Origin: origin } : {}),
      },
      body,
    });
    const result = await response.json();
    let secret: string | undefined;
    if (response.ok && ['redeem', 'payment-redeem'].includes(action)) {
      secret = result.data.token;
      delete result.data.token;
    }
    const out = NextResponse.json(result, { status: response.status, headers });
    if (secret)
      out.cookies.set(
        action === 'payment-redeem' ? 'elvarqo_guest_bag' : cookie,
        secret,
        {
          httpOnly: true,
          secure: request.nextUrl.protocol === 'https:',
          sameSite: 'lax',
          path: '/',
          maxAge: action === 'payment-redeem' ? 30 * 86400 : 86400,
        },
      );
    if (
      action === 'logout' ||
      (response.status === 401 &&
        ['order', 'support', 'returns'].includes(action))
    )
      out.cookies.delete(cookie);
    return out;
  } catch {
    return NextResponse.json(
      {
        error: {
          message:
            'Tracking is temporarily unavailable. Retry when the connection returns.',
        },
      },
      { status: 503, headers },
    );
  }
}
export const GET = handle;
export const POST = handle;
