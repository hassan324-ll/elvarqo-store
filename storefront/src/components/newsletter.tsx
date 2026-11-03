'use client';
import { useState } from 'react';
import { Modal, Field, Button } from '../ui/components';
export default function Newsletter() {
  const [open, setOpen] = useState(false);
  return (
    <>
      <section className="newsletter">
        <div>
          <p className="eyebrow">THE ELVARQO LETTER</p>
          <h2>A little more considered.</h2>
          <p>Collection notes and new stories, in your inbox.</p>
        </div>
        <Button variant="secondary" onClick={() => setOpen(true)}>
          Preview the signup <span aria-hidden="true">→</span>
        </Button>
      </section>
      <Modal
        open={open}
        title="The ELVARQO letter"
        onClose={() => setOpen(false)}
      >
        <p>
          Newsletter signup is not available yet. This preview does not collect
          or send your email.
        </p>
        <Field
          label="Email address"
          type="email"
          autoComplete="email"
          placeholder="you@example.com"
        />
        <label className="consent">
          <input type="checkbox" /> I would like to receive optional collection
          news.
        </label>
        <Button disabled>Signup not yet available</Button>
      </Modal>
    </>
  );
}
