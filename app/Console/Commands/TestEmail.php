<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;

class TestEmail extends Command
{
    protected $signature = 'test:email {user_id} {to_email}';
    protected $description = 'Test email sending using app mail config';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $toEmail = $this->argument('to_email');

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $company = $user->getCurrentCompany();
        $fromName = $company?->name ?: $user->name;

        try {
            $this->info("Sending test email to: {$toEmail}");

            // Generate a simple test PDF
            $pdf = Pdf::loadHTML('<h1>Test PDF</h1><p>This is a test PDF from the jobcard system.</p>');
            $filename = 'test-jobcard.pdf';

            Mail::mailer('smtp')->raw('This is a test email from the jobcard system with a PDF attachment.', function ($message) use ($toEmail, $pdf, $filename, $company, $fromName) {
                $message->to($toEmail)
                    ->subject('Test Email from Jobcard System')
                    ->from(config('mail.from.address'), $fromName)
                    ->attachData($pdf->output(), $filename, [
                        'mime' => 'application/pdf',
                    ]);

                if (!empty($company?->email)) {
                    $message->replyTo($company->email, $company->name ?? null);
                }
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