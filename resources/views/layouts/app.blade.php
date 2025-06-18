<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('assets/img/logo.png') }}" type="image/x-icon">
    <link rel="icon" href="{{ asset('assets/img/logo.png') }}" type="image/x-icon">

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
    @yield('base.content')
    @if (Auth::user() && !str_contains(View::yieldContent('body_class'), 'iframe'))
    <div class="sidebar sidebar-dark sidebar-fixed" id="sidebar">
        <div class="sidebar-brand d-md-flex">
            <a href="{{ route('home') }}" class="d-flex align-items-center sidebar-brand-full text-decoration-none text-white mt-2">
                <h4>IPTV Manager</h4>
            </a>
        </div>
        <ul class="sidebar-nav" data-coreui="navigation" data-simplebar="">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('channel-categories.index') }}">
                    <i class="nav-icon fas fa-list"></i>
                    Categorías de Canales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('channels.index') }}">
                    <i class="nav-icon fas fa-tv"></i>
                    Canales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('accounts.index') }}">
                    <i class="nav-icon fas fa-users"></i>
                    Cuentas
                </a>
            </li>
        </ul>
    </div>
    @endif
    <div id="wrapper" class="wrapper d-flex flex-column min-vh-100 bg-light">
        @if (Auth::user() && !str_contains(View::yieldContent('body_class'), 'iframe'))
        <header id="header" class="header header-sticky mb-4">
            <div class="container-fluid">
                <button class="header-toggler px-md-0 me-md-3" type="button"
                        onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="header-nav d-md-flex">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Dashboard</a></li>
                </ul>
                <ul class="header-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link py-0" data-coreui-toggle="dropdown" href="#"
                                role="button" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-circle fa-2x me-1 align-middle"></i>
                            {{ Auth::user()->name }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pt-0 mt-2">
                            <div class="dropdown-header bg-light py-2">
                                <div class="fw-semibold">User</div>
                            </div>
                            <a class="dropdown-item mt-1" href="{{ route('logout') }}"
                                onclick="event.preventDefault();
                                                document.getElementById('logout-form').submit();">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                {{ __('Logout') }}
                            </a>

                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </li>
                </ul>
            </div>
        </header>
        @endif
        <div class="body flex-grow-1 px-4">
            @if (session()->has('message'))
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-{{ session('message')->type }}" role="alert">
                        {{ session('message')->text }}
                    </div>
                </div>
            </div>
            @endif
            <div class="row">
                <div class="col-12">
                    @yield('content')
                </div>
            </div>
        </div>
        @if (!str_contains(View::yieldContent('body_class'), 'iframe'))
        <footer class="footer" id="footer">
            <div class="mx-auto">
                <small>Created by angelmohi</small>
            </div>
        </footer>
        @endif
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
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    @stack('scripts')
</body>
</html>
