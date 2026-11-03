import GuestCheckout from '../../components/checkout/checkout';
export const metadata = {
  title: 'Guest checkout',
  robots: { index: false, follow: false },
};
export default async function Page({
  searchParams,
}: {
  searchParams: Promise<{ retry?: string }>;
}) {
  const { retry } = await searchParams;
  return (
    <GuestCheckout
      retry={retry && /^[a-f0-9-]{36}$/.test(retry) ? retry : undefined}
    />
  );
}
