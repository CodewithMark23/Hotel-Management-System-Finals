# Vercel deployment

Import the repository with its **Root Directory set to the repository root**, not `frontend` or `backend`. The root `vercel.json` builds two services:

| Service | Directory | Runtime | Public paths |
| --- | --- | --- | --- |
| `backend` | `backend` | FrankenPHP container | `/api` and `/api/*` |
| `frontend` | `frontend` | Vite static build | All remaining paths |

`/api` is retained when a request reaches Laravel. For example, `/api/rooms` matches the existing Laravel API routes. The frontend's hash navigation needs no new server routes.

## Calls and bindings

The browser calls the public same-origin `/api` URL through `src/lib/api.ts`. The static frontend has no server functions and Laravel makes no calls to another application service, so there are **no service bindings**. Do not add a `VITE_BACKEND_URL` binding: bindings are runtime-only and cannot be baked into browser assets. A future server-side proxy would need a caller-side binding and a runtime function that reads its injected URL.

The localhost proxy in `frontend/vite.config.ts` is only for ordinary `npm run dev`; deployed API requests use Vercel routing. Keep `VITE_API_URL=/api`, or remove that variable to use the same default. It is a public browser path, not an internal service URL.

## Database and environment

Supabase PostgreSQL connection strings are supported. Live database credentials have not been supplied yet. Login and CRUD will need those credentials, migrations, and an admin account before the deployed application can be used. The Docker image includes `pdo_pgsql`; local SQLite data is excluded.

Set these values in the Vercel project's environment settings for each deployment environment:

| Variable | Value |
| --- | --- |
| `APP_KEY` | A stable generated Laravel application key |
| `APP_URL` | The deployment's HTTPS URL |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_TIMEZONE` | `Asia/Manila` |
| `DB_CONNECTION` | `mysql` or `pgsql`, matching your provider |
| `DATABASE_URL` | Supabase PostgreSQL connection URL (or use `DB_URL` for MySQL) |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `array` (authentication uses bearer tokens) |
| `QUEUE_CONNECTION` | `sync` |
| `LOG_CHANNEL` | `stderr` |

Alternatively, replace `DB_URL` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Follow your provider's TLS configuration. Never commit credentials. Same-origin browser requests do not need a cross-origin CORS setting; set `FRONTEND_URL` only if you use a separately hosted frontend.

### Supabase connection string

Copy your project's **Session pooler** URI from the Supabase **Connect** panel. Set the following in Vercel, or in the ignored `backend/.env` for local PostgreSQL development:

```ini
DB_CONNECTION=pgsql
DATABASE_URL="postgresql://postgres.PROJECT_REF:ENCODED_PASSWORD@POOLER_HOST:5432/postgres?sslmode=require"
DB_SSLMODE=require
DB_SCHEMA=hotel
DB_EMULATE_PREPARES=false
```

Replace the host, username, and password with your copied values. Percent-encode reserved characters in the password. `DATABASE_URL` takes precedence over `DB_URL` for PostgreSQL. Keep database credentials on the backend; do not use a `VITE_` variable.

Before migrating, create the dedicated application schema in Supabase's SQL Editor:

```sql
CREATE SCHEMA IF NOT EXISTS hotel;
```

`DB_SCHEMA=hotel` keeps Laravel tables in this schema, separate from Supabase's public Data API schema. Laravel Sanctum continues to handle login; no Supabase JavaScript SDK or Supabase Auth migration is needed.

For the **transaction pooler** URI on port **6543**, set `DB_EMULATE_PREPARES=true` to use PDO client-side emulation. The Node/Prisma `pgbouncer=true` flag alone does not configure PHP PDO. Prefer the session pooler for migrations and schema operations.

After changing a local `.env`, run `php artisan config:clear` before migrations. Local PHP also needs the `pdo_pgsql` extension; the Vercel container already installs it. The existing SQLite setup continues to work until you change your environment variables.

See [Supabase's Laravel guide](https://supabase.com/docs/guides/getting-started/quickstarts/laravel) and [pooler connection documentation](https://supabase.com/docs/guides/database/connecting-to-postgres).

Generate `APP_KEY` locally with `php artisan key:generate --show` inside `backend`, then enter it directly into Vercel. Use separate keys/databases for production and previews.

After configuring a terminal or release job to use the **external database**, run inside `backend`:

```powershell
php artisan migrate --force
php artisan tinker
```

In Tinker, create your admin using your own email and password:

```php
App\Models\User::create(['name' => 'Admin', 'email' => 'your-email@example.com', 'password' => 'replace-with-your-own-password']);
```

The User model hashes the password automatically. Production seeding skips the local demo account and sample data, so `admin@hotel.test / password` is for local development. Migrations run separately from container startup.

## Local services test

Install Docker Desktop and the Vercel CLI. Start Docker and supply the Laravel key and database environment variables in the terminal running the services. The container does not include your local `.env`; `vercel dev -L` does not automatically forward `.env.local` values to the container.

From the repository root:

```powershell
vercel dev -L --listen 8080
```

Verify `http://localhost:8080/` renders the login page and `http://localhost:8080/api/health` returns JSON. Then test login and a room operation with your external database account. You can still use `php artisan serve` and `npm.cmd run dev` for the existing SQLite development setup.

The image excludes environment files, local vendor packages, SQLite files, and local caches. Laravel configuration is not cached during the build, so deployment environment variables are read at runtime.

References: [Vercel services](https://vercel.com/docs/services), [service routing](https://vercel.com/docs/services/routing), [runtime bindings](https://vercel.com/docs/services/bindings), [Laravel container setup](https://vercel.com/kb/guide/laravel-php-with-docker).
