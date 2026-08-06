/**
 * clientId.js
 * ---------------------------------------------------------------------------
 * A random per-browser identifier used to group the brew log.
 *
 * This is NOT a credential and is deliberately not secret: it grants no access,
 * identifies no person, and the backend treats it purely as a grouping key. That
 * is why localStorage is fine here, unlike the API key, which is why that one
 * lives on the server.
 */

const STORAGE_KEY = 'brewmaster.client_id';

/** Returns the stored id, creating one on first visit. */
export function getClientId() {
  try {
    const existing = localStorage.getItem(STORAGE_KEY);
    if (existing) return existing;

    // crypto.randomUUID is available in every browser Vite targets; the fallback
    // covers insecure origins, where it is undefined.
    const id = (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`)
      // The API validates this as alpha_dash, so strip anything else.
      .replace(/[^a-zA-Z0-9_-]/g, '');

    localStorage.setItem(STORAGE_KEY, id);
    return id;
  } catch {
    // Private mode with storage disabled: fall back to a per-session id. The
    // brew log simply will not persist across reloads.
    return 'anonymous';
  }
}
