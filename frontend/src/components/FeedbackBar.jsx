/**
 * FeedbackBar
 * The adjust loop. "Too sour" / "too bitter" send the current recipe back to
 * Gemini for a diagnosis + fix; "perfect" just shows a confirmation message.
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
