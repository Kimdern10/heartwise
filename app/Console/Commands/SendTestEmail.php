<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('app:test-email {to : The address to send the test email to}')]
#[Description('Send a test email to check that the mail settings in .env work')]
class SendTestEmail extends Command
{
    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->components->warn("MAIL_MAILER is \"{$mailer}\", so emails are not really sent. Set MAIL_MAILER=smtp in .env first.");

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'This is a test from '.config('app.name').'. If you can read this, sign-up codes will arrive too.',
                fn ($message) => $message->to($to)->subject(config('app.name').' test email'),
            );
        } catch (Throwable $e) {
            $this->components->error('Sending failed: '.$e->getMessage());
            $this->line('Check MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME and MAIL_PASSWORD in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        $this->components->info("Test email sent to {$to} via {$mailer} (".config('mail.mailers.smtp.host').'). Check the inbox and the spam folder.');

        return self::SUCCESS;
    }
}
