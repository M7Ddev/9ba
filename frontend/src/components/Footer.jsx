/**
 * Footer
 * Attribution line. The author's name links to their GitHub.
 *
 * `rel="noopener noreferrer"` is required with target="_blank": without it the
 * opened page gets a handle on this window via window.opener and can navigate
 * it elsewhere (reverse tabnabbing).
 */
export default function Footer({ t }) {
  return (
    <footer className="footer">
      <p>
        {t.footerText}{' '}
        <a
          href="https://github.com/M7Ddev"
          target="_blank"
          rel="noopener noreferrer"
          className="footer-link"
        >
          {t.footerAuthor}
        </a>
      </p>
    </footer>
  );
}
