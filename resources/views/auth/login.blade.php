@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card-group d-block d-md-flex row">
                <div class="card col-md-7 p-4 mb-0">
                    <div class="card-body pt-0">
                        <img class="mx-auto d-block mb-2" style="max-width: 70px;" src="{{ asset('assets/img/logo.png') }}" />
                        <hr />

                        <h4>IPTV Manager</h4>
                        <p class="text-medium-emphasis">Inicia sesión en tu cuenta</p>

                        <form method="POST" action="{{ route('login') }}" data-ajax="false">
                            @csrf
                            <div class="input-group mb-3">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope text-secondary"></i>
                                </span>
                                <input id="email" name="email" class="form-control @error('email') is-invalid @enderror" type="email"
                                    placeholder="Email" autofocus>
                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="input-group mb-4">
                                <span class="input-group-text">
                                    <i class="fas fa-key text-secondary"></i>
                                </span>
                                <input id="password" name="password" class="form-control @error('password') is-invalid @enderror" type="password"
                                    placeholder="Password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-12 d-flex justify-content-between">
                                    <button class="btn btn-outline-primary px-4">Iniciar sesión</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
