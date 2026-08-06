/**
 * config.js
 * ---------------------------------------------------------------------------
 * Frontend configuration.
 *
 * Note what is NOT here any more: the Gemini API key. It now lives in the
 * Laravel backend's .env and never leaves the server, so nothing in this bundle
 * is secret. The only thing the browser needs to know is where the backend is.
 */

const env = import.meta.env ?? {};

/** Base URL of the Laravel API. Override with VITE_API_BASE_URL in .env. */
export const API_BASE_URL = (env.VITE_API_BASE_URL ?? '').trim() || 'http://localhost:8000';
