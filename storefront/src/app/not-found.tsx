import Link from 'next/link';
export default function NotFound() {
  return (
    <div className="store-section placeholder-page">
      <p className="eyebrow">404 / A DIFFERENT DIRECTION</p>
      <h1>That page isn't here.</h1>
      <p>
        The page or product may be unavailable. Explore the current collection
        or return home.
      </p>
      <Link className="button button-primary" href="/shop">
        Explore available pieces
      </Link>
    </div>
  );
}
