<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $organization = Auth::user()->currentOrganization();
        abort_unless($organization, 403, 'Sua conta não possui um espaço de trabalho ativo.');

        return view('dashboard', [
            'siteCount' => $organization->sites()->count(),
            'connectedCount' => $organization->sites()->where('connection_status', 'connected')->count(),
            'pendingArticleCount' => $organization->articles()->whereIn('status', ['local_draft', 'send_failed'])->count(),
            'recentEvents' => AuditEvent::query()
                ->where('organization_id', $organization->id)
                ->latest('created_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
