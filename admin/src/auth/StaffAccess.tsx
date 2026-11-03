import { storefrontUrl } from '../lib/site';
import { useEffect, useRef, useState } from 'react';
import { Button, Field, Icon } from '../ui/components';
import {
  staffRequest,
  StaffApiError,
  type StaffIdentity,
} from '../lib/staff-api';
import RecoveryCodes from './RecoveryCodes';
import Workspace from '../Workspace';
const initialReset = (() => {
  const query = new URLSearchParams(window.location.search);
  const token = query.get('reset_token');
  const email = query.get('email') || '';
  if (token) history.replaceState(null, '', window.location.pathname);
  return token ? { token, email } : null;
})();
type Step =
  | 'checking'
  | 'login'
  | 'enroll'
  | 'verify'
  | 'forgot'
  | 'reset'
  | 'codes'
  | 'ready';
export default function StaffAccess() {
  const [step, setStep] = useState<Step>(initialReset ? 'reset' : 'checking');
  const [staff, setStaff] = useState<StaffIdentity | null>(null);
  const [email, setEmail] = useState(initialReset?.email || '');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState(false);
  const [secret, setSecret] = useState('');
  const [codes, setCodes] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    let active = true;
    if (!initialReset)
      void staffRequest<{ staff: StaffIdentity }>('me')
        .then((data) => {
          if (active) {
            setStaff(data.staff);
            setStep('ready');
          }
        })
        .catch((failure) => {
          if (active) {
            if (!(failure instanceof StaffApiError && failure.status === 401))
              setError(failure.message);
            else if (failure.code === 'SESSION_EXPIRED')
              setError('Your staff session expired. Sign in again.');
            setStep('login');
          }
        });
    const expired = () => {
      setStaff(null);
      setCodes([]);
      setPassword('');
      setCode('');
      setSecret('');
      setStep('login');
      setError('Your staff session expired. Sign in again.');
    };
    window.addEventListener('staff-session-expired', expired);
    return () => {
      active = false;
      window.removeEventListener('staff-session-expired', expired);
    };
  }, []);
  useEffect(() => {
    heading.current?.focus();
  }, [step]);
  function change(next: Step) {
    setError('');
    setErrors({});
    setNotice('');
    setPassword('');
    setConfirmation('');
    setCode('');
    setStep(next);
  }
  async function run(action: () => Promise<void>) {
    setBusy(true);
    setError('');
    setErrors({});
    try {
      await action();
    } catch (failure) {
      setError(
        failure instanceof Error ? failure.message : 'Please try again.',
      );
      if (failure instanceof StaffApiError) {
        setErrors(failure.details);
        if (failure.status === 401) {
          setSecret('');
          setStep('login');
        }
      }
    } finally {
      setBusy(false);
    }
  }
  async function logout() {
    await run(async () => {
      await staffRequest('logout', {});
      setStaff(null);
      setCodes([]);
      setSecret('');
      setPassword('');
      setCode('');
      change('login');
      setNotice('You have signed out.');
    });
  }
  async function submit() {
    await run(async () => {
      if (step === 'login') {
        const result = await staffRequest<{ state: string }>('login', {
          email,
          password,
        });
        setPassword('');
        if (result.state === 'enrollment_required') {
          const setup = await staffRequest<{ secret: string }>('enrollment');
          setSecret(setup.secret);
          setStep('enroll');
        } else {
          setRecovery(false);
          setStep('verify');
        }
      } else if (step === 'enroll' || step === 'verify') {
        const result = await staffRequest<{
          staff: StaffIdentity;
          recovery_codes: string[];
        }>('two-factor', recovery ? { recovery_code: code } : { code });
        setStaff(result.staff);
        setCode('');
        setSecret('');
        if (result.recovery_codes.length) {
          setCodes(result.recovery_codes);
          setStep('codes');
        } else setStep('ready');
      } else if (step === 'forgot') {
        const result = await staffRequest<{ message: string }>(
          'forgot-password',
          { email },
        );
        setNotice(result.message);
      } else if (step === 'reset') {
        await staffRequest('reset-password', {
          email,
          token: initialReset?.token || '',
          password,
          password_confirmation: confirmation,
        });
        change('login');
        setNotice(
          'Password updated. Sign in with your authenticator or an unused recovery code.',
        );
      }
    });
  }
  if (step === 'ready' && staff)
    return (
      <Workspace
        staff={staff}
        onLogout={() => void logout()}
        logoutBusy={busy}
        authError={error}
      />
    );
  const titles = {
    checking: 'Checking your staff session...',
    login: 'Sign in to store admin',
    enroll: 'Set up your authenticator',
    verify: recovery ? 'Use a recovery code' : 'Verify your sign-in',
    forgot: 'Reset your password',
    reset: 'Choose a new password',
    codes: 'Protect your staff access',
    ready: 'Staff access',
  };
  return (
    <div className="staff-access">
      <aside className="access-story">
        <a href={storefrontUrl} className="access-wordmark">
          ELVARQO
        </a>
        <div>
          <p className="eyebrow">PRIVATE STAFF WORKSPACE</p>
          <h1>
            A clear view
            <br />
            of your store.
          </h1>
          <p>
            Private access for authorised staff.
            <br />
            Customer accounts cannot access this workspace.
          </p>
        </div>
        <span>ELVARQO / STORE OPERATIONS</span>
      </aside>
      <main className="access-main">
        <div className="access-form">
          <a className="access-mobile-logo" href={storefrontUrl}>
            ELVARQO
          </a>
          <p className="eyebrow">SECURE STAFF ACCESS</p>
          <h2 ref={heading} tabIndex={-1}>
            {titles[step]}
          </h2>
          {error && (
            <p className="auth-error" role="alert">
              {error}
            </p>
          )}
          {notice && (
            <p className="auth-notice" role="status">
              {notice}
            </p>
          )}
          {step === 'checking' ? (
            <p role="status">Please wait...</p>
          ) : step === 'codes' ? (
            <RecoveryCodes
              codes={codes}
              onDone={() => {
                setCodes([]);
                setStep('ready');
              }}
            />
          ) : (
            <form
              noValidate
              onSubmit={(e) => {
                e.preventDefault();
                void submit();
              }}
            >
              {(step === 'login' || step === 'forgot' || step === 'reset') && (
                <Field
                  label="Work email"
                  type="email"
                  autoComplete="username"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  error={errors.email?.[0]}
                  required
                />
              )}
              {(step === 'login' || step === 'reset') && (
                <Field
                  label={step === 'reset' ? 'New password' : 'Password'}
                  type="password"
                  autoComplete={
                    step === 'reset' ? 'new-password' : 'current-password'
                  }
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  error={errors.password?.[0]}
                  hint={
                    step === 'reset'
                      ? 'At least 14 characters with uppercase, lowercase, a number and a symbol.'
                      : undefined
                  }
                  required
                />
              )}
              {step === 'reset' && (
                <Field
                  label="Confirm new password"
                  type="password"
                  autoComplete="new-password"
                  value={confirmation}
                  onChange={(e) => setConfirmation(e.target.value)}
                  error={errors.password_confirmation?.[0]}
                  required
                />
              )}
              {step === 'enroll' && (
                <div className="auth-enrollment">
                  <p>
                    Add an account manually in your authenticator app. Choose a
                    time-based code and enter this setup key:
                  </p>
                  <code className="setup-key">{secret}</code>
                  <p className="muted">
                    Keep this key private. Enter the six-digit code from your
                    app to finish setup. Recovery codes will be shown next.
                  </p>
                </div>
              )}
              {(step === 'enroll' || step === 'verify') && (
                <Field
                  label={recovery ? 'Recovery code' : 'Six-digit code'}
                  type="text"
                  inputMode={recovery ? 'text' : 'numeric'}
                  autoComplete="one-time-code"
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  maxLength={recovery ? 80 : 6}
                  error={errors.code?.[0] || errors.recovery_code?.[0]}
                  required
                />
              )}
              {step === 'forgot' && (
                <p className="muted">
                  We will send a time-limited link if this address belongs to a
                  staff account. Two-step verification will still be required.
                </p>
              )}
              <Button type="submit" busy={busy} className="access-submit">
                {step === 'login'
                  ? 'Continue'
                  : step === 'forgot'
                    ? 'Send reset link'
                    : step === 'reset'
                      ? 'Update password'
                      : 'Verify and continue'}
                <Icon name="arrow" size={18} />
              </Button>
              {step === 'login' && (
                <button
                  className="auth-link"
                  type="button"
                  onClick={() => change('forgot')}
                >
                  Forgot your password?
                </button>
              )}
              {step === 'verify' && (
                <button
                  className="auth-link"
                  type="button"
                  onClick={() => {
                    setRecovery(!recovery);
                    setCode('');
                    setError('');
                    setErrors({});
                  }}
                >
                  {recovery
                    ? 'Use an authenticator code'
                    : 'Use a recovery code'}
                </button>
              )}
              {step !== 'login' && (
                <button
                  type="button"
                  className="auth-link"
                  onClick={() => {
                    setSecret('');
                    change('login');
                  }}
                >
                  Back to sign in
                </button>
              )}
            </form>
          )}
          <p className="access-help">
            Need access or lost all recovery codes? Contact your store owner.
            There is no public staff registration.
          </p>
        </div>
      </main>
    </div>
  );
}
