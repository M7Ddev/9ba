/**
 * theme.js
 * ---------------------------------------------------------------------------
 * Light/dark theme handling.
 *
 * The rule: the system preference wins until the user explicitly chooses. Once
 * they pick, the choice is remembered and overrides the system.
 *
 * The chosen theme is written to <html data-theme="…">, which styles.css keys
 * off. index.html applies the stored value before first paint, so there is no
 * flash of the wrong theme on load — this module must stay in sync with that
 * inline script.
 */

const STORAGE_KEY = 'brewmaster.theme';

/** @returns {'light'|'dark'} what the operating system asks for. */
export function systemTheme() {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * The theme to start in: the user's stored choice, else the system preference.
 *
 * @returns {'light'|'dark'}
 */
export function initialTheme() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') return stored;
  } catch {
    // Storage disabled (private mode): fall through to the system preference.
  }

  return systemTheme();
}

/**
 * Apply a theme and remember it.
 *
 * Transitions are suppressed for the instant of the swap. Without this, any
 * element with a `transition: background-color` keeps its OLD colour after the
 * custom properties change — the transition never resolves against the new
 * value and the element is left visibly stale. Hover fades still work, because
 * the suppression lasts one frame.
 *
 * @param {'light'|'dark'} theme
 */
export function applyTheme(theme) {
  const root = document.documentElement;

  root.classList.add('theme-switching');
  root.setAttribute('data-theme', theme);

  // Force a synchronous style recalculation so the new colours are committed
  // while transitions are still off.
  void root.offsetHeight;

  // Removed synchronously, not in requestAnimationFrame: rAF does not fire in a
  // tab that is not compositing (backgrounded, or a hidden preview pane), which
  // would leave transitions disabled for the rest of the session. The reflow
  // above has already committed the new values, so re-enabling here starts no
  // transition.
  root.classList.remove('theme-switching');

  try {
    localStorage.setItem(STORAGE_KEY, theme);
  } catch {
    // Not persisting is acceptable; the theme still applies for this session.
  }
}
