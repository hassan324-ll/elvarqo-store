'use client';
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react';
import { commerce, type Bag } from '../../lib/commerce';
type Context = {
  bag: Bag | null;
  loading: boolean;
  error: string;
  refresh: () => Promise<Bag | null>;
  add: (id: number) => Promise<void>;
  change: (id: number, qty: number) => Promise<void>;
};
const BagContext = createContext<Context | null>(null);
export function BagProvider({ children }: { children: React.ReactNode }) {
  const [bag, setBag] = useState<Bag | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const initial = useRef<Promise<Bag> | null>(null);
  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const b = await commerce<Bag | null>('bag');
      setBag(b);
      setError('');
      return b;
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Bag unavailable.');
      throw e;
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => {
    const update = () => void refresh().catch(() => {});
    const timer = setTimeout(update, 0);
    window.addEventListener('focus', update);
    return () => {
      clearTimeout(timer);
      window.removeEventListener('focus', update);
    };
  }, [refresh]);
  async function update(id: number, qty: number, increment: boolean) {
    setLoading(true);
    setError('');
    try {
      let b = await commerce<Bag | null>('bag');
      if (!b) {
        if (!initial.current) initial.current = commerce<Bag>('bags', {});
        try {
          b = await initial.current;
        } finally {
          initial.current = null;
        }
      }
      const quantity = increment
        ? (b.items.find((l) => l.variant_id === id)?.quantity || 0) + qty
        : qty;
      const next = await commerce<Bag>('bag/items', {
        variant_id: id,
        quantity,
        revision: b.revision,
      });
      setBag(next);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Bag unavailable.');
      throw e;
    } finally {
      setLoading(false);
    }
  }
  return (
    <BagContext.Provider
      value={{
        bag,
        loading,
        error,
        refresh,
        add: (id) => update(id, 1, true),
        change: (id, qty) => update(id, qty, false),
      }}
    >
      {children}
    </BagContext.Provider>
  );
}
export function useBag() {
  const c = useContext(BagContext);
  if (!c) throw new Error('BagProvider required.');
  return c;
}
