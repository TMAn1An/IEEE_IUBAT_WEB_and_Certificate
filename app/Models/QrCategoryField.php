<?php

namespace App\Models;

use App\Enums\QrCategoryFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCategoryField extends Model
{
    use HasFactory;

    protected $table = 'qr_category_fields';

    protected $fillable = [
        'qr_category_id',
        'label',
        'key',
        'type',
        'required',
        'options',
        'sort_order',
        'is_recipient_name',
        'show_on_verification',
    ];

    protected function casts(): array
    {
        return [
            'type' => QrCategoryFieldType::class,
            'required' => 'boolean',
            'options' => 'array',
            'sort_order' => 'integer',
            'is_recipient_name' => 'boolean',
            'show_on_verification' => 'boolean',
        ];
    }

    /** @return BelongsTo<QrCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QrCategory::class, 'qr_category_id');
    }
}
