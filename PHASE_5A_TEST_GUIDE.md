# Phase 5-A 測試指引

> 後端 9 條 API 上線後的完整驗證清單。請按順序跑，每一步都對照「預期結果」確認。

---

## 前置：兩個 server 都在跑嗎？

**分頁 1（Laravel）** 應該已經跑著：
```
cd /d "C:\Users\ASUS\OneDrive\桌面\網頁\報告用資料\nutrition-tracker"
php artisan serve
```
畫面停在 `Server running on [http://127.0.0.1:8000]`。

**分頁 2（Vite）** 跑著（Phase 5-A 後端 API 不需要 Vite，但前端頁還在用，建議保持）：
```
npm run dev
```

---

## Step 1：確認 9 條路由都註冊了

開新分頁：
```
cd /d "C:\Users\ASUS\OneDrive\桌面\網頁\報告用資料\nutrition-tracker"
php artisan route:list --path=meals
```

**預期**看到這 9 條（順序可能略不同，但都要在）：

| Method | URI |
| --- | --- |
| GET | api/meals |
| POST | api/meals |
| GET | api/meals/daily-summary |
| GET | api/meals/{meal} |
| PUT | api/meals/{meal} |
| DELETE | api/meals/{meal} |
| POST | api/meals/{meal}/items |
| PUT | api/meals/{meal}/items/{item} |
| DELETE | api/meals/{meal}/items/{item} |

**故障排除**：
- 缺路由 → 確認 `routes/api.php` 有 import `App\Http\Controllers\MealController`
- 跑不出來 → `php artisan optimize:clear` 後重試

---

## Step 2：用 Service 直接驗（不需要 HTTP token，最快）

```
php artisan tinker
```

進到 `>` 提示符後，一條一條貼：

```php
$user = \App\Models\User::first();
```
**預期**：印出 User 物件（你的 llnick91326@gmail.com 那個）

```php
$svc = app(\App\Services\Meal\MealService::class);
```
**預期**：印出 `MealService` 物件

```php
$food1 = \App\Models\Food::first();
$food1->id . ' / ' . $food1->name . ' / ' . $food1->calories . ' kcal';
```
**預期**：類似 `1 / 雞腿便當 / 750 kcal`（拿最便宜的 ID 做測試，下面會用到）

### 2-a：建一筆 meal 帶 1 個 item
```php
$meal = $svc->create(
    $user,
    ['eaten_at' => now()->toDateTimeString(), 'meal_type' => 'lunch', 'note' => '測試午餐'],
    [['food_id' => $food1->id, 'quantity' => 1.5]]
);
```
**預期**：印出 Meal 物件，`items[0].calories` = $food1 的原值（snapshot 沒被乘倍率），`items[0].quantity` = 1.5

### 2-b：確認 total_calories 是 snapshot × quantity
```php
$meal->items->first()->total_calories;
```
**預期**：等於 `$food1->calories * 1.5` 取整數，例如 food=750 → 1125

### 2-c：dailySummary 把當天總攝取算出來
```php
$svc->dailySummary($user->id);
```
**預期**：類似這樣的陣列
```
[
  "date" => "2026-04-29",
  "totals" => [
    "calories" => 1125,
    "protein_g" => ...,  // food protein × 1.5
    "fat_g" => ...,
    "carbs_g" => ...,
  ],
  "by_meal_type" => [
    "breakfast" => [ "meal_count" => 0, "calories" => 0, ... ],
    "lunch"     => [ "meal_count" => 1, "calories" => 1125, ... ],
    "dinner"    => [ "meal_count" => 0, ... ],
    "snack"     => [ "meal_count" => 0, ... ],
  ],
  "meal_count" => 1,
]
```

### 2-d：snapshot 不會被 Food 修改影響（核心特性）
```php
$originalCal = $food1->calories;
$food1->update(['calories' => 9999]);
$meal->refresh()->items->first()->calories;
```
**預期**：仍是 `$originalCal` 的值，**不是 9999**。歷史紀錄不被破壞。

把 food 改回來：
```php
$food1->update(['calories' => $originalCal]);
```

### 2-e：清乾淨
```php
\App\Models\Meal::query()->delete();
\App\Models\Meal::count();
```
**預期**：`=> 0`

```php
exit
```

---

## Step 3：用 curl 真打 API（驗證路由 + middleware + Resource）

### 3-a：登入拿 token（Sanctum 模式）

cmder 裡跑：
```
curl -X POST http://127.0.0.1:8000/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"email\":\"llnick91326@gmail.com\",\"password\":\"你的密碼\"}"
```

**注意**：把「你的密碼」換成真的密碼。Windows cmder 的 curl 用 **雙引號 + 跳脫** 比較穩。

**預期回應**：
```json
{"user":{...},"token":"123|abcXYZ..."}
```

把 `token` 那段（`|` 後面那串）抄起來。下面每條 curl 都要在 header 加 `-H "Authorization: Bearer 你的token"`。

> 為了不用每次貼，下面我用 `%T%` 代表你的 token。實際打的時候自己取代。

