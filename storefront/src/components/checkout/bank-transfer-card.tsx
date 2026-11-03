'use client';
import { useState } from 'react';
import { Button } from '../../ui/components';
import { money } from '../../lib/catalogue-types';
import type { BankInstructions } from '../../lib/commerce';
export default function BankTransferCard({
  instructions,
}: {
  instructions: BankInstructions;
}) {
  const [notice, setNotice] = useState('');
  return (
    <section
      className="bank-transfer-card"
      aria-labelledby="bank-instructions-title"
    >
      <p className="eyebrow">Wise / GBP bank transfer</p>
      <h2 id="bank-instructions-title">Pay using your bank</h2>
      <p>
        Transfer the exact amount to the receiving details below. Include the
        payment reference so we can match it to your order.
      </p>
      <dl>
        <div>
          <dt>Amount to send</dt>
          <dd>{money(instructions.amount_minor)}</dd>
        </div>
        <div>
          <dt>Account holder</dt>
          <dd>{instructions.account_holder}</dd>
        </div>
        <div>
          <dt>Sort code</dt>
          <dd>{instructions.sort_code}</dd>
        </div>
        <div>
          <dt>Account number</dt>
          <dd>{instructions.account_number}</dd>
        </div>
        <div>
          <dt>Payment reference</dt>
          <dd>
            <strong>{instructions.payment_reference}</strong>
          </dd>
        </div>
      </dl>
      <Button
        variant="secondary"
        onClick={async () => {
          try {
            await navigator.clipboard.writeText(instructions.payment_reference);
            setNotice('Payment reference copied.');
          } catch {
            setNotice('Select and copy the payment reference above.');
          }
        }}
      >
        Copy payment reference
      </Button>
      {notice && <p role="status">{notice}</p>}
      <p>
        <strong>
          Pay before {new Date(instructions.pay_before).toLocaleString('en-GB')}{' '}
          (your local time).
        </strong>
      </p>
      <p>
        Do not send money after this deadline. Transfers arriving late, with a
        different amount or a missing reference need staff review. A screenshot
        or a return to this page does not confirm receipt.
      </p>
      <p>
        We confirm your order after checking that the payment has arrived in
        Wise. Card, Apple Pay and Google Pay payments are not offered in this
        checkout.
      </p>
    </section>
  );
}
