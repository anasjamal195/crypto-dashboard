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
            $trades = DB::table('dynamic_trade_handler')->where('market', 'SPOT')->where('tradeAccount', auth()->user()->id)->get();
            $pageSlug = 'DynamicTradesSPOT';
            return view('dynamic-trades.index', compact('trades', 'pageSlug'));
        } else if ($request->market == 'FUTURE') {
            $trades = DB::table('dynamic_trade_handler')->where('market', 'FUTURE')->where('tradeAccount', auth()->user()->id)->get();
            $pageSlug = 'DynamicTradesFUTURE';
            return view('dynamic-trades.index', compact('trades', 'pageSlug'));
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

        // Validation can be added here
        $request->validate([
            'market' => 'required',
            'symbol' => 'nullable',
            'amount' => 'nullable|numeric',
            'qty' => 'nullable|numeric',
            'side' => 'required',
            'leverage' => 'nullable|numeric',
            'priceLock' => 'nullable|numeric',
            'priceLockBuffer' => 'nullable|numeric',
            'isActive' => 'required|boolean'
        ]);

        // Inserting new trade
        if(!$request->openOrderId){
        DB::table('dynamic_trade_handler')->insert([
            'market' => $request->market,
            'symbol' => $request->symbol,
            'amount' => $request->amount,
            'qty' => $request->qty,
            'position' => $request->side === 'BUY' ? 'LONG' : 'SHORT',
            'side' => $request->side,
            'status' => 'PENDING',
            'tradeAccount' => auth()->user()->id,
            'leverage' => $request->leverage,
            'priceLock' => $request->priceLock,
            'priceLockBuffer' => $request->priceLockBuffer,
            'isActive' => $request->isActive,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }else{
        $openOrder = DB::table('dynamic_orders')->where('orderId',$request->openOrderId)->first();

        DB::table('dynamic_trade_handler')->insert([
            'market' => $openOrder->market,
            'symbol' => $openOrder->symbol,
            'amount' => $openOrder->amount,
            'qty' => $openOrder->qty,
            'position' => $openOrder->side === 'BUY' ? 'LONG' : 'SHORT',
            'side' => $openOrder->side,
            'status' => 'PENDING',
            'tradeAccount' => auth()->user()->id,
            'leverage' => $openOrder->leverage,
            'priceLock' => $request->priceLock,
            'priceLockBuffer' => $request->priceLockBuffer,
            'isActive' => $request->isActive,
            'created_at' => now(),
            'updated_at' => now()
        ]);
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
