export async function accountRequest<T>(
  action: string,
  body?: Record<string, unknown>,
): Promise<T> {
  const r = await fetch('/api/customer/' + action, {
    method: body ? 'POST' : 'GET',
    cache: 'no-store',
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : undefined,
  });
  const doc = await r.json();
  if (!r.ok) {
    if (
      r.status === 401 &&
      !['me', 'login', 'redeem'].includes(action.split('?')[0])
    )
      window.dispatchEvent(new Event('elvarqo-account'));
    const fields = Object.values(doc.error?.details || {})
      .flat()
      .join(' ');
    throw Error(fields || doc.error?.message || 'Account request failed.');
  }
  return doc.data as T;
}
export type Customer = {
  id: number;
  name: string;
  email: string;
  verified: boolean;
};
