import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
const base = (
  process.env.COMMERCE_API_URL ||
  process.env.CATALOGUE_API_URL ||
  process.env.NEXT_PUBLIC_API_URL ||
  'http://localhost:8000/api/v1'
).replace(/\/$/, '');
const cookie = 'elvarqo_guest_bag';
const headers = {
  'Cache-Control': 'private, no-store',
  'Referrer-Policy': 'no-referrer',
};
async function handle(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
) {
  const { path } = await context.params;
  const target = path.join('/');
  const post = request.method === 'POST';
  const allowed = post
    ? /^(bags|bag\/items|quote|checkout|orders\/[a-f0-9-]{36}\/resume)$/
    : /^(bag|orders\/[a-f0-9-]{36})$/;
  if (!allowed.test(target))
    return NextResponse.json(
      { error: { message: 'Unknown commerce route.' } },
      { status: 404, headers },
    );
  const origin = process.env.STOREFRONT_ORIGIN || request.nextUrl.origin;
  if (
    post &&
    (request.headers.get('origin') !== origin ||
      !request.headers.get('content-type')?.startsWith('application/json'))
  )
    return NextResponse.json(
      { error: { message: 'Checkout requires a same-origin JSON request.' } },
      { status: 403, headers },
    );
  const jar = await cookies();
  const token = jar.get(cookie)?.value;
  if (!token && target === 'bag')
    return NextResponse.json({ data: null }, { headers });
  if (!token && target !== 'bags')
    return NextResponse.json(
      { error: { message: 'Your bag session expired. Refresh your bag.' } },
      { status: 401, headers },
    );
  if (target === 'bags' && token)
    return NextResponse.json(
      { error: { message: 'A bag session already exists. Refresh your bag.' } },
      { status: 409, headers },
    );
  try {
    const body = post ? await request.text() : undefined;
    if (body && body.length > 16384)
      return NextResponse.json(
        { error: { message: 'Checkout request is too large.' } },
        { status: 413, headers },
      );
    const response = await fetch(`${base}/commerce/${target}`, {
      method: request.method,
      cache: 'no-store',
      redirect: 'error',
      signal: AbortSignal.timeout(30000),
      headers: {
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(post ? { 'Content-Type': 'application/json', Origin: origin } : {}),
      },
      body,
    });
    const result = await response.json();
    if (target === 'bags' && response.ok) {
      const secret = result.data.token;
      delete result.data.token;
      const out = NextResponse.json(
        { data: result.data.bag },
        { status: response.status, headers },
      );
      out.cookies.set(cookie, secret, {
        httpOnly: true,
        secure: request.nextUrl.protocol === 'https:',
        sameSite: 'lax',
        path: '/',
        maxAge: 30 * 86400,
      });
      return out;
    }
    const out = NextResponse.json(result, { status: response.status, headers });
    if (response.status === 401) out.cookies.delete(cookie);
    return out;
  } catch {
    return NextResponse.json(
      {
        error: {
          message:
            'Checkout service is temporarily unavailable. Your payment status has not changed. Refresh before retrying.',
        },
      },
      { status: 503, headers },
    );
  }
}
export const GET = handle;
export const POST = handle;
