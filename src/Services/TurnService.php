<?php
declare(strict_types=1);

namespace Uno\Services;

use Uno\Repositories\PlayerRepository;

final class TurnService
{
    public function __construct(private readonly PlayerRepository $players) {}

    // calcul modular al pozitiei urmatoare tinand cont de directie si skip-uri
    public function nextPlayerId(string $gameId, string $currentPlayerId, string $direction, int $skip = 0): string
    {
        $players = $this->players->listForGame($gameId);
        $active  = array_values(array_filter($players, fn($p) => (bool) $p['is_active']));
        $count   = count($active);
        $idx     = -1;
        foreach ($active as $i => $p) {
            if ($p['id'] === $currentPlayerId) { $idx = $i; break; }
        }
        if ($idx === -1) {
            return $active[0]['id'];
        }
        $step = $direction === 'clockwise' ? 1 : -1;
        $delta = $step * (1 + $skip);
        $next = (($idx + $delta) % $count + $count) % $count;
        return $active[$next]['id'];
    }

    public function reverseDirection(string $direction): string
    {
        return $direction === 'clockwise' ? 'counter_clockwise' : 'clockwise';
    }
}
