@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center p-4">
        <div class="col-md-12">
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
                <div class="col-md-12">
                    <a href="{{ route('channels.index') }}" class="btn btn-secondary mb-3">Volver a la lista</a>
                </div>
            </div>
            
            <form method="POST" action="{{ route('channels.store') }}" class="row">
                @include('channels._form', ['editing' => false])
            </form>
        </div>
    </div>
</div>
@endsection
