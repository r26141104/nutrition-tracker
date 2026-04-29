<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\StoreOrUpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    /**
     * GET /api/profile
     * 回傳目前登入者的個人資料；尚未建立則回傳 profile = null。
     */
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        return response()->json([
            'profile' => $profile ? new UserProfileResource($profile) : null,
        ]);
    }

    /**
     * PUT /api/profile
     * 不存在就建立、存在就更新（upsert）。
     * 因為以 $request->user() 為主體，不可能改到別人的資料。
     */
    public function update(StoreOrUpdateUserProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->validated(),
        );

        return response()->json([
            'profile' => new UserProfileResource($profile),
        ]);
    }
}
