@extends('layouts.app')

@section('title', 'Novo rascunho')

@section('content')
<div class="page-head">
    <div>
        <h1>Novo rascunho</h1>
        <div class="muted">O conteúdo fica na plataforma até você solicitar o envio.</div>
    </div>
    <a class="btn secondary" href="{{ route('articles.index') }}">Voltar aos rascunhos</a>
</div>
<section class="card">
    @if ($sites->isEmpty())
        <p>Conecte um site e confirme as permissões antes de criar rascunhos.</p>
        <a class="btn" href="{{ route('sites.create') }}">Conectar site</a>
    @else
        <form method="post" action="{{ route('articles.store') }}">
            @csrf
            <label for="site_id">Site de destino</label>
            <select id="site_id" name="site_id" required>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->name }} — {{ $site->base_url }}</option>
                @endforeach
            </select>
            @error('site_id')<div class="field-error">{{ $message }}</div>@enderror

            <label for="title">Título</label>
            <input id="title" name="title" value="{{ old('title') }}" maxlength="255" required>
            @error('title')<div class="field-error">{{ $message }}</div>@enderror

            <label for="content">Conteúdo</label>
            <textarea id="content" name="content" required>{{ old('content') }}</textarea>
            <div class="muted">O conector sanitiza o HTML permitido no WordPress. Revise fatos, fontes, direitos e conteúdo antes de enviar.</div>
            @error('content')<div class="field-error">{{ $message }}</div>@enderror

            <div class="form-actions"><button class="btn" type="submit">Salvar rascunho local</button></div>
        </form>
    @endif
</section>
@endsection
