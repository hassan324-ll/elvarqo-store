export type RGB = readonly [number, number, number];
export const colourPalette: { name: string; rgb: RGB }[] = [
  { name: 'Black', rgb: [30, 30, 33] },
  { name: 'White', rgb: [238, 236, 230] },
  { name: 'Cream', rgb: [218, 208, 181] },
  { name: 'Beige', rgb: [173, 152, 123] },
  { name: 'Grey', rgb: [136, 137, 140] },
  { name: 'Brown', rgb: [100, 71, 51] },
  { name: 'Navy', rgb: [37, 51, 77] },
  { name: 'Blue', rgb: [54, 112, 155] },
  { name: 'Light blue', rgb: [139, 186, 199] },
  { name: 'Green', rgb: [89, 116, 82] },
  { name: 'Khaki', rgb: [128, 130, 71] },
  { name: 'Lime', rgb: [180, 190, 105] },
  { name: 'Red', rgb: [176, 42, 48] },
  { name: 'Burgundy', rgb: [97, 38, 55] },
  { name: 'Pink', rgb: [204, 143, 151] },
  { name: 'Purple', rgb: [117, 85, 150] },
  { name: 'Yellow', rgb: [207, 174, 60] },
  { name: 'Orange', rgb: [205, 112, 37] },
];

// Broad colour families, not a claim to identify supplier colour names.
export function colourName([r, g, b]: RGB): string {
  const max = Math.max(r, g, b),
    min = Math.min(r, g, b);
  const d = max - min,
    saturation = max ? d / max : 0;
  if (max < 58) return 'Black';
  if (r >= g && g >= b && r - b > 10 && saturation < 0.3 && max > 175)
    return 'Cream';
  if (saturation < 0.13) return max > 207 ? 'White' : 'Grey';
  if (r >= g && g >= b && saturation < 0.3)
    return max > 204 ? 'Cream' : max > 120 ? 'Beige' : 'Brown';
  let hue = !d
    ? 0
    : max === r
      ? ((g - b) / d) % 6
      : max === g
        ? (b - r) / d + 2
        : (r - g) / d + 4;
  hue = (hue * 60 + 360) % 360;
  if (hue < 18 || hue >= 345)
    return max < 120 ? 'Burgundy' : saturation < 0.45 ? 'Pink' : 'Red';
  if (hue < 43)
    return max < 135 ? 'Brown' : saturation < 0.38 ? 'Beige' : 'Orange';
  if (hue < 83) return max < 160 ? 'Khaki' : hue >= 55 ? 'Lime' : 'Yellow';
  if (hue < 170) return 'Green';
  if (hue < 255)
    return max < 100
      ? 'Navy'
      : max > 165 && saturation < 0.55
        ? 'Light blue'
        : 'Blue';
  return hue < 315 ? 'Purple' : max < 125 ? 'Burgundy' : 'Pink';
}

export function suggestColour(
  pixels: ArrayLike<number>,
  width: number,
  height: number,
): { name: string; hex: string } | null {
  const groups = new Map<string, { count: number; sum: number[] }>();
  // Centre torso region reduces background, faces, trousers and borders.
  for (let y = Math.floor(height * 0.32); y < height * 0.68; y++) {
    for (let x = Math.floor(width * 0.32); x < width * 0.68; x++) {
      const i = (y * width + x) * 4;
      if (pixels[i + 3] < 200) continue;
      const rgb: RGB = [pixels[i], pixels[i + 1], pixels[i + 2]];
      const name = colourName(rgb);
      const group = groups.get(name) || { count: 0, sum: [0, 0, 0] };
      group.count++;
      rgb.forEach((value, c) => {
        group.sum[c] += value;
      });
      groups.set(name, group);
    }
  }
  const winner = [...groups].sort((a, b) => b[1].count - a[1].count)[0];
  if (!winner) return null;
  const [name, group] = winner;
  return {
    name,
    hex:
      '#' +
      group.sum
        .map((v) =>
          Math.round(v / group.count)
            .toString(16)
            .padStart(2, '0'),
        )
        .join(''),
  };
}

