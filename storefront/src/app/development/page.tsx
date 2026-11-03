import HealthCheck from '../health-check';
export const metadata = { title: 'Development connection' };
export default function Page() {
  return (
    <div className="store-section">
      <header className="page-intro">
        <p className="eyebrow">LOCAL DEVELOPMENT</p>
        <h1>Connection check.</h1>
        <p>Phase 1 diagnostics remain available here.</p>
      </header>
      <div className="panel health-panel">
        <HealthCheck />
      </div>
    </div>
  );
}
