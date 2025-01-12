<?php

namespace App\Services;

use App\Mail\OrderMail;
use Illuminate\Support\Facades\Mail;

class MailerService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function sendEmail($details)
    {
        $recipient1 = "anasj5749@gmail.com";
        $recipient2 = "drupalmind@gmail.com";
       
        Mail::to($recipient1)->send(new OrderMail($details));
        Mail::to($recipient2)->send(new OrderMail($details));
    }
}
