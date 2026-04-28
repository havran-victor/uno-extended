<?php
declare(strict_types=1);

namespace Uno\Services;

use PDO;
use Ramsey\Uuid\Uuid;
use Uno\Repositories\CardRepository;
use Uno\Repositories\HandRepository;
use Uno\Repositories\PileRepository;
use Uno\Repositories\PlayerRepository;

// deck standard UNO 108 carti: 76 number + 24 actiune + 4 wild + 4 wild_draw_four
final class DeckService
{
    public const COLORS = ['red', 'yellow', 'green', 'blue'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CardRepository $cards,
        private readonly PileRepository $piles,
        private readonly HandRepository $hands,
        private readonly PlayerRepository $players
    ) {}

    public function dealRound(string $gameId): array
    {
        // pastram cardurile in DB ca sa nu rupem FK din turns
        $this->piles->clearAll($gameId);
        $this->hands->clearForGame($gameId);

        $deck = $this->buildDeck();
        shuffle($deck);

        $this->cards->bulkInsertForGame($gameId, $deck);

        $players       = $this->players->listForGame($gameId);
        $activePlayers = array_values(array_filter($players, fn($p) => (bool) $p['is_active']));
        $cardsPerPlayer = 7;
        $dealtUntil = 0; // index exclusiv - cartile pana aici sunt in maini
        for ($i = 0; $i < $cardsPerPlayer; $i++) {
            foreach ($activePlayers as $p) {
                $this->hands->add($p['id'], $deck[$dealtUntil]['id']);
                $dealtUntil++;
            }
        }

        // prima carte number - regula UNO pentru top card initial
        $topIdx = null;
        for ($i = $dealtUntil; $i < count($deck); $i++) {
            if ($deck[$i]['type'] === 'number') {
                $topIdx = $i;
                break;
            }
        }
        $topCard = $topIdx !== null ? $deck[$topIdx] : null;

        $position = 0;
        for ($i = $dealtUntil; $i < count($deck); $i++) {
            if ($i === $topIdx) continue;
            $this->piles->pushDraw($gameId, $deck[$i]['id'], $position++);
        }

        if ($topCard !== null) {
            $this->piles->pushDiscard($gameId, $topCard['id']);
        }

        $activeColor = $topCard !== null && in_array($topCard['type'], ['number','skip','reverse','draw_two'], true)
            ? $topCard['color']
            : null;

        return ['topCard' => $topCard, 'activeColor' => $activeColor];
    }

    public function buildDeck(): array
    {
        $cards = [];
        foreach (self::COLORS as $color) {
            // 1x0
            $cards[] = $this->card($color, 'number', 0);
            // 2x1-9
            for ($v = 1; $v <= 9; $v++) {
                $cards[] = $this->card($color, 'number', $v);
                $cards[] = $this->card($color, 'number', $v);
            }
            // 2x skip reverse draw_two
            for ($i = 0; $i < 2; $i++) {
                $cards[] = $this->card($color, 'skip', null);
                $cards[] = $this->card($color, 'reverse', null);
                $cards[] = $this->card($color, 'draw_two', null);
            }
        }
        for ($i = 0; $i < 4; $i++) {
            $cards[] = $this->card(null, 'wild', null);
            $cards[] = $this->card(null, 'wild_draw_four', null);
        }
        return $cards;
    }

    private function card(?string $color, string $type, ?int $value): array
    {
        return [
            'id'    => Uuid::uuid4()->toString(),
            'color' => $color,
            'type'  => $type,
            'value' => $value,
        ];
    }

    // daca draw pile e gol reshuffleaza discardúl inainte sa tragi
    public function drawOne(string $gameId): ?array
    {
        $card = $this->piles->popDraw($gameId);
        if ($card !== null) return $card;

        $this->piles->reshuffleDiscardIntoDraw($gameId);
        return $this->piles->popDraw($gameId);
    }
}
