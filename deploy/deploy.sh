#!/usr/bin/env bash
# ব্যবহার: ssh করে সার্ভারে ঢুকে  bash deploy/deploy.sh
set -euo pipefail

APP_DIR=/var/www/genz-agro
cd "$APP_DIR"

echo "== ডিপ্লয়ের আগে ডাটাবেসের ব্যাকআপ =="
php artisan backup:run --only-db

php artisan down --retry=60

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction

# সার্ভারে npm না চালাতে চাইলে নিজের কম্পিউটারে `npm run build` করে public/build আপলোড করো
npm ci
npm run build

# migration ব্যর্থ হলে স্ক্রিপ্ট এখানেই থামে ও অ্যাপ বন্ধ থাকে (আধা-বদলানো অবস্থায় চালু হয় না)
php artisan migrate --force

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart || true

php artisan up

echo "== যাচাই =="
php artisan security:check || echo "!! security:check-এ সমস্যা আছে, ওপরের ✗ দেখুন"
php artisan ops:check || echo "!! ops:check-এ সমস্যা আছে, ওপরের ✗ দেখুন"