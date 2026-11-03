'use client';
import { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Icon, Modal, Field, Button } from '../ui/components';
import BagContents from './checkout/bag';
import { useBag } from './checkout/bag-provider';
type Overlay = 'shop' | 'search' | 'bag' | 'account' | null;
import type { Collection } from '../lib/catalogue-types';
import { useWishlist } from './shop/wishlist';
import SearchSuggestions from './shop/search-suggestions';
export function StoreHeader({
  collections,
  unavailable,
}: {
  collections: Collection[];
  unavailable: boolean;
}) {
  const pathname = usePathname();
  const { items, notice } = useWishlist();
  const { bag } = useBag();
  const [overlay, setOverlay] = useState<Overlay>(null);
  const [query, setQuery] = useState('');
  const [openCollection, setOpenCollection] = useState<number | null>(null);
  const expandedCollection = collections.find((c) => c.id === openCollection);
  function links() {
    return (
      <>
        <Link href="/shop" onClick={() => setOverlay(null)}>
          All products
          <Icon name="arrow" size={16} />
        </Link>
        {collections.map((c) => (
          <div className="drawer-collection-group" key={c.id}>
            <Link
              href={'/collections/' + c.slug}
              onClick={() => setOverlay(null)}
            >
              {c.title}
              <Icon name="arrow" size={16} />
            </Link>
            {c.categories?.map((child) => (
              <Link
                className="drawer-category-link"
                key={child.id}
                href={'/collections/' + child.slug}
                onClick={() => setOverlay(null)}
              >
                {child.title}
              </Link>
            ))}
          </div>
        ))}
      </>
    );
  }
  return (
    <>
      <a className="skip-link" href="#main-content">
        Skip to content
      </a>
      <div className="announcement">ELVARQO / CONSIDERED EVERYDAY WEAR</div>
      <header className="store-header">
        <button
          className="icon-button mobile-menu"
          aria-label="Open shop navigation"
          aria-expanded={overlay === 'shop'}
          aria-haspopup="dialog"
          onClick={() => setOverlay('shop')}
        >
          <Icon name="menu" />
        </button>
        <Link className="wordmark" href="/" aria-label="ELVARQO home">
          ELVARQO
        </Link>
        <nav className="desktop-nav" aria-label="Main navigation">
          <button
            aria-expanded={overlay === 'shop'}
            aria-haspopup="dialog"
            onClick={() => setOverlay('shop')}
          >
            Shop
          </button>
          <Link href="/collections">Collections</Link>
          <Link href="/the-edit">The Edit</Link>
          <Link href="/our-story">Our Story</Link>
        </nav>
        <div className="header-actions">
          <span className="market">UK / GBP</span>
          <button
            className="icon-button search-trigger"
            aria-label="Search"
            aria-haspopup="dialog"
            onClick={() => {
              setQuery(
                (q) =>
                  q ||
                  new URLSearchParams(window.location.search).get('q') ||
                  '',
              );
              setOverlay('search');
            }}
          >
            <Icon name="search" />
          </button>
          <button
            className="icon-button account-trigger"
            aria-label="Account"
            aria-haspopup="dialog"
            onClick={() => setOverlay('account')}
          >
            <Icon name="user" />
          </button>
          <Link className="saved-nav" href="/wishlist">
            Saved <span aria-label="saved piece count">{items.length}</span>
          </Link>
          <button
            className="bag-trigger"
            aria-haspopup="dialog"
            onClick={() => setOverlay('bag')}
          >
            <Icon name="bag" />
            <span>
              Bag{' '}
              <span className="bag-count">
                {bag?.items.reduce((n, l) => n + l.quantity, 0) || 0}
              </span>
            </span>
          </button>
        </div>
      </header>
      <div
        className="collection-navigation"
        onPointerLeave={(e) => {
          if (e.pointerType === 'mouse') setOpenCollection(null);
        }}
        onBlur={(e) => {
          if (!e.currentTarget.contains(e.relatedTarget))
            setOpenCollection(null);
        }}
        onKeyDown={(e) => {
          if (e.key === 'Escape' && openCollection !== null) {
            e.preventDefault();
            document
              .querySelector<HTMLAnchorElement>(
                `[aria-controls="collection-categories-${openCollection}"]`,
              )
              ?.focus();
            setOpenCollection(null);
          }
        }}
      >
        <nav className="collection-menu" aria-label="Shop collections">
          <div className="collection-menu-inner">
            <Link
              href="/shop"
              onPointerEnter={(e) => {
                if (e.pointerType === 'mouse') setOpenCollection(null);
              }}
              onFocus={() => setOpenCollection(null)}
              aria-current={pathname === '/shop' ? 'page' : undefined}
            >
              Shop all
            </Link>
            {collections.map((collection) => (
              <div
                className="collection-menu-group"
                key={collection.id}
                onPointerEnter={(e) => {
                  if (e.pointerType === 'mouse')
                    setOpenCollection(
                      collection.categories?.length ? collection.id : null,
                    );
                }}
              >
                <Link
                  href={'/collections/' + collection.slug}
                  className="collection-category-toggle"
                  aria-expanded={
                    collection.categories?.length
                      ? openCollection === collection.id
                      : undefined
                  }
                  aria-controls={
                    collection.categories?.length
                      ? `collection-categories-${collection.id}`
                      : undefined
                  }
                  aria-current={
                    pathname === '/collections/' + collection.slug
                      ? 'page'
                      : undefined
                  }
                  onClick={() => setOpenCollection(null)}
                  onKeyDown={(e) => {
                    if (
                      e.key === 'ArrowDown' &&
                      collection.categories?.length
                    ) {
                      e.preventDefault();
                      setOpenCollection(collection.id);
                      requestAnimationFrame(() =>
                        document
                          .querySelector<HTMLAnchorElement>(
                            `#collection-categories-${collection.id} a`,
                          )
                          ?.focus(),
                      );
                    }
                  }}
                >
                  {collection.title}
                </Link>
              </div>
            ))}
          </div>
        </nav>
        {expandedCollection && (
          <nav
            className="collection-category-panel"
            id={`collection-categories-${expandedCollection.id}`}
            aria-label={`${expandedCollection.title} categories`}
          >
            <div>
              <div className="collection-category-heading">
                <div>
                  <p className="eyebrow">Shop by category</p>
                  <h2>{expandedCollection.title}</h2>
                </div>
                <Link
                  href={'/collections/' + expandedCollection.slug}
                  onClick={() => setOpenCollection(null)}
                >
                  Shop all {expandedCollection.title}
                  <Icon name="arrow" size={16} />
                </Link>
                <button
                  type="button"
                  className="icon-button"
                  aria-label="Close categories"
                  onClick={() => setOpenCollection(null)}
                >
                  <Icon name="close" size={18} />
                </button>
              </div>
              <div className="collection-category-grid">
                {expandedCollection.categories?.map((c) => (
                  <Link
                    href={'/collections/' + c.slug}
                    key={c.id}
                    aria-current={
                      pathname === '/collections/' + c.slug ? 'page' : undefined
                    }
                    onClick={() => setOpenCollection(null)}
                  >
                    <span className="collection-category-photo">
                      {c.image_url ? (
                        <img src={c.image_url} alt={c.image_alt || c.title} />
                      ) : (
                        <span aria-hidden="true">{c.title.charAt(0)}</span>
                      )}
                    </span>
                    <span>{c.title}</span>
                  </Link>
                ))}
              </div>
            </div>
          </nav>
        )}
      </div>
      <div className="fixture-notice">
        Wise bank transfer · Test tax and delivery rates
      </div>
      {notice && (
        <p role="status" className="shop-notice storage-notice">
          {notice}
        </p>
      )}
      <Modal
        open={overlay === 'shop'}
        title="Explore ELVARQO"
        onClose={() => setOverlay(null)}
      >
        <div className="mega-menu">
          <div>
            <p className="eyebrow">Shop</p>
            {links()}
            {unavailable && (
              <p role="status">
                Collections are temporarily unavailable. Try browsing all
                products.
              </p>
            )}
          </div>
          <div>
            <p className="eyebrow">Explore</p>
            <Link href="/the-edit" onClick={() => setOverlay(null)}>
              The everyday edit
            </Link>
            <Link href="/size-guide" onClick={() => setOverlay(null)}>
              Size guide
            </Link>
            <Link href="/our-story" onClick={() => setOverlay(null)}>
              Our story
            </Link>
          </div>
          <div className="menu-feature">
            <img
              src="/concept/overshirt.jpg"
              alt="Sand overshirt concept"
              width="200"
              height="240"
            />
            <p>Considered layers.</p>
            <Link href="/collections" onClick={() => setOverlay(null)}>
              Explore the edit →
            </Link>
          </div>
        </div>
      </Modal>
      <Modal
        open={overlay === 'search'}
        title="Search the collection"
        onClose={() => setOverlay(null)}
      >
        <form action="/search" method="get" className="search-form">
          <Field
            label="Search products"
            name="q"
            type="search"
            placeholder="Search the collection"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            autoComplete="off"
            maxLength={100}
          />
          <Button type="submit">Search collection</Button>
        </form>
        <SearchSuggestions query={query} onSelect={() => setOverlay(null)} />
      </Modal>
      <Modal
        open={overlay === 'bag'}
        title="Your bag"
        drawer
        onClose={() => setOverlay(null)}
      >
        {overlay === 'bag' && (
          <BagContents onNavigate={() => setOverlay(null)} />
        )}
      </Modal>
      <Modal
        open={overlay === 'account'}
        title="Your ELVARQO account"
        drawer
        onClose={() => setOverlay(null)}
      >
        <p>
          Sign in to view linked orders, manage your addresses and save pieces
          across devices. Guest checkout remains available.
        </p>
        <a className="text-link" href="/account">
          Open your account
        </a>
        <a className="text-link" href="/returns">
          Guest returns
        </a>
        <Button variant="secondary" onClick={() => setOverlay(null)}>
          Continue exploring
        </Button>
      </Modal>
    </>
  );
}
export function StoreFooter() {
  return (
    <footer className="store-footer">
      <div className="footer-main">
        <Link href="/" className="wordmark">
          ELVARQO
        </Link>
        <nav aria-label="Customer care">
          {[
            ['Customer care', '/help'],
            ['Track your order', '/track'],
            ['Delivery & returns', '/delivery-returns'],
            ['Size guide', '/size-guide'],
            ['Contact', '/contact'],
          ].map(([n, u]) => (
            <Link key={u} href={u}>
              {n}
            </Link>
          ))}
        </nav>
        <p>United Kingdom / GBP</p>
      </div>
      <div className="footer-bottom">
        <p>Considered everyday wear.</p>
        <nav aria-label="Store information">
          {[
            ['Privacy', '/privacy'],
            ['Terms', '/terms'],
            ['Cookies', '/cookies'],
            ['Accessibility', '/accessibility'],
            ['UI reference', '/design-system'],
            ['Connection check', '/development'],
          ].map(([n, u]) => (
            <Link key={u} href={u}>
              {n}
            </Link>
          ))}
        </nav>
        <span>ELVARQO / CONSIDERED EVERYDAY WEAR</span>
      </div>
    </footer>
  );
}
