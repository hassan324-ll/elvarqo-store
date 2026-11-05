import { useId } from 'react';
import { ProductField } from './ProductField';
import { addSize, sizeOptions } from './size-options';
import { optionList } from './variant-helper';

export default function SizePicker({
  value,
  onChange,
}: {
  value: string;
  onChange: (value: string) => void;
}) {
  const id = useId();
  return (
    <div className="size-picker">
      <div className="field">
        <label htmlFor={id}>
          <span aria-hidden="true">↔</span> Choose available sizes
        </label>
        <select
          id={id}
          value=""
          onChange={(e) => onChange(addSize(value, e.target.value))}
        >
          <option value="">Select a size to add…</option>
          {sizeOptions.map((group) => (
            <optgroup key={group.label} label={group.label}>
              {group.sizes.map((size) => (
                <option
                  key={size}
                  value={size}
                  disabled={optionList(value).some(
                    (v) => v.toLowerCase() === size.toLowerCase(),
                  )}
                >
                  {size}
                </option>
              ))}
            </optgroup>
          ))}
        </select>
        <small>
          Example sizes only. Choose sizes the supplier actually offers.
        </small>
      </div>
      {!!optionList(value).length && (
        <div className="selected-size-chips" aria-label="Selected sizes">
          {optionList(value).map((size) => (
            <button
              type="button"
              key={size}
              onClick={() =>
                onChange(
                  optionList(value)
                    .filter((v) => v !== size)
                    .join(', '),
                )
              }
              aria-label={`Remove size ${size}`}
            >
              {size}
              <span aria-hidden="true">×</span>
            </button>
          ))}
        </div>
      )}
      <ProductField
        label="Available sizes / custom sizes"
        icon="size"
        value={value}
        maxLength={1000}
        placeholder="Or type supplier sizes, separated by commas"
        onChange={(e) => onChange(e.target.value)}
      />
    </div>
  );
}
