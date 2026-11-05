import type { Collection } from './catalogue-types';

export function collectionContent(group: Collection, parent?: Collection) {
  if (
    group.kind === 'category' &&
    group.slug === 'womens-knitwear' &&
    parent?.slug === 'women'
  ) {
    return {
      heading: "Women's knitwear",
      title: "Women's Knitwear & Knitted Jumpers",
      description:
        "Explore women's knitwear, from textured V-neck jumpers to round-neck and collared styles. View each jumper for colours, sizes and price.",
      introduction:
        'Explore textured and plain knitted jumpers, with V-neck, round-neck and collared styles. View each jumper for colours, sizes and price.',
    };
  }
  return {
    heading: group.title,
    title: group.title,
    description:
      group.description ||
      `Browse ${group.title.toLowerCase()} at ELVARQO. View product details, listed options and current GBP prices.`,
    introduction: group.description || undefined,
  };
}
