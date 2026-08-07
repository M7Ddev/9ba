import { useState } from 'react';

import { verifyAccessCode } from '../lib/api.js';
import { setAccessCode } from '../lib/accessCode.js';

/**
 * AccessGate
 * Shown when the backend reports that an access code is required and the stored
 * one is missing or no longer valid.
 *
 * The code is checked against a cheap endpoint before being stored, so a typo
 * gives immediate feedback instead of failing later on a recipe request.
 */
export default function AccessGate({ t, onUnlocked }) {
  const [code, setCode] = useState('');
  const [checking, setChecking] = useState(false);
  const [rejected, setRejected] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();

    const trimmed = code.trim();
    if (trimmed === '') return;

    setChecking(true);
    setRejected(false);

    const ok = await verifyAccessCode(trimmed);

    if (ok) {
      setAccessCode(trimmed);
      onUnlocked();
    } else {
      setRejected(true);
      setChecking(false);
    }
  }

  return (
    <form className="panel gate" onSubmit={handleSubmit}>
      <h2 className="panel-title">{t.gateTitle}</h2>
      <p className="hint gate-hint">{t.gateHint}</p>

      <label className="field">
        <span className="field-label">{t.gateLabel}</span>
        <input
          type="password"
          className="input"
          value={code}
          onChange={(event) => setCode(event.target.value)}
          autoComplete="off"
          spellCheck="false"
          dir="ltr"
          autoFocus
        />
      </label>

      <button
        type="submit"
        className="btn btn-primary btn-block gate-submit"
        disabled={checking || code.trim() === ''}
      >
        {checking ? t.gateChecking : t.gateSubmit}
      </button>

      {rejected && <p className="error gate-error">{t.gateRejected}</p>}
    </form>
  );
}
