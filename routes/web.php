<?php

use App\Http\Controllers\DynamicTradeController;
use App\Http\Controllers\TradeHandlerController;
use App\Http\Controllers\UserController;
use App\Services\BinanceApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


Auth::routes();


Route::get('/', function () {
	if (auth()->user())
		return view('welcome', ['pageSlug' => 'Dashboard']);
	else
		return view('auth.login', ['pageSlug' => 'Login']);
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');


Route::get('/home', 'App\Http\Controllers\HomeController@index')->name('home')->middleware('auth');
Route::get('/coin-report/{market}', 'App\Http\Controllers\BinanceController@getCoinReport')->name('coinReport')->middleware('auth');
Route::get('/coin-report-details/{market}', 'App\Http\Controllers\BinanceController@getCoinReportDetails')->name('coinReportDetails')->middleware('auth');
Route::get('/market-trends/{market}', 'App\Http\Controllers\BinanceController@showTrends')->name('marketTrends')->middleware('auth');
Route::get('/candle-averages/{market}', 'App\Http\Controllers\BinanceController@showAverages')->name('candle.averages')->middleware('auth');
Route::get('/internal-trader-settings', 'App\Http\Controllers\SettingsController@internalTraderSettings')->name('internal.trader.settings')->middleware('auth');
Route::put('/internal-trader-settings-update', 'App\Http\Controllers\SettingsController@internalTraderSettingsUpdate')->name('internal.trader.settings.update')->middleware('auth');
Route::get('/live-trader-settings', 'App\Http\Controllers\SettingsController@liveTraderSettings')->name('live.trader.settings')->middleware('auth');
Route::put('/live-trader-settings-update', 'App\Http\Controllers\SettingsController@liveTraderSettingsUpdate')->name('live.trader.settings.update')->middleware('auth');

Route::get('/live-trades-results/{market}', 'App\Http\Controllers\BinanceController@liveTradeResults')->name('live.trades.result')->middleware('auth');
Route::get('/live-trades-details/{interval}/{market}/{symbol}', 'App\Http\Controllers\BinanceController@liveTradeDetails')->name('live.trades.details')->middleware('auth');


Route::get('/dynamic-trades-results/{market}', 'App\Http\Controllers\DynamicTradeController@dynamicTradeResults')->name('dynamic.trades.result')->middleware('auth');
Route::get('/get-available-balance', 'App\Http\Controllers\BinanceController@getAvailableBalance')->name('get.available.balance')->middleware('auth');
Route::get('/get-current-price', function (Request $request) {
	return BinanceApiService::getCurrentPrice($request->symbol, $request->market);
})->name('get.current.price')->middleware('auth');

Route::resource('trade-handler', TradeHandlerController::class)->middleware('auth');
Route::resource('dynamic-trading', DynamicTradeController::class)->middleware('auth');
Route::post('/user/toggle-auto-update', [UserController::class, 'toggleAutoUpdate'])->name('user.toggle-auto-update')->middleware('auth');

// Process Handler Routes
Route::get('/process-handler', 'App\Http\Controllers\ProcessController@index')->name('process-handler.index')->middleware('auth');
Route::get('/process-handler/restart/{process_name}', 'App\Http\Controllers\ProcessController@restart')->name('process-handler.restart')->middleware('auth');
Route::get('/process-handler/stop/{process_name}', 'App\Http\Controllers\ProcessController@stop')->name('process-handler.stop')->middleware('auth');
Route::get('/process-handler/action/{action}', 'App\Http\Controllers\ProcessController@performAction')->name('process-handler.action')->middleware('auth');

// Critical process Urls
Route::get('/process-handler/action/plesk-git/{apiKey}/{action}', 'App\Http\Controllers\ProcessController@performActionOnPleskGit')->name('process-handler.action.plesk-git');


Route::group(['middleware' => 'auth'], function () {
	Route::get('icons', ['as' => 'pages.icons', 'uses' => 'App\Http\Controllers\PageController@icons']);
	Route::get('maps', ['as' => 'pages.maps', 'uses' => 'App\Http\Controllers\PageController@maps']);
	Route::get('notifications', ['as' => 'pages.notifications', 'uses' => 'App\Http\Controllers\PageController@notifications']);
	Route::get('rtl', ['as' => 'pages.rtl', 'uses' => 'App\Http\Controllers\PageController@rtl']);
	Route::get('tables', ['as' => 'pages.tables', 'uses' => 'App\Http\Controllers\PageController@tables']);
	Route::get('typography', ['as' => 'pages.typography', 'uses' => 'App\Http\Controllers\PageController@typography']);
	Route::get('upgrade', ['as' => 'pages.upgrade', 'uses' => 'App\Http\Controllers\PageController@upgrade']);
});

Route::group(['middleware' => 'auth'], function () {
	Route::resource('user', 'App\Http\Controllers\UserController', ['except' => ['show']]);
	Route::get('profile', ['as' => 'profile.edit', 'uses' => 'App\Http\Controllers\ProfileController@edit']);
	Route::put('profile', ['as' => 'profile.update', 'uses' => 'App\Http\Controllers\ProfileController@update']);
	Route::put('profile/password', ['as' => 'profile.password', 'uses' => 'App\Http\Controllers\ProfileController@password']);
});
