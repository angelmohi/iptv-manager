@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Token Actual') }}</div>

                <div class="card-body">
                    @if (session()->has('message'))
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-{{ session('message')->type }}" role="alert">
                                {{ session('message')->text }}
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="mb-3">
                        <textarea class="form-control" id="currentToken" rows="10" readonly>{{ $cdnToken ?? 'No hay token disponible' }}</textarea>
                        <small class="form-text text-muted">{{ $expiredDate ? 'Token válido hasta: ' . $expiredDate : '' }}</small>
                    </div>

                    <form method="POST" action="{{ route('tokens.store') }}">
                        <button type="submit" class="btn btn-primary" data-loading-text="Renovando...">Renovar token</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
