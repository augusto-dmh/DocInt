<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('clients/Index', [
            'clients' => Client::query()
                ->withCount('matters')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/Create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        Client::query()->create($request->validated());

        return to_route('clients.index');
    }

    public function show(Client $client): Response
    {
        abort_unless($client->tenant_id === tenant()?->id, 404);

        return Inertia::render('clients/Show', [
            'client' => $client->load([
                'matters' => fn ($query) => $query
                    ->withCount('documents')
                    ->latest(),
            ]),
        ]);
    }

    public function edit(Client $client): Response
    {
        abort_unless($client->tenant_id === tenant()?->id, 404);

        return Inertia::render('clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        abort_unless($client->tenant_id === tenant()?->id, 404);

        $client->update($request->validated());

        return to_route('clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        abort_unless($client->tenant_id === tenant()?->id, 404);

        $client->delete();

        return to_route('clients.index');
    }
}
