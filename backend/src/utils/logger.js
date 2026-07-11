/**
 * Minimal structured logger.
 *
 * Replaces PHP's `error_log(...)` calls. Writes timestamped lines to stdout
 * (and stderr for errors). Kept dependency-free on purpose.
 */
const ts = () => new Date().toISOString();

const logger = {
  info(msg, meta) {
    console.log(`[${ts()}] INFO  ${msg}${meta ? ' ' + safe(meta) : ''}`);
  },
  warn(msg, meta) {
    console.warn(`[${ts()}] WARN  ${msg}${meta ? ' ' + safe(meta) : ''}`);
  },
  error(msg, meta) {
    console.error(`[${ts()}] ERROR ${msg}${meta ? ' ' + safe(meta) : ''}`);
  },
};

function safe(meta) {
  if (meta instanceof Error) return meta.stack || meta.message;
  try {
    return typeof meta === 'string' ? meta : JSON.stringify(meta);
  } catch {
    return String(meta);
  }
}

export default logger;
