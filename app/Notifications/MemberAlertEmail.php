<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The email copy of a MemberAlert. It goes into the queue so a slow mail
 * server never makes a like or a message wait; the scheduler's queue worker
 * (routes/console.php) sends it within about a minute.
 */
class MemberAlertEmail extends MemberAlert implements ShouldQueue
{
    public int $tries = 3;

    /** Wait a bit longer between tries if the mail server is having trouble. */
    public int $backoff = 60;
}
