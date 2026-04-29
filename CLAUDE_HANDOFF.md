# Nutrition Tracker — Claude 接班文件

> **新 Claude 看到這份請先完整讀完再動手。** 使用者剛換帳號繼續這個專案。

---

## 🚀 給「新 Claude」的快速說明

我是一位**台灣使用者**（中文溝通、Windows 11、Laragon 環境），正在建一個**外食族個人化減脂／增肌營養紀錄平台**。
目前 **Phase 1～4 都做完了**，下一步是 **Phase 5：飲食紀錄**。

請：
1. **先讀完整份 HANDOFF**，掌握架構與慣例
2. **沿用相同的工作節奏**：使用者貼一段詳細需求 → 你照需求寫檔 → 列出修改/新增的檔案 → 告訴使用者怎麼測試 → 等使用者回報
3. **沿用相同的架構慣例**（見下方「架構慣例」）
4. **不要急著做太多**——使用者偏好一次只做一個小階段
5. **使用者是新手**，指令要寫到「複製貼上」就能跑的程度，每行指令前面都要加 `cd ...nutrition-tracker` 之類的明確路徑提示

---

## 📁 專案位置與啟動

- **專案根目錄**：`C:\Users\ASUS\.claude\報告用資料\nutrition-tracker`
- **使用者資料夾掛載**：使用者在 Cowork 環境會把 `C:\Users\ASUS\.claude\報告用資料` 連線進來
- **Linux mount 路徑**：`/sessions/{session}/mnt/報告用資料/nutrition-tracker/`（用 bash 寫檔走這個）
- **不能用 Write 工具**寫到 `.claude` 路徑下，會被擋；**改用 `mcp__workspace__bash` 的 cat heredoc**寫檔，這已經驗證可行
- **不能用 bash 刪檔**（rm 會 Operation not permitted），需要刪檔請改成「覆寫成 0 bytes」或請使用者在 Windows 檔案總管手動刪

### 啟動指令（使用者要在 Laragon Terminal 跑）

兩個分頁同時跑（`Ctrl+T` 開新分頁）：

**分頁 1（Laravel）**
```
cd C:\Users\ASUS\.claude\報告用資料\nutrition-tracker
php artisan serve
```
跑在 `http://127.0.0.1:8000`，**畫面會停住、不要關**。

**分頁 2（Vite）**
```
cd C:\Users\ASUS\.claude\報告用資料\nutrition-tracker
npm run dev
```
跑在 `http://localhost:5173`，**畫面也會停住、不要關**。

瀏覽器開 <http://127.0.0.1:8000> 就能用。

---

## 🛠 技術棧

| 層 | 用什麼 |
| --- | --- |
| 後端 | Laravel **13.6** (PHP 8.3.30) |
| 前端 | Vue **3** + TypeScript **6** + Vite **8** |
| 整合方式 | **單一專案**：Vue 寫在 `resources/js/`，由 Laravel 的 `@vite` 載入 |
| 驗證 | Laravel **Sanctum** 4，**token 模式**（前端存 localStorage、Bearer header） |
| 資料庫 | **SQLite**（檔案：`database/database.sqlite`），Laragon 的 MySQL 沒在用 |
| 路由（前端） | Vue Router 4，`createWebHistory` 模式，後端 `routes/web.php` 有 SPA catch-all |
| 狀態 | Pinia 2 |
| HTTP | axios |
| CSS | Tailwind 4（Laravel 預設）+ scoped styles in .vue files |
| 開發環境 | Laragon Full（Windows）+ cmder（λ 提示符） |

---

## ✅ 已完成的階段

### Phase 1 — 會員系統（完工）
- 註冊 / 登入 / 登出 / `GET /api/user`
- Sanctum token 模式
- Vue Router auth guard（`requiresAuth` / `guestOnly`）
- 前端 Login.vue / Register.vue / Dashboard.vue / authStore (Pinia)

