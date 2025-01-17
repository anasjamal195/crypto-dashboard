<?php

namespace App\Services;

use App\Mail\OrderMail;
use App\Mail\WorkerEmail;
use Illuminate\Support\Facades\Mail;

class MailerService
{
    public static $recipients = [
        'anasj5749@gmail.com',
        'drupalmind@gmail.com',
    ];
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function sendEmail($details)
    {
        foreach (self::$recipients as $recipient)
            Mail::to($recipient)->send(new OrderMail($details));
    }
    public static function sendWorkerEmail($processName)
    {
        // foreach (self::$recipients as $recipient)
        //     Mail::to($recipient)->send(new WorkerEmail($processName));
    }
}
