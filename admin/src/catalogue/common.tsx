import { useState, useCallback, type TextareaHTMLAttributes } from 'react';
import { staffRequest, StaffApiError } from '../lib/staff-api';
// Feedback components and their cohesive action hook are intentionally colocated.
/* oxlint-disable react/only-export-components */
export function useAction() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [details, setDetails] = useState<Record<string, string[]>>({});
  const [notice, setNotice] = useState('');
  const run = useCallback(
    async <T,>(work: () => Promise<T>, success?: (value: T) => void) => {
      setBusy(true);
      setError('');
      setDetails({});
      setNotice('');
      try {
        const value = await work();
        success?.(value);
        return value;
      } catch (e) {
        setError(
          e instanceof Error ? e.message : 'Unable to complete request.',
        );
        if (e instanceof StaffApiError) {
          setDetails(e.details);
          if (e.status === 409)
            setError(
              'This record changed or an identifier was taken. Keep your input, open the record in another tab and reconcile before retrying.',
            );
        }
        return undefined;
      } finally {
        setBusy(false);
      }
    },
    [],
  );
  return { busy, error, details, notice, setNotice, setError, run };
}
export function Feedback({ action }: { action: ReturnType<typeof useAction> }) {
  return (
    <>
      {action.error && (
        <div role="alert" className="auth-error">
          <strong>{action.error}</strong>
          {Object.entries(action.details).length > 0 && (
            <ul>
              {Object.entries(action.details).map(([field, messages]) => (
                <li key={field}>
                  <strong>{field.replaceAll('_', ' ')}:</strong>{' '}
                  {messages.join(' ')}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
      {action.notice && (
        <p role="status" className="catalogue-notice">
          {action.notice}
        </p>
      )}
    </>
  );
}
export function TextArea({
  label,
  ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement> & { label: string }) {
  const id = label.toLowerCase().replaceAll(/[^a-z0-9]+/g, '-');
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <textarea {...props} id={id} />
    </div>
  );
}
export function request<T>(
  path: string,
  body?: Record<string, unknown> | FormData,
) {
  return staffRequest<T>('catalogue/' + path, body);
}
