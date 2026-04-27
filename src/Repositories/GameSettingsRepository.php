<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class GameSettingsRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function find(string $gameId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM game_settings WHERE game_id = ?');
        $st->execute([$gameId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function insert(string $gameId, array $settings): void
    {
        $cols = [
            'allow_stacking', 'seven_zero_rule', 'allow_jump_in', 'draw_until_playable',
            'force_play_drawn', 'no_bluff_wild_draw_four', 'points_mode', 'team_mode',
            'speed_mode', 'turn_timer_seconds',
        ];

        $values = [
            (int) ($settings['allowStacking']        ?? 0),
            (int) ($settings['sevenZeroRule']        ?? 0),
            (int) ($settings['allowJumpIn']          ?? 0),
            (int) ($settings['drawUntilPlayable']    ?? 0),
            (int) ($settings['forcePlayDrawn']       ?? 0),
            (int) ($settings['noBluffWildDrawFour']  ?? 0),
            (int) ($settings['pointsMode']           ?? 1),
            (int) ($settings['teamMode']             ?? 0),
            (int) ($settings['speedMode']            ?? 0),
            (int) ($settings['turnTimerSeconds']     ?? 30),
        ];

        $sql = 'INSERT INTO game_settings (game_id, ' . implode(', ', $cols) . ') VALUES (?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $st  = $this->pdo->prepare($sql);
        $st->execute(array_merge([$gameId], $values));
    }

    public static function toApi(array $row): array
    {
        return [
            'allowStacking'         => (bool) $row['allow_stacking'],
            'sevenZeroRule'         => (bool) $row['seven_zero_rule'],
            'allowJumpIn'           => (bool) $row['allow_jump_in'],
            'drawUntilPlayable'     => (bool) $row['draw_until_playable'],
            'forcePlayDrawn'        => (bool) $row['force_play_drawn'],
            'noBluffWildDrawFour'   => (bool) $row['no_bluff_wild_draw_four'],
            'pointsMode'            => (bool) $row['points_mode'],
            'teamMode'              => (bool) $row['team_mode'],
            'speedMode'             => (bool) $row['speed_mode'],
            'turnTimerSeconds'      => (int)  $row['turn_timer_seconds'],
        ];
    }
}
