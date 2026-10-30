<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['question', 'options', 'answer', 'translations', 'bank_key', 'category', 'source_url', 'child_friendly', 'difficulty'];

    protected $casts = [
        'options' => 'array',
        'translations' => 'array',
        'child_friendly' => 'boolean',
    ];

    /** @param Builder<static> $query */
    public function scopeInCategory(Builder $query, string $category): void
    {
        if ($category === 'kids') {
            $query->where('child_friendly', true);
        } else {
            $query->whereIn('difficulty', ['medium', 'hard']);
            if ($category !== 'general') {
                $query->where('category', $category);
            }
        }
    }

    public function forLocale(string $locale): static
    {
        $localized = clone $this;
        $translation = $this->translations[$locale] ?? null;
        if (is_array($translation)) {
            $localized->fill(array_intersect_key($translation, array_flip(['question', 'options', 'answer'])));
        }

        return $localized;
    }
}