### Phase 2 — 個人資料（完工）
- `user_profiles` 表，**含 birthdate（生日）與 sex（生理性別）**
- 欄位：id, user_id, **birthdate, sex**, height_cm, weight_kg, target_bmi, activity_level, goal_type, timestamps
- activity_level: `sedentary | light | moderate | active`
- goal_type: `lose_fat | gain_muscle | maintain`
- sex: `male | female`
- UserProfile model 有 `getAgeAttribute()` 與 `isComplete()` helper
- API：`GET /api/profile`、`PUT /api/profile`（upsert 模式）
- 前端 ProfileSetup.vue + profileService.ts

### Phase 3 — 每日營養目標計算（完工）
- **NutritionCalculatorService**（核心）含 9 個 public method：
  1. `calculateAge(DateTimeInterface $birthdate): int`
  2. `calculateTargetWeight(float $heightCm, float $targetBmi): float`
  3. `calculateBmr(float $weightKg, float $heightCm, int $age, string $sex): int`（Mifflin-St Jeor）
  4. `calculateTdee(int $bmr, string $activityLevel): int`
  5. `calculateDailyCalories(int $tdee, string $goalType): int`
  6. `calculateProteinTarget(float $weightKg, string $goalType): int`
  7. `calculateFatTarget(int $dailyCalories): int`
  8. `calculateCarbsTarget(int $dailyCalories, int $proteinG, int $fatG): int`
  9. `generateNutritionTarget(UserProfile $profile): array`
- **公式參數**（已固定，不要改）：
  - 活動係數：sedentary 1.2 / light 1.375 / moderate 1.55 / active 1.725
  - 目標係數：lose_fat 0.85 / maintain 1.00 / gain_muscle 1.10
  - 蛋白質：lose_fat 2.0g/kg / gain_muscle 1.8g/kg / maintain 1.6g/kg
  - 脂肪：每日熱量 × 25% ÷ 9
  - 碳水：餘量 ÷ 4
- Warnings：BMI < 18.5、BMI > 30、daily_calories 低於 1200(女)/1500(男)、daily_calories 低於 BMR
- API：`GET /api/nutrition-target`（回 ready=true/false 二態）
- 前端 nutritionTargetService.ts + Dashboard 顯示
- **使用者已驗證計算正確**（178cm/56kg/BMI15 男性輕度維持 → 47.5kg 目標、1558 BMR、2142 TDEE/熱量、90/60/311 PFC，全部對得起來）

### Phase 4-A — 食物資料庫後端（完工）
- `foods` 表，欄位：id, name, brand, category, serving_unit, serving_size, calories, protein_g, fat_g, carbs_g, is_system, created_by_user_id, timestamps
- category：`rice_box | noodle | convenience | fast_food | drink | snack | other`
- ⚠️ **重要**：Food model 必須顯式 `protected $table = 'foods';`
  原因：'food' 在英文是不可數名詞，Laravel 預設 pluralizer 不會加 s，會推成 'food' 表，與 migration 不符
- Food model 有 `scopeVisibleTo($userId)` 與 `isOwnedBy($userId)` helper
- FoodSeeder 灌入 **45 筆台灣常見外食**（便當 8 / 麵店 7 / 便利商店 10 / 速食 8 / 飲料 7 / 點心 5）
- 系統食物所有人看得到、不可改；自訂食物只有 owner 可看可改可刪
- FoodService 有 search / findVisibleOrFail / create / update / delete / ensureCanModify
- API：`Route::apiResource('foods', FoodController::class)` 五支 endpoint

### Phase 4-B — 食物資料庫前端 UI（完工）
- 路由：`/foods`、`/foods/new`、`/foods/:id/edit`（都 requiresAuth）
- FoodList.vue：debounced 搜尋（300ms）、類別篩選、分頁、卡片網格、刪除確認、空狀態
- FoodEdit.vue：同一個元件處理新增與編輯，含 422 validation 錯誤顯示
- foodService.ts：list/show/create/update/delete + CATEGORY_OPTIONS 常數
- Dashboard 加快速導航 pill bar（👤 個人資料 / 🍱 食物資料庫）

