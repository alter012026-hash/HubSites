<?php

namespace App\Http\Controllers;

use App\Exceptions\WordPressConnectorException;
use App\Jobs\TestWordPressSite;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\Site;
use App\Services\WordPressUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();

        return view('sites.index', [
            'sites' => $organization->sites()->withCount('articles')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('sites.create', ['site' => null]);
    }

    public function edit(int $site): View
    {
        return view('sites.create', ['site' => $this->site($site)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $organization = $this->organization();

        $site = $organization->sites()->create([
            'uuid' => (string) Str::uuid(),
            'created_by' => Auth::id(),
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'wp_username' => $data['wp_username'],
            'application_password' => $data['application_password'],
            'connection_status' => 'pending',
        ]);

        $this->audit($site, 'site.created', 'Site WordPress cadastrado.');
        TestWordPressSite::dispatch($site->id, Auth::id());

        return redirect()->route('sites.index')->with('status', 'Site cadastrado. O teste de conexão foi enviado para a fila.');
    }

    public function update(Request $request, int $site): RedirectResponse
    {
        $record = $this->site($site);
        $data = $this->validated($request, false);
        $credentialsChanged = ! empty($data['application_password']);
        $connectionDetailsChanged = $record->base_url !== $data['base_url']
            || $record->wp_username !== $data['wp_username'];
        $shouldRetest = $credentialsChanged
            || ($connectionDetailsChanged && (bool) $record->application_password);

        $record->update([
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'wp_username' => $data['wp_username'],
            ...($credentialsChanged ? ['application_password' => $data['application_password']] : []),
            ...($shouldRetest ? [
                'connection_status' => 'pending',
                'connection_message' => null,
                'capabilities' => null,
            ] : []),
        ]);

        $this->audit($record, 'site.updated', 'Configuração do site atualizada.');
        if ($shouldRetest) {
            TestWordPressSite::dispatch($record->id, Auth::id());
        }

        return redirect()->route('sites.index')->with('status', $shouldRetest
            ? 'Site atualizado. A nova credencial será testada.'
            : 'Site atualizado.');
    }

    public function test(int $site): RedirectResponse
    {
        $record = $this->site($site);
        abort_if(in_array($record->connection_status, ['paused', 'disconnected'], true), 422, 'Reative o site antes de testar a conexão.');

        $record->update([
            'connection_status' => 'pending',
            'connection_message' => 'Teste de conexão aguardando na fila.',
        ]);
        TestWordPressSite::dispatch($record->id, Auth::id());

        return redirect()->route('sites.index')->with('status', 'Teste de conexão enviado para a fila.');
    }

    public function pause(int $site): RedirectResponse
    {
        $record = $this->site($site);
        $record->update([
            'connection_status' => 'paused',
            'connection_message' => 'Conexão pausada pelo administrador.',
        ]);
        $this->audit($record, 'site.paused', 'Conexão do site pausada.');

        return redirect()->route('sites.index')->with('status', 'Conexão pausada. Os dados e a credencial foram mantidos.');
    }

    public function resume(int $site): RedirectResponse
    {
        $record = $this->site($site);
        abort_if($record->connection_status === 'disconnected' || ! $record->application_password, 422, 'Reautorize a conexão na edição do site antes de reativá-lo.');

        $record->update([
            'connection_status' => 'pending',
            'connection_message' => 'Teste de conexão aguardando na fila.',
        ]);
        TestWordPressSite::dispatch($record->id, Auth::id());
        $this->audit($record, 'site.reconnected', 'Revalidação da conexão solicitada.');

        return redirect()->route('sites.index')->with('status', 'Revalidação enviada para a fila.');
    }

    public function disconnect(int $site): RedirectResponse
    {
        $record = $this->site($site);
        $record->update([
            'application_password' => null,
            'connection_status' => 'disconnected',
            'connection_message' => 'Credencial removida pelo administrador.',
            'capabilities' => null,
        ]);
        $this->audit($record, 'site.credential_revoked', 'Credencial removida; histórico preservado.');

        return redirect()->route('sites.index')->with('status', 'Credencial removida. O histórico do site foi mantido.');
    }

    private function organization(): Organization
    {
        $organization = Auth::user()->currentOrganization();
        abort_unless($organization, 403, 'Sua conta não possui um espaço de trabalho ativo.');

        return $organization;
    }

    private function site(int $siteId): Site
    {
        return $this->organization()->sites()->findOrFail($siteId);
    }

    /**
     * @return array{name: string, base_url: string, wp_username: string, application_password?: string}
     */
    private function validated(Request $request, bool $passwordRequired): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'base_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'wp_username' => ['required', 'string', 'max:100'],
            'application_password' => [
                $passwordRequired ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
        ]);

        try {
            $data['base_url'] = app(WordPressUrlGuard::class)->normalize($data['base_url']);
        } catch (WordPressConnectorException $exception) {
            throw ValidationException::withMessages(['base_url' => $exception->getMessage()]);
        }

        return $data;
    }

    private function audit(Site $site, string $action, string $message): void
    {
        AuditEvent::query()->create([
            'organization_id' => $site->organization_id,
            'actor_id' => Auth::id(),
            'action' => $action,
            'subject_type' => Site::class,
            'subject_id' => $site->id,
            'result' => 'success',
            'message' => $message,
        ]);
    }
}
