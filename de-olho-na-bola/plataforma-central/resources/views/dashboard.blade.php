@extends('layouts.app')

@section('title', 'Visão geral')

@section('content')
<div class="page-head">
    <div>
        <h1>Visão geral</h1>
        <div class="muted">Acompanhe conexões e rascunhos do seu espaço de trabalho.</div>
    </div>
    <a class="btn" href="{{ route('sites.create') }}">Conectar site</a>
</div>
<section class="cards" aria-label="Resumo">
    <div class="card"><div class="muted">Sites cadastrados</div><div class="metric">{{ $siteCount }}</div></div>
    <div class="card"><div class="muted">Conexões ativas</div><div class="metric">{{ $connectedCount }}</div></div>
    <div class="card"><div class="muted">Rascunhos pendentes</div><div class="metric">{{ $pendingArticleCount }}</div></div>
</section>
<section class="card">
    <h2>Atividade recente</h2>
    @if ($recentEvents->isEmpty())
        <p class="muted">As ações de conexão e envio de rascunhos aparecerão aqui.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Ação</th><th>Resultado</th><th>Quando</th></tr></thead>
                <tbody>
                    @foreach ($recentEvents as $event)
                        <tr>
                            <td>{{ $event->message }}</td>
                            <td>{{ $event->result === 'success' ? 'Concluído' : 'Falha' }}</td>
                            <td>{{ $event->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
<section class="card">
    <h2>Fluxo seguro</h2>
    <p>O painel envia conteúdo ao WordPress com status de <strong>rascunho</strong>. A publicação pública continua dependendo de revisão no site de destino. Credenciais são cifradas e não voltam a ser exibidas após o cadastro.</p>
</section>
@endsection
