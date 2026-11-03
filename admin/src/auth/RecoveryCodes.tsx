import { useState } from 'react';
import { Button } from '../ui/components';
export default function RecoveryCodes({
  codes,
  onDone,
}: {
  codes: string[];
  onDone: () => void;
}) {
  const [saved, setSaved] = useState(false);
  function download() {
    const blob = new Blob(
      [
        'ELVARQO staff recovery codes\nEach code can be used once and expires after one year.\n\n' +
          codes.join('\n') +
          '\n',
      ],
      { type: 'text/plain' },
    );
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'elvarqo-staff-recovery-codes.txt';
    a.click();
    URL.revokeObjectURL(url);
  }
  return (
    <section className="recovery-sheet">
      <h2>Save your recovery codes.</h2>
      <p>
        These codes are shown once. Keep them in a secure place separate from
        your authenticator. Each code works once; codes expire after one year.
      </p>
      <ul className="recovery-list">
        {codes.map((code) => (
          <li key={code}>
            <code>{code}</code>
          </li>
        ))}
      </ul>
      <Button type="button" variant="secondary" onClick={download}>
        Download recovery codes
      </Button>
      <label className="recovery-confirm">
        <input
          type="checkbox"
          checked={saved}
          onChange={(e) => setSaved(e.target.checked)}
        />{' '}
        I have stored these codes securely.
      </label>
      <Button disabled={!saved} onClick={onDone}>
        Continue to admin
      </Button>
    </section>
  );
}
