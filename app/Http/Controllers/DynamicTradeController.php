<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicTradeController extends Controller
{
    public function index()
    {
        // Fetching all trades
        $trades = DB::table('dynamic_trade_handler')->get();
        $pageSlug = 'DynamicTrades';
        return view('dynamic-trades.index', compact('trades', 'pageSlug')); // Ensure the view path matches the actual location
    }

    public function create()
    {
        // Loading the creation view
        $pageSlug = 'DynamicTradesCreate';

        return view('dynamic-trades.create', compact('pageSlug')); // Make sure this view exists in the correct directory
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
            'side' => 'required',
            'leverage' => 'nullable|numeric',
            'priceLock' => 'nullable|numeric',
            'priceLockBuffer' => 'nullable|numeric',
            'isActive' => 'required|boolean'
        ]);

        // Inserting new trade
        DB::table('dynamic_trade_handler')->insert([
            'market' => $request->market,
            'symbol' => $request->symbol,
            'amount' => $request->amount,
            'qty' => $request->qty,
            'side' => $request->side,
            'status' => 'PENDING',
            'tradeAccount' => auth()->user()->id, // Assuming the logged in user's ID should be used
            'leverage' => $request->leverage,
            'priceLock' => $request->priceLock,
            'priceLockBuffer' => $request->priceLockBuffer,
            'isActive' => $request->isActive,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return redirect()->route('dynamic-trading.index')->with('success', 'Trade created successfully!');
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
