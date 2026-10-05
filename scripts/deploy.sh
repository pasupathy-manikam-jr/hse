#!/usr/bin/env bash
#
# Staging deploy (run ON the server): https://ui.staging.oriclabdev.com/hse
#
#   Usage:  cd ~/hse && bash scripts/deploy.sh
#
# Pulls the CI-built `deploy` branch (main + compiled public/build, see
# .github/workflows/deploy-assets.yml), migrates and refreshes caches.
# No npm/node on the server.
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

# Stash hand-edits instead of silently destroying them with the reset below.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    STAMP="pre-deploy-$(date +%Y%m%d-%H%M%S)"
    echo "==> Local changes found; stashing as '$STAMP'"
    git stash push -m "$STAMP"
fi

echo "==> Fetching deploy branch"
git fetch origin deploy

# public/build is untracked until the first switch to deploy, which tracks it.
if [ "$(git branch --show-current)" != "deploy" ]; then
    rm -rf public/build
fi

# deploy is force-pushed by CI each build, so reset rather than pull/merge.
git checkout -B deploy origin/deploy
git reset --hard origin/deploy

echo "==> Installing PHP dependencies"
composer install --no-interaction --no-progress --optimize-autoloader

echo "==> Migrating"
php artisan migrate --force

echo "==> Refreshing caches"
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan event:cache

echo "==> Deployed $(git log --oneline -1)"
