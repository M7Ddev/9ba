# صَبّة ☕

An AI coffee assistant that generates precise, SCA-standard brewing recipes.
You describe your beans and setup — origin, processing method, roast, brew method,
grinder — and a Gemini-powered agent returns an exact recipe. Taste it, tell the app it
came out sour or bitter, and the agent diagnoses the cause and adjusts.

Arabic-first (RTL) with an English toggle, and light/dark themes.

```
Browser (React)  ──►  Laravel API  ──►  Gemini
                      └── holds the API key; the browser never sees it
```

**The idea:** an LLM should not be trusted with numbers. Every figure the app shows —
dose, ratio, temperature, grind clicks — is computed by PHP and then *forced* onto the
model's output. The model chooses the approach and writes the prose; it never originates
a number. See [How the AI agent works](#how-the-ai-agent-works).

> Developed by [M7dev](https://github.com/M7Ddev).

---

## Setup

**Requirements:** PHP 8.4+, Composer, Node 20+.

> PHP 8.4.1 is a hard floor: Laravel 13 depends on Symfony v8, which requires it.
> Vite 8 requires Node 20.19+.

### 1. Get a Gemini API key

1. Go to <https://aistudio.google.com/app/apikey>
2. Sign in and click **Create API key**
3. Copy the value

### 2. Backend

```bash
cd backend
composer install
```

Open `backend/.env` and set your key:

```
GEMINI_API_KEY=your_key_here
GEMINI_MODEL=gemini-flash-lite-latest
```

Then:

```bash
php artisan serve
```

The API runs on <http://localhost:8000>.

> **`.env` is read at boot.** After editing it, restart `php artisan serve`.
> If you have ever run `php artisan config:cache`, run `php artisan config:clear` too.

### 3. Frontend

In a second terminal:

```bash
cd frontend
npm install
npm run dev
```

The app runs on <http://localhost:5173>. It checks `/api/health` on load, so if the
backend is down or has no key you get a clear banner instead of a mysterious failure.

---

## Where the key lives, and why

The key sits in `backend/.env` and is used only inside PHP. It is sent to Google over
HTTPS in a request header and **never reaches the browser** — you can confirm this by
opening DevTools → Network and inspecting any request the page makes.

This is the reason the project has a backend at all. An earlier version called Gemini
directly from React with the key in a `VITE_*` variable, which does not work as a secret:
Vite inlines those values into the JavaScript bundle at build time, so anyone could read
the key out of the deployed site. Moving the call server-side is the actual fix.

Also in place:

- `backend/.env` is gitignored; `backend/.env.example` is the committed template.
- CORS (`backend/config/cors.php`) allows only the frontend origin, not `*`.
- API routes are rate-limited to 30 requests/minute, since each call costs a Gemini request.
- Inputs are validated against an allowlist (`backend/config/coffee.php`) before any
  prompt is built, so arbitrary user text is never interpolated into the model prompt.
- `/api/health` reports *whether* a key is configured — never the key or any part of it.

---

## How the AI agent works

This is the interesting part, and the reason the numbers can be trusted.

The model is given **four tools** and is not allowed to do any of these jobs itself:

```php
get_brew_history(client_id)                  -> this user's past results, summarised
get_bean_profile(origin, process, roast)     -> recommended temp, ratio, grind, behaviour
get_grind_setting(grinder, method, adjust)   -> "22-28 clicks"
calculate_brew_ratio(method, water_ml, ratio) -> ['coffee_grams' => 18.2, ...]
```

Both are plain PHP and run on the server. The round trip for one recipe:

| # | Who | What happens |
|---|-----|--------------|
| 1 | Laravel | Sends the setup + a system instruction ("act as an SCA barista, you MUST call the tools") + both tool declarations |
| 2 | Gemini | Calls `get_bean_profile(origin: "Yemen", process: "Natural", roast: "Medium")` |
| 3 | Laravel | Looks it up → `['recommended_temp_c' => 91, 'recommended_ratio' => '1:15.5', …]` |
| 4 | Gemini | Calls `calculate_brew_ratio(method: "V60", water_ml: 300, ratio: "1:15.5")` |
| 5 | Laravel | Computes it → `['coffee_grams' => 19.4]` |
| 6 | Gemini | Produces the final JSON recipe using **our** numbers |

Each tool result is appended to the conversation as a `functionResponse` part. The loop is
bounded by `GEMINI_MAX_TOOL_ROUNDS` (default 4) in
`backend/app/Services/Gemini/GeminiAgent.php`, so a confused model can never spin forever.

### `calculate_brew_ratio` — the arithmetic

Computes the dose, and validates the ratio the model asked for against per-method bounds
(espresso 1:1.5–1:3, V60 1:12–1:18, …). If the model sends something absurd, the calculator
substitutes a safe default **and reports the substitution back to the model**, which then
mentions it in the recipe notes.

### `get_bean_profile` — the coffee knowledge

This is what turns "I have Yemeni naturals" into actual numbers. It looks the bean up in
`backend/config/coffee.php` and returns density, expected acidity and body, typical flavour
notes, and — crucially — a **recommended temperature and ratio**:

```
origin base  (Yemen: 92 °C, 1:15)
  + process adjustment  (Natural: −1 °C, +0.5 ratio, "one step coarser")
  + roast adjustment    (Light +1, Medium 0, Dark −2)
  = clamped to the SCA range of 90–96 °C
```

So Yemeni natural comes out at 91 °C / 1:15.5, while Ethiopian washed light lands on
95 °C / 1:16. Both verified against the live model.

The point is the same as with the ratio tool: the model is told **not** to rely on its own
knowledge of origins. Everything it claims about a bean traces back to one auditable table,
which is also the only place to edit if you disagree with the coffee science.

### `get_grind_setting` — actionable numbers

"Medium-fine, like table salt" is not something a beginner can act on. This looks the
user's grinder up in `config/coffee.php` and returns a real click window — `20-26 clicks`
on a Comandante C40 for V60 — shifted by whatever the bean profile asked for. Click counts
are exactly the kind of specific fact a language model states confidently and wrongly, so
it is forbidden from inventing them. Unknown grinder? The tool says so, and the recipe
falls back to describing the texture.

### `get_brew_history` — the agent learns

Every recipe is written to a `brews` table with the rating the user later gives it. Before
designing anything, the agent asks what this person's cups have been like:

```
"This user repeatedly reports SOUR cups (under-extraction).
 Start finer and/or hotter than the profile default."
```

Verified end to end: for a client with three sour ratings, the model raised the water to
96 °C and wrote *"Adjusted temperature up to 96°C and ground slightly finer to counteract
your tendency toward under-extracted (sour) cups."*

The aggregation is deterministic PHP and deliberately cautious — it refuses to claim a
tendency below three rated brews, or when the sour/bitter split is close. Two data points
are not a palate. Brews are grouped by a random `client_id` the browser stores; it is not
a credential and identifies nobody.

## Making the model obey its own tools

This is the part worth being honest about in a presentation, because it is the thing that
surprised us.

The model reliably **calls** the tools. It does not reliably **respect** them. Observed in
real runs against `gemini-flash-lite-latest`:

- It looked up a natural-process profile that said *"one step coarser"*, then requested a
  **finer** grind.
- It received the recommended ratio `1:16.5` and asked for `1:15` instead.
- It received `18.2 g at 1:16.5` from `calculate_brew_ratio` and then wrote
  **`18.8 g at 1:16`** into its final JSON anyway.

Prompt instructions did not fix this. So three guards run in `GeminiAgent`:

| Guard | What it does |
|---|---|
| `enforceGrindAdjustment()` | The grind step is dictated by the processing method, not chosen by the model |
| `enforceRatio()` | The ratio must stay within ±0.5 of the profile's recommendation, plus the shift the taste preference justifies (`Strong` −1, `Light` +1) |
| `reconcile()` | The finished recipe's `coffee_grams`, `ratio`, `water_ml` and `grind_clicks` are overwritten with what the tools actually returned |

Every correction is logged, and there are regression tests for all three. Without
`reconcile()` in particular, the claim "these numbers came from PHP" would be an
intention rather than a fact.

Enforcement is skipped during `adjust()` — there the whole point is that the model changes
the grind and ratio to fix a bad cup, so holding it to the defaults would defeat the fix.

## Reading a coffee bag

`POST /api/beans/scan` takes a photo and returns the setup fields to prefill, using
Gemini's vision capability — no extra key, no extra service.

The image is never written to disk: it is read from the temp upload, base64-encoded, sent,
and discarded. Two safeguards matter:

- **The answer is coerced onto our vocabulary.** A bag from Papua New Guinea becomes
  `Other` rather than an origin the rest of the app has no data for; `bean_name` and
  `flavor_notes` are truncated to the lengths the recipe endpoints accept. Whatever comes
  back is always safe to drop straight into the form and submit.
- **Label text is not an instruction.** The system prompt says so explicitly: it is a
  photograph, not a request.

## Brew timer

The recipe steps already arrive with timestamps (`0:30 - Pour 60 ml...`), so the frontend
parses them and runs a live countdown that highlights the current step. No AI, no network,
no API cost — it just makes the app usable *while* brewing rather than only before.

Users may also type the flavour notes printed on their bag. That free text is length-capped,
whitespace-collapsed, and fenced in the prompt as a *description, not an instruction* — it
never becomes something the agent acts on. There is a test for that.

### The recipe contract

Gemini must reply with one JSON object:

```json
{
  "coffee_grams": 18.8,
  "water_ml": 300,
  "ratio": "1:16",
  "water_temp_c": 93,
  "grind_size": "medium-fine, like table salt",
  "total_time": "3:00",
  "steps": ["Rinse the filter…", "Bloom with 60 ml…"],
  "notes": "…",
  "bean_insight": "…",    // how the origin + process shaped this recipe
  "change_summary": "…"   // only after a feedback adjustment
}
```

The parser tolerates markdown fences and stray prose around the object, and validates
that every required field is present before anything reaches the UI. A malformed reply
becomes a `BAD_JSON` error rather than a half-rendered recipe.

### The feedback loop

"Too sour" / "too bitter" send the current recipe plus the symptom back to the agent,
labelled as under- and over-extraction respectively. The model diagnoses the cause,
changes as few variables as it sensibly can (grind, temperature, time, ratio), calls the
tool again for the new dose, and returns the same JSON shape plus `change_summary`.

---

## API

| Method | Path | Body | Returns |
|--------|------|------|---------|
| `GET` | `/api/health` | — | `{ ok, key_configured, model }` |
| `POST` | `/api/recipes/generate` | `{ method, roast, amount_ml, taste, origin, process, flavor_notes?, language }` | `{ recipe }` |
| `POST` | `/api/recipes/adjust` | above + `{ feedback: "sour"\|"bitter", recipe }` | `{ recipe }` |
| `POST` | `/api/recipes/translate` | `{ language, recipe }` | `{ recipe }` |
| `POST` | `/api/beans/scan` | `photo` (multipart image, ≤4 MB) | `{ beans }` |
| `GET` | `/api/brews` | `?client_id=` | `{ brews }` |
| `POST` | `/api/brews/{id}/feedback` | `{ feedback, client_id }` | `{ ok }` |

`generate` and `adjust` also return `brew_id`, which the browser uses to attach a rating.
A brew can only be rated by the client that created it.

`translate` rewrites the prose of an existing recipe in the other language while keeping
every brewing number identical — used when the user switches language with a recipe on
screen, since the recipe body is model output and cannot be retranslated client-side. It
registers no tools, and the numeric fields are restored from the original afterwards, so
the model cannot alter them even if it tries.

Every failure uses one shape, so the frontend has a single thing to parse:

```json
{ "error": "MODEL_NOT_FOUND", "message": "Gemini returned HTTP 404" }
```

`error` is a stable code; the user-facing sentence is chosen by the frontend from its own
translations, which is why the API returns no localised text.

| Code | Cause |
|------|-------|
| `MISSING_KEY` | `GEMINI_API_KEY` empty in `backend/.env` |
| `INVALID_KEY` | Bad or unauthorised key (400 with "API key not valid", 401, 403) |
| `RATE_LIMIT` | Quota or rate limit hit (429) |
| `MODEL_NOT_FOUND` | Key has no access to the configured model (404) |
| `VALIDATION` | Input failed the allowlist (422) |
| `NETWORK` / `TIMEOUT` | Server could not reach Gemini |
| `SERVER` | Gemini returned 5xx |
| `BAD_JSON` / `EMPTY_RESPONSE` | Model output unparseable, or blocked with no content |
| `UNKNOWN` | Anything else — details go to the Laravel log, not the browser |

Classification is driven by the **HTTP status**, not by searching the message text. Loose
substring matching is unreliable here: an earlier version searched for `rate`, which
matches the `rate` inside `gene`**`rate`**`Content` and mislabelled every 404 as a rate
limit. There is a regression test for exactly that
(`test_a_404_is_reported_as_model_not_found_not_rate_limit`).

---

## Project structure

```
brewmaster-ai/
├── backend/                    # Laravel 13 API — holds the key, runs the agent
│   ├── app/
│   │   ├── Exceptions/AgentException.php        # failure + stable error code
│   │   ├── Http/
│   │   │   ├── Controllers/RecipeController.php # generate + adjust
│   │   │   ├── Controllers/HealthController.php # setup check
│   │   │   └── Requests/                        # allowlist validation
│   │   ├── Models/Brew.php                      # the brew log
│   │   └── Services/
│   │       ├── Coffee/
│   │       │   ├── BrewRatioCalculator.php      # tool 1: the dose arithmetic
│   │       │   ├── BeanProfiler.php             # tool 2: origin/process knowledge
│   │       │   ├── GrinderCalibrator.php        # tool 3: click settings
│   │       │   └── BrewHistory.php              # tool 4: what this user likes
│   │       └── Gemini/
│   │           ├── GeminiClient.php             # HTTP transport + error mapping
│   │           └── GeminiAgent.php              # prompts, tool loop, enforcement
│   ├── config/gemini.php                        # key, model, timeouts
│   ├── config/coffee.php                        # methods, origins, processes, grinders
│   ├── config/cors.php
│   ├── routes/api.php
│   └── tests/                                   # 67 tests, Gemini faked
│
└── frontend/                   # React + Vite — no key, no Gemini SDK
    ├── src/
    │   ├── App.jsx                              # all state (useState only)
    │   ├── i18n/translations.js                 # every UI string, ar + en
    │   ├── lib/api.js                           # calls the Laravel API
    │   ├── lib/config.js                        # just the API base URL
    │   ├── lib/clientId.js                      # anonymous brew-log grouping key
    │   └── components/
    │       ├── RecipeForm.jsx
    │       ├── BagScanner.jsx                   # photograph the label
    │       ├── RecipeCard.jsx
    │       ├── BrewTimer.jsx                    # live countdown, no API calls
    │       ├── BrewLog.jsx                      # what the agent remembers
    │       └── FeedbackBar.jsx
    └── src/styles.css                           # brown/white, logical props for RTL
```

## Tests

```bash
cd backend
php artisan test
```

67 tests. Gemini is faked with `Http::fake()`, so they run offline and cost nothing. They
cover the dose maths (including clamping absurd ratios), the bean profiler (process and
roast adjustments, SCA temperature clamping, unknown-origin fallback), grinder click
lookups and their clamping, brew-history aggregation (including its refusal to claim a
tendency from thin or evenly-split data), the multi-tool calling loop, the three
enforcement guards above, bag-scan coercion and upload rejection, brew-log ownership,
markdown-fence stripping, contract validation, translation number-preservation, every
error-code mapping, input validation, and prompt-injection resistance on the free-text
flavour-notes field.

To demo personalisation without brewing four coffees:

```bash
php artisan db:seed --class=DemoBrewSeeder
```

That seeds a client (`learner-1`) with a sour tendency.

## Notes / limitations

- No auth and no database — this is a capstone prototype. Rate limiting is by IP.
- The frontend calls Gemini through Laravel, so deploying means running both. A single-origin
  deploy (serving the built frontend from Laravel's `public/`) would remove the CORS config.