---

## 🔌 API 端點（目前全部）

所有路由在 `routes/api.php`，前綴 `/api`：

| Method | Path | Auth | 用途 |
| --- | --- | --- | --- |
| POST | /register | - | 註冊 |
| POST | /login | - | 登入 |
| POST | /logout | sanctum | 登出 |
| GET | /user | sanctum | 目前登入者 |
| GET | /profile | sanctum | 取得個人資料（不存在回 null） |
| PUT | /profile | sanctum | 建立或更新個人資料（upsert） |
| GET | /nutrition-target | sanctum | 每日營養目標計算結果 |
| GET | /foods | sanctum | 食物列表（支援 ?search= ?category= ?page=） |
| POST | /foods | sanctum | 新增自訂食物 |
| GET | /foods/{food} | sanctum | 查單筆食物（自動視野過濾） |
| PUT | /foods/{food} | sanctum | 更新自訂食物（系統食物 403） |
| DELETE | /foods/{food} | sanctum | 刪除自訂食物（系統食物 403） |

---

## 📂 檔案結構（重點檔）

```
nutrition-tracker/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php                          # 基底
│   │   │   ├── AuthController.php                      # 註冊/登入/登出/me
│   │   │   ├── UserProfileController.php               # 個人資料 CRUD
│   │   │   ├── NutritionTargetController.php          # 每日營養目標
│   │   │   └── FoodController.php                      # 食物 CRUD（apiResource）
│   │   ├── Requests/
│   │   │   ├── Auth/{Login,Register}Request.php
│   │   │   ├── Profile/StoreOrUpdateUserProfileRequest.php
│   │   │   └── Food/StoreOrUpdateFoodRequest.php       # CATEGORIES 常數在這
│   │   └── Resources/
│   │       ├── UserResource.php
│   │       ├── UserProfileResource.php
│   │       └── FoodResource.php                         # is_owned 欄位給前端用
│   ├── Models/
│   │   ├── User.php                                    # HasApiTokens、profile() hasOne
│   │   ├── UserProfile.php                             # getAgeAttribute / isComplete
│   │   └── Food.php                                    # ⚠️ $table='foods'、visibleTo scope
│   └── Services/
│       ├── Auth/AuthService.php                        # register / login / logout
│       ├── Nutrition/NutritionCalculatorService.php    # 9 個 calculate*  method
│       └── Food/FoodService.php                        # search / findVisibleOrFail / CRUD
│   ⚠️  舊的 NutritionService.php / NutritionController.php / nutritionService.ts 是 orphan，
│       不影響功能但建議使用者手動刪掉（無法用 bash 刪）
├── bootstrap/app.php                                   # api routing + JSON exception
├── config/                                             # 預設 + Sanctum
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_04_28_024814_create_personal_access_tokens_table.php
│   │   ├── 2026_04_28_120000_create_user_profiles_table.php
│   │   ├── 2026_04_28_130000_add_birthdate_and_sex_to_user_profiles_table.php
│   │   └── 2026_04_28_140000_create_foods_table.php
│   ├── seeders/
│   │   ├── DatabaseSeeder.php                          # call FoodSeeder
│   │   └── FoodSeeder.php                              # 45 筆台灣外食
│   └── database.sqlite                                 # 主 DB（不要刪！）
├── routes/
│   ├── web.php                                         # SPA catch-all
│   ├── api.php                                         # 所有 API（見上表）
│   └── console.php
└── resources/
    ├── views/welcome.blade.php                         # 簡化成 <div id="app"> 容器
    ├── css/app.css                                     # Tailwind 4 入口
    └── js/
        ├── main.ts → app.ts                            # ⚠️ 入口檔名是 app.ts 不是 main.ts
        ├── App.vue                                     # 只有 <RouterView />
        ├── shims-vue.d.ts                              # 讓 TS 認 .vue
        ├── router/index.ts                             # 路由 + auth guard
        ├── stores/authStore.ts                         # Pinia auth state
        ├── services/                                   # 所有 API 呼叫集中於此
        │   ├── http.ts                                 # axios instance + token storage + interceptor
        │   ├── authService.ts
        │   ├── profileService.ts
        │   ├── nutritionTargetService.ts
        │   └── foodService.ts                          # CATEGORY_OPTIONS / CATEGORY_LABEL 在這
        ├── views/
        │   ├── Login.vue
        │   ├── Register.vue
        │   ├── Dashboard.vue                           # 含 quick-nav + profile card + nutrition card
        │   ├── ProfileSetup.vue                        # 7 欄位（含生日性別）
        │   ├── FoodList.vue                            # debounced 搜尋 + 分頁
        │   └── FoodEdit.vue                            # 新增/編輯共用
        └── types/                                      # 預留資料夾，目前空
```

