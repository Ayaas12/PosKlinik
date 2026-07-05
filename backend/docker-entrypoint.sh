#!/bin/sh

# Exit immediately if a command exits with a non-zero status
set -e

# Setup environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# Run Composer Install if vendor is missing
if [ ! -d vendor ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Wait for database connection
echo "Checking database connection..."
php -r "
\$dbHost = getenv('DB_HOST') ?: 'db';
\$dbPort = getenv('DB_PORT') ?: '5432';
\$dbUser = getenv('DB_USERNAME') ?: 'pos_user';
\$dbPass = getenv('DB_PASSWORD') ?: 'pos_password';
\$dbName = getenv('DB_DATABASE') ?: 'posklinik';

for (\$i = 0; \$i < 30; \$i++) {
    try {
        \$pdo = new PDO(\"pgsql:host=\$dbHost;port=\$dbPort;dbname=\$dbName\", \$dbUser, \$dbPass);
        exit(0);
    } catch (PDOException \$e) {
        echo \"Waiting for database (\$dbHost:\$dbPort)... \n\";
        sleep(2);
    }
}
exit(1);
"

# Generate App Key if not set in .env
if ! grep -q "APP_KEY=base64" .env || [ -z "$(grep APP_KEY= .env | cut -d '=' -f2)" ]; then
    echo "Generating Application Key..."
    php artisan key:generate
fi

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear any cached config/routes
echo "Clearing application cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Seed the database (checking if we have any seeders, we run them force)
echo "Seeding database..."
php artisan db:seed --force || echo "Seeding completed with warnings (data may already exist)."

# Optimize performance for WSL2 filesystem mounts by caching
echo "Caching configuration and routes for faster response times..."
php artisan config:cache
php artisan route:cache

echo "Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port=8000
