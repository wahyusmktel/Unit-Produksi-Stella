<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'product_category_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'price',
        'stock',
        'unit',
        'image_path',
        'status',
        'is_featured',
    ];

    protected $appends = [
        'image_url',
        'image_urls',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('images')) {
            return $this->images->first()?->image_url;
        }

        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }

    /**
     * @return array<int, string>
     */
    public function getImageUrlsAttribute(): array
    {
        if ($this->relationLoaded('images')) {
            return $this->images->pluck('image_url')->values()->all();
        }

        return $this->image_url ? [$this->image_url] : [];
    }
}
