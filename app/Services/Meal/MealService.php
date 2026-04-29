<?php

namespace App\Services\Meal;

use App\Models\Food;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MealService
{
    /**
     * 列出某使用者某一天的所有 meals（含 items + items.food），按 eaten_at 升冪。
     * date = null 時預設取「今天」。
     */
    public function listOfDate(int $userId, ?string $date = null, ?string $mealType = null): Collection
    {
        $date = $date ?: now()->toDateString();

        $query = Meal::query()
            ->forUser($userId)
            ->ofDate($date)
            ->with(['items.food'])
            ->orderBy('eaten_at');

        if ($mealType !== null && $mealType !== '') {
            $query->where('meal_type', $mealType);
        }

        return $query->get();
    }

    /**
     * 找某筆 meal，並驗證屬於該 user。找不到 / 不屬於該 user 一律 404。
     */
    public function findVisibleOrFail(int $mealId, int $userId): Meal
    {
        $meal = Meal::query()
            ->forUser($userId)
            ->with(['items.food'])
            ->find($mealId);

        if (! $meal) {
            throw new NotFoundHttpException('找不到此餐點');
        }

        return $meal;
    }

    /**
     * 建立一筆 meal，可同時帶 items。
     *
     * @param  array<string, mixed>  $data       含 eaten_at / meal_type / note
     * @param  array<int, array<string, mixed>>  $items 每個元素 { food_id, quantity }
     */
    public function create(User $user, array $data, array $items = []): Meal
    {
        return DB::transaction(function () use ($user, $data, $items) {
            $meal = Meal::create([
                'user_id'   => $user->id,
                'eaten_at'  => $data['eaten_at'],
                'meal_type' => $data['meal_type'],
                'note'      => $data['note'] ?? null,
            ]);

            $this->syncItems($meal, $user, $items, replace: false);

            return $meal->load(['items.food']);
        });
    }

    /**
     * 更新 meal。如果 $items 不是 null，會「整批替換」items。
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $items
     */
    public function update(Meal $meal, User $user, array $data, ?array $items = null): Meal
    {
        $this->ensureCanModify($meal, $user);

        return DB::transaction(function () use ($meal, $user, $data, $items) {
            $meal->update([
                'eaten_at'  => $data['eaten_at']  ?? $meal->eaten_at,
                'meal_type' => $data['meal_type'] ?? $meal->meal_type,
                'note'      => array_key_exists('note', $data) ? $data['note'] : $meal->note,
            ]);

            if ($items !== null) {
                $this->syncItems($meal, $user, $items, replace: true);
            }

            return $meal->fresh(['items.food']);
        });
    }

    /**
     * 刪除整筆 meal（cascade 連 items 一起死）。
     */
    public function delete(Meal $meal, User $user): void
    {
        $this->ensureCanModify($meal, $user);
        $meal->delete();
    }

    /**
     * 在某筆 meal 上加一個 item。
     *
     * @param  array<string, mixed>  $itemData  { food_id, quantity }
     */
    public function addItem(Meal $meal, User $user, array $itemData): MealItem
    {
        $this->ensureCanModify($meal, $user);

        $food = $this->loadVisibleFoodOrFail((int) $itemData['food_id'], $user->id);

        return $meal->items()->create(
            $this->buildItemPayload($food, (float) $itemData['quantity']),
        )->load('food');
    }

    /**
     * 更新某筆 item。如果 food_id 改了，會重新 snapshot；只改 quantity 則 snapshot 保留。
     *
     * @param  array<string, mixed>  $itemData
     */
    public function updateItem(Meal $meal, MealItem $item, User $user, array $itemData): MealItem
    {
        $this->ensureCanModify($meal, $user);
        $this->ensureItemBelongsToMeal($item, $meal);

        $payload = ['quantity' => (float) $itemData['quantity']];

        if ((int) $itemData['food_id'] !== (int) $item->food_id) {
            $food = $this->loadVisibleFoodOrFail((int) $itemData['food_id'], $user->id);
            $payload = array_merge(
                $payload,
                $this->buildItemPayload($food, (float) $itemData['quantity']),
            );
        }

        $item->update($payload);

        return $item->fresh('food');
    }

    /**
     * 刪除某筆 item。
     */
    public function deleteItem(Meal $meal, MealItem $item, User $user): void
    {
        $this->ensureCanModify($meal, $user);
        $this->ensureItemBelongsToMeal($item, $meal);

        $item->delete();
    }

    /**
     * 一日總覽：給 Dashboard 用。
     * 回傳形狀：
     *   {
     *     date,
     *     totals: { calories, protein_g, fat_g, carbs_g },
     *     by_meal_type: { breakfast: {...}, lunch: {...}, dinner: {...}, snack: {...} },
     *     meal_count
     *   }
     *
     * 注意：沒回 meals 陣列本身（避免 payload 太大），前端要看完整列表請打 GET /meals?date=
     *
     * @return array<string, mixed>
     */
    public function dailySummary(int $userId, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();

        $meals = $this->listOfDate($userId, $date);

        $totals = [
            'calories'  => 0,
            'protein_g' => 0.0,
            'fat_g'     => 0.0,
            'carbs_g'   => 0.0,
        ];

        // 預先把四種餐別的 bucket 都建好（前端就不用判斷 key 存不存在）
        $byMealType = [];
        foreach (Meal::MEAL_TYPES as $type) {
            $byMealType[$type] = [
                'meal_count' => 0,
                'calories'   => 0,
                'protein_g'  => 0.0,
                'fat_g'      => 0.0,
                'carbs_g'    => 0.0,
            ];
        }

        foreach ($meals as $meal) {
            $bucket = $meal->meal_type;

            $byMealType[$bucket]['meal_count']++;

            foreach ($meal->items as $item) {
                $byMealType[$bucket]['calories']  += $item->total_calories;
                $byMealType[$bucket]['protein_g'] += (float) $item->total_protein_g;
                $byMealType[$bucket]['fat_g']     += (float) $item->total_fat_g;
                $byMealType[$bucket]['carbs_g']   += (float) $item->total_carbs_g;

                $totals['calories']  += $item->total_calories;
                $totals['protein_g'] += (float) $item->total_protein_g;
                $totals['fat_g']     += (float) $item->total_fat_g;
                $totals['carbs_g']   += (float) $item->total_carbs_g;
            }
        }

        // 統一 round（避免 float 累加誤差顯示成 100.000000001）
        $totals['protein_g'] = round($totals['protein_g'], 2);
        $totals['fat_g']     = round($totals['fat_g'], 2);
        $totals['carbs_g']   = round($totals['carbs_g'], 2);

        foreach ($byMealType as $type => &$bucket) {
            $bucket['protein_g'] = round($bucket['protein_g'], 2);
            $bucket['fat_g']     = round($bucket['fat_g'], 2);
            $bucket['carbs_g']   = round($bucket['carbs_g'], 2);
        }
        unset($bucket);

        return [
            'date'         => $date,
            'totals'       => $totals,
            'by_meal_type' => $byMealType,
            'meal_count'   => $meals->count(),
        ];
    }

    // ============================================================
    // 內部工具
    // ============================================================

    /**
     * 整批替換 items（replace=true）或新增 items（replace=false）。
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Meal $meal, User $user, array $items, bool $replace): void
    {
        if ($replace) {
            $meal->items()->delete();
        }

        foreach ($items as $itemData) {
            $food = $this->loadVisibleFoodOrFail((int) $itemData['food_id'], $user->id);
            $meal->items()->create(
                $this->buildItemPayload($food, (float) $itemData['quantity']),
            );
        }
    }

    /**
     * 拿可見食物或 404。同時拿來確認：使用者不能引用別人的私人食物。
     */
    private function loadVisibleFoodOrFail(int $foodId, int $userId): Food
    {
        $food = Food::query()->visibleTo($userId)->find($foodId);

        if (! $food) {
            throw new NotFoundHttpException('找不到指定的食物（或您沒有權限看到它）');
        }

        return $food;
    }

    /**
     * 從 Food 拷貝 snapshot 欄位 + 帶上 quantity 與 food_id，組成寫進 meal_items 的 payload。
     *
     * @return array<string, mixed>
     */
    private function buildItemPayload(Food $food, float $quantity): array
    {
        return [
            'food_id'   => $food->id,
            'quantity'  => $quantity,
            // ↓ 全部都是「每 1 單位」的當下值，未來 Food 改 / 刪都不影響歷史
            'calories'  => (int) $food->calories,
            'protein_g' => (float) $food->protein_g,
            'fat_g'     => (float) $food->fat_g,
            'carbs_g'   => (float) $food->carbs_g,
        ];
    }

    /**
     * 確保此 meal 屬於該 user，否則丟 AuthorizationException（403）。
     *
     * @throws AuthorizationException
     */
    public function ensureCanModify(Meal $meal, User $user): void
    {
        if ($meal->user_id !== $user->id) {
            throw new AuthorizationException('您沒有權限存取此餐點');
        }
    }

    /**
     * 確保此 item 屬於這個 meal（路由 /meals/{meal}/items/{item} 防止 mix-and-match）。
     */
    private function ensureItemBelongsToMeal(MealItem $item, Meal $meal): void
    {
        if ($item->meal_id !== $meal->id) {
            throw new NotFoundHttpException('找不到此項目');
        }
    }
}
