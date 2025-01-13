<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function liveTraderSettings(Request $request)
    {
        $pageSlug = 'InternalTraderSettings';

        return view('settings.livetrader', compact('pageSlug'));
    }
    public function internalTraderSettingsUpdate(Request $request)
    {

        $settings = $request->except(['_token', '_method']);

        // dd($request->all());
        foreach ($settings as $key => $value) {
            DB::table('trade_settings')->updateOrInsert(
                ['settings_key' => $key],
                ['settings_value' => $value]
            );
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function internalTraderSettings(Request $request)
    {
        $pageSlug = 'LiveTraderSettings';

        return view('settings.internaltrader', compact('pageSlug'));
    }

    public function liveTraderSettingsUpdate(Request $request)
    {

        $settings = $request->except(['_token', '_method']);

        foreach ($settings as $key => $value) {
            DB::table('user_meta')->updateOrInsert(
                [
                    'meta_key' => $key,
                    'user_id' => auth()->user()->id,
                ],
                ['meta_value' => $value]
            );
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
