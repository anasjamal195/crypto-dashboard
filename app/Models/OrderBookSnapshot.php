<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderBookSnapshot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'symbol',
        'snapshot_time',
        'depth',
        'raw_data',
        'bid_volume',
        'ask_volume',
        'volume_imbalance',
        'highest_bid',
        'lowest_ask',
        'spread',
        'support_levels',
        'resistance_levels',
        'thin_liquidity_areas',
        'signal',
        'long_strength',
        'short_strength',
        'long_entry_points',
        'short_entry_points',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'snapshot_time' => 'datetime',
        'raw_data' => 'array',
        'support_levels' => 'array',
        'resistance_levels' => 'array',
        'thin_liquidity_areas' => 'array',
        'long_entry_points' => 'array',
        'short_entry_points' => 'array',
        'bid_volume' => 'decimal:8',
        'ask_volume' => 'decimal:8',
        'volume_imbalance' => 'decimal:4',
        'highest_bid' => 'decimal:8',
        'lowest_ask' => 'decimal:8',
        'spread' => 'decimal:8',
        'long_strength' => 'decimal:2',
        'short_strength' => 'decimal:2',
    ];

    /**
     * Get snapshots for a specific symbol within a time range
     *
     * @param string $symbol
     * @param \DateTime $startTime
     * @param \DateTime $endTime
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getSymbolHistory($symbol, $startTime, $endTime)
    {
        return self::where('symbol', $symbol)
            ->whereBetween('snapshot_time', [$startTime, $endTime])
            ->orderBy('snapshot_time')
            ->get();
    }

    /**
     * Get the most recent trading signals for all symbols
     *
     * @param int $limit Number of symbols to return
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRecentSignals($limit = 10)
    {
        return self::selectRaw('symbol, MAX(id) as latest_id')
            ->groupBy('symbol')
            ->orderByDesc('latest_id')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return self::find($item->latest_id);
            });
    }

    /**
     * Get historical signals for backtesting
     *
     * @param string $symbol
     * @param \DateTime $startTime
     * @param \DateTime $endTime
     * @param string $signalType LONG, SHORT, or null for all
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getHistoricalSignals($symbol, $startTime, $endTime, $signalType = null)
    {
        $query = self::where('symbol', $symbol)
            ->whereBetween('snapshot_time', [$startTime, $endTime]);
            
        if ($signalType) {
            $query->where('signal', $signalType);
        }
        
        return $query->orderBy('snapshot_time')->get();
    }
}