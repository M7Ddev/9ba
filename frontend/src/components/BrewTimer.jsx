import { useEffect, useState } from 'react';

/**
 * BrewTimer
 * A live countdown that walks through the recipe while you brew.
 *
 * No AI and no network: the recipe steps already start with timestamps like
 * "0:30 - Pour 60 ml...", so we parse those and highlight whichever step is
 * current. Steps without a timestamp are simply never auto-highlighted.
 */

/** Parse a leading "M:SS" (or "MM:SS") into seconds. Returns null if absent. */
function parseStepTime(step) {
  const match = /^\s*(\d{1,2}):(\d{2})/.exec(step);
  if (!match) return null;

  return Number(match[1]) * 60 + Number(match[2]);
}

/** Format seconds as "M:SS". */
function formatTime(totalSeconds) {
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;

  return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

export default function BrewTimer({ t, steps }) {
  const [running, setRunning] = useState(false);
  const [elapsed, setElapsed] = useState(0);

  // Tick once a second while running.
  useEffect(() => {
    if (!running) return undefined;

    const id = setInterval(() => setElapsed((value) => value + 1), 1000);
    return () => clearInterval(id);
  }, [running]);

  const stepTimes = steps.map(parseStepTime);

  // The current step is the last one whose timestamp has passed.
  let activeIndex = -1;
  stepTimes.forEach((time, index) => {
    if (time !== null && time <= elapsed) activeIndex = index;
  });

  // The next upcoming timestamp, for the countdown readout.
  const nextTime = stepTimes.find((time) => time !== null && time > elapsed) ?? null;

  function reset() {
    setRunning(false);
    setElapsed(0);
  }

  return (
    <section className="panel timer">
      <h2 className="panel-title">{t.timerTitle}</h2>

      <div className="timer-head">
        <span className="timer-clock" dir="ltr">
          {formatTime(elapsed)}
        </span>

        <div className="timer-actions">
          <button type="button" className="btn btn-primary btn-small" onClick={() => setRunning((r) => !r)}>
            {running ? t.pause : elapsed > 0 ? t.resume : t.start}
          </button>
          <button type="button" className="btn btn-ghost btn-small" onClick={reset} disabled={elapsed === 0}>
            {t.reset}
          </button>
        </div>
      </div>

      {nextTime !== null && (
        <p className="hint" dir="auto">
          {t.nextStepIn} {formatTime(nextTime - elapsed)}
        </p>
      )}

      <ol className="steps timer-steps">
        {steps.map((step, index) => (
          <li key={index} className={index === activeIndex ? 'step-active' : undefined}>
            {step}
          </li>
        ))}
      </ol>
    </section>
  );
}
