import { useState } from 'react';
import { Badge, Button, DataTable, Field } from '../ui/components';
import { staffRequest, type StaffIdentity } from '../lib/staff-api';
import RecoveryCodes from './RecoveryCodes';
type AuditEvent = {
  id: string;
  staff_user_id: number | null;
  action: string;
  target: string | null;
  result: string;
  created_at: string;
};
export default function SecurityPanel({ staff }: { staff: StaffIdentity }) {
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [codes, setCodes] = useState<string[]>([]);
  const [events, setEvents] = useState<AuditEvent[]>([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  async function regenerate() {
    setBusy(true);
    setError('');
    try {
      const data = await staffRequest<{ recovery_codes: string[] }>(
        'recovery-codes',
        { password, code },
      );
      setCodes(data.recovery_codes);
      setPassword('');
      setCode('');
    } catch (failure) {
      setError((failure as Error).message);
    } finally {
      setBusy(false);
    }
  }
  async function audit() {
    setBusy(true);
    setError('');
    try {
      const data = await staffRequest<{ events: AuditEvent[] }>('audit-events');
      setEvents(data.events);
    } catch (failure) {
      setError((failure as Error).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <div className="security-grid">
      <section className="panel">
        <h2>Your staff security</h2>
        <p>
          {staff.name} / {staff.email}
        </p>
        <Badge tone="success">Two-step verification enabled</Badge>
        <p className="muted">
          Role: {staff.role}. Sessions expire after 30 minutes idle or 8 hours
          total.
        </p>
        <h3>Your permissions</h3>
        <div className="permission-tags">
          {staff.permissions.map((permission) => (
            <Badge key={permission}>{permission}</Badge>
          ))}
        </div>
      </section>
      <section className="panel">
        <h2>Recovery codes</h2>
        {error && (
          <p role="alert" className="auth-error">
            {error}
          </p>
        )}
        {codes.length ? (
          <RecoveryCodes codes={codes} onDone={() => setCodes([])} />
        ) : (
          <form
            noValidate
            onSubmit={(e) => {
              e.preventDefault();
              void regenerate();
            }}
          >
            <p>
              Generate a new set only when needed. All previous codes stop
              working. Enter your password and a fresh authenticator code; a
              code already used to sign in cannot be reused.
            </p>
            <Field
              label="Current password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            <Field
              label="Fresh six-digit code"
              inputMode="numeric"
              autoComplete="one-time-code"
              value={code}
              maxLength={6}
              onChange={(e) => setCode(e.target.value)}
              required
            />
            <Button type="submit" busy={busy}>
              Replace recovery codes
            </Button>
          </form>
        )}
      </section>
      {staff.permissions.includes('audit.view') && (
        <section className="panel security-audit">
          <h2>Security events</h2>
          <p className="muted">
            Most recent 50 real staff security events. Full operational audit
            management follows in Phase 10.
          </p>
          <Button variant="secondary" busy={busy} onClick={() => void audit()}>
            Load security events
          </Button>
          {events.length > 0 && (
            <DataTable
              caption="Staff security event history (UTC)"
              columns={['Time', 'Actor', 'Event', 'Target', 'Result']}
              rows={events.map((event) => [
                event.created_at,
                event.staff_user_id === null
                  ? 'System / anonymous'
                  : `Staff ${event.staff_user_id}`,
                event.action,
                event.target || '—',
                event.result,
              ])}
            />
          )}
        </section>
      )}
    </div>
  );
}
