/**
 * bcrypt hashing worker.
 *
 * bcryptjs is pure JavaScript: `hashSync` occupies the thread for the whole
 * key-stretching run and yields nothing. Hashing a 10,000-employee import on the
 * main thread would therefore freeze the entire server — measured on this
 * machine, ~236ms per hash at cost 12 is ~39 minutes of a completely
 * unresponsive event loop. Every other request would hang behind it.
 *
 * So hashing happens here instead, on worker threads, where blocking is
 * harmless: the main thread keeps serving normal traffic while the import
 * grinds through its batch.
 */
import { parentPort } from 'node:worker_threads';
import bcrypt from 'bcryptjs';

parentPort.on('message', ({ items, rounds }) => {
  try {
    // items: [{ key, password }] -> [{ key, hash }]
    const out = items.map(({ key, password }) => ({
      key,
      hash: bcrypt.hashSync(password, rounds),
    }));
    parentPort.postMessage({ ok: true, out });
  } catch (err) {
    parentPort.postMessage({ ok: false, error: err?.message || String(err) });
  }
});