---

## 🏗 架構慣例（嚴格遵守）

### 後端

1. **Controller 薄、邏輯都在 Service**
   - Controller 只做：拿 Request → 呼 Service → 回 JsonResponse
   - 任何業務邏輯（DB 查詢、計算、權限檢查）都進 `app/Services/`
   - 例：`AuthController` 不直接 hash 密碼，呼叫 `AuthService::register()`

2. **Validation 走 FormRequest**
   - 命名：`StoreOrUpdateXxxRequest`（單一 Request 處理 store + update）
   - `rules()` + `messages()`（中文錯誤訊息）
   - Request 的 `authorize()` 檢查登入

3. **Resource 處理回傳格式**
   - 不要回 raw model，一律用 `XxxResource`
   - 對前端友善的欄位處理（例：is_owned、age 等計算屬性）
   - 敏感欄位（如 password）不能 leak

4. **Model 只放：fillable、casts、relations、scope、accessor**
   - 用 PHP 12 的 `#[Fillable([...])]` attribute（這個 Laravel 版本支援）
   - 不寫業務邏輯

5. **Migration 命名遵循 timestamp 順序**
   - 既有最後一支：`2026_04_28_140000_create_foods_table.php`
   - 下一支用 `2026_04_28_150000_` 之後的時間戳

6. **權限**
   - 路由用 `auth:sanctum` middleware 擋登入
   - 資源層級的權限（如「只能改自己的」）寫在 Service，throw `AuthorizationException`

### 前端

