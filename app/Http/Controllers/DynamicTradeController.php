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
    public function dynamicTradeResults($market)
    {
        // Fetching all trades
        if ($market == 'SPOT') {
            $results = DB::table('dynamic_trades_spot_results')->where('trade_acc', auth()->user()->id)->get();
            $pageSlug = 'DynamicTradesResultSPOT';
            return view('dynamic-trades.index-spot-results', compact('results', 'pageSlug'));
        } else if ($market == 'FUTURE') {
            $results = DB::table('dynamic_trades_future_results')->where('trade_acc', auth()->user()->id)->get();
            $pageSlug = 'DynamicTradesResultFUTURE';
            return view('dynamic-trades.index-future-results', compact('results', 'pageSlug'));
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
                'stopLoss' => $request->stopLoss,
                'stopLossBuffer' => $request->stopLossBuffer,
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
                'stopLoss' => $request->stopLoss,
                'stopLossBuffer' => $request->stopLossBuffer,
                'isActive' => $request->isActive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            abort(404);
        }

        return redirect()->route('dynamic-trading.index', ['market' => $request->market])->with('success', 'Trade created successfully!');
    }

    public function edit(Request $request, $id)
    {
        if ($request->market == 'SPOT') {
            $trade = DB::table('dynamic_trades_spot')->where('id', $id)->first();
            $pageSlug = 'DynamicTradesEdit';

            return view('dynamic-trades.edit-spot', compact('trade', 'pageSlug')); // Ensure this view exists
        } else if ($request->market == 'FUTURE') {
            $trade = DB::table('dynamic_trades_future')->where('id', $id)->first();
            $pageSlug = 'DynamicTradesEdit';

            return view('dynamic-trades.edit-future', compact('trade', 'pageSlug')); // Ensure this view exists
        }
        // Loading the edit view with trade data

    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        if ($request->market == 'SPOT') {
            DB::table('dynamic_trades_spot')->where('id', $id)->update([
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
                'stopLoss' => $request->stopLoss,
                'stopLossBuffer' => $request->stopLossBuffer,
                'isActive' => $request->isActive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else if ($request->market == 'FUTURE') {
            DB::table('dynamic_trades_future')->where('id', $id)->update([
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
                'stopLoss' => $request->stopLoss,
                'stopLossBuffer' => $request->stopLossBuffer,
                'isActive' => $request->isActive,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            abort(404);
        }

        return redirect()->route('dynamic-trading.index', ['market' => $request->market])->with('success', 'Trade Updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        // Deleting a trade
        if ($request->market == 'SPOT')
            DB::table('dynamic_trades_spot')->where('id', $id)->delete();
        else if ($request->market == 'FUTURE')
            DB::table('dynamic_trades_future')->where('id', $id)->delete();
        else
            abort(404);
        return redirect()->route('dynamic-trading.index', ['market' => $request->market])->with('success', 'Trade Deleted successfully!');
    }
}
