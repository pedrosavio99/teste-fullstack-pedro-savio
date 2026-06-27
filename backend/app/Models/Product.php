<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    // id vem da fakestoreapi, não é autoincrementado
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'title', 'price', 'category', 'description', 'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}