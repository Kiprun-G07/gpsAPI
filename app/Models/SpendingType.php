<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\SpendingType;
class SpendingType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function spendings()
    {
        return $this->hasMany(Spending::class);
    }
}