<?php
namespace CapsuleCmdr\SeatOsmm\Models;

use Illuminate\Database\Eloquent\Model;

class OsmmMenuOverride extends Model
{
    protected $table = 'osmm_menu_overrides';
    protected $fillable = [
        'item_key','label_override','permission_override','icon_override',
        'route_override','visible','order_override',
    ];
}
