<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Black Dashboard') }}</title>
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('black') }}/img/apple-icon.png">
    <link rel="icon" type="image/png" href="{{ asset('black') }}/img/favicon.png">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,600,700,800" rel="stylesheet" />
    <link href="https://use.fontawesome.com/releases/v5.0.6/css/all.css" rel="stylesheet">
    <!-- Icons -->
    <link href="{{ asset('black') }}/css/nucleo-icons.css" rel="stylesheet" />
    <!-- CSS -->
    <link href="{{ asset('black') }}/css/black-dashboard.css" rel="stylesheet" />
    <link href="{{ asset('black') }}/css/theme.css" rel="stylesheet" />

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation"></script>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.css">

    {{-- Swal Popup --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@10">

    <!-- Select 2  -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">




</head>
<style>
    /* Custom CSS for DataTables in Dark Mode */
    .dataTables_wrapper {
        color: rgba(255, 255, 255, 0.7);

    }

    table.dataTable thead th,
    table.dataTable thead td {

        color: rgba(255, 255, 255, 0.7);
    }


    table.dataTable tbody th,
    table.dataTable tbody td {
        border-bottom: 1px solid #32383e;
    }

    table.dataTable tbody tr:nth-child(even) {
        background-color: #2a2e35;
    }

    table.dataTable tbody tr:hover {
        background-color: #32383e;
    }

    .dataTables_filter input,
    .dataTables_length select {

        color: rgba(255, 255, 255, 0.7);
    }

    .dataTables_paginate .paginate_button {
        color: rgba(255, 255, 255, 0.7) !important;
        border: 1px solid transparent;
        background-color: transparent;
    }

    .dataTables_paginate .paginate_button.current,
    .dataTables_paginate .paginate_button.current:hover {
        color: rgba(255, 255, 255, 0.7) !important;
        border-color: #5e72e4 !important;
        background-color: #5e72e4 !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background-color: #5e72e4;
        color: rgba(255, 255, 255, 0.7) !important;
    }

    .dataTables_info,
    .paginate_button {
        color: rgba(255, 255, 255, 0.7) !important;
    }

    .swal2-toast-popup {
        box-shadow: none !important;
        /* Remove drop shadows */
    }

    .swal2-toast-title {
        color: white !important;
        /* Ensure text color is white */
    }

    select {
        background-color: #ffffff;
        /* White background */
        color: #333333;
        /* Dark text for readability */
        border: 1px solid #cccccc;
        /* Light border to define the edges */
    }

    .action-btn {
        display: flex;
        align-items: center;
        justify-content: space-around;
        gap: 20px;
    }
</style>
<style>
    .select2-container--default .select2-selection--single {
        background-color: #2a2e35;
        /* Dark background */
        border: 1px solid #444;
        /* Darker border for contrast */
        color: #fff;
        /* White text */
    }

    .select2-container--bootstrap .select2-selection {
        border-color: #2b3553;
        border-radius: 0.4285rem;
        font-size: 0.75rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        background-color: #333;
        /* Ensure this doesn't blend into the arrow itself */
        height: calc(2.25rem + 2px);
        /* Ensure height matches the parent */
        display: block;
        /* Ensure it is not set to 'none' */
        position: absolute;
        /* Ensure it's positioned correctly */
        top: 0;
        right: 1px;
        /* Adjust as necessary */
        width: 20px;
        /* Ensure width is enough for the arrow to show */
        line-height: 2.25rem;
        /* Align arrow vertically */
    }


    .select2-dropdown {
        background-color: #2a2e35;
        color: #fff;
        border-color: #444;
    }

    .select2-results__option {
        color: #fff;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: #4a536e;
        /* Lighter dark background for highlighted item */
    }

    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border: solid black 1px;
        outline: none;
    }

    .select2-container--default .select2-search--dropdown .select2-search__field {
        color: #fff;
        background: #333;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #fff;
    }

    .select2-container--bootstrap .select2-selection {
        /* background-color: #333; */
        /* Dark background for the input */
        border: 1px solid #2b3553;
        /* Darker border color for visibility */
        color: #fff;
        /* White text color */
        border-radius: 0.25rem;
        /* Keeping the border-radius from Bootstrap */
    }

    .select2-container--bootstrap .select2-selection--single {
        height: calc(2.25rem + 2px);
        /* Standard height adjustment */
    }

    .select2-container--bootstrap .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        /* Line height for vertical alignment */
        color: #fff;
        /* Ensure the text color is white for visibility */
    }

    .select2-container--bootstrap .select2-dropdown {
        /* background-color: #2b355; */
        /* Dark background for the dropdown */
        border-color: #2b3553;
        /* Dark border for the dropdown */
    }

    .select2-container--bootstrap .select2-results__option {
        color: #ccc;
        /* Lighter text color for items in the dropdown */
    }

    .select2-container--bootstrap .select2-results__option--highlighted[aria-selected] {
        background-color: #565656;
        /* Darker background for highlighted item */
        color: white;
        /* White text color for highlighted item */
    }

    .select2-container--bootstrap .select2-selection--single .select2-selection__arrow {
        color: #ccc;
        /* Arrow color */
    }

    .select2-container--bootstrap .select2-search--dropdown .select2-search__field {
        color: #fff;
        /* Color for the search input text */
        background-color: #333;
        /* Background for the search input */
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        padding-top: 10px;
    }

    .flatpickr-input {
        color: white !important;
    }