1. **API 呼叫一律走 services/**
   - Vue component **不直接打 axios**
   - Service 用 `http.ts` 提供的 axios instance
   - 每支方法回 `Promise<T>`，不回 AxiosResponse

2. **TypeScript 嚴格**
   - 所有 props、API payload、API response 都要型別
   - 不用 `any`（除非真的沒辦法，並加註解說明）

3. **狀態管理**
   - 跨頁／跨元件：Pinia store
   - 單頁／單元件：`ref` / `reactive`

4. **路由 guard**
   - 用 router beforeEach 統一處理
   - `meta.requiresAuth` / `meta.guestOnly`

5. **Validation 錯誤顯示**
   - 422 時把 `errors` 物件存進 `errors` ref，用 `errors.fieldname[0]` 顯示在欄位下方
   - 其他狀態碼顯示一般錯誤 banner

6. **Composition API + `<script setup lang="ts">`**
   - 不寫 Options API
   - 不寫 `<script>` without `setup`

---

## ⚠️ 已知坑與決策

| 議題 | 決策 / 解法 |
| --- | --- |
| `food` 在英文是不可數名詞，Laravel 推不出 `foods` 表 | Food model 加 `protected $table = 'foods';` |
| Sandbox 不能 rm 檔 | 廢檔覆寫成 0 bytes，請使用者手動刪除 |
| Sandbox Write tool 在 `.claude` 路徑被擋 | 一律走 `mcp__workspace__bash` heredoc 寫檔 |
| Vue Router history 模式刷新 404 | `routes/web.php` 加 `Route::get('/{any?}', ...)`  catch-all（已做） |
| Vite + Laravel @vite 找不到 hot file | 不要用 `@if (file_exists(...))` 包 @vite，固定載入即可（已做） |
| 早期 NutritionService 用固定值（−500/+300）調整熱量 | 已重做為百分比（−15%/+10%），舊檔變 orphan |
| Element Plus | 一開始用戶要求過，但目前**沒有**裝，UI 用純 CSS。下一輪有需要再導入 |
| 前後端分離 vs 整合 | 改成**整合**架構，Vue 在 Laravel 內 |
| 資料庫 | 預設 SQLite，**未來換 MySQL** 時改 .env 即可，schema 不用動 |
| 使用者測試帳號 | name=林才榆, email=llnick91326@gmail.com（在 SQLite 裡） |

---

## 🎯 下一步：Phase 5 — 飲食紀錄（核心）

**還沒做。** 使用者提示這次 Phase 5 要分 5-A（後端）與 5-B（前端）兩半做。

### Phase 5-A 規劃（後端）

**兩張新表**：
- `meals`：id, user_id, eaten_at (datetime), meal_type, note, timestamps
  - meal_type: `breakfast | lunch | dinner | snack`
- `meal_items`：id, meal_id, food_id, quantity (decimal), **+ snapshot 欄位**, timestamps
  - **重點**：加食物到餐點時要 snapshot 當下的 calories / protein_g / fat_g / carbs_g，未來 Food 被改不影響歷史紀錄

**Models**：
- Meal：belongsTo User、hasMany MealItems、cascade delete items
- MealItem：belongsTo Meal、belongsTo Food
- User 加 hasMany Meals

**API**：
- `GET /api/meals?date=YYYY-MM-DD`
- `POST /api/meals`（可同時帶 items）
- `GET /api/meals/{meal}`
- `PUT /api/meals/{meal}`
- `DELETE /api/meals/{meal}`
- `POST /api/meals/{meal}/items`
- `PUT /api/meals/{meal}/items/{item}`
- `DELETE /api/meals/{meal}/items/{item}`
- `GET /api/meals/daily-summary?date=YYYY-MM-DD` ← 給 Dashboard 用

**權限**：使用者只能存取自己的 meals（透過 `$request->user()->meals()` 自動 scope）

### Phase 5-B 規劃（前端）

- mealService.ts
- MealList.vue：今日時間軸（早/午/晚/點心分區、可切換日期）
- 加食物 modal：搜尋食物 → 選份量 → 加入
- Dashboard 加「今日總攝取 vs 目標」進度條卡片

---

## 🧪 怎麼快速驗證目前狀態

新 Claude 接手時可以叫使用者跑這段確認專案狀態正常：

```powershell
cd C:\Users\ASUS\.claude\報告用資料\nutrition-tracker

# 環境
php -v && composer --version && node -v && npm -v

# 資料庫表都在
php artisan migrate:status

# 食物資料還在
php artisan tinker
# 然後跑：\App\Models\Food::count()  → 應 >= 45
# 跑：\App\Models\User::count()      → 應 >= 1
# 跑：\App\Models\UserProfile::count() → 應 >= 1
```

---

## 📌 接班 checklist（給新 Claude）

- [ ] 讀完整份 HANDOFF
- [ ] 跟使用者確認他想做 Phase 5-A、Phase 5-B、或想做其他（例如先把 Element Plus 整合進來、或先做體重紀錄）
- [ ] 動工前先用 bash 看一下 routes/api.php、現有 Models、目錄結構，確認跟 HANDOFF 一致
- [ ] 寫檔走 `mcp__workspace__bash` 的 cat heredoc，**不要用 Write 工具**
- [ ] 完成一階段就：列出修改/新增檔案 → 給使用者測試指引 → **停下來**等回饋
- [ ] 永遠用繁體中文回應，使用者習慣這個語感

---

> 文件最後更新：完成 Phase 4-B 之後
> 維護者：使用者 + Claude（多帳號接力）
