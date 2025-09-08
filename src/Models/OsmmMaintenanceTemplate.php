<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsmmMaintenanceTemplate extends Model
{
    protected $table = 'osmm_maintenance_templates';

    protected $fillable = [
        'name', 'reason', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
