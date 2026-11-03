import { storefrontUrl } from './lib/site';
import { useState } from 'react';
import {
  Badge,
  Button,
  ComponentGallery,
  EmptyState,
  Icon,
  Modal,
} from './ui/components';
import Overview from './Overview';
import HealthCheck from './HealthCheck';
import { canView, type StaffIdentity } from './lib/staff-api';
import SecurityPanel from './auth/SecurityPanel';
import Catalogue from './catalogue/Catalogue';
import Inventory from './catalogue/Inventory';
import Orders from './orders/Orders';
import AfterSales from './after-sales/AfterSales';
const sections = [
  'Overview',
  'Orders',
  'Returns',
  'Products',
  'Inventory',
  'Customers',
  'Marketing',
  'Content',
  'Payments',
  'Reports',
  'Support',
  'Settings',
];
const slugs = sections.map((s) => s.toLowerCase());
function Navigation({
  active,
  onNavigate,
  staff,
}: {
  active: string;
  staff: StaffIdentity;
  onNavigate?: () => void;
}) {
  return (
    <nav className="admin-nav" aria-label="Admin navigation">
      {sections
        .filter((label) => canView(staff, label.toLowerCase()))
        .map((label) => (
          <a
            href={`?view=${label.toLowerCase()}`}
            key={label}
            aria-current={active === label.toLowerCase() ? 'page' : undefined}
            onClick={onNavigate}
          >
            <Icon
              name={
                label === 'Overview'
                  ? 'grid'
                  : label === 'Products'
                    ? 'box'
                    : label === 'Reports'
                      ? 'chart'
                      : label === 'Settings'
                        ? 'settings'
                        : 'box'
              }
              size={18}
            />
            {label}
          </a>
        ))}
    </nav>
  );
}
export default function Workspace({
  staff,
  onLogout,
  logoutBusy,
  authError,
}: {
  staff: StaffIdentity;
  onLogout: () => void;
  logoutBusy: boolean;
  authError: string;
}) {
  const requested =
    new URLSearchParams(window.location.search).get('view') || 'overview';
  const active = [
    ...slugs,
    'design-system',
    'development',
    'security',
  ].includes(requested)
    ? requested
    : 'overview';
  const [menu, setMenu] = useState(false);
  const [notifications, setNotifications] = useState(false);
  const title =
    active === 'security'
      ? 'Your security'
      : active === 'design-system'
        ? 'UI reference'
        : active === 'development'
          ? 'Connection check'
          : sections[slugs.indexOf(active)];
  return (
    <>
      <a href="#main-content" className="skip-link">
        Skip to content
      </a>
      <div className="admin-layout">
        <aside className="admin-sidebar">
          <a href="/" className="wordmark">
            ELVARQO
          </a>
          <p className="admin-caption">STORE ADMIN</p>
          <Navigation active={active} staff={staff} />
          <div className="sidebar-bottom">
            <a href={storefrontUrl} className="text-link">
              View storefront <Icon name="arrow" size={16} />
            </a>
            <a href="?view=design-system">UI reference</a>
            <a href="?view=development">Connection check</a>
            <span>
              {staff.name} / {staff.role}
            </span>
            <a href="?view=security">Your security</a>
            <Button variant="secondary" busy={logoutBusy} onClick={onLogout}>
              Sign out
            </Button>
          </div>
        </aside>
        <div className="admin-workspace">
          <header className="admin-topbar">
            <button
              className="icon-button admin-menu-trigger"
              aria-label="Open admin navigation"
              aria-haspopup="dialog"
              onClick={() => setMenu(true)}
            >
              <Icon name="menu" />
            </button>
            <p className="topbar-location">
              Workspace <span>/</span> {title}
            </p>
            <div className="topbar-actions">
              <button
                className="icon-button"
                aria-label="Notifications"
                onClick={() => setNotifications(true)}
              >
                <Icon name="bag" />
              </button>
              <span className="avatar" aria-hidden="true">
                EV
              </span>
              <span className="staff-label">{staff.name}</span>
            </div>
          </header>
          <div className="fixture-notice">
            {[
              'overview',
              'products',
              'inventory',
              'security',
              'orders',
              'returns',
              'payments',
            ].includes(active)
              ? 'Live staff workspace · Changes are saved to the development database'
              : 'Layout preview · Business figures and records below are demonstration data'}
          </div>
          <main id="main-content" className="admin-content" tabIndex={-1}>
            {authError && (
              <p role="alert" className="auth-error">
                {authError}
              </p>
            )}
            {!canView(staff, active) ? (
              <section className="panel">
                <h1>Access restricted</h1>
                <p>
                  Your staff role does not allow this workspace. Laravel
                  independently enforces every protected operation.
                </p>
              </section>
            ) : active === 'overview' ? (
              <Overview />
            ) : active === 'returns' ? (
              <AfterSales staff={staff} />
            ) : active === 'payments' ? (
              <AfterSales staff={staff} finance />
            ) : active === 'products' ? (
              <Catalogue staff={staff} />
            ) : active === 'inventory' ? (
              <Inventory staff={staff} />
            ) : active === 'orders' ? (
              <Orders staff={staff} />
            ) : active === 'security' ? (
              <SecurityPanel staff={staff} />
            ) : active === 'design-system' ? (
              <ComponentGallery />
            ) : active === 'development' ? (
              <>
                <h1>Connection check</h1>
                <div className="panel health-panel">
                  <HealthCheck />
                </div>
              </>
            ) : (
              <>
                <header className="admin-page-heading">
                  <div>
                    <p className="eyebrow">ELVARQO / STORE OPERATIONS</p>
                    <h1>{title}</h1>
                    <p className="muted">
                      Workspace layout preview. Functional tools follow in their
                      scheduled phase.
                    </p>
                  </div>
                  <Badge tone="warning">Demonstration only</Badge>
                </header>
                <section className="panel">
                  <EmptyState title={`${title} workspace`}>
                    Navigation and layout are ready. Live operations require
                    later-phase Laravel endpoints and staff permissions.
                  </EmptyState>
                  <Button disabled>Management tools unavailable</Button>
                  <p className="muted">
                    Use the UI reference to inspect shared controls and states.
                  </p>
                  <a className="text-link" href="?view=design-system">
                    Open UI reference <Icon name="arrow" size={16} />
                  </a>
                </section>
              </>
            )}
          </main>
          <footer className="admin-footer">
            ELVARQO / Staff workspace{' '}
            <span>
              Catalogue, inventory, order operations and staff security are
              live; other business tools remain previews
            </span>
          </footer>
        </div>
      </div>
      <Modal
        open={menu}
        title="Admin navigation"
        onClose={() => setMenu(false)}
        drawer
      >
        <Navigation
          active={active}
          staff={staff}
          onNavigate={() => setMenu(false)}
        />
        <a className="text-link" href="?view=security">
          Your security
        </a>
        <Button busy={logoutBusy} onClick={onLogout}>
          Sign out
        </Button>
        <a className="text-link" href="?view=design-system">
          UI reference
        </a>
        <a className="text-link" href="?view=development">
          Connection check
        </a>
      </Modal>
      <Modal
        open={notifications}
        title="Notifications"
        onClose={() => setNotifications(false)}
      >
        <EmptyState title="A quieter workspace">
          Live staff notifications will be connected in a later phase.
        </EmptyState>
      </Modal>
    </>
  );
}
