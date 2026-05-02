<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceCatalog extends Model
{
    use HasFactory;

    protected $table = 'service_catalog';

    protected static function booted(): void
    {
        static::creating(function (ServiceCatalog $catalog) {
            if (empty($catalog->code)) {
                $catalog->code = static::generateUniqueCode($catalog->name ?? 'SVC');
            }
        });
    }

    public static function generateUniqueCode(string $name): string
    {
        $base = strtoupper(Str::slug($name, ''));
        $base = substr($base ?: 'SVC', 0, 8);
        $n    = 1;
        do {
            $code = $base . str_pad($n++, 3, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    protected $fillable = [
        'department', 'name', 'code', 'hs_code', 'description', 'category', 'price',
        'turnaround_time', 'is_active', 'default_parameters',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'default_parameters' => 'json',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'service_catalog_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }
}
