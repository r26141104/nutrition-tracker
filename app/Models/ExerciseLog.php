<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 使用者的運動消耗紀錄。
 * 每筆 = 一次運動（例：跑步 30 分鐘消耗 300 kcal）。
 */
class ExerciseLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exercise_name',
        'duration_min',
        'calories_burned',
        'logged_at',
        'note',
    ];

    protected $casts = [
        'duration_min'    => 'decimal:1',
        'calories_burned' => 'integer',
        'logged_at'       => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
