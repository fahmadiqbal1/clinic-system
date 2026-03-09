<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCatalog extends Model
{
    use HasFactory;

    protected $table = 'service_catalog';

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
