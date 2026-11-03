const base =
  (import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1').replace(
    /\/$/,
    '',
  ) + '/staff';
export type StaffIdentity = {
  id: number;
  name: string;
  email: string;
  role: string;
  permissions: string[];
};
export class StaffApiError extends Error {
  status: number;
  code: string;
  details: Record<string, string[]>;
  constructor(
    status: number,
    code: string,
    message: string,
    details: Record<string, string[]> = {},
  ) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}
export async function staffRequest<T>(
  path: string,
  body?: Record<string, unknown> | FormData,
): Promise<T> {
  try {
    let csrf = '';
    if (body) {
      const response = await fetch(`${base}/csrf`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
        signal: AbortSignal.timeout(10000),
        cache: 'no-store',
      });
      const result = await response.json();
      if (!response.ok)
        throw new StaffApiError(
          response.status,
          result.error?.code || 'CSRF_UNAVAILABLE',
          result.error?.message || 'Unable to start a security session.',
        );
      csrf = result.data.csrf_token;
    }
    const response = await fetch(`${base}/${path}`, {
      method: body ? 'POST' : 'GET',
      credentials: 'include',
      cache: 'no-store',
      signal: AbortSignal.timeout(body instanceof FormData ? 60000 : 10000),
      headers: {
        Accept: 'application/json',
        ...(body
          ? {
              ...(body instanceof FormData
                ? {}
                : { 'Content-Type': 'application/json' }),
              'X-CSRF-TOKEN': csrf,
            }
          : {}),
      },
      ...(body
        ? { body: body instanceof FormData ? body : JSON.stringify(body) }
        : {}),
    });
    const result = await response.json();
    if (!response.ok) {
      if (
        response.status === 401 &&
        (['overview', 'logout', 'recovery-codes', 'audit-events'].includes(
          path,
        ) ||
          path.startsWith('catalogue/') ||
          path.startsWith('orders') ||
          path.startsWith('shipments') ||
          path.startsWith('after-sales')) &&
        (path !== 'me' || result.error?.code === 'SESSION_EXPIRED')
      )
        window.dispatchEvent(new Event('staff-session-expired'));
      throw new StaffApiError(
        response.status,
        result.error?.code || 'REQUEST_FAILED',
        result.error?.message || 'The request could not be completed.',
        result.error?.details || {},
      );
    }
    return result.data as T;
  } catch (error) {
    if (error instanceof StaffApiError) throw error;
    throw new Error(
      'Cannot reach the staff API. Check the backend connection and try again.',
    );
  }
}
export const viewPermissions: Record<string, string[]> = {
  orders: ['fulfilment.view'],
  returns: ['fulfilment.view'],
  products: ['catalogue.view'],
  inventory: ['catalogue.view'],
  customers: ['customers.view'],
  marketing: ['content.view'],
  content: ['content.view'],
  payments: ['finance.view'],
  reports: ['finance.view'],
  support: ['customers.view'],
  settings: ['staff.manage', 'integrations.manage'],
  audit: ['audit.view'],
};
export function canView(staff: StaffIdentity, view: string) {
  return (
    !viewPermissions[view] ||
    viewPermissions[view].some((p) => staff.permissions.includes(p))
  );
}

export async function staffDownload(
  path: string,
  filename: string,
): Promise<void> {
  const response = await fetch(`${base}/${path}`, {
    credentials: 'include',
    cache: 'no-store',
    headers: { Accept: 'image/svg+xml' },
    signal: AbortSignal.timeout(15000),
  });
  if (!response.ok) {
    if (response.status === 401)
      window.dispatchEvent(new Event('staff-session-expired'));
    throw new Error('Label unavailable or staff access expired.');
  }
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
