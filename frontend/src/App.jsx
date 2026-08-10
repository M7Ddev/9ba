import { useEffect, useState } from 'react';

import RecipeForm from './components/RecipeForm.jsx';
import RecipeCard from './components/RecipeCard.jsx';
import FeedbackBar from './components/FeedbackBar.jsx';
import BrewTimer from './components/BrewTimer.jsx';
import BrewLog from './components/BrewLog.jsx';
import Footer from './components/Footer.jsx';
import AccessGate from './components/AccessGate.jsx';
import { initialTheme, applyTheme } from './lib/theme.js';
import { getAccessCode, clearAccessCode } from './lib/accessCode.js';

import { translations, LANGUAGES } from './i18n/translations.js';
import {
  generateRecipe,
  adjustRecipe,
  translateRecipe,
  recordFeedback,
  fetchBrews,
  checkHealth,
} from './lib/api.js';

/**
 * App
 * Holds all state (useState only, per the brief) and wires the three stages
 * together: setup form -> AI recipe -> feedback adjustment.
 *
 * The Gemini key is not in this app at all — the browser calls the Laravel
 * backend, and the backend calls Gemini. See src/lib/api.js.
 */
export default function App() {
  // 'ar' is the default language, so the app opens in Arabic / RTL.
  const [lang, setLang] = useState('ar');

  // Starts from the user's stored choice, else the operating system preference.
  const [theme, setTheme] = useState(initialTheme);

  const [setup, setSetup] = useState({
    method: 'V60',
    roast: 'Medium',
    amountMl: 300,
    taste: 'Balanced',
    // The user's beans — these drive the get_bean_profile tool on the backend.
    origin: 'Colombia',
    process: 'Washed',
    flavorNotes: '',
    grinder: 'Other',
    serve: 'Hot',
    // Optional overrides — empty means "assistant decides".
    coffeeGrams: '',
    iceGrams: '',
  });

  const [recipe, setRecipe] = useState(null);
  // Which language the displayed recipe was generated in. The recipe body is a
  // snapshot of model output, so toggling the UI language cannot retranslate it
  // — we track the mismatch and offer to regenerate instead.
  const [recipeLang, setRecipeLang] = useState(null);
  const [loading, setLoading] = useState(false); // initial generation
  const [adjusting, setAdjusting] = useState(false); // feedback loop
  const [translating, setTranslating] = useState(false); // language-switch rewrite
  const [satisfied, setSatisfied] = useState(false); // user pressed "perfect"
  const [errorCode, setErrorCode] = useState(null);

  // null = still checking; otherwise the backend's /api/health answer.
  const [health, setHealth] = useState(null);

  // Brew log: the id of the recipe currently on screen, and the recent history.
  const [brewId, setBrewId] = useState(null);
  const [brews, setBrews] = useState([]);

  // Set when the backend requires an access code and we do not have a valid one.
  const [locked, setLocked] = useState(false);

  const t = translations[lang];
  const dir = LANGUAGES[lang].dir;

  // Keep the document in sync with the chosen language so the whole page flips.
  useEffect(() => {
    document.documentElement.lang = lang;
    document.documentElement.dir = dir;
  }, [lang, dir]);

  // Write the theme to <html data-theme> and remember the choice.
  useEffect(() => {
    applyTheme(theme);
  }, [theme]);

  // Ask the backend on mount whether it is reachable and has a key configured,
  // so setup problems surface immediately instead of on the first button press.
  useEffect(() => {
    checkHealth().then((result) => {
      setHealth(result ?? { ok: false, key_configured: false });

      // Lock the UI when the server wants a code and this browser has none.
      // A stored-but-wrong code is caught later, by a 401 from a real request.
      if (result?.access_required && getAccessCode() === '') {
        setLocked(true);
      }
    });

    fetchBrews().then(setBrews);
  }, []);

  /**
   * Handle a rejected access code from any request: drop the stored value and
   * show the gate again rather than leaving the user with a dead button.
   */
  function handleErrorCode(code) {
    if (code === 'UNAUTHORIZED') {
      clearAccessCode();
      setLocked(true);
      return;
    }

    setErrorCode(code);
  }

  /** Refresh the brew log after anything that changes it. */
  function refreshLog() {
    fetchBrews().then(setBrews);
  }

  /** Prefill the bean fields from a scanned bag photo. */
  function handleScanned(beans) {
    setSetup((current) => ({
      ...current,
      origin: beans.origin,
      process: beans.process,
      roast: beans.roast,
      flavorNotes: beans.flavor_notes || current.flavorNotes,
    }));
  }

  const backendReady = health?.ok === true && health?.key_configured === true;

  // Setup banner: distinguish "backend not running" from "backend has no key".
  const setupCode =
    health === null
      ? null
      : health.ok === false
        ? 'API_UNREACHABLE'
        : !health.key_configured
          ? 'MISSING_KEY'
          : null;

  /** Basic client-side guard before we spend an API call. */
  function validAmount() {
    const amount = Number(setup.amountMl);
    return Number.isFinite(amount) && amount >= 20 && amount <= 2000;
  }

  async function handleGenerate() {
    if (!validAmount()) {
      setErrorCode('AMOUNT');
      return;
    }

    setLoading(true);
    setErrorCode(null);
    setSatisfied(false);

    try {
      const { recipe: result, brewId: id } = await generateRecipe({
        language: lang,
        setup: { ...setup, amountMl: Number(setup.amountMl) },
      });
      setRecipe(result);
      setRecipeLang(lang);
      setBrewId(id);
      refreshLog();
    } catch (error) {
      // api.js throws the backend's short code ('INVALID_KEY', 'RATE_LIMIT', …).
      handleErrorCode(error.message);
    } finally {
      setLoading(false);
    }
  }

  async function handleFeedback(kind) {
    // Every rating is recorded, including "perfect" — that is what teaches the
    // agent, via the get_brew_history tool, what this user's palate is like.
    await recordFeedback(brewId, kind);
    refreshLog();

    // "Perfect" needs no new recipe — just confirm and stop.
    if (kind === 'perfect') {
      setSatisfied(true);
      return;
    }

    setAdjusting(true);
    setErrorCode(null);
    setSatisfied(false);

    try {
      const { recipe: result, brewId: id } = await adjustRecipe({
        language: lang,
        setup: { ...setup, amountMl: Number(setup.amountMl) },
        recipe,
        feedback: kind,
      });
      setRecipe(result);
      setRecipeLang(lang);
      setBrewId(id);
      refreshLog();
    } catch (error) {
      handleErrorCode(error.message);
    } finally {
      setAdjusting(false);
    }
  }

  /**
   * Translate the displayed recipe into the current language.
   * Keeps the brewing numbers identical — unlike handleGenerate, which asks the
   * model for a brand-new recipe that may differ slightly.
   */
  async function handleTranslate() {
    setTranslating(true);
    setErrorCode(null);

    try {
      const { recipe: result } = await translateRecipe({ language: lang, recipe });
      setRecipe(result);
      setRecipeLang(lang);
    } catch (error) {
      handleErrorCode(error.message);
    } finally {
      setTranslating(false);
    }
  }

  // The displayed recipe was written in a language the user is no longer reading.
  const languageMismatch = recipe !== null && recipeLang !== null && recipeLang !== lang;

  // Error text: 'AMOUNT' is our own validation, everything else comes from the API.
  const errorMessage =
    errorCode === 'AMOUNT' ? t.amountError : errorCode ? t.errors[errorCode] ?? t.errors.UNKNOWN : null;

  return (
    <div className="app">
      <header className="header">
        <div>
          <h1 className="brand">{t.appName}</h1>
          <p className="tagline">{t.tagline}</p>
        </div>
        <div className="header-actions">
          {/* Label and icon describe what the button switches TO. */}
          <button
            type="button"
            className="btn btn-ghost theme-toggle"
            onClick={() => setTheme((current) => (current === 'dark' ? 'light' : 'dark'))}
            aria-label={theme === 'dark' ? t.switchToLight : t.switchToDark}
            title={theme === 'dark' ? t.switchToLight : t.switchToDark}
          >
            {theme === 'dark' ? '☀' : '☾'}
          </button>

          <button
            type="button"
            className="btn btn-ghost lang-toggle"
            onClick={() => setLang((current) => (current === 'ar' ? 'en' : 'ar'))}
          >
            {LANGUAGES[lang].label}
          </button>
        </div>
      </header>

      <main className="main">
        {/* Locked: nothing else is usable, so show only the gate. */}
        {locked && (
          <AccessGate
            t={t}
            onUnlocked={() => {
              setLocked(false);
              setErrorCode(null);
              refreshLog();
            }}
          />
        )}

        {!locked && (
          <>
            {/* Setup problem (backend down or key missing), not a user mistake. */}
            {setupCode && <p className="error">{t.errors[setupCode]}</p>}

        <RecipeForm
          t={t}
          setup={setup}
          onChange={setSetup}
          onSubmit={handleGenerate}
          onScanned={handleScanned}
          loading={loading}
          canSubmit={backendReady}
        />

        {errorMessage && <p className="error">{errorMessage}</p>}

        {/* The recipe text is model output in the previous language; offer a
            regenerate rather than showing English labels around Arabic steps. */}
        {languageMismatch && (
          <div className="notice">
            <span>{t.languageMismatch}</span>
            <div className="notice-actions">
              {/* Translate keeps the numbers; regenerate asks for a fresh recipe. */}
              <button
                type="button"
                className="btn btn-primary btn-small"
                onClick={handleTranslate}
                disabled={loading || adjusting || translating}
              >
                {translating ? t.translating : t.translate}
              </button>
              <button
                type="button"
                className="btn btn-outline btn-small"
                onClick={handleGenerate}
                disabled={loading || adjusting || translating}
              >
                {loading ? t.generating : t.regenerate}
              </button>
            </div>
          </div>
        )}

        {recipe && (
          <>
            <RecipeCard t={t} recipe={recipe} />
            <BrewTimer t={t} steps={recipe.steps} />
            <FeedbackBar t={t} onFeedback={handleFeedback} loading={adjusting} satisfied={satisfied} />
          </>
        )}

            <BrewLog t={t} brews={brews} />
          </>
        )}
      </main>

      <Footer t={t} />
    </div>
  );
}
