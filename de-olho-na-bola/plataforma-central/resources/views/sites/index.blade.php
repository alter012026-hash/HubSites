@extends('layouts.app')

@section('title', 'Sites conectados')

@section('content')
<div class="page-head">
    <div>
        <h1>Sites conectados</h1>
        <div class="muted">Cada instalação WordPress tem conexão e credencial próprias. Multisite é opcional.</div>
    </div>
    <a class="btn" href="{{ route('sites.create') }}">Adicionar site</a>
</div>
<section class="card">
    @if ($sites->isEmpty())
        <p>Nenhum site cadastrado. Adicione uma instalação WordPress para começar.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Site</th><th>Conexão</th><th>Conteúdo</th><th>Ações</th></tr></thead>
                <tbody>
                @foreach ($sites as $site)
                    <tr>
                        <td>
                            <strong>{{ $site->name }}</strong><br>
                            <a href="{{ $site->base_url }}" target="_blank" rel="noopener noreferrer">{{ $site->base_url }}</a>
                            @if ($site->wp_version)<div class="muted">WordPress {{ $site->wp_version }}</div>@endif
                        </td>
                        <td>
                            <span class="status {{ $site->connection_status }}">{{ match($site->connection_status) { 'connected' => 'Conectado', 'pending' => 'Testando', 'paused' => 'Pausado', 'disconnected' => 'Desconectado', default => 'Falha' } }}</span>
                            @if ($site->connection_message)<div class="muted" style="margin-top:6px">{{ $site->connection_message }}</div>@endif
                            @if ($site->last_checked_at)<div class="muted">Teste: {{ $site->last_checked_at->format('d/m/Y H:i') }}</div>@endif
                            @if ($site->capabilities)
                                <div class="muted">Posts: {{ $site->capabilities['create_posts'] ? 'sim' : 'não' }} · Mídia: {{ $site->capabilities['upload_files'] ? 'sim' : 'não' }} · Categorias: {{ $site->capabilities['assign_categories'] ? 'sim' : 'não' }}</div>
                            @endif
                        </td>
                        <td>{{ $site->articles_count }} rascunho(s)</td>
                        <td class="actions">
                            <div class="stack">
                                <a href="{{ route('sites.edit', $site->id) }}">Editar / reautorizar</a>
                                @if ($site->connection_status === 'paused')
                                    <form class="inline" method="post" action="{{ route('sites.resume', $site->id) }}">@csrf<button class="btn secondary" type="submit">Revalidar</button></form>
                                @elseif ($site->connection_status === 'disconnected')
                                    <span class="muted">Edite para reautorizar</span>
                                @else
                                    <form class="inline" method="post" action="{{ route('sites.test', $site->id) }}">@csrf<button class="btn secondary" type="submit">Testar conexão</button></form>
                                    <form class="inline" method="post" action="{{ route('sites.pause', $site->id) }}">@csrf<button class="btn secondary" type="submit">Pausar</button></form>
                                @endif
                                @if ($site->connection_status !== 'disconnected')
                                    <form class="inline" method="post" action="{{ route('sites.disconnect', $site->id) }}" onsubmit="return confirm('Remover a credencial? O histórico será mantido.');">
                                        @csrf @method('DELETE')<button class="btn danger" type="submit">Remover credencial</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $sites->links() }}</div>
    @endif
</section>
@endsection
