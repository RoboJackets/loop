# loop
Tracking, auditing, and reconciling SOFO forms

> [!WARNING]
> While this repository itself is open-source, we use several **confidential and proprietary** components which are packed into Docker images produced by this process. Images should **never** be pushed to a public registry.

Install Docker and Docker Compose.

Clone the repository, then run

```sh
export DOCKER_BUILDKIT=1
docker build --pull --target backend-uncompressed --network host --secret id=composer_auth,src=auth.json . --tag robojackets/loop
docker compose up
```

You will need to provide an `auth.json` file that has credentials for downloading Laravel Nova. Ask in Slack and we can provide this file to you.


🚀 Run Project Locally (Laravel + React + Inertia.js)
✅ Requirements

Make sure the following are installed:

PHP 8.1+

Composer

Node.js & NPM

MySQL (or MariaDB)

Git

📌 Installation Steps
# Clone the repository
git clone <repo-url>
cd <project-folder>

# Install Laravel dependencies
composer install

# Install frontend dependencies
npm install

# Setup environment file
cp .env.example .env
php artisan key:generate

# Configure your database in .env file
# Then run migrations + seeders
php artisan migrate --seed

# Start backend server (Laravel)
php artisan serve   # => http://127.0.0.1:8000

# Start frontend (React + Vite)
npm run dev         # Auto reloads in browser

🧹 Optional Commands (Fix Common Issues)
php artisan config:clear
php artisan cache:clear
npm run build

