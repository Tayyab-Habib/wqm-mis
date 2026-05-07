<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\DeleteClientRequest;
use App\Http\Requests\Client\ShowClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Requests\Client\ViewClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewClientRequest $request)
    {
        $clients = Client::query()
            ->select(['id', 'name'])
            ->whereHas('waterSamples', fn($query) => $query->where('created_by', '=', auth()->id()))
            ->get();

        if (0 === $clients->count()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching clients',
            'data' => $clients
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreClientRequest $request
     * @return JsonResponse
     */
    public function store(StoreClientRequest $request)
    {
        $client = Client::query()
            ->firstOrCreate($request->validated());

        return response()->json([
            'message' => 'Success creating client',
            'data' => $client,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowClientRequest $request
     * @param Client $client
     * @return JsonResponse
     */
    public function show(ShowClientRequest $request, Client $client)
    {
        return response()->json([
            'message' => 'Success fetching client',
            'data' => $client
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateClientRequest $request
     * @param Client $client
     * @return JsonResponse
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        if ($client->wasChanged()) {
            return response()->json([
                'message' => 'Success updating client',
                'data' => $client
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Error updating client'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteClientRequest $request
     * @param Client $client
     * @return JsonResponse
     */
    public function destroy(DeleteClientRequest $request, Client $client)
    {
        $client->delete();

        return response()->json([
            'message' => 'Success deleting client',
            'data' => $client
        ], SymfonyResponse::HTTP_OK);
    }
}
