export const sizeOptions = [
  {
    label: 'Clothing letters',
    sizes: ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'],
  },
  {
    label: 'UK clothing',
    sizes: [
      'UK 4',
      'UK 6',
      'UK 8',
      'UK 10',
      'UK 12',
      'UK 14',
      'UK 16',
      'UK 18',
      'UK 20',
      'UK 22',
      'UK 24',
    ],
  },
  {
    label: 'Baby & children',
    sizes: [
      'Newborn',
      '0–3 months',
      '3–6 months',
      '6–9 months',
      '9–12 months',
      '12–18 months',
      '18–24 months',
      '2–3 years',
      '3–4 years',
      '4–5 years',
      '5–6 years',
      '6–7 years',
      '7–8 years',
      '8–9 years',
      '9–10 years',
      '10–11 years',
      '11–12 years',
    ],
  },
  { label: 'Other', sizes: ['One size'] },
];

// Suggestions are never selected automatically or treated as supplier stock.
export function addSize(current: string, size: string): string {
  const parts = current
    .split(/[,;\n]/)
    .map((v) => v.trim())
    .filter(Boolean);
  if (
    size.trim() &&
    !parts.some((v) => v.toLowerCase() === size.trim().toLowerCase())
  )
    parts.push(size.trim());
  return parts.join(', ');
}
