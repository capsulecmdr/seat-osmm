<?php

namespace CapsuleCmdr\SeatOsmm\Support\Esi;

interface EsiTokenStorage
{
    public function getTokenFor(int $characterId): ?array;
    public function saveToken(int $characterId, array $token): void;
    public function listCharacterIdsForUser($user): array;
}
