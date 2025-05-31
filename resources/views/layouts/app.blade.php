<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome-free-5.15.4/css/solid.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome-free-5.15.4/css/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/coreui/coreui.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/simplebar/simplebar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/sweetalert-7.0.5/sweetalert2.min.css') }}">
    <link href="{{ asset('assets/vendor/datatables-1.13.1/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/select2-4.1.0/css/select2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/select2-4.1.0/css/select2-bootstrap-5-theme.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/tempus-dominus/css/tempus-dominus.min.css') }}" rel="stylesheet">
    @stack('css')

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.6.1/tinymce.min.js" integrity="sha512-bib7srucEhHYYWglYvGY+EQb0JAAW0qSOXpkPTMgCgW8eLtswHA/K4TKyD4+FiXcRHcy8z7boYxk0HTACCTFMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- UAParser.js para parsear User-Agent en el cliente -->
    <script src="https://cdn.jsdelivr.net/npm/ua-parser-js@1.0.35/dist/ua-parser.min.js"></script>
    
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">

                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('lists.edit') }}">{{ __('Listas') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('tokens.edit') }}">{{ __('Token') }}</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>

    <!-- JS -->
    <script src="{{ asset('assets/vendor/jquery-3.6.3/jquery-3.6.3.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/moment-2.29.4/moment.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/coreui/coreui.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/jquery.form/jquery.form.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/sweetalert-7.0.5/sweetalert2.min.js') }}"></script>
    <script src="{{ asset('assets/js/lib.ajaxform.js') }}"></script>
    <script src="{{ asset('assets/js/layout.base.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/datatables-1.13.1/js/jquery.dataTables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/datatables-1.13.1/js/dataTables.bootstrap5.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/select2-4.1.0/js/select2.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/tempus-dominus/js/popper.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/tempus-dominus/js/tempus-dominus.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/full-calendar-6.1.9/index.global.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
