<?php

namespace CapsuleCmdr\SeatOsmm\Support\Esi;

use Illuminate\Support\Facades\DB;

class LaravelDbEsiTokenStorage implements EsiTokenStorage
{
    public function __construct(
        private string $tokenTable = 'character_tokens',
        private string $pivotTable = 'user_characters'
    ) {}

    public function getTokenFor(int $characterId): ?array
    {
        $row = DB::table($this->tokenTable)->where('character_id', $characterId)->first();
        if (!$row) return null;

        return [
            'character_id'  => (int) $row->character_id,
            'access_token'  => $row->access_token ?? null,
            'refresh_token' => $row->refresh_token ?? null,
            'expires_at'    => is_numeric($row->expires_at ?? null)
                                ? (int) $row->expires_at
                                : ($row->expires_at ? strtotime($row->expires_at) : 0),
            'scopes'        => $row->scopes ?? '',
        ];
    }

    public function saveToken(int $characterId, array $token): void
    {
        DB::table($this->tokenTable)->updateOrInsert(
            ['character_id' => $characterId],
            [
                'access_token'  => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at'    => $token['expires_at'] ?? null,
                'scopes'        => $token['scopes'] ?? '',
                'updated_at'    => now(),
            ]
        );
    }

    public function listCharacterIdsForUser($user): array
    {
        return DB::table($this->pivotTable)
            ->where('user_id', $user->id)
            ->pluck('character_id')
            ->map(fn($v) => (int) $v)
            ->all();
    }
}
