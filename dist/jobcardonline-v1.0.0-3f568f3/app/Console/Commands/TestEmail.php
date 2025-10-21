<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;

class TestEmail extends Command
{
    protected $signature = 'test:email {user_id} {to_email}';
    protected $description = 'Test email sending with user SMTP settings';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $toEmail = $this->argument('to_email');

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Testing email for user: {$user->name} ({$user->email})");
        $this->info("SMTP Host: {$user->smtp_host}");
        $this->info("SMTP Port: {$user->smtp_port}");
        $this->info("SMTP Username: {$user->smtp_username}");
        $this->info("SMTP Encryption: {$user->smtp_encryption}");

        if (!$user->smtp_host || !$user->smtp_username || !$user->smtp_password) {
            $this->error("User SMTP settings are incomplete.");
            return 1;
        }

        try {
            // Configure mail using user's SMTP settings
            Config::set([
                'mail.mailers.smtp.host' => $user->smtp_host,
                'mail.mailers.smtp.port' => $user->smtp_port ?? 587,
                'mail.mailers.smtp.username' => $user->smtp_username,
                'mail.mailers.smtp.password' => $user->smtp_password,
                'mail.mailers.smtp.encryption' => $user->smtp_encryption ?? 'tls',
            ]);

            $this->info("Sending test email to: {$toEmail}");

            // Generate a simple test PDF
            $pdf = Pdf::loadHTML('<h1>Test PDF</h1><p>This is a test PDF from the jobcard system.</p>');
            $filename = 'test-jobcard.pdf';

            Mail::mailer('smtp')->raw('This is a test email from the jobcard system with a PDF attachment.', function ($message) use ($user, $toEmail, $pdf, $filename) {
                $fromEmail = $user->smtp_from_email ?? $user->email;
                $fromName = $user->smtp_from_name ?? $user->name;
                
                $message->to($toEmail)
                    ->subject('Test Email from Jobcard System')
                    ->from($fromEmail, $fromName)
                    ->attachData($pdf->output(), $filename, [
                        'mime' => 'application/pdf',
                    ]);
            });

            $this->info("✅ Email sent successfully!");
            $this->info("Check the logs for more details: storage/logs/laravel.log");

        } catch (\Exception $e) {
            $this->error("❌ Email sending failed: " . $e->getMessage());
            $this->error("Full error: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}