<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SoapKeyword extends Model
{
    protected $fillable = [
        'section',
        'display_text',
        'canonical_key',
        'specialty',
        'doctor_id',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'doctor_id'   => 'integer',
    ];

    /**
     * Returns chips visible to this doctor: their own private chips plus all global chips.
     */
    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where(function (Builder $q) use ($doctorId) {
            $q->where('doctor_id', $doctorId)->orWhereNull('doctor_id');
        });
    }

    /**
     * Normalise a display string to a stable deduplication key.
     * Strips numbers, common duration words, and non-alphabetic characters.
     *
     * Example: "Fever 3 days" → "fever"
     */
    public static function canonicalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        // Remove standalone numbers
        $text = preg_replace('/\b\d+\b/', '', $text);
        // Remove common temporal / filler words that carry no diagnostic meaning
        $text = preg_replace(
            '/\b(day|days|week|weeks|month|months|hour|hours|year|years|since|for|the|a|an|of|with|and|or|in|on|at|to|from|since|ago)\b/',
            '',
            $text,
        );
        // Keep only a-z and spaces
        $text = preg_replace('/[^a-z\s]/u', '', $text);
        // Collapse multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
