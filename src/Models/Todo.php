<?php 
namespace CapsuleCmdr\SeatOsmm\Models;

use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    public $timestamps = false;                 // we only store created_at
    protected $table = 'osmm_todos';
    protected $fillable = ['user_id', 'text'];
}
