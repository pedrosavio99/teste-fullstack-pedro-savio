<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use SoftDeletes; // ativa o deleted_at (exclusão lógica)

    // id vem da fakestoreapi (cart.id), não é autoincrementado
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'affiliate_id', 'status', 'total_value', 'ordered_at',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'ordered_at'  => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }
}