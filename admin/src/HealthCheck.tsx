import { useEffect, useState } from 'react';
import { fetchHealth, type Health } from './lib/api';

export default function HealthCheck() {
  const [health, setHealth] = useState<Health | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  async function check() {
    setLoading(true);
    setError('');
    setHealth(null);
    try {
      setHealth(await fetchHealth());
    } catch (failure) {
      setError(
        failure instanceof Error
          ? failure.message
          : 'Connection failed. Please retry.',
      );
    } finally {
      setLoading(false);
    }
  }
  useEffect(() => {
    let active = true;
    void fetchHealth()
      .then((result) => {
        if (active) setHealth(result);
      })
      .catch((failure: unknown) => {
        if (active)
          setError(
            failure instanceof Error
              ? failure.message
              : 'Connection failed. Please retry.',
          );
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);
  return (
    <section aria-labelledby="connection-title">
      <h2 id="connection-title">Laravel connection</h2>
      <div role="status" aria-live="polite">
        {loading && <p>Checking API connection...</p>}
        {error && <p>{error}</p>}
        {health && (
          <>
            <p>Connected to Laravel. Database is ready.</p>
            <p>
              Request ID: <code>{health.meta.request_id}</code>
            </p>
          </>
        )}
      </div>
      <button type="button" onClick={() => void check()} disabled={loading}>
        {loading ? 'Checking...' : 'Retry connection'}
      </button>
    </section>
  );
}
