<?php

namespace App\Http\Controllers;

use App\Jobs\SendArticleToWordPress;
use App\Models\Article;
use App\Models\AuditEvent;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();

        return view('articles.index', [
            'articles' => $organization->articles()->with('site')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('articles.create', [
            'sites' => $this->organization()->sites()
                ->where('connection_status', 'connected')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where('organization_id', $organization->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:100000'],
        ]);
        $site = $organization->sites()
            ->whereKey($data['site_id'])
            ->where('connection_status', 'connected')
            ->firstOrFail();

        DB::transaction(function () use ($organization, $site, $data): void {
            $article = $organization->articles()->create([
                'uuid' => (string) Str::uuid(),
                'site_id' => $site->id,
                'created_by' => Auth::id(),
                'title' => $data['title'],
                'content' => $data['content'],
                'status' => 'local_draft',
            ]);
            $article->versions()->create([
                'version' => 1,
                'title' => $article->title,
                'content' => $article->content,
                'created_by' => Auth::id(),
                'reason' => 'Rascunho inicial',
            ]);
            AuditEvent::query()->create([
                'organization_id' => $organization->id,
                'actor_id' => Auth::id(),
                'action' => 'article.created',
                'subject_type' => Article::class,
                'subject_id' => $article->id,
                'result' => 'success',
                'message' => 'Rascunho criado na plataforma central.',
            ]);
        });

        return redirect()->route('articles.index')->with('status', 'Rascunho criado apenas na plataforma central.');
    }

    public function send(int $article): RedirectResponse
    {
        $record = $this->organization()->articles()->findOrFail($article);
        abort_if($record->remote_post_id !== null, 409, 'Este rascunho já foi enviado ao WordPress.');
        abort_unless(in_array($record->status, ['local_draft', 'send_failed'], true), 422, 'O rascunho já está na fila ou em processamento.');

        $record->update(['status' => 'queued', 'last_error' => null]);
        AuditEvent::query()->create([
            'organization_id' => $record->organization_id,
            'actor_id' => Auth::id(),
            'action' => 'article.send_queued',
            'subject_type' => Article::class,
            'subject_id' => $record->id,
            'result' => 'success',
            'message' => 'Envio de rascunho solicitado; o WordPress receberá status draft.',
        ]);
        SendArticleToWordPress::dispatch($record->id, Auth::id());

        return redirect()->route('articles.index')->with('status', 'Envio do rascunho enfileirado. Ele não será publicado publicamente.');
    }

    private function organization(): Organization
    {
        $organization = Auth::user()->currentOrganization();
        abort_unless($organization, 403, 'Sua conta não possui um espaço de trabalho ativo.');

        return $organization;
    }
}
