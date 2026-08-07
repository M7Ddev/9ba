/**
 * accessCode.js
 * ---------------------------------------------------------------------------
 * The shared access code, remembered so it is entered once per browser.
 *
 * localStorage is appropriate here for the same reason it was for the client
 * id, and for a reason it was NOT appropriate for the Gemini key: this value is
 * already known to the person holding it, and it protects the owner's API quota
 * from strangers rather than protecting one user's data from another. The
 * Gemini key, by contrast, must never be in a browser at all — which is why the
 * backend exists.
 */

const STORAGE_KEY = 'brewmaster.access_code';

/** @returns {string} the stored code, or '' if none. */
export function getAccessCode() {
  try {
    return localStorage.getItem(STORAGE_KEY) ?? '';
  } catch {
    return '';
  }
}

/** @param {string} code */
export function setAccessCode(code) {
  try {
    localStorage.setItem(STORAGE_KEY, code);
  } catch {
    // Storage disabled: the code holds for this page only.
  }
}

export function clearAccessCode() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Nothing to do.
  }
}
