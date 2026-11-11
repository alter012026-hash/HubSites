@extends('layouts.app')

@section('title', 'Rascunhos')

@section('content')
<div class="page-head">
    <div>
        <h1>Rascunhos</h1>
        <div class="muted">Envie para o WordPress somente depois da revisão; o envio sempre cria ou recupera um rascunho.</div>
    </div>
    <a class="btn" href="{{ route('articles.create') }}">Novo rascunho</a>
</div>
<section class="card">
    @if ($articles->isEmpty())
        <p>Nenhum rascunho criado.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Rascunho</th><th>Site</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody>
                @foreach ($articles as $article)
                    <tr>
                        <td><strong>{{ $article->title }}</strong>@if ($article->last_error)<div class="field-error">{{ $article->last_error }}</div>@endif</td>
                        <td>{{ $article->site->name }}</td>
                        <td>
                            <span class="status {{ $article->status }}">{{ match($article->status) { 'local_draft' => 'Local', 'queued' => 'Na fila', 'sending' => 'Enviando', 'sent' => 'Rascunho no WordPress', 'send_failed' => 'Falha', default => $article->status } }}</span>
                            @if ($article->remote_post_id)<div class="muted">ID remoto: {{ $article->remote_post_id }}</div>@endif
                        </td>
                        <td>
                            @if (in_array($article->status, ['local_draft', 'send_failed'], true) && ! $article->remote_post_id)
                                <form method="post" action="{{ route('articles.send', $article->id) }}" onsubmit="return confirm('Enviar este conteúdo ao WordPress como rascunho, sem publicar?');">
                                    @csrf<button class="btn secondary" type="submit">Enviar como rascunho</button>
                                </form>
                            @elseif ($article->remote_url)
                                <a href="{{ $article->remote_url }}" target="_blank" rel="noopener noreferrer">Abrir rascunho no WordPress</a>
                            @else
                                <span class="muted">Aguardando processamento</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $articles->links() }}</div>
    @endif
</section>
@endsection
