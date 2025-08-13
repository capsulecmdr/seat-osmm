<?php

namespace CapsuleCmdr\SeatOsmm\Support\Esi;

use Illuminate\Support\Facades\DB;

/**
 * SeAT-aware token storage that:
 *  - Pulls character IDs from $user->characters (no pivot table assumptions)
 *  - Reads tokens from generic token tables if present
 *
 * Adjust the token lookups to match your live schema if they differ.
 */
class SeatRelationTokenStorage implements EsiTokenStorage
{
    /**
     * Return all linked character IDs for the given SeAT user.
     */
    public function listCharacterIdsForUser($user): array
    {
        // SeAT exposes this relation; you've used it in tinker before.
        // Falls back to [] if relation empty.
        return $user->characters
            ? $user->characters->pluck('character_id')->map(fn($v) => (int) $v)->all()
            : [];
    }

    /**
     * Fetch the token bundle for a character.
     *
     * Adjust this method to your actual token storage.
     * The example below tries common SeAT 5.x patterns without assuming a specific model.
     */
    public function getTokenFor(int $characterId): ?array
    {
        // Try a consolidated token table first (preferred if you created one)
        if ($this->tableExists('character_tokens')) {
            $row = DB::table('character_tokens')->where('character_id', $characterId)->first();
            if ($row) {
                return [
                    'character_id'  => (int) $row->character_id,
                    'access_token'  => $row->access_token ?? null,
                    'refresh_token' => $row->refresh_token ?? null,
                    'expires_at'    => $this->toUnix($row->expires_at ?? null),
                    'scopes'        => $row->scopes ?? '',
                ];
            }
        }

        // Fallback: separate token tables often used in SeAT setups.
        // Tweak these table names/columns if your schema differs.
        $refresh = null;
        if ($this->tableExists('refresh_tokens')) {
            $refresh = DB::table('refresh_tokens')->where('character_id', $characterId)->orderByDesc('updated_at')->first();
        }

        $access = null;
        if ($this->tableExists('access_tokens')) {
            $access = DB::table('access_tokens')->where('character_id', $characterId)->orderByDesc('updated_at')->first();
        }

        if ($refresh || $access) {
            return [
                'character_id'  => $characterId,
                'access_token'  => $access->access_token  ?? null,
                'refresh_token' => $refresh->refresh_token ?? null,
                'expires_at'    => $this->toUnix($access->expires_at ?? null),
                'scopes'        => $access->scopes ?? ($refresh->scopes ?? ''),
            ];
        }

        // Nothing found
        return null;
    }

    /**
     * Save/Update a token bundle back to storage.
     * If you don’t maintain your own table, you can NO-OP or write to a simple table.
     */
    public function saveToken(int $characterId, array $token): void
    {
        // Prefer a consolidated app-owned table to avoid touching SeAT’s internals.
        if ($this->tableExists('character_tokens')) {
            DB::table('character_tokens')->updateOrInsert(
                ['character_id' => $characterId],
                [
                    'access_token'  => $token['access_token']  ?? null,
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'expires_at'    => $token['expires_at']    ?? null,
                    'scopes'        => $token['scopes']        ?? '',
                    'updated_at'    => now(),
                ]
            );
            return;
        }

        // Otherwise, skip writing to unknown tables. You can log here if desired.
        // logger()->warning('No writable token table found; skipping saveToken', ['character_id' => $characterId]);
    }

    // ---- helpers ----

    private function tableExists(string $name): bool
    {
        try {
            DB::connection()->getPdo(); // ensure connection
            return DB::getSchemaBuilder()->hasTable($name);
        } catch (\Throwable) {
            return false;
        }
    }

    private function toUnix($ts): ?int
    {
        if ($ts === null) return null;
        if (is_numeric($ts)) return (int) $ts;
        $u = strtotime((string) $ts);
        return $u !== false ? $u : null;
        }
}
