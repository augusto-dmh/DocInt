<?php

namespace App\Http\Controllers;

use App\Http\Requests\Matters\StoreMatterRequest;
use App\Http\Requests\Matters\UpdateMatterRequest;
use App\Models\Client;
use App\Models\Matter;
use App\Support\DocumentExperienceGuardrails;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MatterController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Matter::class);

        return Inertia::render('matters/Index', [
            'matters' => Matter::query()
                ->with('client')
                ->withCount('documents')
                ->latest()
                ->paginate(15),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Matter::class);

        return Inertia::render('matters/Create', [
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreMatterRequest $request): RedirectResponse
    {
        $this->authorize('create', Matter::class);

        Matter::query()->create($request->validated());

        return to_route('matters.index');
    }

    public function show(Matter $matter): Response
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);
        $this->authorize('view', $matter);

        return Inertia::render('matters/Show', [
            'matter' => $matter->load([
                'client',
                'documents' => fn ($query) => $query
                    ->with('uploader')
                    ->latest(),
            ]),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function edit(Matter $matter): Response
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);
        $this->authorize('update', $matter);

        return Inertia::render('matters/Edit', [
            'matter' => $matter,
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdateMatterRequest $request, Matter $matter): RedirectResponse
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);
        $this->authorize('update', $matter);

        $matter->update($request->validated());

        return to_route('matters.show', $matter);
    }

    public function destroy(Matter $matter): RedirectResponse
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);
        $this->authorize('delete', $matter);

        $matter->delete();

        return to_route('matters.index');
    }
}
