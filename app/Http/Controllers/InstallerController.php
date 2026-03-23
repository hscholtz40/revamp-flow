<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallerController extends Controller
{
    public function show()
    {
        return view('install');
    }

    public function install(Request $request)
    {
        $data = $request->validate([
            'app_url' => ['required', 'url'],
            'db_host' => ['required', 'string'],
            'db_port' => ['required', 'numeric'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        // Write .env
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $env = File::get($envPath);
        $env = $this->setEnv($env, 'APP_ENV', 'production');
        $env = $this->setEnv($env, 'APP_DEBUG', 'false');
        $env = $this->setEnv($env, 'APP_URL', $data['app_url']);
        $env = $this->setEnv($env, 'DB_CONNECTION', 'mysql');
        $env = $this->setEnv($env, 'DB_HOST', $data['db_host']);
        $env = $this->setEnv($env, 'DB_PORT', (string) $data['db_port']);
        $env = $this->setEnv($env, 'DB_DATABASE', $data['db_database']);
        $env = $this->setEnv($env, 'DB_USERNAME', $data['db_username']);
        $env = $this->setEnv($env, 'DB_PASSWORD', $data['db_password'] ?? '');
        // Set cache and session to non-database drivers to avoid connection during installation
        $env = $this->setEnv($env, 'CACHE_STORE', 'array');
        $env = $this->setEnv($env, 'SESSION_DRIVER', 'file');
        $env = $this->stripEnvKeys($env, ['ADMIN_EMAIL', 'ADMIN_PASSWORD']);
        if (! Str::contains($env, 'APP_KEY=')) {
            $env .= "\nAPP_KEY=\n";
        }
        File::put($envPath, $env);

        // CRITICAL: Set cache and session config FIRST to prevent DB connection attempts
        Config::set('cache.default', 'array');
        Config::set('session.driver', 'file');

        // CRITICAL: Set database config BEFORE any operations
        // This must happen before any Laravel operations that might connect to DB
        $dbPassword = $data['db_password'] ?? '';

        // Update Config directly - this overrides any cached or .env values
        Config::set('database.connections.mysql.host', $data['db_host']);
        Config::set('database.connections.mysql.port', $data['db_port']);
        Config::set('database.connections.mysql.database', $data['db_database']);
        Config::set('database.connections.mysql.username', $data['db_username']);
        Config::set('database.connections.mysql.password', $dbPassword);
        Config::set('database.default', 'mysql');

        // Also set environment variables for env() helper
        putenv('DB_CONNECTION=mysql');
        putenv('DB_HOST='.$data['db_host']);
        putenv('DB_PORT='.$data['db_port']);
        putenv('DB_DATABASE='.$data['db_database']);
        putenv('DB_USERNAME='.$data['db_username']);
        putenv('DB_PASSWORD='.$dbPassword);

        // Set $_ENV superglobal as well
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_HOST'] = $data['db_host'];
        $_ENV['DB_PORT'] = $data['db_port'];
        $_ENV['DB_DATABASE'] = $data['db_database'];
        $_ENV['DB_USERNAME'] = $data['db_username'];
        $_ENV['DB_PASSWORD'] = $dbPassword;

        // Purge any existing connection to force reconnection with new credentials
        try {
            DB::purge('mysql');
        } catch (\Exception $e) {
            // Ignore if connection doesn't exist yet
        }

        // DO NOT call config:clear - it may try to connect to DB with old credentials
        // Config::set() above will override any cached values

        // Generate app key if missing - this shouldn't need DB connection
        if (empty(config('app.key')) && empty(env('APP_KEY'))) {
            try {
                Artisan::call('key:generate', ['--force' => true]);
            } catch (\Exception $e) {
                // If key generation fails, try to continue
                // The key might already be set in .env
            }
        }

        Config::set('installer.bootstrap_admin_email', $data['admin_email']);
        Config::set('installer.bootstrap_admin_password', $data['admin_password']);

        // Now run migrations and seeders with the updated config
        // These will use the Config::set() values we set above
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        Config::set('installer.bootstrap_admin_email', null);
        Config::set('installer.bootstrap_admin_password', null);

        // Remove any legacy bootstrap lines copied from templates (never persist admin secrets)
        $env = File::get($envPath);
        $env = $this->stripEnvKeys($env, ['ADMIN_EMAIL', 'ADMIN_PASSWORD']);
        File::put($envPath, $env);

        // Create installed flag
        File::put(base_path('.installed'), now()->toDateTimeString());

        return redirect()->route('login')->with('status', 'Application installed successfully. You can log in with the admin credentials.');
    }

    private function setEnv(string $env, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $line = $key.'='.(str_contains($value, ' ') ? '"'.$value.'"' : $value);

        return preg_match($pattern, $env) ? preg_replace($pattern, $line, $env) : $env."\n".$line;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function stripEnvKeys(string $env, array $keys): string
    {
        foreach ($keys as $key) {
            $env = preg_replace('/^'.preg_quote($key, '/').'=.*\R?/m', '', $env) ?? $env;
        }

        return rtrim($env)."\n";
    }
}
