<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['entry_date', 'title', 'clinic', 'notes', 'next_visit', 'file_path', 'file_name'])]
class Checkup extends Model
{
    protected function casts(): array
    {
        return ['entry_date' => 'date', 'next_visit' => 'date'];
    }
}
