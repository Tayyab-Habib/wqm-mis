<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folder\DeleteFolderRequest;
use App\Http\Requests\Folder\ShowFolderRequest;
use App\Http\Requests\Folder\StoreFolderRequest;
use App\Http\Requests\Folder\UpdateFolderRequest;
use App\Http\Requests\Folder\ViewFolderRequest;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FolderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param ViewFolderRequest $request
     * @return JsonResponse
     */
    public function index(ViewFolderRequest $request)
    {
        $authUser = auth()->user();
        $laboratory = $authUser->laboratoryUser;

        $folders = Folder::query()
            ->with(['laboratory:id,name'])
            ->when(!$authUser->isUnscoped(), fn(Builder $query) => $query->where('laboratory_id', '=', $laboratory->id))
            ->get();

        if ($folders->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching folders',
            'data' => $folders
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreFolderRequest $request
     * @return JsonResponse
     */
    public function store(StoreFolderRequest $request)
    {
        $folder = Folder::query()
            ->create(array_merge($request->validated(), [
                'laboratory_id' => auth()->user()->laboratoryUser->id,
            ]));

        return response()->json([
            'message' => 'Success creating folders',
            'data' => $folder
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowFolderRequest $request
     * @param Folder $folder
     * @return JsonResponse
     */
    public function show(ShowFolderRequest $request, Folder $folder)
    {
        if ($this->checkRelatedData($folder)) {
            return response()->json([
                'message' => 'You are not authorize to show'
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'message' => 'Success fetching folder',
            'data' => $folder
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateFolderRequest $request
     * @param Folder $folder
     * @return JsonResponse
     */
    public function update(UpdateFolderRequest $request, Folder $folder)
    {
        if ($this->checkRelatedData($folder)) {
            return response()->json([
                'message' => 'You are not authorize to update'
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $folder->update($request->validated());

        if ($folder->wasChanged()) {
            return response()->json([
                'message' => 'Success updating folder',
                'data' => $folder
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Error updating folder'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteFolderRequest $request
     * @param Folder $folder
     * @return JsonResponse
     */
    public function destroy(DeleteFolderRequest $request, Folder $folder)
    {
        if ($this->checkRelatedData($folder)) {
            return response()->json([
                'message' => 'You are not authorize to delete'
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        if ($folder->loadExists('diaryDispatches')->diary_dispatches_exists) {
            return response()->json([
                'message' => 'You are not authorize to deleted ' . $folder->name
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }


        $folder->delete();

        return response()->json([
            'message' => 'Success deleting '  . $folder->name,
            'data' => $folder,
        ], SymfonyResponse::HTTP_OK);
    }

    public function checkRelatedData(Folder $folder)
    {
        $authUser = auth()->user();
        $laboratory = $authUser->laboratoryUser;

        $authUser = auth()->user();
        $laboratory = $authUser->laboratoryUser;
        if ($authUser->isUnscoped()) {
            return false;
        }
        return (int)$folder->laboratory_id !== $laboratory->id;
    }
}
