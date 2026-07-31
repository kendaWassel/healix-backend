<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Specialization extends Model
{
    use HasFactory, HasLocalizedColumns;

    /**
     * `name` stays the canonical English value and the stable lookup key used
     * by registration and by the AI service's `recommended_specialty`.
     * Reading `$specialization->name` returns the Arabic value when the request
     * locale is Arabic; `name_ar` and the raw English are still addressable.
     */
    protected array $localizable = ['name'];

    protected $fillable = [
        'name',
        'name_ar',
    ];

    public function doctors() {
        return $this->hasMany(Doctor::class);
    }

}
