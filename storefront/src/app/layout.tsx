import type { Metadata } from 'next';
import '../ui/tokens.css';
import './globals.css';
import { StoreHeader, StoreFooter } from '../components/store-shell';
import { BagProvider } from '../components/checkout/bag-provider';
import { AccountProvider } from '../components/account/provider';
import { WishlistProvider } from '../components/shop/wishlist';
import { navigation, siteOrigin, indexEnabled } from '../lib/catalogue-server';
export const metadata: Metadata = {
  metadataBase: new URL(siteOrigin),
  title: {
    default: 'ELVARQO | Considered everyday wear',
    template: '%s | ELVARQO',
  },
  description:
    'Explore the ELVARQO collection. Considered everyday wear, with garment details to help you choose.',
  robots: { index: indexEnabled, follow: true },
};
export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  let all: Awaited<ReturnType<typeof navigation>> = [];
  let unavailable = false;
  try {
    all = await navigation();
  } catch {
    unavailable = true;
  }
  return (
    <html lang="en-GB">
      <body>
        <AccountProvider>
          <WishlistProvider>
            <BagProvider>
              <StoreHeader collections={all} unavailable={unavailable} />
              <main id="main-content" tabIndex={-1}>
                {children}
              </main>
              <StoreFooter />
            </BagProvider>
          </WishlistProvider>
        </AccountProvider>
      </body>
    </html>
  );
}
