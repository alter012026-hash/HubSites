<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · WordPress Automatizado</title>
    <style>
        :root{font-family:Inter,Segoe UI,Arial,sans-serif;color:#14251e;background:#f4f7f5;font-synthesis:none}
        *{box-sizing:border-box}body{margin:0}a{color:#176b49;text-decoration:none}a:hover{text-decoration:underline}
        .topbar{background:#10271d;color:#fff}.topbar-inner{max-width:1180px;margin:auto;padding:14px 22px;display:flex;align-items:center;gap:26px}
        .brand{font-weight:750;color:#fff;letter-spacing:.01em}.nav{display:flex;gap:18px;flex:1}.nav a{color:#d9e9df}.logout{margin:0}
        .logout button{border:1px solid #6c8979;background:transparent;color:#fff;padding:7px 11px;border-radius:6px;cursor:pointer}
        main{max-width:1180px;margin:30px auto;padding:0 22px}.page-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
        h1{font-size:26px;margin:0 0 6px}h2{font-size:18px}p{line-height:1.55}.muted{color:#63736b}.card{background:#fff;border:1px solid #dfe8e2;border-radius:12px;padding:20px;margin-bottom:18px}
        .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}.metric{font-size:28px;font-weight:750;margin:4px 0}
        .btn,button.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#176b49;color:#fff;border:1px solid #176b49;border-radius:7px;padding:9px 13px;font:inherit;font-weight:650;cursor:pointer}
        .btn.secondary,button.secondary{background:#fff;color:#245640;border-color:#c8d8ce}.btn.danger,button.danger{background:#fff;color:#a52e31;border-color:#e4b7b9}
        form.inline{display:inline-flex;margin:3px}input,select,textarea{width:100%;padding:10px;border:1px solid #b9c9bf;border-radius:6px;font:inherit;background:#fff}
        label{display:block;font-weight:650;margin:15px 0 6px}textarea{min-height:220px;resize:vertical}.field-error{color:#a52e31;font-size:13px}.form-actions{display:flex;gap:9px;margin-top:18px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 9px;border-bottom:1px solid #e6ece8;vertical-align:top}th{font-size:13px;color:#51645a}
        .status{font-size:12px;font-weight:700;display:inline-block;padding:4px 8px;border-radius:99px;background:#eef2ef}.status.connected,.status.sent{background:#e5f5ec;color:#176b49}.status.error,.status.send_failed{background:#fff0ee;color:#a52e31}.status.pending,.status.queued,.status.sending{background:#fff5d9;color:#795b00}.notice{padding:12px 15px;border-radius:8px;background:#e8f4ec;border:1px solid #b8d7c2;margin-bottom:18px}.notice.error{background:#fff0ee;border-color:#e8b9b7}
        .actions{min-width:210px}.stack{display:grid;gap:8px}.pagination{margin-top:16px}.login-wrap{max-width:440px;margin:7vh auto;padding:0 18px}.login-wrap .card{padding:28px}.table-wrap{overflow-x:auto}
        @media(max-width:720px){.topbar-inner{flex-wrap:wrap;gap:12px}.nav{order:3;flex-basis:100%;gap:14px}.page-head{align-items:flex-start;flex-direction:column}main{margin-top:22px;padding:0 14px}th,td{padding:10px 7px}.actions{min-width:185px}}
    </style>
</head>
<body>
    @auth
        <header class="topbar">
            <div class="topbar-inner">
                <a class="brand" href="{{ route('dashboard') }}">WordPress Automatizado</a>
                <nav class="nav" aria-label="Principal">
                    <a href="{{ route('dashboard') }}">Visão geral</a>
                    <a href="{{ route('sites.index') }}">Sites</a>
                    <a href="{{ route('articles.index') }}">Rascunhos</a>
                </nav>
                <form class="logout" method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Sair</button>
                </form>
            </div>
        </header>
    @endauth
    <main>
        @if (session('status'))
            <div class="notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice error" role="alert">
                <strong>Revise os campos indicados.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
