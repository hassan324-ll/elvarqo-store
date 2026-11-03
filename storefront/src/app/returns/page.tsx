import { GuestReturns } from '../../components/account/returns';
export const metadata = {
  title: 'Guest returns',
  robots: { index: false, follow: false },
};
export default function Page() {
  return <GuestReturns />;
}
