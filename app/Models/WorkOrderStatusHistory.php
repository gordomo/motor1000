<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderStatusHistory extends Model
{
    protected $table = 'work_order_status_history';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'work_order_id',
        'from_status',
        'to_status',
        'comment',
        'user_id',
        'mechanic_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Qué mecánico hizo el cambio (elegido en el totem del taller). */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }
}
