/**
 * Server entrypoint. Boots the Express app and starts listening.
 */
import { createApp } from './src/app.js';
import config from './src/config/index.js';
import logger from './src/utils/logger.js';

const app = createApp();

const server = app.listen(config.port, () => {
  logger.info(`IFQM backend listening on http://localhost:${config.port} (${config.env})`);
});

// Graceful shutdown
for (const sig of ['SIGINT', 'SIGTERM']) {
  process.on(sig, () => {
    logger.info(`${sig} received — shutting down`);
    server.close(() => process.exit(0));
  });
}

process.on('unhandledRejection', (reason) => {
  logger.error('Unhandled promise rejection', reason);
});
process.on('uncaughtException', (err) => {
  logger.error('Uncaught exception', err);
  process.exit(1);
});
