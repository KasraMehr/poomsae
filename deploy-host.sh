#!/usr/bin/env bash
set -euo pipefail

# Run from the repository root on a Linux host with PHP, Composer and Node installed.
BRANCH="feature/competition-operations-polish"

if [[ ! -f artisan || ! -d .git ]]; then
  echo "Run this script from the poomsae-system repository root." >&2
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "The working tree has local changes. Save them before deploying." >&2
  exit 1
fi

git fetch origin "$BRANCH"
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
  git switch "$BRANCH"
else
  git switch --track -c "$BRANCH" "origin/$BRANCH"
fi
git pull --ff-only origin "$BRANCH"

if [[ ! -f .env ]] || ! grep -Eq '^APP_KEY=.+$' .env; then
  echo "Configure .env with a persistent APP_KEY and production services before deploying." >&2
  exit 1
fi

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan optimize:clear --no-interaction
php artisan migrate --force --no-interaction
php artisan optimize --no-interaction
php artisan queue:restart --no-interaction

echo "Deployed $BRANCH at $(git rev-parse --short HEAD)."