</style>

<body class="{{ $class ?? '' }}">
    @auth()
        <div class="wrapper">
            @include('layouts.navbars.sidebar')
            <div class="main-panel" data="blue">
                @include('layouts.navbars.navbar')

                <div class="content">
                    @yield('content')
                </div>

                @include('layouts.footer')
            </div>
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    @else
        @include('layouts.navbars.navbar')
        <div class="wrapper wrapper-full-page">
            <div class="full-page {{ $contentClass ?? '' }}">
                <div class="content">
                    <div class="container">
                        @yield('content')
                    </div>
                </div>
                @include('layouts.footer')
            </div>
        </div>
    @endauth
    <div class="fixed-plugin">
        <div class="dropdown show-dropdown">
            <a href="#" data-toggle="dropdown">
                <i class="fa fa-cog fa-2x"> </i>
            </a>
            <ul class="dropdown-menu">
                <li class="header-title"> Sidebar Background</li>
                <li class="adjustments-line">
                    <a href="javascript:void(0)" class="switch-trigger background-color">
                        <div class="badge-colors text-center">
                            <span class="badge filter badge-primary active" data-color="primary"></span>
                            <span class="badge filter badge-info" data-color="blue"></span>
                            <span class="badge filter badge-success" data-color="green"></span>
                        </div>
                        <div class="clearfix"></div>
                    </a>
                </li>

            </ul>
        </div>
    </div>
    <script src="{{ asset('black') }}/js/core/jquery.min.js"></script>
    <script src="{{ asset('black') }}/js/core/popper.min.js"></script>
    <script src="{{ asset('black') }}/js/core/bootstrap.min.js"></script>
    <script src="{{ asset('black') }}/js/plugins/perfect-scrollbar.jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!--  Google Maps Plugin    -->
    <!-- Place this tag in your head or just before your close body tag. -->
    {{-- <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_KEY_HERE"></script> --}}
    <!-- Chart JS -->
    {{-- <script src="{{ asset('black') }}/js/plugins/chartjs.min.js"></script> --}}
    <!--  Notifications Plugin    -->
    <script src="{{ asset('black') }}/js/plugins/bootstrap-notify.js"></script>

    <script src="{{ asset('black') }}/js/black-dashboard.js"></script>

    <script src="{{ asset('black') }}/js/theme.js"></script>


    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.js"></script>

    {{-- Select 2 --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    @stack('js')
    <script>
        @if (session('success'))
            displayToast('success', '{{ session('success') }}');
        @elseif (session('error'))
            displayToast('error', '{{ session('error') }}');
        @elseif (session('warning'))
            displayToast('warning', '{{ session('warning') }}');
        @endif

        function displayToast(type, message) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                icon: type,
                title: message,
                background: '#1d253b',
                color: '#FFFFFF',
                customClass: {
                    popup: 'swal2-toast-popup',
                    title: 'swal2-toast-title'
                }
            });
        }
    </script>


    <script>
        $(document).ready(function() {
            $().ready(function() {
                $sidebar = $('.sidebar');
                $navbar = $('.navbar');
                $main_panel = $('.main-panel');

                $full_page = $('.full-page');

                $sidebar_responsive = $('body > .navbar-collapse');
                sidebar_mini_active = true;
                white_color = false;

                window_width = $(window).width();

                fixed_plugin_open = $('.sidebar .sidebar-wrapper .nav li.active a p').html();

                $('.fixed-plugin a').click(function(event) {
                    if ($(this).hasClass('switch-trigger')) {
                        if (event.stopPropagation) {
                            event.stopPropagation();
                        } else if (window.event) {
                            window.event.cancelBubble = true;
                        }
                    }
                });

                $('.fixed-plugin .background-color span').click(function() {
                    $(this).siblings().removeClass('active');
                    $(this).addClass('active');

                    var new_color = $(this).data('color');

                    if ($sidebar.length != 0) {
                        $sidebar.attr('data', new_color);
                    }

                    if ($main_panel.length != 0) {
                        $main_panel.attr('data', new_color);
                    }

                    if ($full_page.length != 0) {
                        $full_page.attr('filter-color', new_color);
                    }

                    if ($sidebar_responsive.length != 0) {
                        $sidebar_responsive.attr('data', new_color);
                    }
                });

                $('.switch-sidebar-mini input').on("switchChange.bootstrapSwitch", function() {
                    var $btn = $(this);

                    if (sidebar_mini_active == true) {
                        $('body').removeClass('sidebar-mini');
                        sidebar_mini_active = false;
                        blackDashboard.showSidebarMessage('Sidebar mini deactivated...');
                    } else {
                        $('body').addClass('sidebar-mini');
                        sidebar_mini_active = true;
                        blackDashboard.showSidebarMessage('Sidebar mini activated...');
                    }

                    // we simulate the window Resize so the charts will get updated in realtime.
                    var simulateWindowResize = setInterval(function() {
                        window.dispatchEvent(new Event('resize'));
                    }, 180);

                    // we stop the simulation of Window Resize after the animations are completed
                    setTimeout(function() {
                        clearInterval(simulateWindowResize);
                    }, 1000);
                });

                $('.switch-change-color input').on("switchChange.bootstrapSwitch", function() {
                    var $btn = $(this);

                    if (white_color == true) {
                        $('body').addClass('change-background');
                        setTimeout(function() {
                            $('body').removeClass('change-background');
                            $('body').removeClass('white-content');
                        }, 900);
                        white_color = false;
                    } else {
                        $('body').addClass('change-background');
                        setTimeout(function() {
                            $('body').removeClass('change-background');
                            $('body').addClass('white-content');
                        }, 900);

                        white_color = true;
                    }
                });





                $('.select2').select2({
                    theme: 'bootstrap'
                });

                $('.flatpickr-input').flatpickr({
                    enableTime: true,
                    dateFormat: "Y-m-d H:i", // Format: YYYY-MM-DD HH:MM
                    time_24hr: true // Use 24-hour format
                });

                $(document).ready(function() {
                    var table = $('.dataTable').DataTable({
                        "paging": true,
                        "ordering": false,
                        "info": true,
                        "rowCallback": function(row, data) {
                            var formula = $(row).data('formula');
                            $(row).insertAfter(`
                                <tr class="formula-row">
                                    <td colspan="13" class="text-center py-2">
                                        <strong>Formula:</strong> ${formula?formula:'No formula found'}
                                    </td>
                                </tr>
                            `);
                        }
                    });
                });

            });



        });
    </script>

    @stack('js')
</body>

</html>
