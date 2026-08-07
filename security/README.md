# BrewMaster AI — security test corpus

A golden dataset of security cases plus the checks that execute them.

| File | What it is |
|---|---|
| `golden-dataset.json` | 45 cases: payload, attack goal, expected behaviour, assertion, severity, status |
| `run-checks.sh` | The checks that cannot be PHPUnit tests (bundle, logs, dependencies, source greps) |
| `../backend/tests/Feature/SecurityGoldenDatasetTest.php` | The automated cases |

## Running it

```bash
cd backend && php artisan test --filter=SecurityGoldenDatasetTest
```

```bash
bash security/run-checks.sh
```

`run-checks.sh` exits non-zero if any check fails, so it drops straight into CI.

## Why the dataset and the tests cannot drift apart

`test_every_automated_case_has_an_existing_test` reads `golden-dataset.json`, collects every
case with `"automated": true`, and fails if its `test_ref` names a method that does not exist
in the suite. A case cannot claim automated coverage it does not have.

## Status vocabulary

Statuses record **what was actually executed**, not what is believed to be true.

- `pass` — verified by an executed test or executed manual check
- `fail` — an executed check found a real problem (open finding)
- `mitigated` — design prevents it, verified by inspection rather than execution
- `accepted_risk` — known, deliberately accepted for a prototype, documented
- `untested` — not verified; treat as unknown, **not** as safe

## Coverage

All ten OWASP Web Top 10 categories, plus the OWASP LLM Top 10 categories that apply to this
architecture (LLM01, LLM02, LLM03, LLM04, LLM06, LLM08, LLM09).

The threat model is unusual in one respect: the most valuable asset is not user data but the
**integrity of the numbers**. A recipe whose dose came from the model rather than from PHP is
treated as a Critical failure, because the entire product claim rests on it. `PI-014` covers
the observed case where the model was handed `18.2 g at 1:16.5` and wrote `18.8 g at 1:16`
anyway.

## Standing caveats

- **Access control is a shared code, not authentication** (`AUTH-001`). `APP_ACCESS_CODE`
  gates every endpoint that costs a Gemini request; leaving it empty leaves the API open,
  which is the local-development default. The code identifies nobody, so it protects the
  owner's quota rather than any user's data.
- **`client_id` is a grouping key, not a credential** (`AC-002`, `AC-003`). Knowing one grants
  read access to that brew log and the ability to poison its history.
- **`APP_DEBUG=true`** (`CFG-001`). Correct locally, must be false before any deploy.
- These checks do not test the live model. Prompt-injection cases assert that the application
  *fences and constrains* input and *overrides* output — not that Gemini itself resists
  persuasion. That distinction is deliberate: the defence is structural, not behavioural.
