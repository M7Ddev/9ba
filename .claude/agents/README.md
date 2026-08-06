# BrewMaster AI — multi-agent development system

Four agent definitions that run a feature or bug fix through a **Coding → Review → Testing**
loop with enforced quality gates.

```
                 ┌───────────────┐
   your task ───►│  orchestrator │  owns requirements + acceptance criteria
                 └───────┬───────┘  never writes feature code
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
   coding-agent   code-review-agent  testing-agent
   implements     read-only review   writes + runs tests
          ▲              │              │
          └──── fixes ───┴──────────────┘
                  loop until gates pass
```

Review and Testing run **independently** — neither sees the other's conclusions before
forming its own, so agreement between them is real signal rather than an echo.

## Usage

```
Use the orchestrator agent to add a water-chemistry tool to the recipe agent.
```

The orchestrator defines the spec, delegates, consolidates findings, loops on corrections,
and reports in a fixed 8-section format ending in **APPROVED** / **APPROVED WITH MINOR
ISSUES** / **CHANGES REQUIRED**.

You can also invoke a sub-agent directly for narrow work — e.g. the `code-review-agent` on a
change you made yourself.

## Quality gates

The orchestrator may not report success until all of these hold:

| Gate | Command |
|---|---|
| Backend tests pass | `cd backend && php artisan test` |
| Formatting clean | `cd backend && ./vendor/bin/pint --test app config routes tests` |
| Frontend builds | `cd frontend && npm run build` |
| No unresolved Critical/High review findings | — |
| Acceptance criteria met, Medium/Low documented | — |

Baseline at the time these agents were written: **67 tests / 181 assertions**, Pint clean,
build clean. A drop in test count without explanation is treated as a regression.

## The invariant these agents protect

Every brewing number the user sees — dose, ratio, temperature, grind clicks — must trace
back to PHP, never to the model. The model demonstrably ignores tool results when writing
its final JSON (it received `18.2 g at 1:16.5` and wrote `18.8 g at 1:16`), which is why
`GeminiAgent::reconcile()` overwrites them and the enforcement guards gate tool arguments.

The reviewer treats a regression here as **Critical**, and the tester is instructed to prove
the invariant by having the fake model return numbers that disagree with the tool result.

## Scope note

These are scoped to **BrewMaster AI** and encode its conventions. A separate, differently
configured set exists in `FullSttackAI/.claude/agents/` for that project — they are not
interchangeable, because each encodes project-specific architecture and security rules.

Agent definitions are discovered from the project root, so run Claude Code with
`brewmaster-ai` as the working directory for these to be available.
