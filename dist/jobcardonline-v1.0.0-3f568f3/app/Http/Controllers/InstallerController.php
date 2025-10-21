<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
        if (!file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $env = File::get($envPath);
        $env = $this->setEnv($env, 'APP_ENV', 'production');
        $env = $this->setEnv($env, 'APP_DEBUG', 'false');
        $env = $this->setEnv($env, 'APP_URL', $data['app_url']);
        $env = $this->setEnv($env, 'DB_HOST', $data['db_host']);
        $env = $this->setEnv($env, 'DB_PORT', (string)$data['db_port']);
        $env = $this->setEnv($env, 'DB_DATABASE', $data['db_database']);
        $env = $this->setEnv($env, 'DB_USERNAME', $data['db_username']);
        $env = $this->setEnv($env, 'DB_PASSWORD', $data['db_password'] ?? '');
        $env = $this->setEnv($env, 'ADMIN_EMAIL', $data['admin_email']);
        $env = $this->setEnv($env, 'ADMIN_PASSWORD', $data['admin_password']);
        if (!Str::contains($env, 'APP_KEY=')) {
            $env .= "\nAPP_KEY=\n";
        }
        File::put($envPath, $env);

        // Generate app key if missing
        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        // Run migrations and seeders
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

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
}


