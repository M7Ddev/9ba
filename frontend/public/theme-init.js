/*
 * Applies the stored theme before the first paint, so a dark-mode user never
 * sees a flash of the cream background while React boots.
 *
 * This lives in public/ as a separate file rather than inline in index.html so
 * the Content-Security-Policy can keep `script-src 'self'` — allowing inline
 * scripts would mean 'unsafe-inline', which is the main thing CSP exists to
 * prevent.
 *
 * Must stay in sync with src/lib/theme.js: same storage key, same rule.
 */
(function () {
  try {
    var stored = localStorage.getItem('brewmaster.theme');
    var dark = stored
      ? stored === 'dark'
      : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  } catch (e) {
    /* Storage blocked: the CSS media query still handles it. */
  }
})();
