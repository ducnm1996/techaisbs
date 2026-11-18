<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['entry_date', 'meal_type', 'title', 'notes'])]
class Meal extends Model
{
    public const TYPES = ['breakfast' => 'Bữa sáng', 'lunch' => 'Bữa trưa', 'dinner' => 'Bữa tối', 'snack' => 'Bữa phụ'];

    public const MAX_PHOTOS = 4;

    public function photoDirectory(): string
    {
        return 'diary/'.$this->user_id.'/'.$this->id;
    }

    public function photos(): array
    {
        return array_map('basename', Storage::disk('local')->files($this->photoDirectory()));
    }

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }
}
