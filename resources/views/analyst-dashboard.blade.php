@section('content')
    <div class="row">
        <div class="col-lg-12">
            <!-- Header Card -->
            <div class="card card-chart">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6 text-left">
                            <h2 class="card-title">Analyst Dashboard</h2>
                            <span class="badge badge-warning">BETA VERSION</span>
                        </div>
                        <div class="col-sm-6">
                            <div class="btn-group btn-group-toggle float-right" data-toggle="buttons">
                                <label class="btn btn-sm btn-primary btn-simple active">
                                    <span class="d-none d-sm-block d-md-block d-lg-block d-xl-block">Welcome</span>
                                    <span class="d-block d-sm-none">
                                        <i class="tim-icons icon-single-02"></i>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="text-white">Hello, {{ Auth::user()->name }}!</h4>
                            <p class="text-muted">
                                Welcome to the Analyst Dashboard. This platform is currently in <strong>BETA</strong> 
                                and provides advanced trading analysis tools for cryptocurrency markets.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="stats">
                                <i class="tim-icons icon-bell-55 text-info"></i>
                                <p class="text-muted">BETA Testing Phase</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- About Platform -->
        <div class="col-lg-6 col-md-12">
            <div class="card card-tasks">
                <div class="card-header">
                    <h6 class="title d-inline">About Our Platform</h6>
                    <div class="dropdown">
                        <button type="button" class="btn btn-link dropdown-toggle btn-icon" data-toggle="dropdown">
                            <i class="tim-icons icon-settings-gear-63"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-full-width table-responsive">
                        <div class="timeline">
                            <div class="timeline-simple-item">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="timeline-simple-item-icon">
                                            <i class="tim-icons icon-chart-bar-32 text-success"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <h5 class="text-white">Trading Analysis Suite</h5>
                                        <p class="text-muted">
                                            Professional-grade tools for analyzing Binance FUTURES symbols 
                                            with multiple timeframes and comprehensive market insights.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline-simple-item">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="timeline-simple-item-icon">
                                            <i class="tim-icons icon-coins text-info"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <h5 class="text-white">Real-time Data</h5>
                                        <p class="text-muted">
                                            Access live market data, order books, volume analysis, 
                                            and technical indicators for informed trading decisions.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="timeline-simple-item">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="timeline-simple-item-icon">
                                            <i class="tim-icons icon-laptop text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <h5 class="text-white">Beta Version</h5>
                                        <p class="text-muted">
                                            Currently in testing phase. Your feedback helps us improve 
                                            the platform and deliver better analysis tools.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analysis Tools -->
        <div class="col-lg-6 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Available Analysis Tools</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table tablesorter">
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="img-container">
                                            <i class="tim-icons icon-book-bookmark text-primary"></i>
                                        </div>
                                    </td>
                                    <td class="text-left">
                                        <p class="title">Order Book Analysis</p>
                                        <span class="text-muted">Live market depth & liquidity</span>
                                    </td>
                                    <td class="td-actions text-right">
                                        <button type="button" rel="tooltip" title="View Tool" class="btn btn-link btn-sm">
                                            <i class="tim-icons icon-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="img-container">
                                            <i class="tim-icons icon-sound-wave text-success"></i>
                                        </div>
                                    </td>
                                    <td class="text-left">
                                        <p class="title">Volume Analysis</p>
                                        <span class="text-muted">Trading volume patterns</span>
                                    </td>
                                    <td class="td-actions text-right">
                                        <button type="button" rel="tooltip" title="View Tool" class="btn btn-link btn-sm">
                                            <i class="tim-icons icon-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="img-container">
                                            <i class="tim-icons icon-chart-bar-32 text-warning"></i>
                                        </div>
                                    </td>
                                    <td class="text-left">
                                        <p class="title">Technical Indicators</p>
                                        <span class="text-muted">Bollinger Bands, RSI, MACD</span>
                                    </td>
                                    <td class="td-actions text-right">
                                        <button type="button" rel="tooltip" title="View Tool" class="btn btn-link btn-sm">
                                            <i class="tim-icons icon-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="img-container">
                                            <i class="tim-icons icon-support-17 text-info"></i>
                                        </div>
                                    </td>
                                    <td class="text-left">
                                        <p class="title">Support & Resistance</p>
                                        <span class="text-muted">Key price levels identification</span>
                                    </td>
                                    <td class="td-actions text-right">
                                        <button type="button" rel="tooltip" title="View Tool" class="btn btn-link btn-sm">
                                            <i class="tim-icons icon-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="img-container">
                                            <i class="tim-icons icon-paper text-danger"></i>
                                        </div>
                                    </td>
                                    <td class="text-left">
                                        <p class="title">Analysis Summary</p>
                                        <span class="text-muted">Consolidated market insights</span>
                                    </td>
                                    <td class="td-actions text-right">
                                        <span class="badge badge-success">NEW</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Beta Notice -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="text-primary">
                                <i class="tim-icons icon-bell-55"></i>
                                Beta Testing Information
                            </h5>
                            <p class="text-muted mb-0">
                                This platform is currently in BETA version. All analysis tools are available for testing 
                                and feedback collection. Please report any issues or suggestions to help us improve.
                            </p>
                        </div>
                      
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection