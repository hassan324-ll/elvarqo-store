'use client';
import {
  createContext,
  useSyncExternalStore,
  useContext,
  useEffect,
  useState,
  useRef,
  type ReactNode,
} from 'react';
import { useAccount } from '../account/provider';
import { accountRequest } from '../account/api';
import type { SavedChoice } from '../../lib/catalogue-types';
const key = 'elvarqo.wishlist.v1';
type State = {
  items: SavedChoice[];
  ready: boolean;
  notice: string;
  save: (id: number, variant?: number | null) => void;
  remove: (id: number) => void;
};
const Context = createContext<State | null>(null);
function parse(raw: string | null): SavedChoice[] {
  if (!raw) return [];
  const doc = JSON.parse(raw);
  if (doc.version !== 1 || !Array.isArray(doc.items) || doc.items.length > 50)
    throw Error('Invalid saved list');
  const items: SavedChoice[] = [];
  for (const item of doc.items) {
    if (
      !Number.isSafeInteger(item.product_id) ||
      item.product_id < 1 ||
      (item.variant_id !== null &&
        (!Number.isSafeInteger(item.variant_id) || item.variant_id < 1))
    )
      throw Error('Invalid saved item');
    if (!items.some((i) => i.product_id === item.product_id))
      items.push({ product_id: item.product_id, variant_id: item.variant_id });
  }
  return items;
}

