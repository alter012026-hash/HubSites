<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_connect_a_site_and_send_an_idempotent_wordpress_draft(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (str_contains($request->url(), '/wp-json/wp/v2/users/me')) {
                return Http::response(['id' => 19], 200);
            }

            if (str_contains($request->url(), '/wp-json/bec/v1/connection')) {
                return Http::response([
                    'wp_version' => '6.9',
                    'capabilities' => [
                        'create_posts' => true,
                        'upload_files' => true,
                        'assign_categories' => true,
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/wp-json/bec/v1/central-drafts')) {
                return Http::response([
                    'id' => 42,
                    'link' => 'http://127.0.0.1:8080/wp-admin/post.php?post=42&action=edit',
                    'status' => 'draft',
                ], 201);
            }

            return Http::response(['message' => 'Unexpected request'], 404);
        });

        $owner = $this->createOwner('Redação');
        $this->actingAs($owner)->post(route('sites.store'), [
            'name' => 'Site de teste',
            'base_url' => 'http://127.0.0.1:8080/wordpress/',
            'wp_username' => 'integracao',
            'application_password' => 'senha-de-aplicacao-secreta',
        ])->assertRedirect(route('sites.index'));

        $site = Site::query()->firstOrFail();
        $this->assertSame('connected', $site->connection_status);
        $this->assertSame('senha-de-aplicacao-secreta', $site->application_password);
        $this->assertNotSame(
            'senha-de-aplicacao-secreta',
            DB::table('sites')->where('id', $site->id)->value('application_password')
        );
        $this->assertSame('http://127.0.0.1:8080/wordpress', $site->base_url);
        $this->get(route('sites.index'))->assertOk()->assertDontSee('senha-de-aplicacao-secreta');

        $this->post(route('articles.store'), [
            'site_id' => $site->id,
            'title' => 'Rascunho de validação',
            'content' => '<p>Conteúdo revisado.</p>',
        ])->assertRedirect(route('articles.index'));

        $article = Article::query()->firstOrFail();
        $this->assertSame('local_draft', $article->status);

        $this->post(route('articles.send', $article->id))
            ->assertRedirect(route('articles.index'));

        $article->refresh();
        $this->assertSame('sent', $article->status);
        $this->assertSame(42, $article->remote_post_id);
        $this->assertSame('draft', $article->remote_status);
        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/wp-json/bec/v1/central-drafts')
            && $request['idempotency_key'] === $article->uuid);
        $this->assertDatabaseHas('audit_events', [
            'subject_type' => Article::class,
            'subject_id' => $article->id,
            'action' => 'article.sent_as_draft',
            'result' => 'success',
        ]);

        $this->post(route('articles.send', $article->id))->assertStatus(409);
        $draftRequests = Http::recorded()->filter(
            fn (array $record): bool => str_contains($record[0]->url(), '/wp-json/bec/v1/central-drafts')
        );
        $this->assertCount(1, $draftRequests);
    }

    public function test_users_cannot_read_or_edit_sites_from_another_organization(): void
    {
        $owner = $this->createOwner('Workspace A');
        $site = $owner->currentOrganization()->sites()->create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $owner->id,
            'name' => 'Site privado',
            'base_url' => 'https://example.com',
            'wp_username' => 'integracao',
            'application_password' => 'segredo',
            'connection_status' => 'connected',
        ]);

        $otherOwner = $this->createOwner('Workspace B');
        $this->actingAs($otherOwner)
            ->get(route('sites.edit', $site->id))
            ->assertNotFound();
    }

    public function test_anonymous_users_are_redirected_to_login_and_api_is_versioned(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Entrar');
        $this->get('/api/v1/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'api_version' => 'v1']);
    }

    public function test_authenticated_workspace_pages_render(): void
    {
        $owner = $this->createOwner('Painel');
        $organization = $owner->currentOrganization();
        $organization->sites()->create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $owner->id,
            'name' => 'Site conectado',
            'base_url' => 'https://example.com',
            'wp_username' => 'integracao',
            'application_password' => 'credencial-cifrada',
            'connection_status' => 'connected',
            'capabilities' => [
                'create_posts' => true,
                'upload_files' => false,
                'assign_categories' => true,
            ],
        ]);

        $this->actingAs($owner)
            ->get(route('sites.index'))
            ->assertOk()
            ->assertSee('Site conectado')
            ->assertSee('Mídia: não');
        $this->get(route('articles.index'))->assertOk()->assertSee('Novo rascunho');
        $this->get(route('articles.create'))->assertOk()->assertSee('Conteúdo');
    }

    private function createOwner(string $workspaceName): User
    {
        $user = User::factory()->create();
        $organization = Organization::query()->create([
            'name' => $workspaceName,
            'slug' => Str::slug($workspaceName).'-'.$user->id,
        ]);
        $organization->users()->attach($user, ['role' => 'owner', 'status' => 'active']);

        return $user;
    }
}
