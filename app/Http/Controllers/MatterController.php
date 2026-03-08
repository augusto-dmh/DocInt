<?php

namespace App\Http\Controllers;

use App\Http\Requests\Matters\StoreMatterRequest;
use App\Http\Requests\Matters\UpdateMatterRequest;
use App\Models\Client;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MatterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('matters/Index', [
            'matters' => Matter::query()
                ->with('client')
                ->withCount('documents')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('matters/Create', [
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreMatterRequest $request): RedirectResponse
    {
        Matter::query()->create($request->validated());

        return to_route('matters.index');
    }

    public function show(Matter $matter): Response
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);

        return Inertia::render('matters/Show', [
            'matter' => $matter->load([
                'client',
                'documents' => fn ($query) => $query->latest(),
            ]),
        ]);
    }

    public function edit(Matter $matter): Response
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);

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

        $matter->update($request->validated());

        return to_route('matters.show', $matter);
    }

    public function destroy(Matter $matter): RedirectResponse
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);

        $matter->delete();

        return to_route('matters.index');
    }
}
