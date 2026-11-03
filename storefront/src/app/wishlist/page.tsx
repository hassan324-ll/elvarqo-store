import SavedPieces from '../../components/shop/saved-pieces';
export const metadata = {
  title: 'Your saved pieces',
  alternates: { canonical: '/wishlist' },
  robots: { index: false, follow: false },
};
export default function Wishlist() {
  return (
    <div className="store-section">
      <header className="page-intro">
        <p className="eyebrow">ELVARQO / SAVED FOR LATER</p>
        <h1>Your saved pieces</h1>
        <p>
          A little more time to choose. Guests save pieces in this browser; sign
          in to sync them with your account.
        </p>
      </header>
      <SavedPieces />
    </div>
  );
}
