#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")"

if ! command -v php >/dev/null 2>&1; then
    echo "[ShopWeb] PHP was not found in PATH."
    echo "Please install PHP 8.3+ and enable required extensions, then run this script again."
    exit 1
fi

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp ".env.example" ".env"
    echo "[ShopWeb] Created .env from .env.example."
fi

mkdir -p \
    storage/app/private/payment-proofs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    public/uploads

chmod -R u+rwX storage bootstrap/cache public/uploads 2>/dev/null || true

echo "[ShopWeb] Starting local server at http://127.0.0.1:8000"
echo "[ShopWeb] First install URL: http://127.0.0.1:8000/install"
echo "[ShopWeb] Press Ctrl+C to stop."

exec php artisan serve --host=127.0.0.1 --port=8000
