/**
 * RecipeCard
 * Renders the JSON recipe returned by Gemini: three big numbers at the top,
 * then the details, the ordered steps, and any notes.
 *
 * `change_summary` only exists on recipes produced by the feedback loop, so it
 * is rendered conditionally.
 */
export default function RecipeCard({ t, recipe }) {
  return (
    <section className="panel recipe">
      <h2 className="panel-title">{t.recipeTitle}</h2>

      {/* Headline numbers */}
      <div className="stats">
        <div className="stat">
          <span className="stat-value">
            {recipe.coffee_grams}
            <small>{t.grams}</small>
          </span>
          <span className="stat-label">{t.coffee}</span>
        </div>
        <div className="stat">
          <span className="stat-value">
            {recipe.water_ml}
            <small>{t.ml}</small>
          </span>
          <span className="stat-label">{t.water}</span>
        </div>
        <div className="stat">
          <span className="stat-value">
            {recipe.water_temp_c}
            <small>{t.celsius}</small>
          </span>
          <span className="stat-label">{t.temp}</span>
        </div>
      </div>

      {/* Secondary details */}
      <dl className="details">
        <div className="detail">
          <dt>{t.ratio}</dt>
          <dd dir="ltr">{recipe.ratio}</dd>
        </div>
        <div className="detail">
          <dt>{t.grind}</dt>
          <dd>{recipe.grind_size}</dd>
        </div>
        <div className="detail">
          <dt>{t.time}</dt>
          <dd dir="ltr">{recipe.total_time}</dd>
        </div>

        {/* Only present when the user named a grinder we have click data for. */}
        {recipe.grind_clicks && (
          <div className="detail">
            <dt>{t.clicks}</dt>
            <dd dir="ltr">{recipe.grind_clicks}</dd>
          </div>
        )}
      </dl>

      {/* Why the origin and processing method led to these numbers */}
      {recipe.bean_insight && (
        <p className="insight">
          <strong>{t.beanInsight}:</strong> {recipe.bean_insight}
        </p>
      )}

      {/* Shown only after a "too sour" / "too bitter" adjustment */}
      {recipe.change_summary && (
        <p className="change">
          <strong>{t.changed}:</strong> {recipe.change_summary}
        </p>
      )}

      <h3 className="section-title">{t.steps}</h3>
      <ol className="steps">
        {recipe.steps.map((step, index) => (
          <li key={index}>{step}</li>
        ))}
      </ol>

      {recipe.notes && (
        <>
          <h3 className="section-title">{t.notes}</h3>
          <p className="notes">{recipe.notes}</p>
        </>
      )}
    </section>
  );
}
