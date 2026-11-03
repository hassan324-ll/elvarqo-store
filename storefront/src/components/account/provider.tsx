'use client';
import {
  createContext,
  useContext,
  useState,
  useEffect,
  useRef,
  useCallback,
  type ReactNode,
} from 'react';
import { accountRequest, type Customer } from './api';
const Context = createContext<{
  customer: Customer | null;
  ready: boolean;
  refresh: () => Promise<void>;
}>({ customer: null, ready: false, refresh: async () => {} });
export function AccountProvider({ children }: { children: ReactNode }) {
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [ready, setReady] = useState(false);
  const generation = useRef(0);
  const invalidate = useCallback(() => {
    generation.current++;
  }, []);
  async function refresh() {
    const version = ++generation.current;
    try {
      const result = await accountRequest<Customer>('me');
      if (version === generation.current) setCustomer(result);
    } catch {
      if (version === generation.current) setCustomer(null);
    } finally {
      if (version === generation.current) setReady(true);
    }
  }
  useEffect(() => {
    const t = setTimeout(() => void refresh(), 0);
    const sync = () => void refresh();
    window.addEventListener('focus', sync);
    window.addEventListener('elvarqo-account', sync);
    return () => {
      clearTimeout(t);
      invalidate();
      window.removeEventListener('focus', sync);
      window.removeEventListener('elvarqo-account', sync);
    };
  }, [invalidate]);
  return (
    <Context.Provider value={{ customer, ready, refresh }}>
      {children}
    </Context.Provider>
  );
}
export function useAccount() {
  return useContext(Context);
}
