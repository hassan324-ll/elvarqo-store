'use client';
import { useRouter } from 'next/navigation';
import { useTransition } from 'react';
import Link from 'next/link';
import { Button } from '../../ui/components';
export default function Recovery({
  message = 'We could not load the collection. Please try again.',
}: {
  message?: string;
}) {
  const router = useRouter();
  const [pending, start] = useTransition();
  return (
    <section className="shop-recovery" role="alert">
      <h2>A moment to reconnect.</h2>
      <p>{message}</p>
      <Button busy={pending} onClick={() => start(() => router.refresh())}>
        Retry catalogue
      </Button>
      <Link href="/shop" className="text-link" prefetch={false}>
        Browse all pieces
      </Link>
    </section>
  );
}
