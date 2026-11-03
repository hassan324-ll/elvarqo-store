import type { ProductPreview } from '../ui/components';
// PDF concept fixtures only. Never import these as production catalogue records.
export const conceptProducts: ProductPreview[] = [
  {
    id: 'polo',
    name: 'The Knit Polo',
    category: 'Knitwear',
    colour: 'Midnight navy',
    detail: 'Fine knit',
    priceMinor: 6800,
    currency: 'GBP',
    image: '/concept/polo.jpg',
    alt: 'Concept illustration of a midnight navy short-sleeve knit polo',
  },
  {
    id: 'overshirt',
    name: 'The Everyday Overshirt',
    category: 'Shirts & layers',
    colour: 'Sand',
    detail: 'Cotton blend concept',
    priceMinor: 8900,
    currency: 'GBP',
    image: '/concept/overshirt.jpg',
    alt: 'Concept illustration of a sand overshirt with two chest pockets',
  },
  {
    id: 'trouser',
    name: 'The Tailored Trouser',
    category: 'Trousers',
    colour: 'Charcoal',
    detail: 'Tailored fit concept',
    priceMinor: 7900,
    currency: 'GBP',
    image: '/concept/trouser.jpg',
    alt: 'Concept illustration of charcoal tailored trousers',
  },
];
