<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeHandler extends Model
{
    use HasFactory;
    public function setIsActiveAttribute($value)
    {
        $this->attributes['isActive'] = ($value === 'on' || $value === true || $value === 1) ? 1 : 0;
    }
    protected $table = 'trade_handler'; // Define the table associated with the model

    protected $fillable = [
        'market',
        'symbol',
        'interval',
        'buyPrice',
        'targetProfit',
        'tradeAccount',
        'leverage',
        'stopLoss',
        'stopLossReductionPrecentage',
        'rsiThreshold',
        'obvLimit',
        'stochLimit',
        'wrLimit',
        'isActive'
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];
}
