<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    // id vem da fakestoreapi, não é autoincrementado
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'email', 'username', 'phone', 'status',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}