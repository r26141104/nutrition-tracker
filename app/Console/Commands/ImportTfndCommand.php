<?php

namespace App\Console\Commands;

use App\Models\Food;
use Illuminate\Console\Command;

/**
 * 匯入衛福部食品藥物管理署「食品營養成分資料庫(2025 版)」官方資料。
 *
 * 資料來源：衛福部食藥署 TFND 2025 UPDATE1（更新日 2026/4/30）
 * https://consumer.fda.gov.tw/Food/TFND.aspx?nodeID=178
 *
 * 資料路徑：database/data/tfnd_official.json
 *   由 EXCEL 原檔轉換而來，2212 筆食物，每 100g 含量。
 *
 * 用法：
 *   php artisan import:tfnd                # 從預設 JSON 匯入
 *   php artisan import:tfnd --truncate     # 先清掉舊的 official 食物再匯
 *   php artisan import:tfnd --dry-run      # 只看會匯入幾筆，不實際寫 DB
 *
 * 重複執行安全：用 (name, source_type=official) 去重，已存在會更新數值。
 */
class ImportTfndCommand extends Command
{
    protected $signature = 'import:tfnd
                            {--file= : 自訂 JSON 檔路徑（預設用 database/data/tfnd_official.json）}
                            {--truncate : 先清掉舊的 official 資料}
                            {--dry-run : 只看會匯入幾筆，不實際寫 DB}';

    protected $description = '匯入衛福部 TFND 2025 版官方食品營養資料（2212 筆）';

    /** TFND 食品大類 → 我們的 category */
    private const CATEGORY_MAP = [
        '穀物類'                => 'rice_box',
        '澱粉類'                => 'rice_box',
        '堅果及種子類'          => 'snack',
        '水果類'                => 'snack',
        '蔬菜類'                => 'other',
        '藻類'                  => 'other',
        '菇類'                  => 'other',
        '豆類'                  => 'other',
        '肉類'                  => 'other',
        '魚貝類'                => 'other',
        '蛋類'                  => 'other',
        '乳品類'                => 'drink',
        '油脂類'                => 'other',
        '糖類'                  => 'snack',
        '糕餅點心類'            => 'snack',
        '飲料類'                => 'drink',
        '嗜好性飲料類'          => 'drink',
        '調理加工食品類'        => 'fast_food',
        '加工調理食品類'        => 'fast_food',
        '加工調理食品及其他類'  => 'fast_food',
        '調味料及香辛料類'      => 'other',
    ];

    public function handle(): int
    {
        $this->info('=== TFND 2025 版官方食品營養資料匯入 ===');
        $this->info('資料來源：衛福部食藥署食品營養成分資料庫');

        // 1. 讀取 JSON 檔
        $file = (string) ($this->option('file') ?: database_path('data/tfnd_official.json'));
        if (! file_exists($file)) {
            $this->error("找不到檔案：{$file}");
            $this->line('請確認 database/data/tfnd_official.json 存在（這是專案內建的官方資料）');
            return self::FAILURE;
        }

        $rawJson = file_get_contents($file);
        $records = json_decode($rawJson, true);
        if (! is_array($records) || empty($records)) {
            $this->error('JSON 格式不正確或為空');
            return self::FAILURE;
        }

        $this->info('已載入 ' . count($records) . ' 筆官方資料');

        if ($this->option('dry-run')) {
            $this->info('--- 前 5 筆預覽 ---');
            foreach (array_slice($records, 0, 5) as $r) {
                $this->line(sprintf(
                    '%s | %s | %d kcal | P %.1f F %.1f C %.1f',
                    $r['category'], $r['name'],
                    $r['calories'], $r['protein_g'], $r['fat_g'], $r['carbs_g'],
                ));
            }
            $this->info('（dry-run 結束，未寫入 DB）');
            return self::SUCCESS;
        }

        // 2. 視情況清空舊的 official
        if ($this->option('truncate')) {
            $deleted = Food::where('source_type', 'official')->delete();
            $this->warn("已刪除 {$deleted} 筆舊的 official 資料");
        }

        // 3. 寫入
        $bar = $this->output->createProgressBar(count($records));
        $bar->start();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($records as $r) {
            $bar->advance();

            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                $skipped++;
                continue;
            }

            $payload = [
                'name'             => $name,
                'brand'            => '衛福部',
                'category'         => $this->mapCategory((string) ($r['category'] ?? '')),
                'serving_unit'     => 'g',
                'serving_size'     => 100,
                'calories'         => (int) max(0, $r['calories'] ?? 0),
                'protein_g'        => round((float) max(0, $r['protein_g'] ?? 0), 1),
                'fat_g'            => round((float) max(0, $r['fat_g'] ?? 0), 1),
                'carbs_g'          => round((float) max(0, $r['carbs_g'] ?? 0), 1),
                'is_system'        => true,
                'created_by_user_id' => null,
                'source_type'      => 'official',
                'confidence_level' => 'high',
            ];

            $existing = Food::where('name', $name)
                ->where('source_type', 'official')
                ->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Food::create($payload);
                $created++;
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ 新增 {$created} 筆、更新 {$updated} 筆、跳過 {$skipped} 筆");
        $this->info('完成！打開食物資料庫應該就能看到「✓ 衛福部」綠色標籤');

        return self::SUCCESS;
    }

    private function mapCategory(string $rawCategory): string
    {
        // 完全相符
        if (isset(self::CATEGORY_MAP[$rawCategory])) {
            return self::CATEGORY_MAP[$rawCategory];
        }
        // 部分相符（容錯）
        foreach (self::CATEGORY_MAP as $keyword => $cat) {
            if (str_contains($rawCategory, $keyword) || str_contains($keyword, $rawCategory)) {
                return $cat;
            }
        }
        return 'other';
    }
}
