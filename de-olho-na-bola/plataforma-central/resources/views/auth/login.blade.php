@extends('layouts.app')

@section('title', 'Entrar')

@section('content')
<div class="login-wrap">
    <div class="card">
        <h1>Entrar</h1>
        <p class="muted">Acesse o painel central para administrar seus sites WordPress.</p>
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password')<div class="field-error">{{ $message }}</div>@enderror
            <div class="form-actions"><button class="btn" type="submit">Entrar</button></div>
        </form>
    </div>
</div>
@endsection
