/**
 * BrewLog
 * The user's recent brews. Its real purpose is to make the agent's memory
 * visible: these are the rows `get_brew_history` aggregates when it decides to
 * pre-correct a recipe.
 */
export default function BrewLog({ t, brews }) {
  if (brews.length === 0) return null;

  const feedbackLabel = {
    sour: t.tooSour,
    bitter: t.tooBitter,
    perfect: t.perfect,
  };

  return (
    <section className="panel">
      <h2 className="panel-title">{t.logTitle}</h2>
      <p className="hint">{t.logHint}</p>

      <ul className="log">
        {brews.map((brew) => (
          <li key={brew.id} className="log-row">
            <span className="log-main">
              {t.origins[brew.origin] ?? brew.origin} · {t.methods[brew.method] ?? brew.method}
              {brew.coffee_grams != null && (
                <span dir="ltr">
                  {' '}
                  — {brew.coffee_grams}
                  {t.grams} / {brew.water_ml}
                  {t.ml}
                </span>
              )}
            </span>

            <span className={`log-tag log-tag-${brew.feedback ?? 'none'}`}>
              {feedbackLabel[brew.feedback] ?? t.logUnrated}
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}
