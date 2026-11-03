export type CollectionCampaign = {
  headline: string[];
  ribbon: string;
  refreshed: string;
  finishing: string;
  suffixes: string[];
  story: string[];
  alt: string;
  width: number;
  height: number;
};
export const collectionCampaigns: Record<
  string,
  CollectionCampaign | undefined
> = {
  boys: {
    width: 2020,
    height: 778,
    headline: ['Ready for every', 'adventure.'],
    ribbon: 'Little adventures. Endless possibilities.',
    refreshed: 'Made for their every day',
    finishing: 'Ready for the everyday',
    suffixes: ['boots', 'trainers', 'pyjamas'],
    story: ['Big days.', 'Little details.'],
    alt: 'Boys in casual autumn knitwear walking beside a warm stone garden',
  },
  girls: {
    width: 2020,
    height: 778,
    headline: ['Every day, a little', 'extraordinary.'],
    ribbon: 'Everyday favourites. Their own way.',
    refreshed: 'Ready for every plan',
    finishing: 'The finishing touches',
    suffixes: ['leggings', 'boots', 'pyjamas'],
    story: ['Their world.', 'Their own style.'],
    alt: 'Girls in seasonal knitwear and coats walking beside an autumn garden wall',
  },
  baby: {
    width: 2020,
    height: 778,
    headline: ['Little details.', 'Lots of love.'],
    ribbon: 'For little moments and growing adventures.',
    refreshed: 'Little wardrobe essentials',
    finishing: 'Little essentials',
    suffixes: ['bodysuits', 'first-shoes', 'socks-tights'],
    story: ['Small beginnings.', 'Beautiful details.'],
    alt: 'Cream and sage baby clothing, socks and shoes arranged on textured linen',
  },
  beauty: {
    width: 2017,
    height: 780,
    headline: ['Your everyday,', 'beautifully.'],
    ribbon: 'Discover a little more of what you love.',
    refreshed: 'Find your beauty favourites',
    finishing: 'Thoughtful gifting',
    suffixes: ['fragrance-gifts', 'skincare-gifts', 'boxes'],
    story: ['A little ritual.', 'A little you.'],
    alt: 'Unbranded beauty bottles and fragrance arranged on warm stone plinths',
  },
};
