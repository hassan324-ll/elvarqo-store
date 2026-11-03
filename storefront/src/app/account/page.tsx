import Account from '../../components/account/account';
export const metadata = {
  title: 'Your account',
  robots: { index: false, follow: false },
};
export default function Page() {
  return <Account />;
}
