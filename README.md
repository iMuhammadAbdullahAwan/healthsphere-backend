# HealthSphere Backend

This is the API server for HealthSphere, built with CodeIgniter 4.

## Setup
1. `composer install`
2. Configure `.env` database settings.
3. `php spark migrate`
4. `php spark db:seed UserSeeder`
5. `php spark serve`

## Architecture
- **Controllers**: `app/Controllers/Api`
- **Models**: `app/Models`
- **Database**: MySQL/MariaDB.
