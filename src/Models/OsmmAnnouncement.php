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

    public static function refreshAllComputedStatus(): array
    {
        $now = now('UTC');

        // 1) Expire anything that has ended
        $expired = static::query()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->where('status', '!=', 'expired')
            ->update([
                'status'     => 'expired',
                'updated_at' => now(),
            ]);

        // 2) Mark future items as 'scheduled' (optional but nice for clarity)
        $scheduled = static::query()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', $now)
            ->whereNotIn('status', ['scheduled', 'expired'])
            ->update([
                'status'     => 'scheduled',
                'updated_at' => now(),
            ]);

        // 3) Activate anything that should be active now
        //    (started, not yet ended, and currently new/scheduled)
        $active = static::query()
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                ->orWhere('ends_at', '>', $now);
            })
            ->whereIn('status', ['new', 'scheduled'])
            ->update([
                'status'     => 'active',
                'updated_at' => now(),
            ]);

        return compact('expired', 'scheduled', 'active');
    }

    /** Scope: active/not-expired to banner */
    public function scopeBannerable($q) {
        return $q->where('show_banner', true)
                 ->whereIn('status', ['new','active'])
                 ->orderByRaw("COALESCE(starts_at, created_at) DESC");
    }

    public function scopeActive($q)
    {
        $now = \Carbon\Carbon::now();

        return $q->where('status', '!=', 'expired')
                ->where(function ($query) use ($now) {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                });
    }
}
