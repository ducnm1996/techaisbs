<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['entry_date', 'meal_type', 'title', 'notes'])]
class Meal extends Model
{
    public const TYPES = ['breakfast' => 'Bữa sáng', 'lunch' => 'Bữa trưa', 'dinner' => 'Bữa tối', 'snack' => 'Bữa phụ'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }
}