### 3-b：建立一筆 meal
```
curl -X POST http://127.0.0.1:8000/api/meals -H "Authorization: Bearer %T%" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"eaten_at\":\"2026-04-29 12:30:00\",\"meal_type\":\"lunch\",\"note\":\"curl 測試\",\"items\":[{\"food_id\":1,\"quantity\":1.5}]}"
```

**預期 201 回應**：
```json
{
  "meal": {
    "id": 1,
    "user_id": 1,
    "eaten_at": "2026-04-29T12:30:00+08:00",
    "meal_type": "lunch",
    "note": "curl 測試",
    "items": [ { "id": 1, "food_id": 1, "quantity": 1.5, "snapshot": {...}, "total": {...}, "food_summary": {...} } ],
    "totals": { "calories": ..., "protein_g": ..., ... },
    "item_count": 1,
    ...
  }
}
```
**抄下回應的 `meal.id`，下面用 `%M%` 代表。**

### 3-c：取單筆 meal
```
curl http://127.0.0.1:8000/api/meals/%M% -H "Authorization: Bearer %T%" -H "Accept: application/json"
```
**預期**：跟 3-b 同樣的 meal 物件

### 3-d：今日列表
```
curl "http://127.0.0.1:8000/api/meals?date=2026-04-29" -H "Authorization: Bearer %T%" -H "Accept: application/json"
```
**預期**：`data` 陣列裡至少 1 筆

### 3-e：今日總覽
```
curl "http://127.0.0.1:8000/api/meals/daily-summary?date=2026-04-29" -H "Authorization: Bearer %T%" -H "Accept: application/json"
```
**預期**：tutorial Step 2-c 那種總覽 JSON，`totals.calories` 對得上

### 3-f：加一個 item 到剛剛的 meal
```
curl -X POST http://127.0.0.1:8000/api/meals/%M%/items -H "Authorization: Bearer %T%" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"food_id\":2,\"quantity\":1}"
```
**預期 201**：回 `item` 物件，snapshot 是 food id=2 的當下值

### 3-g：刪整筆 meal
```
curl -X DELETE http://127.0.0.1:8000/api/meals/%M% -H "Authorization: Bearer %T%" -H "Accept: application/json"
```
**預期**：`{"message":"已刪除"}`，items 也跟著 cascade 刪掉

---

## Step 4：邊界 case（5 個必驗）

### 4-1：未登入要 401
```
curl http://127.0.0.1:8000/api/meals -H "Accept: application/json"
```
**預期**：`401 Unauthorized` / `{"message":"Unauthenticated."}`

### 4-2：meal_type 亂填要 422
```
curl -X POST http://127.0.0.1:8000/api/meals -H "Authorization: Bearer %T%" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"eaten_at\":\"2026-04-29 12:00:00\",\"meal_type\":\"midnight_snack\"}"
```
**預期**：`422`，errors.meal_type = `["餐別必須是 breakfast / lunch / dinner / snack 之一"]`

### 4-3：food_id 不存在要 422
```
curl -X POST http://127.0.0.1:8000/api/meals -H "Authorization: Bearer %T%" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"eaten_at\":\"2026-04-29 12:00:00\",\"meal_type\":\"lunch\",\"items\":[{\"food_id\":99999,\"quantity\":1}]}"
```
**預期**：`422`，errors 提到 food_id 不存在

### 4-4：別的使用者的 meal 要 404
（這個比較難測，需要建第二個 user。如果你只有一個帳號可跳過。）

### 4-5：刪除 food 後，舊 meal 紀錄還在（snapshot 保留）
```
php artisan tinker
```
```php
$user = \App\Models\User::first();
$svc = app(\App\Services\Meal\MealService::class);

// 先建一個自訂食物
$customFood = \App\Models\Food::create([
    'name' => '快被刪掉的食物',
    'category' => 'other',
    'serving_unit' => '份',
    'serving_size' => 1,
    'calories' => 500,
    'protein_g' => 20,
    'fat_g' => 10,
    'carbs_g' => 60,
    'is_system' => false,
    'created_by_user_id' => $user->id,
]);

// 用它建 meal
$meal = $svc->create($user, ['eaten_at' => now(), 'meal_type' => 'snack'], [['food_id' => $customFood->id, 'quantity' => 1]]);

// 刪掉 food
$customFood->delete();

// meal_item 還在嗎？
$item = $meal->items()->first();
echo "food_id: " . ($item->food_id ?? 'NULL') . "\n";
echo "snapshot calories: " . $item->calories . "\n";
echo "food relation: " . ($item->food === null ? 'null (預期)' : '有 food') . "\n";

// 清掉測試
$meal->delete();
exit
```
**預期**：`food_id: NULL`、`snapshot calories: 500`（snapshot 還在！）、`food relation: null (預期)`

---

## ✅ 驗收清單

- [ ] Step 1：route:list 看到 9 條
- [ ] Step 2-a/b/c：tinker 直打 service 都通
- [ ] Step 2-d：snapshot 不被 Food 修改影響
- [ ] Step 3-b/c/d/e/f/g：6 條 curl API 都通
- [ ] Step 4-1/2/3：401 / 422 都有正確回應
- [ ] Step 4-5：snapshot 在 food 被刪後仍保留

全部打勾後告訴我「都過」，就進 **Phase 5-B（前端）**。
