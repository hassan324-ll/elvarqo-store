import { ComponentGallery } from '../../ui/components';
export const metadata = {
  robots: { index: false, follow: false },
  title: 'UI reference',
};
export default function Page() {
  return <ComponentGallery />;
}
