import type { Variant } from './catalogue-types';

// Decorative approximations of supplied colour names, never image-derived supplier data.
const swatches: Record<string, string> = {
  black: '#252526',
  white: '#fafaf7',
  cream: '#e7dfcc',
  ivory: '#f1ead9',
  beige: '#c1aa8b',
  sand: '#c7b494',
  taupe: '#a29484',
  brown: '#74543f',
  grey: '#939494',
  gray: '#939494',
  charcoal: '#4d5051',
  silver: '#c5c8c9',
  green: '#7b8a70',
  sage: '#a0ae92',
  khaki: '#96936b',
  olive: '#777c4a',
  lime: '#c1c87c',
  navy: '#283d51',
  blue: '#627fad',
  'light blue': '#a9cbd7',
  pink: '#d3a5a0',
  blush: '#e3bfbc',
  rose: '#c18b92',
  red: '#ad3f45',
  burgundy: '#733b4d',
  purple: '#9b83af',
  lilac: '#c1acd2',
  yellow: '#d9c271',
  orange: '#cd985b',
  rust: '#ac6749',
  chocolate: '#614737',
};
export function colourSwatch(name: string): string | undefined {
  return swatches[name.trim().toLowerCase()];
}
export function cardPrice(variants: Variant[]) {
  const sorted = [...variants].sort(
    (a, b) => a.price_minor - b.price_minor || a.id - b.id,
  );
  const lowest = sorted[0];
  if (!lowest) return null;
  const regular =
    lowest.compare_at_minor && lowest.compare_at_minor > lowest.price_minor
      ? lowest.compare_at_minor
      : null;
  return {
    amount: lowest.price_minor,
    regular,
    from: sorted.some((v) => v.price_minor !== lowest.price_minor),
  };
}
