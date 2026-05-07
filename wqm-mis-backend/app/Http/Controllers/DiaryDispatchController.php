<?php

namespace App\Http\Controllers;

use App\Enums\DiaryDispatchEnum;
use App\Http\Requests\DiaryDispatch\DeleteDiaryDispatchRequest;
use App\Http\Requests\DiaryDispatch\ShowDiaryDispatchRequest;
use App\Http\Requests\DiaryDispatch\StoreDiaryDispatchRequest;
use App\Http\Requests\DiaryDispatch\UpdateDiaryDispatchRequest;
use App\Http\Requests\DiaryDispatch\ViewDiaryDispatchRequest;
use App\Models\DiaryDispatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DiaryDispatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param ViewDiaryDispatchRequest $request
     * @param DiaryDispatchEnum $enum
     * @return JsonResponse
     */
    public function index(ViewDiaryDispatchRequest $request, DiaryDispatchEnum $enum)
    {
        $authUser = auth()->user();
        $diaryDispatches = DiaryDispatch::query()
            ->where('type', '=', $enum->value)
            ->select([
                'id',
                'subject',
                'type',
                'person_name',
                'date_on_letter',
//                'receival_date',
                'designation_id',
                'folder_id',
                'laboratory_id',
                'created_at'
            ])
            ->with([
                'laboratory:id,name',
                'designation:id,name',
                'folder:id,name',
                'createdByUser:id,name',
            ])
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('laboratory_id', '=', $authUser->laboratoryUser->id))
            ->latest()
            ->get();

        if ($diaryDispatches->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching diary dispatches',
            'data' => $diaryDispatches,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreDiaryDispatchRequest $request
     * @param DiaryDispatchEnum $enum
     * @return JsonResponse
     */
    public function store(StoreDiaryDispatchRequest $request, DiaryDispatchEnum $enum)
    {
        $validatedData = $request->validated();
        $path = 'diaryDispatches';

        if (!Storage::disk('public')->path($path)) {
            Storage::disk('public')->makeDirectory($path);
        }
        $path = Storage::disk('public')->putFile($path, $request->file('attachment'));

        $diaryDispatch = DiaryDispatch::query()
            ->create(array_merge($validatedData, [
                'laboratory_id' => auth()->user()->laboratoryUser->id,
                'type' => $enum->value,
                'attachment' => $path,
            ]));

        return response()->json([
            'message' => 'Success creating ' . $diaryDispatch->type->value,
            'data' => $diaryDispatch
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowDiaryDispatchRequest $request
     * @param DiaryDispatchEnum $enum
     * @param DiaryDispatch $register
     * @return JsonResponse
     */
    public function show(ShowDiaryDispatchRequest $request, DiaryDispatchEnum $enum, DiaryDispatch $register)
    {
        if ($enum->value !== $register->type->value) {
            return response()->json([
                'message' => 'Resource not found',
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        if ($this->checkRelatedData($register)) {
            return response()->json([
                'message' => 'You are not authorize to view'
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'message' => 'Success fetching ' . $register->type->value,
            'data' => $register->load([
                'laboratory:id,name',
                'designation:id,name',
                'folder:id,name',
            ])
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateDiaryDispatchRequest $request
     * @param DiaryDispatchEnum $enum
     * @param DiaryDispatch $register
     * @return JsonResponse
     */
    public function update(UpdateDiaryDispatchRequest $request, DiaryDispatchEnum $enum, DiaryDispatch $register)
    {
        $validatedData = $request->validated();

        try {
            if ($enum->value !== $register->type->value) {
                return response()->json([
                    'message' => 'Resource not found',
                ], SymfonyResponse::HTTP_NOT_FOUND);
            }

            if ($this->checkRelatedData($register)) {
                return response()->json([
                    'message' => 'You are not authorize to view'
                ], SymfonyResponse::HTTP_BAD_REQUEST);
            }

            $path = $register->getAttributes()['attachment'];

            if ($request->hasFile('attachment')) {
                $path = Storage::disk('public')->put('/diaryDispatches', $request->attachment);
                $validatedData = array_merge($validatedData, ['attachment' => $path]);
            }

            $register->update(array_merge($validatedData, ['attachment' => $path]));

            if (!$register->wasChanged()) {
                return response()->json([
                    'message' => 'Nothing updating ' . $register->type->value,
                    'data' => $register
                ], SymfonyResponse::HTTP_OK);
            }
            return response()->json([
                'message' => 'Success updating ' . $register->type->value,
                'data' => $register
            ], SymfonyResponse::HTTP_OK);

        } catch (\Exception $exception) {
            info($exception->getMessage());
            return response()->json([
                'message' => 'Error updating ' . $register->type->value
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteDiaryDispatchRequest $request
     * @param DiaryDispatchEnum $enum
     * @param DiaryDispatch $register
     * @return JsonResponse
     */
    public function destroy(DeleteDiaryDispatchRequest $request, DiaryDispatchEnum $enum, DiaryDispatch $register)
    {
        if ($enum->value !== $register->type->value) {
            return response()->json([
                'message' => 'Resource not found',
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        if ($this->checkRelatedData($register)) {
            return response()->json([
                'message' => 'You are not authorize to view'
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $register->delete();

        return response()->json([
            'message' => 'Success deleting ' . $register->type->value,
            'data' => $register,
        ], SymfonyResponse::HTTP_OK);

    }

    public function checkRelatedData(DiaryDispatch $diaryDispatch)
    {
        $authUser = auth()->user();
        $laboratory = $authUser->laboratoryUser;
        if ($authUser->hasRole('system-administrator')) {
            return false;
        }
        return (int)$diaryDispatch->laboratory_id !== $laboratory->id;
    }
}
