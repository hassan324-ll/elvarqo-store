import TrackingPage from '../../components/orders/tracking';
export const metadata = {
  title: 'Secure payment invitation',
  robots: { index: false, follow: false },
  referrer: 'no-referrer' as const,
};
export default function Page() {
  return <TrackingPage payment />;
}
