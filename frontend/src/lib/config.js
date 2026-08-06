/**
 * config.js
 * ---------------------------------------------------------------------------
 * Frontend configuration.
 *
 * Note what is NOT here: the Gemini API key. It lives in the Laravel backend's
 * .env and never leaves the server, so nothing in this bundle is secret.
 */

/*
 * `import.meta.env.DEV` is written out directly rather than read through a
 * local alias, because Vite substitutes the literal `true`/`false` at build
 * time only when it can see this exact expression. Assigning `import.meta.env`
 * to a variable first defeats that, and the development URL then survives into
 * the production bundle as dead code — harmless at runtime, but it makes a
 * "no localhost in the shipped build" check impossible to enforce.
 */
const DEV_API_URL = import.meta.env.DEV ? 'http://localhost:8000' : '';

/**
 * Base URL of the Laravel API.
 *
 * Production: empty, giving relative URLs like `/api/health`. Laravel serves the
 * built frontend from its own public/ directory, so the API is same-origin —
 * which means no CORS, and nothing to misconfigure when the host name changes.
 *
 * Development: the frontend runs on :5173 and the API on :8000, so it needs an
 * absolute URL.
 *
 * Override with VITE_API_BASE_URL — but put it in `.env.development`, never
 * `.env`, which Vite loads in every mode including production builds.
 */
export const API_BASE_URL =
  (import.meta.env.VITE_API_BASE_URL ?? '').trim() || DEV_API_URL;
