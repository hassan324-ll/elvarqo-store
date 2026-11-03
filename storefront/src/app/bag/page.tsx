import BagContents from '../../components/checkout/bag';
export const metadata = {
  title: 'Your bag',
  robots: { index: false, follow: false },
};
export default function Page() {
  return (
    <section className="commerce-page">
      <h1>Your bag</h1>
      <BagContents />
    </section>
  );
}
