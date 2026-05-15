<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\DeleteUserRequest;
use App\Http\Requests\User\ShowUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\ViewUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param ViewUserRequest $request
     * @return JsonResponse
     */
    public function index(ViewUserRequest $request)
    {
        $authUser = auth()->user();
        $users = User::query()
            ->select([
                'users.id',
                'name',
                'email',
                'phone',
                'basic_pay_scale',
                'date_of_birth',
                'created_by',
                'modified_by',
                'date_of_joining',
                'designation_id',
                'district_id',
                'region_id',
                'circle_id',
                'phed_division_id',
                'created_at',
            ])
            ->when(
                !$authUser->isUnscoped(),
                fn($query) => $query->whereHas(
                    'laboratoryUser',
                    fn($query) => $query->where('laboratories.id', $authUser->laboratoryUser->id)
                )
            )
            ->with([
                'designation:id,name',
                'laboratoryUser:laboratories.id,name',
                'district:id,name',
                'createdByUser:id,name',
                'region:id,name',
                'circle:id,name',
                'phedDivision:id,name',
                'roles:id,name',
            ])
            ->reorder('users.id', 'asc')
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching users',
            'data' => $users
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = array_merge($request->validated(), ['password' => Hash::make($request->password)]);

            $path = 'users';
            if (!Storage::disk('public')->path($path)) {
                Storage::disk('public')->makeDirectory($path);
            }

            $image = 'users/avatar.png';

            if ($request->hasFile('image')) {
                $image = Storage::disk('public')->putFile($path, $request->file('image'));
            }

            $user = User::query()
                ->create(array_merge($validatedData, [
                    'image'      => $image,
                    'created_by' => auth()->id(),
                ]));

            if (isset($request->laboratory_id)) {
                $user->laboratories()
                    ->sync([
                        $validatedData['laboratory_id'] => [
                            'present_duty' => $validatedData['present_duty'],
                            'assigned_parameters' => $validatedData['assigned_parameters'],
                        ]
                    ]);
            }

            $user->assignRole($validatedData['role']);

            DB::commit();

            return response()->json([
                'message' => 'Success creating user',
                'data' => $user,
            ], SymfonyResponse::HTTP_CREATED);
        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating user',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param ShowUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function show(ShowUserRequest $request, User $user)
    {
        return response()->json([
            'message' => 'Success fetching user',
            'data' => $user->load([
                'designation:id,name',
                'roles:id,name',
                'district:id,name,division_id' => [
                    'division:id,name,province_id' => [
                        'province:id,name'
                    ],
                ],
                'laboratoryDetails.laboratory:id,name',
                'region:id,name',
                'circle:id,name',
                'phedDivision:id,name'
            ])
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validatedData = $request->validated();
        
        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']);
        }

        try {
            DB::beginTransaction();

            $path = 'users';
            if (!Storage::disk('public')->path($path)) {
                Storage::disk('public')->makeDirectory($path);
            }

            $image = $user->getAttributes()['image'];

            if ($request->hasFile('image')) {
                $image = Storage::disk('public')->putFile($path, $request->file('image'));
            }
            $user->update(array_merge($validatedData, [
                'image'       => $image,
                'modified_by' => auth()->id(),
            ]));

            if (isset($request->laboratory_id)) {
                $user->laboratories()
                    ->sync([
                        $request->laboratory_id => [
                            'present_duty' => $request->present_duty,
                            'assigned_parameters' => $request->assigned_parameters,
                        ]
                    ]);
            }

            $user->syncRoles($request->role);

            DB::commit();

            return response()->json([
                'message' => 'Success updating user',
                'data' => $user,
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error updating user',
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(DeleteUserRequest $request, User $user)
    {
        if ($user->loadExists('complaints')->complaints_exists) {
            return response()->json([
                'message' => 'Error deleting user, delete all complaints belonging to this user first',
                'data' => null
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $user->delete();

        return response()->json([
            'message' => 'Success deleting user',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
