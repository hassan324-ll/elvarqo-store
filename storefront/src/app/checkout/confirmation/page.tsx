import Confirmation from '../../../components/checkout/confirmation';
export const metadata = {
  title: 'Order status',
  robots: { index: false, follow: false },
  referrer: 'no-referrer' as const,
};
export default async function Page({
  searchParams,
}: {
  searchParams: Promise<{ order?: string }>;
}) {
  const { order } = await searchParams;
  if (!order || !/^[a-f0-9-]{36}$/.test(order))
    return (
      <section className="commerce-page">
        <h1>Order reference missing</h1>
        <p>Return to your bag to resume an existing checkout.</p>
      </section>
    );
  return <Confirmation id={order} />;
}
