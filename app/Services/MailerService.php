<?php

namespace App\Services;

use App\Mail\DynamicFutureTradeNotification;
use App\Mail\DynamicSpotTradeNotification;
use App\Mail\OrderMail;
use App\Mail\SkipEmail;
use App\Mail\WalletEmail;
use App\Mail\WorkerEmail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class MailerService
{
    public static $recipients = [
        'anasj5749@gmail.com',
        'drupalmind@gmail.com',
        // 'egeniuscare@gmail.com'
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
    public static function sendSpotTradeDynamicEmail($details)
    {
        foreach (self::$recipients as $recipient)
            Mail::to($recipient)->send(new DynamicSpotTradeNotification($details));
    }
    public static function sendFutureTradeDynamicEmail($details)
    {
        foreach (self::$recipients as $recipient)
            Mail::to($recipient)->send(new DynamicFutureTradeNotification($details));
    }
    public static function sendWalletEmail($order)
    {
        foreach (self::$recipients as $recipient)
            Mail::to($recipient)->send(new WalletEmail($order));
    }


    public static function sendSkipEmail($tradeInstance,$subject)
    {
        $data =  [
            'orderId' => '',
            'symbol' => $tradeInstance->symbol,
            'side' =>  $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL',
            'amount' => '',
            'type' => '',
            'position' => $tradeInstance->position,
            'qty' => '',
            'leverage' => '',
            'stopLoss' => '',
            'stopLossReductionPrecentage' => 0.1,
            'price' => BinanceApiService::getCurrentPrice($tradeInstance->symbol,$tradeInstance->market),
            'trade_status' => 'open',
            'trade_acc' => $tradeInstance->tradeAccount,
            'targetProfit' => 0.5,
            'formula' => '',
            'liqPrice' => '',
            'subject' => $subject. ' :Account ' . User::find($tradeInstance->tradeAccount)->name,
            'created_at' => Carbon::now('Asia/Karachi'),
        ];
        foreach (self::$recipients as $recipient)
            Mail::to($recipient)->send(new SkipEmail($data));
    }
}
