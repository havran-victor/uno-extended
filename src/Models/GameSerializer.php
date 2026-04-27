<?php
declare(strict_types=1);

namespace Uno\Models;

use Uno\Repositories\GameSettingsRepository;

final class GameSerializer
{
    public static function toApi(array $game, ?array $settings = null): array
    {
        $payload = [
            'id'                    => $game['id'],
            'name'                  => $game['name'],
            'hostPlayerId'          => $game['host_player_id'],
            'phase'                 => $game['phase'],
            'maxPlayers'            => (int) $game['max_players'],
            'pointsToWin'           => (int) $game['points_to_win'],
            'currentTurnPlayerId'   => $game['current_turn_player_id'],
            'playDirection'         => $game['play_direction'],
            'activeColor'           => $game['active_color'],
            'currentRound'          => (int) $game['current_round'],
            'pendingDrawCount'      => (int) $game['pending_draw_count'],
            'createdAt'             => date(DATE_ATOM, strtotime($game['created_at'])),
            'updatedAt'             => date(DATE_ATOM, strtotime($game['updated_at'])),
        ];
        if ($settings !== null) {
            $payload['settings'] = GameSettingsRepository::toApi($settings);
        }
        return $payload;
    }

    public static function playerToApi(array $player, int $cardCount): array
    {
        return [
            'id'          => $player['id'],
            'userId'      => $player['user_id'],
            'displayName' => $player['display_name'],
            'cardCount'   => $cardCount,
            'totalScore'  => (int) $player['total_score'],
            'saidUno'     => (bool) $player['said_uno'],
            'isActive'    => (bool) $player['is_active'],
            'team'        => $player['team'],
            'joinedAt'    => date(DATE_ATOM, strtotime($player['joined_at'])),
        ];
    }

    public static function cardToApi(array $card): array
    {
        return [
            'id'    => $card['id'],
            'color' => $card['color'],
            'type'  => $card['type'],
            'value' => $card['value'] !== null ? (int) $card['value'] : null,
        ];
    }
}
