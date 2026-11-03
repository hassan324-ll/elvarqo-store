'use client';
import { useState } from 'react';
import Link from 'next/link';
import { Button, Field, Modal, Select } from '../../ui/components';
type Values = Record<string, string>;
export default function ListingControls({
  path,
  values,
  colours,
  sizes,
  search = false,
}: {
  path: string;
  values: Values;
  colours: string[];
  sizes: string[];
  search?: boolean;
}) {
  const [open, setOpen] = useState(false);
  function fields() {
    return (
      <>
        <Field
          label="Search products"
          name="q"
          type="search"
          defaultValue={values.q || ''}
          maxLength={100}
        />
        <Select label="Size" name="size" defaultValue={values.size || ''}>
          <option value="">All sizes</option>
          {[...new Set([...sizes, ...(values.size ? [values.size] : [])])].map(
            (s) => (
              <option value={s} key={s}>
                {s}
              </option>
            ),
          )}
        </Select>
        <Select label="Colour" name="colour" defaultValue={values.colour || ''}>
          <option value="">All colours</option>
          {[
            ...new Set([...colours, ...(values.colour ? [values.colour] : [])]),
          ].map((c) => (
            <option value={c} key={c}>
              {c}
            </option>
          ))}
        </Select>
        <Field
          label="Minimum price GBP"
          name="min"
          inputMode="decimal"
          defaultValue={values.min || ''}
        />
        <Field
          label="Maximum price GBP"
          name="max"
          inputMode="decimal"
          defaultValue={values.max || ''}
        />
        <Select
          label="Availability"
          name="stock"
          defaultValue={values.stock || 'all'}
        >
          <option value="all">All pieces</option>
          <option value="in_stock">In stock</option>
        </Select>
        <Select
          label="Sort pieces"
          name="sort"
          defaultValue={values.sort || 'featured'}
        >
          <option value="featured">Featured</option>
          <option value="newest">Newest</option>
          <option value="trending">Trending now</option>
          <option value="price_asc">Price: low to high</option>
          <option value="price_desc">Price: high to low</option>
        </Select>
        <Button type="submit">
          {search ? 'Search & apply filters' : 'Apply filters'}
        </Button>
        <Link href={path} className="text-link" prefetch={false}>
          Clear all filters
        </Link>
      </>
    );
  }
  return (
    <>
      <form
        key={JSON.stringify(values) + '-desktop'}
        action={path}
        method="get"
        className="listing-filter-form desktop-filters"
        aria-label="Refine products"
      >
        {fields()}
      </form>
      <div className="mobile-filters">
        <Button variant="secondary" onClick={() => setOpen(true)}>
          Filter & sort
        </Button>
        <span>{values.sort?.replaceAll('_', ' ') || 'Featured'}</span>
      </div>
      <Modal
        open={open}
        title="Refine your selection"
        onClose={() => setOpen(false)}
        drawer
      >
        <form
          key={JSON.stringify(values) + '-mobile'}
          action={path}
          method="get"
          className="listing-filter-form"
        >
          {fields()}
        </form>
      </Modal>
      <noscript>
        <style>
          {
            '.desktop-filters{display:grid!important}.mobile-filters{display:none!important}'
          }
        </style>
      </noscript>
    </>
  );
}
