<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicTradeController extends Controller
{
    public function index(Request $request)
    {
        // Fetching all trades
        if ($request->market == 'SPOT') {
            $trades = DB::table('dynamic_trades_spot')->where('tradeAccount', auth()->user()->id)->get();
            $pageSlug = 'DynamicTradesSPOT';
            return view('dynamic-trades.index-spot', compact('trades', 'pageSlug'));
        } else if ($request->market == 'FUTURE') {
            $trades = DB::table('dynamic_trades_future')->where('tradeAccount', auth()->user()->id)->get();
            $pageSlug = 'DynamicTradesFUTURE';
            return view('dynamic-trades.index-future', compact('trades', 'pageSlug'));
        } else {
            abort(404);
        }
    }

    public function create(Request $request)
    {
        if ($request->market == 'SPOT') {
            $pageSlug = 'DynamicTradesCreateSPOT';

            return view('dynamic-trades.create-spot', compact('pageSlug'));
        } else  if ($request->market == 'FUTURE') {
            $pageSlug = 'DynamicTradesCreateFUTURE';
            return view('dynamic-trades.create-future', compact('pageSlug'));
        } else {
            abort(404);
        }
    }

    public function store(Request $request)
    {

        // dd($request->all());
        // Validation can be added here
        $request->validate([
            'market' => 'required',
            'symbol' => 'required',
            'amount' => 'nullable|numeric',
            'qty' => 'nullable|numeric',
            'side' => 'nullable',
            'leverage' => 'nullable|numeric',
            'priceLock' => 'nullable|numeric',
            'priceLockBuffer' => 'nullable|numeric',
            'isActive' => 'required|boolean'
        ]);

        // Inserting new trade
        if ($request->market == 'SPOT') {
            DB::table('dynamic_trades_spot')->insert([
                'symbol' => $request->symbol,
                'amount' => $request->amount,
                'qty' => $request->qty,
                'side' => $request->side,
                'status' => 'PENDING-BUY',
                'tradeAccount' => auth()->user()->id,
                'priceLockBuy' => $request->priceLockBuy,
                'priceLockBuyBuffer' => $request->priceLockBuyBuffer,
                'priceLockSell' => $request->priceLockSell,
                'priceLockSellBuffer' => $request->priceLockSellBuffer,
                'isActive' => $request->isActive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else if ($request->market == 'FUTURE') {
            DB::table('dynamic_trades_future')->insert([
                'symbol' => $request->symbol,
                'amount' => $request->amount,
                'position' => $request->position,
                'allowClose' => $request->allowClose === 'on',
                'qty' => $request->qty,
                'leverage' => $request->leverage,
                'status' => 'PENDING-OPEN',
                'tradeAccount' => auth()->user()->id,
                'priceLockOpen' => $request->priceLockOpen,
                'priceLockOpenBuffer' => $request->priceLockOpenBuffer,
                'priceLockClose' => $request->priceLockClose,
                'priceLockCloseBuffer' => $request->priceLockCloseBuffer,
                'isActive' => $request->isActive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            abort(404);
        }

        return redirect()->route('dynamic-trading.index', ['market' => $request->market])->with('success', 'Trade created successfully!');
    }

    public function edit($id)
    {
        // Loading the edit view with trade data
        $trade = DB::table('dynamic_trade_handler')->where('id', $id)->first();
        $pageSlug = 'DynamicTradesEdit';

        return view('dynamic-trades.edit', compact('trade', 'pageSlug')); // Ensure this view exists
    }

    public function update(Request $request, $id)
    {
        // Validation can be similar to the store method
        $request->validate([
            'market' => 'required',
            'symbol' => 'required',
            'amount' => 'nullable|numeric',
            'qty' => 'nullable|numeric',
            'side' => 'required',
            'leverage' => 'nullable|numeric',
            'priceLock' => 'nullable|numeric',
            'priceLockBuffer' => 'nullable|numeric',
            'isActive' => 'required|boolean'
        ]);

        // Updating existing trade
        DB::table('dynamic_trade_handler')->where('id', $id)->update([
            'market' => $request->market,
            'symbol' => $request->symbol,
            'amount' => $request->amount,
            'qty' => $request->qty,
            'side' => $request->side,
            'leverage' => $request->leverage,
            'priceLock' => $request->priceLock,
            'priceLockBuffer' => $request->priceLockBuffer,
            'isActive' => $request->isActive,
            'updated_at' => now()
        ]);

        return redirect()->route('dynamic-trading.index')->with('success', 'Trade updated successfully!');
    }

    public function destroy($id)
    {
        // Deleting a trade
        DB::table('dynamic_trade_handler')->where('id', $id)->delete();
        return redirect()->route('dynamic-trading.index')->with('success', 'Trade deleted successfully!');
    }
}
