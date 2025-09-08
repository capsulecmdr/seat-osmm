<?php

namespace CapsuleCmdr\SeatOsmm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OsmmAnnouncement extends Model
{
    protected $table = 'osmm_announcements';
    protected $fillable = [
        'title','content','status','starts_at','ends_at','show_banner','send_to_discord'
    ];
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'show_banner' => 'bool',
        'send_to_discord' => 'bool',
    ];

    /** Is visible “now” based on status/schedule */
    public function getIsVisibleAttribute(): bool
    {
        $now = Carbon::now('UTC');
        // Scheduled window logic
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at   && $now->gte($this->ends_at))  return false;
        // Status logic: show if status is 'active' or 'new' that has started
        if ($this->status === 'active') return true;
        if ($this->status === 'new' && (!$this->starts_at || $now->gte($this->starts_at))) return true;
        return false;
    }

    /** Auto-advance status based on schedule (optional to call in controller) */
    public function refreshComputedStatus(): void
    {
        $now = now('UTC');
        if ($this->ends_at && $now->gte($this->ends_at) && $this->status !== 'expired') {
            $this->status = 'expired';
            $this->save();
        } elseif ($this->starts_at && $now->gte($this->starts_at) && $this->status === 'new') {
            $this->status = 'active';
            $this->save();
        }
    }

    /** Scope: active/not-expired to banner */
    public function scopeBannerable($q) {
        return $q->where('show_banner', true)
                 ->whereIn('status', ['new','active'])
                 ->orderByRaw("COALESCE(starts_at, created_at) DESC");
    }

    public function scopeActive($query)
    {
        $now = \Carbon\Carbon::now();
        return $query->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now);
    }
}
