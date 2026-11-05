// Production-only Passenger startup: parent hPanel environment can retain stale values.
process.env.STOREFRONT_INDEXING = 'true';
require('./server.js');
