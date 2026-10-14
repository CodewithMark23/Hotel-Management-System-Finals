<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DatabaseHealthController extends Controller
{
    public function __invoke()
    {
        $connection = config('database.default');

        if (app()->environment('production') && $connection === 'sqlite') {
            return $this->failure('DATABASE_NOT_CONFIGURED', 'Set DB_CONNECTION=pgsql and DATABASE_URL in Vercel production environment variables.');
        }

        try {
            DB::connection()->getPdo();

            $requiredTables = ['users', 'personal_access_tokens'];
            if (config('cache.default') === 'database') {
                $requiredTables[] = config('cache.stores.database.table', 'cache');
            }
            foreach ($requiredTables as $table) {
                if (! Schema::hasTable($table)) {
                    return $this->failure('MIGRATIONS_REQUIRED', 'Run Laravel migrations against the deployment database and schema.');
                }
            }

            if (! DB::table('users')->exists()) {
                return $this->failure('ADMIN_ACCOUNT_REQUIRED', 'Create an admin account in the deployment database.');
            }
        } catch (Throwable $exception) {
            // Map exceptions to fixed messages; never return connection strings or traces.
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'could not find driver')) {
                return $this->failure('DATABASE_DRIVER_MISSING', 'The backend runtime is missing its database driver.');
            }
            if (str_contains($message, 'password authentication failed')) {
                return $this->failure('DATABASE_AUTHENTICATION_FAILED', 'Correct the database credentials in Vercel production environment variables.');
            }

            return $this->failure('DATABASE_CONNECTION_FAILED', $exception->getMessage());
        }

        if (! config('app.key')) {
            return $this->failure('APP_KEY_MISSING', 'Set a stable Laravel APP_KEY in Vercel production environment variables.');
        }

        return response()->json(['status' => 'ok', 'code' => 'DATABASE_READY']);
    }

    private function failure(string $code, string $message)
    {
        return response()->json(['status' => 'error', 'code' => $code, 'message' => $message], 503);
    }
}
