/**
 * FeedbackBar
 * The adjust loop. Anything other than "perfect" sends the current recipe back
 * to Gemini for a diagnosis and fix; "perfect" just confirms.
 *
 * The three faults are not variations of one another. Sour and bitter are
 * EXTRACTION problems, corrected with grind, temperature and time. Weak is a
 * CONCENTRATION problem — the extraction may be fine, there is simply too little
 * coffee for the water — and is corrected with the ratio. The backend keeps them
 * on separate axes for exactly this reason.
 */
export default function FeedbackBar({ t, onFeedback, loading, satisfied }) {
  return (
    <section className="panel feedback">
      <h2 className="panel-title">{t.feedbackTitle}</h2>

      <div className="feedback-buttons">
        <button
          type="button"
          className="btn btn-outline"
          onClick={() => onFeedback('sour')}
          disabled={loading}
        >
          {t.tooSour}
        </button>
        <button
          type="button"
          className="btn btn-outline"
          onClick={() => onFeedback('bitter')}
          disabled={loading}
        >
          {t.tooBitter}
        </button>
        <button
          type="button"
          className="btn btn-outline"
          onClick={() => onFeedback('weak')}
          disabled={loading}
        >
          {t.tooWeak}
        </button>
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => onFeedback('perfect')}
          disabled={loading}
        >
          {t.perfect}
        </button>
      </div>

      {loading && <p className="hint">{t.adjusting}</p>}
      {satisfied && !loading && <p className="success">{t.perfectMessage}</p>}
    </section>
  );
}
