import {
  BREW_METHODS,
  ROAST_LEVELS,
  TASTE_PREFERENCES,
  SERVE_STYLES,
  ORIGINS,
  PROCESSES,
  GRINDERS,
} from '../i18n/translations.js';
import BagScanner from './BagScanner.jsx';

/**
 * RecipeForm
 * The four inputs that describe the user's setup. Values are the canonical
 * English terms (that is what Gemini receives); only the labels are localised.
 */
export default function RecipeForm({ t, setup, onChange, onSubmit, onScanned, loading, canSubmit }) {
  // Small helper so each control updates one field of the setup object.
  const update = (field) => (event) => onChange({ ...setup, [field]: event.target.value });

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit();
  };

  // Espresso talks about "yield", every other method about "water".
  const amountLabel = setup.method === 'Espresso' ? t.amountEspresso : t.amount;

  return (
    <form className="panel" onSubmit={handleSubmit}>
      <h2 className="panel-title">{t.formTitle}</h2>

      <div className="grid">
        <label className="field">
          <span className="field-label">{t.method}</span>
          <select className="input" value={setup.method} onChange={update('method')}>
            {BREW_METHODS.map((method) => (
              <option key={method} value={method}>
                {t.methods[method]}
              </option>
            ))}
          </select>
        </label>

        <label className="field">
          <span className="field-label">{t.roast}</span>
          <select className="input" value={setup.roast} onChange={update('roast')}>
            {ROAST_LEVELS.map((roast) => (
              <option key={roast} value={roast}>
                {t.roasts[roast]}
              </option>
            ))}
          </select>
        </label>

        <label className="field">
          <span className="field-label">{amountLabel}</span>
          <input
            type="number"
            className="input"
            value={setup.amountMl}
            onChange={update('amountMl')}
            min="20"
            max="2000"
            step="10"
            required
          />
        </label>

        <label className="field">
          <span className="field-label">{t.taste}</span>
          <select className="input" value={setup.taste} onChange={update('taste')}>
            {TASTE_PREFERENCES.map((taste) => (
              <option key={taste} value={taste}>
                {t.tastes[taste]}
              </option>
            ))}
          </select>
        </label>

        {/* Iced uses the Japanese method: part of the total liquid is ice, and
            the backend computes the split so the dose stays correct. */}
        <label className="field">
          <span className="field-label">{t.serve}</span>
          <select className="input" value={setup.serve} onChange={update('serve')}>
            {SERVE_STYLES.map((style) => (
              <option key={style} value={style}>
                {t.serves[style]}
              </option>
            ))}
          </select>
        </label>

        {/* Feeds get_grind_setting, which turns "medium-fine" into click numbers. */}
        <label className="field">
          <span className="field-label">{t.grinder}</span>
          <select className="input" value={setup.grinder} onChange={update('grinder')}>
            {GRINDERS.map((grinder) => (
              <option key={grinder} value={grinder}>
                {grinder === 'Other' ? t.grinderOther : grinder}
              </option>
            ))}
          </select>
        </label>
      </div>

      {/* The beans themselves. These drive the get_bean_profile tool server-side. */}
      <h2 className="panel-title panel-title-spaced">{t.beansTitle}</h2>

      {/* Fastest path: let Gemini read the label instead of typing it. */}
      <BagScanner t={t} onScanned={onScanned} disabled={!canSubmit || loading} />

      <div className="grid">
        <label className="field">
          <span className="field-label">{t.origin}</span>
          <select className="input" value={setup.origin} onChange={update('origin')}>
            {ORIGINS.map((origin) => (
              <option key={origin} value={origin}>
                {t.origins[origin]}
              </option>
            ))}
          </select>
        </label>

        <label className="field">
          <span className="field-label">{t.process}</span>
          <select className="input" value={setup.process} onChange={update('process')}>
            {PROCESSES.map((process) => (
              <option key={process} value={process}>
                {t.processes[process]}
              </option>
            ))}
          </select>
        </label>
      </div>

      <label className="field field-wide">
        <span className="field-label">{t.flavorNotes}</span>
        <input
          type="text"
          className="input"
          value={setup.flavorNotes}
          onChange={update('flavorNotes')}
          placeholder={t.flavorNotesPlaceholder}
          maxLength={200}
        />
      </label>

      <button type="submit" className="btn btn-primary btn-block" disabled={!canSubmit || loading}>
        {loading ? t.generating : t.generate}
      </button>

      {/* Explain *why* the button is disabled instead of leaving a dead control. */}
      {!canSubmit && !loading && <p className="hint hint-warn">{t.envHint}</p>}
    </form>
  );
}
