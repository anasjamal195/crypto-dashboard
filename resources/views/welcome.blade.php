@extends('layouts.app')

@section('content')
    <div class="header py-7 py-lg-8">
        <div class="container">
            <div class="header-body text-center mb-7">
                <div class="row justify-content-center">
                    <div class="col-lg-5 col-md-6">
                        <h1 class="text-white">{{ __('Welcome to Our Binance Auto Trader and Coin Analytics System!') }}</h1>
                        <p class="text-lead text-light">
                            {{ __('Unlock the power of automated cryptocurrency trading with our advanced analytics and trading algorithms. Designed to help you make more informed decisions, our system analyzes market trends and executes trades on your behalf, ensuring you never miss a profitable opportunity.') }}
                        </p>
                        <p class="text-lead text-light text-left">
                            {{ __('Here are some features you can look forward to:') }}
                        </p>
                        <ul class="text-lead text-light text-left">
                            <li>{{ __('Real-time market data analysis') }}</li>
                            <li>{{ __('Automated trading strategies that adjust based on market conditions') }}</li>
                            <li>{{ __('Detailed analytics dashboard with insights into your trades and market trends') }}</li>
                            <li>{{ __('Secure API integration with Binance for reliable and fast execution of trades') }}</li>
                            <li>{{ __('Customizable settings to align with your trading preferences') }}</li>
                        </ul> 
                        <p class="text-lead text-light text-left">
                            {{ __('Get started today and take the first step towards smarter, automated trading!') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
