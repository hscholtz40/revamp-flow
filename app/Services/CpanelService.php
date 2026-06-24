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
     * @param  array{email?: string, password?: string, name?: string, must_reset_password?: bool}|null  $adminBootstrap
     * @return array{success: bool, message: string, details?: array}
     */
    public function deploy(string $subdomain, string $zipPath, string $appUrl, ?array $adminBootstrap = null): array
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
                $this->buildSeedCommand($adminBootstrap),
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
                    'admin_email' => is_string($adminBootstrap['email'] ?? null) ? $adminBootstrap['email'] : null,
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
     * Execute a shell command via cPanel Terminal API, with web-exec fallback.
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
            Log::info('Shell::command not available, trying web-exec fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Try 2: Write a temp PHP script to public_html, execute via HTTP, self-deletes
        return $this->runShellViaWebExec($command);
    }

    /**
     * Fallback: Execute a shell command by placing a temp PHP script in public_html
     * and requesting it via HTTP. The script self-deletes after execution.
     */
    private function runShellViaWebExec(string $command): array
    {
        $scriptName = '_cpanel_exec_'.Str::random(32).'.php';
        $publicHtml = "{$this->homeDir}/{$this->username}/public_html";

        try {
            // Base64-encode the command to avoid any PHP/shell escaping issues
            // (shell commands with $(), $VAR etc. would be mangled inside PHP strings)
            $encodedCommand = base64_encode($command.' 2>&1');
            $phpScript = <<<'PHPSCRIPT'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$command = base64_decode("COMMAND_PLACEHOLDER");
$result = ['output' => '', 'exit_code' => -1, 'method' => 'none'];

// Try 1: proc_open (most reliable)
if (function_exists('proc_open')) {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (is_resource($process)) {
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $result = [
            'output' => trim($stdout . "\n" . $stderr),
            'exit_code' => $exitCode,
            'method' => 'proc_open',
        ];
        echo json_encode($result);
        @unlink(__FILE__);
        exit;
    }
}

// Try 2: shell_exec
if (function_exists('shell_exec')) {
    $output = shell_exec($command);
    if ($output !== null) {
        $result = [
            'output' => trim($output),
            'exit_code' => 0,
            'method' => 'shell_exec',
        ];
        echo json_encode($result);
        @unlink(__FILE__);
        exit;
    }
}

// Try 3: exec
if (function_exists('exec')) {
    $lines = [];
    $exitCode = 0;
    exec($command, $lines, $exitCode);
    $result = [
        'output' => implode("\n", $lines),
        'exit_code' => $exitCode,
        'method' => 'exec',
    ];
    echo json_encode($result);
    @unlink(__FILE__);
    exit;
}

// Try 4: passthru with output buffering
if (function_exists('passthru')) {
    ob_start();
    $exitCode = 0;
    passthru($command, $exitCode);
    $output = ob_get_clean();
    $result = [
        'output' => trim($output),
        'exit_code' => $exitCode,
        'method' => 'passthru',
    ];
    echo json_encode($result);
    @unlink(__FILE__);
    exit;
}

// Try 5: system
if (function_exists('system')) {
    ob_start();
    $exitCode = 0;
    system($command, $exitCode);
    $output = ob_get_clean();
    $result = [
        'output' => trim($output),
        'exit_code' => $exitCode,
        'method' => 'system',
    ];
    echo json_encode($result);
    @unlink(__FILE__);
    exit;
}

// No execution method available
$disabled = ini_get('disable_functions');
$result = [
    'output' => 'No shell execution functions available. Disabled functions: ' . $disabled,
    'exit_code' => 126,
    'method' => 'none',
];
echo json_encode($result);
@unlink(__FILE__);
PHPSCRIPT;

            // Replace the command placeholder with the base64-encoded command
            $phpScript = str_replace('COMMAND_PLACEHOLDER', $encodedCommand, $phpScript);

            // Write the script to public_html
            $writeResult = $this->cpanelApiCall('Fileman', 'save_file_content', [
                'dir' => $publicHtml,
                'file' => $scriptName,
                'content' => $phpScript,
            ]);

            if (! $writeResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to create exec script: '.($writeResult['error'] ?? 'Unknown error'),
                ];
            }

            // Execute the script via HTTP request to the main domain
            $execResponse = null;
            $execUrls = [
                "https://{$this->domain}/{$scriptName}",
                "http://{$this->domain}/{$scriptName}",
            ];

            foreach ($execUrls as $execUrl) {
                try {
                    $execResponse = Http::withoutVerifying()
                        ->timeout(120)
                        ->get($execUrl);

                    if ($execResponse->successful()) {
                        break;
                    }
                } catch (\Exception $e) {
                    Log::info('Web-exec HTTP attempt failed', [
                        'url' => $execUrl,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            if (! $execResponse || ! $execResponse->successful()) {
                // Clean up the script since it wasn't executed
                $this->cpanelApiCall('Fileman', 'save_file_content', [
                    'dir' => $publicHtml,
                    'file' => $scriptName,
                    'content' => '<?php @unlink(__FILE__);',
                ]);

                $status = $execResponse ? $execResponse->status() : 'no response';
                $body = $execResponse ? $execResponse->body() : '';

                return [
                    'success' => false,
                    'error' => "Could not execute script via HTTP (status: {$status}). Response: {$body}",
                ];
            }

            $body = $execResponse->body();
            $result = $execResponse->json();

            Log::info('Web-exec response', [
                'command' => Str::limit($command, 100),
                'http_status' => $execResponse->status(),
                'body' => Str::limit($body, 500),
                'parsed' => $result,
            ]);

            if ($result && isset($result['exit_code'])) {
                $success = (int) $result['exit_code'] === 0;

                return [
                    'success' => $success,
                    'output' => $result['output'] ?? '',
                    'method' => $result['method'] ?? 'unknown',
                    'error' => ! $success
                        ? ($result['output'] ?: 'Command exited with code '.$result['exit_code'])
                        : null,
                ];
            }

            // Response wasn't JSON — return raw body
            return [
                'success' => false,
                'output' => $body,
                'error' => 'Unexpected response from exec script: '.Str::limit($body, 300),
            ];

        } catch (\Exception $e) {
            Log::error('Web-exec fallback failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Shell execution failed: '.$e->getMessage(),
            ];
        }
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
     * Build the db:seed command with one-shot admin bootstrap env vars when deploying.
     */
    private function buildSeedCommand(?array $adminBootstrap): string
    {
        if (! is_array($adminBootstrap) || empty($adminBootstrap['email'])) {
            return 'php artisan db:seed --force';
        }

        $email = $this->escapeShellArg((string) $adminBootstrap['email']);
        $password = $this->escapeShellArg(
            (string) ($adminBootstrap['password'] ?? config('services.cpanel.deploy_default_admin_password', 'P@ssw0rd'))
        );
        $name = $this->escapeShellArg((string) ($adminBootstrap['name'] ?? 'Administrator'));
        $mustReset = ! empty($adminBootstrap['must_reset_password']) ? '1' : '0';

        return "ADMIN_EMAIL={$email} ADMIN_PASSWORD={$password} ADMIN_NAME={$name} ADMIN_MUST_RESET_PASSWORD={$mustReset} php artisan db:seed --force";
    }

    private function escapeShellArg(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
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
