const API_URL = (
  import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'
).replace(/\/$/, '');

export type Health = {
  data: { status: 'ok'; service: string; database: 'ok' };
  meta: { request_id: string; timestamp: string };
};

export async function fetchHealth(): Promise<Health> {
  try {
    const response = await fetch(`${API_URL}/health`, {
      signal: AbortSignal.timeout(5000),
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    });
    if (!response.ok) throw new Error(`API returned HTTP ${response.status}`);
    const body: Health = await response.json();
    if (
      body.data?.status !== 'ok' ||
      body.data?.database !== 'ok' ||
      !body.meta?.request_id
    ) {
      throw new Error('Unexpected health response');
    }
    return body;
  } catch {
    throw new Error(
      'Cannot connect to the Laravel API. Check that it is running on port 8000 and the database is migrated, then retry.',
    );
  }
}
