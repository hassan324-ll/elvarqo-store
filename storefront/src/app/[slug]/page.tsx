import Link from 'next/link';
import { notFound } from 'next/navigation';
const pages: Record<
  string,
  { title: string; phase: string; description: string }
> = {
  'the-edit': {
    title: 'The Edit',
    phase: 'Phase 9',
    description:
      'Editorial stories and collection notes will appear here once real content is approved.',
  },
  'our-story': {
    title: 'Our story',
    phase: 'Phase 9',
    description:
      'A considered wardrobe for everyday life. The final story, sourcing and material claims require merchant approval.',
  },
  help: {
    title: 'How can we help?',
    phase: 'Phase 9',
    description:
      'Customer care information will be published with approved contact details and policies.',
  },
  'delivery-returns': {
    title: 'Delivery & returns',
    phase: 'Phases 7–9',
    description:
      'Courier services, actual rates, destinations and return rules remain merchant decisions. No delivery promises are published in this preview.',
  },
  'size-guide': {
    title: 'Find your fit',
    phase: 'GARMENT-SPECIFIC MEASUREMENTS',
    description:
      'Open a product and choose Size guide for its garment-specific measurements in centimetres or inches. Select a colour first to include any variant measurement overrides.',
  },
  contact: {
    title: 'Customer care',
    phase: 'Phase 9',
    description:
      'Support contact details and the request form will be added after merchant confirmation.',
  },
  privacy: {
    title: 'Privacy notice',
    phase: 'Phase 9',
    description:
      'The merchant-specific notice will reflect actual processors, retention and data use. This preview uses no optional analytics or advertising.',
  },
  terms: {
    title: 'Terms & conditions',
    phase: 'Phase 9',
    description:
      'Purchase terms and registered business details require merchant review before launch.',
  },
  cookies: {
    title: 'Your privacy choices',
    phase: 'Phase 9',
    description:
      'No optional analytics, advertising or marketing trackers are loaded in this preview.',
  },
  accessibility: {
    title: 'Accessible by design',
    phase: 'Phases 2–11',
    description:
      'This interface supports keyboard navigation, visible focus, reduced motion and responsive reflow. Full accessibility verification continues throughout development.',
  },
};
export function generateStaticParams() {
  return Object.keys(pages).map((slug) => ({ slug }));
}

export default async function Page({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const page = pages[slug];
  if (!page) notFound();
  return (
    <div className="store-section placeholder-page">
      <p className="eyebrow">ROUTE PREVIEW / {page.phase}</p>
      <h1>{page.title}</h1>
      <p>{page.description}</p>
      <p className="muted">
        This page establishes the layout and navigation only.
      </p>
      <Link href="/collections" className="button button-primary">
        Explore the collection
      </Link>
    </div>
  );
}
