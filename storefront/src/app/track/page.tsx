import TrackingPage from '../../components/orders/tracking';
export const metadata = {
  title: 'Track your order',
  robots: { index: false, follow: false },
  referrer: 'no-referrer' as const,
};
export default function Page() {
  return <TrackingPage />;
}
