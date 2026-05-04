<?php

namespace App\Services\Food;

use App\Models\Food;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FoodService
{
    /**
     * 列表 + 搜尋。系統食物所有人都能看；自訂食物只有 owner 能看。
     */
    public function search(
        ?string $search,
        ?string $category,
        ?int $userId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Food::query()->visibleTo($userId);

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        // 系統食物排前面，再依名稱排序
        return $query
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * 找到並確認可見性；找不到或不可見一律 404，避免外洩存在。
     */
    public function findVisibleOrFail(int $id, ?int $userId): Food
    {
        $food = Food::query()->visibleTo($userId)->find($id);

        if (! $food) {
            throw new NotFoundHttpException('找不到此食物');
        }

        return $food;
    }

    /**
     * 建立自訂食物。is_system / created_by_user_id / source_type / confidence_level
     * 由 server 端決定，不接受前端傳入。
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Food
    {
        unset(
            $data['is_system'],
            $data['created_by_user_id'],
            $data['source_type'],
            $data['confidence_level'],
        );
        $data['is_system']          = false;
        $data['created_by_user_id'] = $user->id;
        // 修正四：使用者手動建立 → user_custom + low（使用者輸入未經驗證）
        $data['source_type']        = 'user_custom';
        $data['confidence_level']   = 'low';

        return Food::create($data);
    }

    /**
     * 更新；只有自訂食物的 owner 可以改。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Food $food, array $data, User $user): Food
    {
        $this->ensureCanModify($food, $user);

        // 不允許從 update 改 server-controlled 欄位
        unset(
            $data['is_system'],
            $data['created_by_user_id'],
            $data['source_type'],
            $data['confidence_level'],
        );

        $food->update($data);

        return $food->refresh();
    }

    /**
     * 刪除；只有自訂食物的 owner 可以刪。
     */
    public function delete(Food $food, User $user): void
    {
        $this->ensureCanModify($food, $user);
        $food->delete();
    }

    /**
     * 權限檢查：
     *   1) 連鎖店原始菜單（is_system + 連到非 guess- store）→ 不可改
     *   2) AI 推測菜單（store 的 slug 以 'guess-' 開頭）→ 任何登入使用者可編輯/刪除
     *      理由：使用者反映 AI 常推錯店家類型，需要可以清掉錯的、補上對的
     *   3) 自訂食物 → 只有 owner 可改
     *
     * @throws AuthorizationException
     */
    public function ensureCanModify(Food $food, User $user): void
    {
        // AI 推測 store 的菜單 → 開放編輯
        if ($food->store_id) {
            $food->loadMissing('store');
            $store = $food->store;
            if ($store && str_starts_with((string) $store->slug, 'guess-')) {
                return;
            }
        }

        if ($food->is_system) {
            throw new AuthorizationException('系統食物不可修改或刪除');
        }
        if ($food->created_by_user_id !== $user->id) {
            throw new AuthorizationException('您沒有權限修改此食物');
        }
    }
}