const empty = { items: [] as SavedChoice[], ready: false, notice: '' };
let snapshot = empty;
let initialized = false;
const listeners = new Set<() => void>();
function emit(next: typeof empty) {
  snapshot = next;
  for (const listener of listeners) listener();
}
function subscribe(listener: () => void) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}
function commit(items: SavedChoice[]) {
  let notice = snapshot.notice;
  try {
    localStorage.setItem(key, JSON.stringify({ version: 1, items }));
  } catch {
    notice =
      'Your browser could not store these pieces. They remain saved in this tab only.';
  }
  emit({ ...snapshot, items, notice });
}
function save(id: number, variant: number | null = null) {
  if (!snapshot.ready) return;
  if (
    !snapshot.items.some((i) => i.product_id === id) &&
    snapshot.items.length >= 50
  ) {
    emit({
      ...snapshot,
      notice:
        'Your saved list holds up to 50 pieces. Remove one before saving another.',
    });
    return;
  }
  commit([
    ...snapshot.items.filter((i) => i.product_id !== id),
    { product_id: id, variant_id: variant },
  ]);
}
function remove(id: number) {
  commit(snapshot.items.filter((i) => i.product_id !== id));
}
export function WishlistProvider({ children }: { children: ReactNode }) {
  const state = useSyncExternalStore(
    subscribe,
    () => snapshot,
    () => empty,
  );
  useEffect(() => {
    if (!initialized) {
      initialized = true;
      try {
        emit({
          items: parse(localStorage.getItem(key)),
          ready: true,
          notice: '',
        });
      } catch {
        emit({
          items: [],
          ready: true,
          notice:
            'Saved storage is unavailable or unreadable. You can still save pieces in this tab.',
        });
      }
    }
    const sync = (e: StorageEvent) => {
      if (e.key !== key && e.key !== null) return;
      try {
        emit({
          items: parse(e.key === null ? null : e.newValue),
          ready: true,
          notice: 'Saved pieces updated from another tab.',
        });
      } catch {
        emit({
          ...snapshot,
          notice:
            'We could not read the saved list from another tab. Your current list is unchanged.',
        });
      }
    };
    window.addEventListener('storage', sync);
    return () => window.removeEventListener('storage', sync);
  }, []);

  const { customer, ready: accountReady } = useAccount();
  const [accountItems, setAccountItems] = useState<{
    id: number;
    items: SavedChoice[];
  } | null>(null);
  const [accountNotice, setAccountNotice] = useState('');
  const [accountBusy, setAccountBusy] = useState(false);
  const current = useRef(customer?.id);
  const customerId = customer?.id;
  useEffect(() => {
    current.current = customerId;
  }, [customerId]);
  const [retry, setRetry] = useState(0);
  useEffect(() => {
    if (!customerId || !state.ready) return;
    let active = true;
    const id = customerId;
    let source: string | null = null;
    let guest: SavedChoice[];
    try {
      source = localStorage.getItem(key);
      guest = source ? parse(source) : snapshot.items;
    } catch {
      guest = snapshot.items;
    }
    accountRequest<SavedChoice[]>('saved', { action: 'merge', items: guest })
      .then((items) => {
        if (!active || current.current !== id) return;
        setAccountItems({ id, items });
        setAccountNotice('');
        if (localStorage.getItem(key) === source) {
          localStorage.removeItem(key);
          emit({ ...snapshot, items: [] });
        }
      })
      .catch(async (e) => {
        if (!active) return;
        setAccountNotice(
          e.message +
            ' Guest pieces remain saved. Review your account choices and retry.',
        );
        try {
          const existing = await accountRequest<SavedChoice[]>('saved');
          if (active && current.current === id)
            setAccountItems({ id, items: existing });
        } catch {
          // Keep the guest source and retry control available while offline.
        }
      });
    return () => {
      active = false;
    };
  }, [customerId, state.ready, retry]);
  async function update(
    id: number,
    variant: number | null,
    action: 'save' | 'remove',
  ) {
    if (!customer || accountBusy) return;
    const owner = customer.id;
    setAccountBusy(true);
    try {
      const items = await accountRequest<SavedChoice[]>('saved', {
        action,
        items: [{ product_id: id, variant_id: variant }],
      });
      if (current.current === owner) setAccountItems({ id: owner, items });
    } catch (e) {
      setAccountNotice((e as Error).message);
    } finally {
      setAccountBusy(false);
    }
  }
  const accountState = customer
    ? {
        items: accountItems?.id === customer.id ? accountItems.items : [],
        ready: accountItems?.id === customer.id && !accountBusy,
        notice: accountNotice,
        save: (id: number, variant: number | null = null) =>
          void update(id, variant, 'save'),
        remove: (id: number) => void update(id, null, 'remove'),
      }
    : { ...state, ready: state.ready && accountReady, save, remove };
  return (
    <Context.Provider value={accountState}>
      {customer && accountNotice && (
        <p role="status">
          {accountNotice}{' '}
          <button onClick={() => setRetry((n) => n + 1)}>
            Retry saved list sync
          </button>
        </p>
      )}

      {children}
    </Context.Provider>
  );
}
export function useWishlist() {
  const value = useContext(Context);
  if (!value) throw Error('WishlistProvider required');
  return value;
}
export function SavePiece({
  id,
  variant = null,
  iconOnly = false,
  title,
}: {
  id: number;
  variant?: number | null;
  iconOnly?: boolean;
  title?: string;
}) {
  const { items, ready, save, remove } = useWishlist();
  const saved = items.some((i) => i.product_id === id);
  return (
    <button
      className={`save-piece${iconOnly ? ' save-piece-icon' : ''}`}
      type="button"
      aria-pressed={saved}
      aria-label={
        iconOnly
          ? `${saved ? 'Remove' : 'Save'} ${title || 'piece'}${saved ? ' from saved pieces' : ''}`
          : undefined
      }
      title={
        iconOnly ? (saved ? 'Remove saved piece' : 'Save piece') : undefined
      }
      disabled={!ready}
      onClick={() => (saved ? remove(id) : save(id, variant))}
    >
      {iconOnly ? (
        <svg
          viewBox="0 0 24 24"
          width="20"
          height="20"
          aria-hidden="true"
          fill={saved ? 'currentColor' : 'none'}
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" />
        </svg>
      ) : saved ? (
        'Remove saved piece'
      ) : (
        'Save piece'
      )}
    </button>
  );
}
