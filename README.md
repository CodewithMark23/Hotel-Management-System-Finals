# Hotel Management System

Local development boilerplate with a **Laravel 12 REST API** and a separate **React + TypeScript + Vite** frontend.

The website deliberately uses minimal CSS: plain navigation buttons, tables, and forms. There is no UI framework or decorative dashboard styling.

## Included

- Admin login/logout using Laravel Sanctum bearer tokens (8-hour expiry).
- Simple navbar with Roomlist, Add Room, Room Details, and Logout.
- Searchable room list, a separate Add Room screen, and room details with edit/delete actions.
- Circular bottom-right button on Roomlist that opens Add Room.
- Room forms connected to the API; guest, reservation, and dashboard endpoints remain available in the backend.
- Server validation, booking overlap checks, maintenance checks, and calculated reservation totals.
- SQLite migrations, local demo seed data, CORS configuration, and API feature tests.

## Start developing

Dependencies and the local database have been prepared in this workspace. Open two terminals at the project root:

**Terminal 1 — API**

```powershell
cd backend
.\serve.ps1
```

**Terminal 2 — frontend**

```powershell
cd frontend
npm.cmd run dev
```

Open **http://localhost:5173**. Demo login: **admin@hotel.test** / **password**.

## Set up a fresh checkout

Requires PHP 8.2+, Composer, and Node 22.12+ (Node 24 is installed here).

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/database.sqlite -Force
php artisan migrate --seed
cd ../frontend
npm.cmd install
Copy-Item .env.example .env
```

SQLite is the default, so you do not need to start XAMPP MySQL. To use MySQL, create a database and set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `backend/.env`, then run `php artisan migrate --seed`. Do not commit `.env` files.

## API

Base URL: `http://127.0.0.1:8000/api`. Send `Accept: application/json` and `Content-Type: application/json`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/health` | Public health check |
| GET | `/health/database` | Public database readiness check with safe error codes |
| POST | `/login` | Email/password login; returns user and token |
| GET | `/me` | Current user |
| POST | `/logout` | Revoke current token |
| GET | `/dashboard` | Hotel statistics |
| GET / POST | `/rooms`, `/guests`, `/reservations` | List / create |
| GET / PUT / DELETE | `/rooms/{id}`, `/guests/{id}`, `/reservations/{id}` | Read / update / delete |

All routes except the health checks and login require `Authorization: Bearer <token>`. Updates use the full form payload. Successful resource responses use `{ "data": ... }`; validation failures return HTTP 422 with `message` and `errors`. Lists are intentionally unpaginated for this starter.

Example reservation payload:

```json
{
  "guest_id": 1,
  "room_id": 2,
  "check_in": "2026-11-01",
  "check_out": "2026-11-03",
  "status": "confirmed",
  "notes": "Late arrival"
}
```

Rooms use `standard`, `deluxe`, or `suite`; room status is `available` (in service) or `maintenance`. Occupancy comes from reservations, not the room status. Reservation statuses: `confirmed`, `checked_in`, `checked_out`, `cancelled`. Checkout is exclusive: another stay can begin on the checkout date. Confirmed and checked-in reservations block overlapping dates. Totals use nightly rate × nights; editing a stay in the same room preserves its booked rate. Rooms and guests with reservation history cannot be deleted.

## Configuration and project structure

```text
backend/
  app/Http/Controllers/Api/  REST controllers
  app/Models/               Hotel models and relationships
  database/migrations/      Database schema
  database/seeders/         Local demo data
  routes/api.php            API endpoints
  tests/Feature/            API behavior tests
frontend/
  src/HotelApp.tsx          Login, navbar, room list, add room, and room details
  src/lib/api.ts            Fetch client and error handling
  src/types.ts              API types
  src/styles.css            Responsive UI styles
```

Vite proxies `/api` to Laravel during development. Set `VITE_API_URL` in the frontend for a separately hosted API; set `FRONTEND_URL` in the backend to its allowed frontend origin (comma separated for multiple origins). Currency defaults to PHP and is adjustable via `VITE_CURRENCY`. Tokens live in browser session storage. All authenticated users currently have admin access.

## Verification

For the Vercel multi-service setup, see [Vercel deployment](docs/vercel-deployment.md). The repository root contains `vercel.json`; Laravel runs as a container and React builds with Vite. An external database is still required for deployment.

```powershell
cd backend
php artisan test
cd ../frontend
npm.cmd run build
```

This is a development starter. Payments, invoices, staff roles, pagination, audit logs, and a reservation status workflow can be added as your requirements grow. Before production, create your own admin account, configure HTTPS and deployment origins, set `APP_ENV=production` and `APP_DEBUG=false`, and choose your authentication/session policy. Demo seeding only runs in local/testing environments. SQLite is suitable for local development; use a database with row locking such as MySQL for concurrent booking writes.
