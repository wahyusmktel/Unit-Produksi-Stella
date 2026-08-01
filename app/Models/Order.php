<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'public_token', 'order_number', 'user_id', 'customer_name',
        'customer_email', 'customer_phone', 'payment_method', 'payment_status',
        'status', 'subtotal', 'total', 'profit_total', 'payment_reference',
        'qris_payload', 'qris_image', 'qris_expires_at', 'notes', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'profit_total' => 'decimal:2',
            'qris_expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }
}
