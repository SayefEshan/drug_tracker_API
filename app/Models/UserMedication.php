<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMedication extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'rxcui',
        'drug_name',
        'base_names',
        'dose_form_group_names',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_names' => 'array',
        'dose_form_group_names' => 'array',
    ];

    /**
     * Get the user that owns the medication.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
