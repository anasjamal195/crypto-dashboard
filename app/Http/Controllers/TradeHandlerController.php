<?php

namespace App\Http\Controllers;

use App\Models\TradeHandler;
use Illuminate\Http\Request;

class TradeHandlerController extends Controller
{
    public function index()
    {
        $tradeHandlers = TradeHandler::where('tradeAccount', auth()->user()->id)->get();
        $pageSlug = 'TradeHandler';
        return view('trade_handler.index', compact('tradeHandlers', 'pageSlug'));
    }

    public function create()
    {
        $pageSlug = 'TradeHandlerCreate';

        return view('trade_handler.create', compact('pageSlug'));
    }

    public function store(Request $request)
    {
        TradeHandler::create($request->all());
        return redirect()->route('trade-handler.index')->with('success', 'Handler created successfully.');
    }

    public function edit(TradeHandler $tradeHandler)
    {
        $pageSlug = 'TradeHandlerEdit';

        return view('trade_handler.edit', compact('tradeHandler', 'pageSlug'));
    }

    public function update(Request $request, TradeHandler $tradeHandler)
    {
        $tradeHandler->update($request->all());
        return redirect()->route('trade-handler.index')->with('success', 'Handler updated successfully.');
    }

    public function destroy(TradeHandler $tradeHandler)
    {
        $tradeHandler->delete();
        return redirect()->route('trade-handler.index')->with('success', 'Handler deleted successfully.');
    }
}
