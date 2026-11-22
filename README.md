# VoltFlow ERP

VoltFlow ERP is a project for developing an ERP system and operational workflows. The repository's **`main` branch is currently a Laravel foundation/scaffold**, not the completed ERP implementation.

> **Development status:** The larger integrated ERP implementation is being proposed in [Draft PR #3](https://github.com/khalidropid/VoltFlow-ERP/pull/3). It is not merged into `main`. Review that branch and its tests before treating its features as available.

## Current `main` stack

- PHP 8.2+
- Laravel 12
- Filament
- Laravel Sanctum
- Spatie Laravel Permission
- Vite, Tailwind CSS, and npm
- PHPUnit

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite (the example environment defaults to SQLite; configure another database in `.env` if needed)

## Setup

Clone the repository and run:

```bash
composer setup
```

The setup script installs Composer and npm dependencies, creates `.env` if missing, generates the application key, runs migrations, and builds frontend assets.

If you prefer to run the steps individually:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Run locally

Start the development services:

```bash
composer dev
```

Or start the Laravel server separately:

```bash
php artisan serve
```

## Tests

```bash
composer test
```

## Development branches

- [Draft PR #3 — Complete integrated ERP build](https://github.com/khalidropid/VoltFlow-ERP/pull/3): large proposed integration; currently open as a draft.
- [PR #2 — Phase 6H integration and audit governance](https://github.com/khalidropid/VoltFlow-ERP/pull/2): open and targets a phase branch rather than `main`.

Please check the pull requests and their test status before using features from those branches.

## Configuration and security

- Keep local environment settings in `.env`; never commit real credentials.
- Use test/demo data only in local development.
- Do not commit database files, backups, logs, or generated assets containing private information.

## License

A project-specific license has not been documented in this README yet. Confirm the license before redistributing the project.
