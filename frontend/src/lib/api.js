/**
 * api.js
 * ---------------------------------------------------------------------------
 * Thin client for the Laravel backend. This file replaced the old gemini.js:
 * the browser no longer talks to Google at all.
 *
 * Old flow:  Browser -> Gemini            (key exposed in the JS bundle)
 * New flow:  Browser -> Laravel -> Gemini (key stays in the backend .env)
 *
 * The whole agent — the system instruction, the `calculate_brew_ratio` tool and
 * the function-calling loop — now runs server-side in
 * backend/app/Services/Gemini/GeminiAgent.php.
 *
 * The backend always answers with one of two shapes:
 *   success -> { "recipe": { ... } }
 *   failure -> { "error": "MODEL_NOT_FOUND", "message": "..." }
 * so the frontend just re-throws the error code and lets the i18n layer
 * translate it, exactly as before.
 */

import { API_BASE_URL } from './config.js';
import { getClientId } from './clientId.js';

/**
 * POST helper with consistent error handling.
 *
 * @param {string} path   API path, e.g. '/api/recipes/generate'
 * @param {object} body   JSON payload
 * @returns {Promise<object>} The recipe object.
 */
async function postJson(path, body) {
  let response;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    });
  } catch (error) {
    // fetch() only rejects when the request never reached the server.
    console.error('[BrewMaster] Could not reach the API:', error);
    throw new Error('API_UNREACHABLE');
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    // Non-JSON body (a PHP fatal error page, an HTML 500, …).
    payload = null;
  }

  if (!response.ok || !payload?.recipe) {
    const code = payload?.error ?? 'UNKNOWN';
    console.error(`[BrewMaster] API error (${response.status}):`, payload ?? '(no JSON body)');
    throw new Error(code);
  }

  // brew_id is present when the recipe was written to the brew log; the caller
  // needs it to attach feedback later.
  return { recipe: payload.recipe, brewId: payload.brew_id ?? null };
}

/**
 * Read a photo of a coffee bag and get back the setup fields to prefill.
 *
 * @param {File} file  The image the user picked.
 * @returns {Promise<object>} { found, bean_name, origin, process, roast, flavor_notes }
 */
export async function scanBag(file) {
  const form = new FormData();
  form.append('photo', file);

  let response;
  try {
    // No Content-Type header: the browser sets the multipart boundary itself.
    response = await fetch(`${API_BASE_URL}/api/beans/scan`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: form,
    });
  } catch (error) {
    console.error('[BrewMaster] Could not reach the API:', error);
    throw new Error('API_UNREACHABLE');
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok || !payload?.beans) {
    const code = payload?.error ?? 'UNKNOWN';
    console.error(`[BrewMaster] Scan failed (${response.status}):`, payload ?? '(no JSON body)');
    throw new Error(code);
  }

  return payload.beans;
}

/**
 * Record how a brew actually tasted, so the agent can learn from it.
 *
 * Fire-and-forget from the UI's point of view: a failure here must never block
 * the user, so the caller ignores the result.
 *
 * @param {number} brewId
 * @param {'sour'|'bitter'|'perfect'} feedback
 */
export async function recordFeedback(brewId, feedback) {
  if (!brewId) return;

  try {
    await fetch(`${API_BASE_URL}/api/brews/${brewId}/feedback`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ feedback, client_id: getClientId() }),
    });
  } catch (error) {
    console.error('[BrewMaster] Could not record feedback:', error);
  }
}

/**
 * The user's recent brews, for the history panel.
 *
 * @returns {Promise<Array<object>>}
 */
export async function fetchBrews() {
  try {
    const response = await fetch(
      `${API_BASE_URL}/api/brews?client_id=${encodeURIComponent(getClientId())}`,
      { headers: { Accept: 'application/json' } },
    );
    if (!response.ok) return [];

    const payload = await response.json();
    return payload.brews ?? [];
  } catch {
    return [];
  }
}

/**
 * Ask the backend whether it is configured correctly.
 * Used on mount so a missing GEMINI_API_KEY shows a clear setup banner instead
 * of failing only when the user presses the button.
 *
 * @returns {Promise<{ok: boolean, key_configured: boolean, model: string}|null>}
 */
export async function checkHealth() {
  try {
    const response = await fetch(`${API_BASE_URL}/api/health`, {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return null;
    return await response.json();
  } catch {
    return null; // Backend not running — the UI reports API_UNREACHABLE.
  }
}

/**
 * Generate a fresh recipe from the form inputs.
 *
 * @param {object} params
 * @param {string} params.language  'ar' | 'en'
 * @param {object} params.setup     { method, roast, amountMl, taste }
 */
export async function generateRecipe({ language, setup }) {
  return postJson('/api/recipes/generate', {
    language,
    method: setup.method,
    roast: setup.roast,
    amount_ml: Number(setup.amountMl),
    taste: setup.taste,
    origin: setup.origin,
    process: setup.process,
    flavor_notes: setup.flavorNotes,
    grinder: setup.grinder,
    client_id: getClientId(),
  });
}

/**
 * Rewrite an existing recipe in the other language.
 *
 * Used when the user switches language while a recipe is on screen. The recipe
 * body is model output, so the frontend cannot retranslate it — but unlike a
 * regenerate, this keeps every brewing number exactly as it was.
 *
 * @param {object} params
 * @param {string} params.language  The language to translate INTO.
 * @param {object} params.recipe
 */
export async function translateRecipe({ language, recipe }) {
  return postJson('/api/recipes/translate', { language, recipe });
}

/**
 * Adjust an existing recipe based on the user's tasting feedback.
 *
 * @param {object} params
 * @param {string} params.language
 * @param {object} params.setup
 * @param {object} params.recipe            The recipe the user actually brewed.
 * @param {'sour'|'bitter'} params.feedback
 */
export async function adjustRecipe({ language, setup, recipe, feedback }) {
  return postJson('/api/recipes/adjust', {
    language,
    method: setup.method,
    roast: setup.roast,
    amount_ml: Number(setup.amountMl),
    taste: setup.taste,
    origin: setup.origin,
    process: setup.process,
    flavor_notes: setup.flavorNotes,
    grinder: setup.grinder,
    client_id: getClientId(),
    feedback,
    recipe,
  });
}