export function optionList(value: string): string[] {
  const seen = new Set<string>();
  return value
    .split(/[,;\n]/)
    .map((x) => x.trim())
    .filter((x) => {
      if (!x || seen.has(x.toLowerCase())) return false;
      seen.add(x.toLowerCase());
      return true;
    });
}
export function priceMinor(value: string, allowZero = false): number | null {
  if (!/^\d{1,7}(\.\d{1,2})?$/.test(value.trim())) return null;
  const [whole, fraction = ''] = value.trim().split('.');
  const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
  return (allowZero ? minor >= 0 : minor > 0) && minor <= 100000000
    ? minor
    : null;
}
export function variantPrices(regular: string, sale: string) {
  const regularMinor = priceMinor(regular, true);
  if (regularMinor === null)
    throw Error(
      'Enter a valid regular GBP price with up to two decimal places.',
    );
  if (!sale.trim())
    return { price_minor: regularMinor, compare_at_minor: null };
  const saleMinor = priceMinor(sale);
  if (saleMinor === null || saleMinor >= regularMinor)
    throw Error(
      'Sale price must be positive and lower than the regular price.',
    );
  return { price_minor: saleMinor, compare_at_minor: regularMinor };
}
export function priceFields(variant: {
  price_minor: number;
  compare_at_minor: number | null;
}) {
  return {
    price: ((variant.compare_at_minor ?? variant.price_minor) / 100).toFixed(2),
    sale:
      variant.compare_at_minor === null
        ? ''
        : (variant.price_minor / 100).toFixed(2),
  };
}

type DraftVariantInput = {
  id?: number;
  sku: string;
  colour: string;
  size: string;
  price: string;
  sale: string;
  barcode?: string | null;
  weight_grams?: number | null;
  low_stock_threshold?: number;
  active?: boolean;
  measurements?: Record<string, number> | null;
  fit?: string | null;
  care?: string | null;
};
export function isUnusedVariant(row: DraftVariantInput): boolean {
  return (
    !row.id &&
    [
      row.sku,
      row.colour,
      row.size,
      row.price,
      row.sale,
      row.barcode,
      row.fit,
      row.care,
    ].every((v) => !v?.trim()) &&
    row.weight_grams == null &&
    !Object.keys(row.measurements || {}).length &&
    (row.low_stock_threshold === undefined || row.low_stock_threshold === 5) &&
    row.active !== false
  );
}
type Existing = { colour: string; size: string; sku: string };
export function variantCombinations(
  colours: string[],
  sizes: string[],
  existing: Existing[],
  price: string,
  prefix: string,
) {
  const minor = priceMinor(price);
  if (minor === null)
    throw Error('Enter a positive GBP price with up to two decimal places.');
  colours = optionList(colours.join(','));
  sizes = optionList(sizes.join(','));
  if (!colours.length || !sizes.length)
    throw Error('Confirm at least one colour and enter the available sizes.');
  if ([...colours, ...sizes].some((x) => x.length > 80))
    throw Error('Colour and size names must be 80 characters or fewer.');
  const combo = (c: string, s: string) =>
    JSON.stringify([c.trim().toLowerCase(), s.trim().toLowerCase()]);
  const used = new Set(existing.map((v) => combo(v.colour, v.size)));
  const skus = new Set(existing.map((v) => v.sku.toUpperCase()));
  const rows: {
    colour: string;
    size: string;
    sku: string;
    price_minor: number;
  }[] = [];
  const token = (s: string) =>
    s
      .toUpperCase()
      .replace(/[^A-Z0-9]+/g, '-')
      .replace(/^-|-$/g, '')
      .slice(0, 12) || 'OPT';
  for (const colour of colours)
    for (const size of sizes) {
      const key = combo(colour, size);
      if (used.has(key)) continue;
      const stem = `${token(prefix)}-${token(colour)}-${token(size)}`;
      let sku = stem,
        suffix = 2;
      while (skus.has(sku)) sku = `${stem}-${suffix++}`;
      rows.push({ colour, size, sku, price_minor: minor });
      used.add(key);
      skus.add(sku);
    }
  if (existing.length + rows.length > 100)
    throw Error(
      'A product supports up to 100 variants. Choose fewer colours or sizes.',
    );
  return rows;
}
