@extends('layouts.app')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@800;900&display=swap');
    
    .body {
        padding: 0 !important;
    }
    
    .body > .row {
        height: 100%;
        margin: 0;
    }
    
    .body > .row > .col-12 {
        height: 100%;
        padding: 0;
    }
    
    .login-container {
        height: 100%;
        min-height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        padding-top: 80px;
    }
    
    .login-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        padding: 48px 40px;
        max-width: 420px;
        width: 100%;
    }
    
    .login-logo {
        text-align: center;
        margin-bottom: 32px;
    }
    
    .login-logo h1 {
        font-size: 48px;
        font-weight: 900;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
        letter-spacing: 3px;
        text-transform: uppercase;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        text-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        position: relative;
        display: inline-block;
    }
    
    @media (max-width: 576px) {
        .login-logo h1 {
            font-size: 32px;
            letter-spacing: 1px;
        }
        
        .login-card {
            padding: 32px 24px;
        }
        
        .login-container {
            padding: 16px;
            padding-top: 40px;
        }
    }
    
    .login-logo h1::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 2px;
    }
    
    .login-logo p {
        color: #6c757d;
        font-size: 14px;
        margin-top: 16px;
        margin-bottom: 0;
    }
    
    .minimal-input {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    
    .minimal-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .minimal-input.is-invalid {
        border-color: #dc3545;
    }
    
    .minimal-input::placeholder {
        color: #adb5bd;
    }
    
    .minimal-btn {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        color: white;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 12px;
    }
    
    .minimal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }
    
    .minimal-btn:active {
        transform: translateY(0);
    }
    
    .error-message {
        color: #dc3545;
        font-size: 13px;
        margin-top: -12px;
        margin-bottom: 12px;
        display: block;
    }
    
    .input-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #495057;
        margin-bottom: 8px;
    }
</style>

<div class="login-container">
    <div class="login-card">
        <div class="login-logo">
            <h1>CINESTRELLA</h1>
        </div>

        <form method="POST" action="{{ route('login') }}" data-ajax="false">
            @csrf
            
            <div>
                <label for="email" class="input-label">Correo electrónico</label>
                <input 
                    id="email" 
                    name="email" 
                    class="minimal-input @error('email') is-invalid @enderror" 
                    type="email"
                    placeholder="tu@correo.com" 
                    value="{{ old('email') }}"
                    autofocus
                    required>
                @error('email')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>
            
            <div>
                <label for="password" class="input-label">Contraseña</label>
                <input 
                    id="password" 
                    name="password" 
                    class="minimal-input @error('password') is-invalid @enderror" 
                    type="password"
                    placeholder="••••••••"
                    required>
                @error('password')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>
            
            <button type="submit" class="minimal-btn">
                Iniciar sesión
            </button>
        </form>
    </div>
</div>
@endsection
