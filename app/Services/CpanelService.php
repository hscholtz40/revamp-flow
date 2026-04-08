<?php

namespace App\Services;

use App\Support\SafeLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CpanelService
{
    private string $host;

    private int $port;

    private string $username;

    private string $apiToken;

    private string $domain;

    private string $homeDir;

    public function __construct()
    {
        $config = config('services.cpanel');

        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->username = $config['username'];
        $this->apiToken = $config['api_token'];
        $this->domain = $config['domain'];
        $this->homeDir = rtrim($config['home_dir'], '/');
    }

    /**
     * Check if the cPanel service is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->host)
            && ! empty($this->username)
            && ! empty($this->apiToken)
            && ! empty($this->domain);
    }

    /**
     * Request AutoSSL and enable force HTTPS redirect for a domain.
     *
     * @param  string  $domain  The full domain (e.g. "client1.domain.com")
     * @return array{success: bool, message: string}
     */
    public function forceSSL(string $domain): array
    {
        $messages = [];

        try {
            $autoSSLResult = $this->requestAutoSSL($domain);
            if ($autoSSLResult['success']) {
                $messages[] = 'AutoSSL certificate requested';
            } else {
                $messages[] = 'AutoSSL request failed: '.($autoSSLResult['error'] ?? 'Unknown error');
            }

            $redirectResult = $this->enableForceHttpsRedirect($domain);
            if ($redirectResult['success']) {
                $messages[] = 'HTTPS redirect enabled';
            } else {
                $messages[] = 'HTTPS redirect failed: '.($redirectResult['error'] ?? 'Unknown error');
            }

            $allSuccess = $autoSSLResult['success'] && $redirectResult['success'];

            return [
                'success' => $allSuccess,
                'message' => implode('. ', $messages),
            ];

        } catch (\Exception $e) {
            Log::error('Force SSL failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Force SSL failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Deploy a new instance to cPanel.
     *
     * @param  string  $subdomain  The subdomain to create (e.g. "client1" for client1.domain.com)
     * @param  string  $zipPath  The local path to the uploaded zip file
     * @param  string  $appUrl  The full URL for the instance
     * @return array{success: bool, message: string, details?: array}
     */
    public function deploy(string $subdomain, string $zipPath, string $appUrl): array
    {
        $steps = [];

        try {
            // 1. Create subdomain with document root set to subdomain/public
            $subdomainRoot = "{$this->homeDir}/{$this->username}/public_html/{$subdomain}";
            $documentRoot = "{$subdomainRoot}/public";

            $result = $this->createSubdomain($subdomain, $documentRoot);
            $steps[] = ['step' => 'Create subdomain', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to create subdomain: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // 1b. Request AutoSSL certificate for the subdomain
            $fullSubdomain = "{$subdomain}.{$this->domain}";

            $result = $this->requestAutoSSL($fullSubdomain);
            $steps[] = ['step' => 'Request AutoSSL', 'result' => $result];

            if (! $result['success']) {
                // AutoSSL failure is non-fatal — log and continue
                Log::warning('AutoSSL request failed, continuing deployment', [
                    'domain' => $fullSubdomain,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

            // 1c. Enable force HTTPS redirect
            $result = $this->enableForceHttpsRedirect($fullSubdomain);
            $steps[] = ['step' => 'Enable force HTTPS redirect', 'result' => $result];

            if (! $result['success']) {
                // HTTPS redirect failure is non-fatal — log and continue
                Log::warning('Force HTTPS redirect failed, continuing deployment', [
                    'domain' => $fullSubdomain,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

            // 2. Create database and user
            $dbName = $this->sanitizeForDb($subdomain).'_'.Str::random(4);
            $dbUser = $dbName.'_'.Str::random(4);
            $dbPassword = Str::random(24);

            $fullDbName = "{$this->username}_{$dbName}";
            $fullDbUser = "{$this->username}_{$dbUser}";

            $result = $this->createDatabase($fullDbName);
            $steps[] = ['step' => 'Create database', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to create database: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            $result = $this->createDatabaseUser($fullDbUser, $dbPassword);
            $steps[] = ['step' => 'Create database user', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to create database user: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            $result = $this->assignUserToDatabase($fullDbUser, $fullDbName);
            $steps[] = ['step' => 'Assign user to database', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to assign user to database: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // 3. Upload zip file to subdomain directory
            $uploadRoot = "public_html/{$subdomain}";
            $result = $this->uploadFile($zipPath, $uploadRoot);
            $steps[] = ['step' => 'Upload zip file', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload zip file: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // Extract the zip file
            $zipFilename = basename($zipPath);
            $result = $this->extractZip($subdomainRoot, $zipFilename);
            $steps[] = ['step' => 'Extract zip file', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to extract zip file: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // 4. Move contents from nested folder to subdomain root
            $result = $this->moveExtractedContents($subdomainRoot);
            $steps[] = ['step' => 'Move extracted contents', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to move extracted contents: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // 5. Set .env variables
            $result = $this->updateEnvFile($subdomainRoot, [
                'APP_URL' => $appUrl,
                'DB_DATABASE' => $fullDbName,
                'DB_USERNAME' => $fullDbUser,
                'DB_PASSWORD' => $dbPassword,
            ]);
            $steps[] = ['step' => 'Update .env file', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to update .env file: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // 6 & 7. Run artisan commands
            $artisanCommands = [
                'php artisan migrate --force',
                'php artisan db:seed --force',
                'php artisan storage:link',
            ];

            foreach ($artisanCommands as $command) {
                $result = $this->runCommand($subdomainRoot, $command);
                $steps[] = ['step' => "Run: {$command}", 'result' => $result];

                if (! $result['success']) {
                    return [
                        'success' => false,
                        'message' => "Failed to run '{$command}': ".($result['error'] ?? 'Unknown error'),
                        'details' => $steps,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Instance deployed successfully.',
                'details' => $steps,
                'credentials' => [
                    'db_name' => $fullDbName,
                    'db_user' => $fullDbUser,
                    'db_password' => $dbPassword,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('cPanel deploy failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Deployment failed: '.$e->getMessage(),
                'details' => $steps,
            ];
        }
    }

    /**
     * Upgrade an existing instance on cPanel.
     *
     * @param  string  $subdomain  The subdomain to upgrade
     * @param  string  $zipPath  The local path to the uploaded zip file
     * @return array{success: bool, message: string, details?: array}
     */
    public function upgrade(string $subdomain, string $zipPath): array
    {
        $steps = [];

        try {
            $subdomainRoot = "{$this->homeDir}/{$this->username}/public_html/{$subdomain}";

            // 1. Upload zip file to subdomain directory
            $result = $this->uploadFile($zipPath, $subdomainRoot);
            $steps[] = ['step' => 'Upload zip file', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload zip file: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // 2. Extract the zip file
            $zipFilename = basename($zipPath);
            $result = $this->extractZip($subdomainRoot, $zipFilename);
            $steps[] = ['step' => 'Extract zip file', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to extract zip file: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            $command = "cd {$subdomainRoot} && ".
            "EXTRACTED_DIR=\$(find . -maxdepth 1 -mindepth 1 -type d -name 'jobcardonline-v*' | head -1) && ".
            'if [ -n "$EXTRACTED_DIR" ]; then '.
            'echo "Found: $EXTRACTED_DIR" && '.
            'rm "$EXTRACTED_DIR"/.env . && '.
            "echo 'env file removed'; ".
            'else '.
            "echo 'No extracted directory found. Contents:' && ls -la {$subdomainRoot}; ".
            'fi';

            $this->runShellCommand($command);

            // 3. Move contents from nested folder (overwriting existing files)
            $result = $this->moveExtractedContents($subdomainRoot);
            $steps[] = ['step' => 'Move extracted contents', 'result' => $result];

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to move extracted contents: '.($result['error'] ?? 'Unknown error'),
                    'details' => $steps,
                ];
            }

            // 4. Run artisan commands
            $artisanCommands = [
                'php artisan migrate --force',
                'php artisan storage:link',
            ];

            foreach ($artisanCommands as $command) {
                $result = $this->runCommand($subdomainRoot, $command);
                $steps[] = ['step' => "Run: {$command}", 'result' => $result];

                if (! $result['success']) {
                    return [
                        'success' => false,
                        'message' => "Failed to run '{$command}': ".($result['error'] ?? 'Unknown error'),
                        'details' => $steps,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Instance upgraded successfully.',
                'details' => $steps,
            ];

        } catch (\Exception $e) {
            Log::error('cPanel upgrade failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Upgrade failed: '.$e->getMessage(),
                'details' => $steps,
            ];
        }
    }

    /**
     * Create a subdomain in cPanel.
     */
    private function createSubdomain(string $subdomain, string $documentRoot): array
    {
        return $this->cpanelApiCall('SubDomain', 'addsubdomain', [
            'domain' => $subdomain,
            'rootdomain' => $this->domain,
            'dir' => $documentRoot,
        ]);
    }

    /**
     * Request AutoSSL for a domain via cPanel.
     */
    private function requestAutoSSL(string $domain): array
    {
        return $this->cpanelApiCall('SSL', 'request_autossl', [
            'domain' => $domain,
        ]);
    }

    /**
     * Enable force HTTPS redirect for a domain via cPanel.
     */
    private function enableForceHttpsRedirect(string $domain): array
    {
        return $this->cpanelApiCall('SSL', 'set_https_redirect', [
            'domain' => $domain,
            'state' => 1,
        ]);
    }

    /**
     * Create a MySQL database.
     */
    private function createDatabase(string $dbName): array
    {
        return $this->cpanelApiCall('Mysql', 'create_database', [
            'name' => $dbName,
        ]);
    }

    /**
     * Create a MySQL database user.
     */
    private function createDatabaseUser(string $username, string $password): array
    {
        return $this->cpanelApiCall('Mysql', 'create_user', [
            'name' => $username,
            'password' => $password,
        ]);
    }

    /**
     * Assign a user to a database with all privileges.
     */
    private function assignUserToDatabase(string $user, string $database): array
    {
        return $this->cpanelApiCall('Mysql', 'set_privileges_on_database', [
            'user' => $user,
            'database' => $database,
            'privileges' => 'ALL PRIVILEGES',
        ]);
    }

    /**
     * Upload a file to a cPanel directory.
     */
    private function uploadFile(string $localPath, string $remoteDir): array
    {
        try {
            $filename = basename($localPath);
            $url = "https://{$this->host}:{$this->port}/execute/Fileman/upload_files";

            $response = Http::withHeaders([
                'Authorization' => "cpanel {$this->username}:{$this->apiToken}",
            ])
                ->timeout(300)
                ->attach('file-0', file_get_contents($localPath), $filename)
                ->post($url, [
                    'dir' => $remoteDir,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 1) {
                    return ['success' => true];
                }

                return [
                    'success' => false,
                    'error' => $data['errors'][0] ?? 'Upload returned non-success status',
                ];
            }

            return [
                'success' => false,
                'error' => 'HTTP '.$response->status().': '.$response->body(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract a zip file on the server using a shell command.
     */
    private function extractZip(string $directory, string $filename): array
    {
        return $this->runShellCommand("cd {$directory} && unzip -o {$filename} && rm -f {$filename}");
    }

    /**
     * Move contents from extracted folder to the root directory.
     * The zip will typically extract to a folder like jobcardonline-v-xxxx/
     * We need to skip the 'public' directory which was created by the subdomain setup.
     */
    private function moveExtractedContents(string $subdomainRoot): array
    {
        // First, list what's in the directory so we can see exactly what we're working with
        $listResult = $this->runShellCommand("ls -la {$subdomainRoot}");
        Log::info('Subdomain directory contents before move', [
            'path' => $subdomainRoot,
            'listing' => $listResult['output'] ?? 'no output',
        ]);

        // Find the extracted directory matching jobcardonline-v*, then move its contents up and remove it
        $command = "cd {$subdomainRoot} && ".
            "EXTRACTED_DIR=\$(find . -maxdepth 1 -mindepth 1 -type d -name 'jobcardonline-v*' | head -1) && ".
            'if [ -n "$EXTRACTED_DIR" ]; then '.
            'echo "Found: $EXTRACTED_DIR" && '.
            'cp -rf "$EXTRACTED_DIR"/. . && '.
            'rm -rf "$EXTRACTED_DIR" && '.
            "echo 'Contents moved successfully'; ".
            'else '.
            "echo 'No jobcardonline-v* directory found. Contents:' && ls -la {$subdomainRoot}; ".
            'fi';

        return $this->runShellCommand($command);
    }

    /**
     * Update the .env file on the remote server.
     */
    private function updateEnvFile(string $subdomainRoot, array $variables): array
    {
        // Build sed commands to replace each variable
        // Using | as delimiter so / in URLs doesn't need escaping
        $sedCommands = [];
        foreach ($variables as $key => $value) {
            // Only escape characters special to sed with | delimiter: \ & |
            $escapedValue = str_replace(['\\', '&', '|'], ['\\\\', '\\&', '\\|'], $value);
            $sedCommands[] = "sed -i 's|^{$key}=.*|{$key}={$escapedValue}|' {$subdomainRoot}/.env";
        }

        $command = implode(' && ', $sedCommands);

        return $this->runShellCommand($command);
    }

    /**
     * Run a command in the subdomain directory.
     */
    private function runCommand(string $subdomainRoot, string $command): array
    {
        return $this->runShellCommand("cd {$subdomainRoot} && {$command}");
    }

    /**
     * Execute a shell command via the cPanel Terminal API only.
     */
    private function runShellCommand(string $command): array
    {
        // Try 1: UAPI Shell::command (requires shell access enabled in cPanel)
        try {
            $url = "https://{$this->host}:{$this->port}/execute/Shell/command";

            $response = Http::withHeaders([
                'Authorization' => "cpanel {$this->username}:{$this->apiToken}",
            ])
                ->timeout(120)
                ->post($url, [
                    'command' => $command,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 1) {
                    return [
                        'success' => true,
                        'output' => $data['data'] ?? '',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Shell::command execution failed', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => 'Shell execution via cPanel Terminal API failed: '.$e->getMessage(),
            ];
        }

        return [
            'success' => false,
            'error' => 'cPanel Terminal API is unavailable or command execution is not permitted.',
        ];
    }

    /**
     * Make a cPanel UAPI call.
     */
    private function cpanelApiCall(string $module, string $function, array $params = []): array
    {
        try {
            $url = "https://{$this->host}:{$this->port}/execute/{$module}/{$function}";

            $response = Http::withHeaders([
                'Authorization' => "cpanel {$this->username}:{$this->apiToken}",
            ])
                ->timeout(60)
                ->post($url, $params);

            if ($response->successful()) {
                $data = $response->json();

                Log::info("cPanel API call: {$module}/{$function}", SafeLog::redactContext([
                    'params' => $params,
                    'response_status' => $data['status'] ?? null,
                ]));

                if (isset($data['status']) && $data['status'] === 1) {
                    return [
                        'success' => true,
                        'data' => $data['data'] ?? null,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $data['errors'][0] ?? ($data['messages'][0] ?? 'API returned non-success status'),
                    'data' => $data,
                ];
            }

            Log::error("cPanel API HTTP error: {$module}/{$function}", SafeLog::httpResponseContext($response->status(), $response->body()));

            return [
                'success' => false,
                'error' => 'HTTP '.$response->status().': '.SafeLog::excerpt($response->body(), 200),
            ];

        } catch (\Exception $e) {
            Log::error("cPanel API exception: {$module}/{$function}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sanitize a string for use as a database name.
     * cPanel prefixes db names with username_, and has length limits.
     */
    private function sanitizeForDb(string $name): string
    {
        // Remove dots and special chars, keep alphanumeric and underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        // cPanel db name max length is 64, but username_prefix takes some
        // Username prefix + underscore can be up to ~16 chars
        $maxLength = 64 - strlen($this->username) - 1;

        return substr($sanitized, 0, $maxLength);
    }
}
