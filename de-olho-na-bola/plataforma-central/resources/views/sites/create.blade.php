@extends('layouts.app')

@section('title', $site ? 'Editar conexão' : 'Conectar site')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $site ? 'Editar conexão' : 'Conectar um WordPress' }}</h1>
        <div class="muted">Use um usuário técnico com permissões mínimas e uma senha de aplicação do WordPress.</div>
    </div>
    <a class="btn secondary" href="{{ route('sites.index') }}">Voltar aos sites</a>
</div>
<section class="card">
    <form method="post" action="{{ $site ? route('sites.update', $site->id) : route('sites.store') }}">
        @csrf
        @if ($site) @method('PUT') @endif
        <label for="name">Nome para identificação</label>
        <input id="name" name="name" value="{{ old('name', $site?->name) }}" maxlength="160" required>
        @error('name')<div class="field-error">{{ $message }}</div>@enderror

        <label for="base_url">URL base do WordPress</label>
        <input id="base_url" name="base_url" type="url" inputmode="url" placeholder="https://meusite.com.br" value="{{ old('base_url', $site?->base_url) }}" required>
        <div class="muted">Em desenvolvimento local, HTTP pode ser usado apenas com APP_ENV=local. Em produção, HTTPS é obrigatório.</div>
        @error('base_url')<div class="field-error">{{ $message }}</div>@enderror

        <label for="wp_username">Usuário técnico WordPress</label>
        <input id="wp_username" name="wp_username" autocomplete="username" value="{{ old('wp_username', $site?->wp_username) }}" maxlength="100" required>
        @error('wp_username')<div class="field-error">{{ $message }}</div>@enderror

        <label for="application_password">Senha de aplicação</label>
        <input id="application_password" name="application_password" type="password" autocomplete="new-password" {{ $site ? '' : 'required' }}>
        @if ($site)<div class="muted">A credencial atual nunca é mostrada. Deixe em branco para mantê-la; informe outra para reautorizar.</div>@endif
        @error('application_password')<div class="field-error">{{ $message }}</div>@enderror

        <div class="notice" style="margin-top:18px">As credenciais são armazenadas cifradas com a chave APP_KEY da plataforma. Em produção, proteja e faça backup dessa chave separadamente do banco de dados.</div>
        <div class="form-actions"><button class="btn" type="submit">{{ $site ? 'Salvar conexão' : 'Conectar e testar' }}</button></div>
    </form>
</section>
@endsection
