#!/usr/bin/env bash
# BrewMaster AI — manual security checks.
#
# Covers the golden-dataset cases that cannot be expressed as PHPUnit tests
# (bundle contents, log contents, dependency advisories, source-level greps).
# The automated cases live in backend/tests/Feature/SecurityGoldenDatasetTest.php.
#
# Usage:  bash security/run-checks.sh
# Exits non-zero if any check fails.

set -uo pipefail
cd "$(dirname "$0")/.."

KEY_PATTERN='AIza[0-9A-Za-z_-]{20,}'
fails=0

pass() { echo "  PASS  $1"; }
fail() { echo "  FAIL  $1"; fails=$((fails + 1)); }

echo "== SD-002: API key must not be in the frontend bundle =="
if [ -d frontend/dist ]; then
  if grep -rqE "$KEY_PATTERN" frontend/dist/ 2>/dev/null; then
    fail "key-shaped material found in frontend/dist"
  else
    pass "no key material in frontend/dist"
  fi
else
  echo "  SKIP  frontend/dist not built (run: cd frontend && npm run build)"
fi

echo "== SD-003: API key must not be in the logs =="
if compgen -G "backend/storage/logs/*.log" > /dev/null; then
  if grep -rqE "$KEY_PATTERN" backend/storage/logs/ 2>/dev/null; then
    fail "key-shaped material found in logs"
  else
    pass "no key material in logs"
  fi
else
  echo "  SKIP  no log files yet"
fi

echo "== SD-006 / CFG-001: secrets and debug configuration =="
grep -qE '^\.env$' .gitignore && pass ".env is gitignored" || fail ".env is NOT gitignored"
if grep -qE '^APP_DEBUG=false' backend/.env; then
  pass "APP_DEBUG=false"
else
  if grep -qE '^APP_ENV=local' backend/.env; then
    echo "  WARN  APP_DEBUG=true (acceptable locally, MUST be false before deploying)"
  else
    fail "APP_DEBUG is true outside local"
  fi
fi

echo "== INJ-004: no raw HTML injection sink in the frontend =="
if grep -rq "dangerouslySetInnerHTML" frontend/src/ 2>/dev/null; then
  fail "dangerouslySetInnerHTML present — audit it for model-generated text"
else
  pass "no dangerouslySetInnerHTML"
fi

echo "== Source hygiene: no hardcoded secrets, no debug leftovers =="
if grep -rqE "$KEY_PATTERN" backend/app backend/config frontend/src 2>/dev/null; then
  fail "hardcoded key-shaped literal in source"
else
  pass "no hardcoded key material in source"
fi
if grep -rqE '\b(dd|var_dump|print_r)\s*\(' backend/app 2>/dev/null; then
  fail "debug statement left in backend/app"
else
  pass "no debug statements in backend/app"
fi
if grep -rqn "env(" backend/app 2>/dev/null; then
  fail "env() called outside config/ — breaks config:cache and risks leaks"
else
  pass "env() only used in config/"
fi

echo "== DEP-001: dependency advisories =="
(cd backend && composer audit --no-interaction > /dev/null 2>&1) \
  && pass "composer: no advisories" \
  || fail "composer audit reported advisories (run: cd backend && composer audit)"
(cd frontend && npm audit --audit-level=high > /dev/null 2>&1) \
  && pass "npm: no high/critical advisories" \
  || fail "npm audit reported high/critical advisories (run: cd frontend && npm audit)"

echo
if [ "$fails" -eq 0 ]; then
  echo "All manual checks passed."
else
  echo "$fails check(s) failed."
fi
exit "$fails"
