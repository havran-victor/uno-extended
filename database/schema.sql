-- UNO Extended Edition — schema DB
-- Rulează cu:  mysql -u root < database/schema.sql

DROP DATABASE IF EXISTS uno_extended;
CREATE DATABASE uno_extended CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uno_extended;

-- 1. users
CREATE TABLE users (
    id            CHAR(36)      NOT NULL PRIMARY KEY,
    email         VARCHAR(190)  NOT NULL UNIQUE,
    display_name  VARCHAR(100)  NOT NULL,
    avatar_url    VARCHAR(500)  NULL,
    password_hash VARCHAR(255)  NOT NULL,
    created_at    DATETIME(3)   NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB;

-- 2. player_stats (un row per user, agregat global)
CREATE TABLE player_stats (
    user_id          CHAR(36) NOT NULL PRIMARY KEY,
    games_played     INT      NOT NULL DEFAULT 0,
    games_won        INT      NOT NULL DEFAULT 0,
    total_points     INT      NOT NULL DEFAULT 0,
    cards_played     INT      NOT NULL DEFAULT 0,
    uno_calls_made   INT      NOT NULL DEFAULT 0,
    challenges_won   INT      NOT NULL DEFAULT 0,
    challenges_lost  INT      NOT NULL DEFAULT 0,
    CONSTRAINT fk_stats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. games
CREATE TABLE games (
    id                       CHAR(36)     NOT NULL PRIMARY KEY,
    name                     VARCHAR(150) NOT NULL,
    host_player_id           CHAR(36)     NULL,
    phase                    ENUM('lobby','playing','round_over','finished') NOT NULL DEFAULT 'lobby',
    max_players              TINYINT      NOT NULL,
    points_to_win            INT          NOT NULL DEFAULT 500,
    current_turn_player_id   CHAR(36)     NULL,
    play_direction           ENUM('clockwise','counter_clockwise') NOT NULL DEFAULT 'clockwise',
    active_color             ENUM('red','yellow','green','blue') NULL,
    current_round            INT          NOT NULL DEFAULT 0,
    pending_draw_count       INT          NOT NULL DEFAULT 0,
    pending_color_choice     TINYINT(1)   NOT NULL DEFAULT 0,
    last_action_turn         INT          NOT NULL DEFAULT 0,
    has_acted_this_turn      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at               DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at               DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    INDEX idx_games_phase (phase)
) ENGINE=InnoDB;

-- 4. game_settings
CREATE TABLE game_settings (
    game_id                CHAR(36)   NOT NULL PRIMARY KEY,
    allow_stacking         TINYINT(1) NOT NULL DEFAULT 0,
    seven_zero_rule        TINYINT(1) NOT NULL DEFAULT 0,
    allow_jump_in          TINYINT(1) NOT NULL DEFAULT 0,
    draw_until_playable    TINYINT(1) NOT NULL DEFAULT 0,
    force_play_drawn       TINYINT(1) NOT NULL DEFAULT 0,
    no_bluff_wild_draw_four TINYINT(1) NOT NULL DEFAULT 0,
    points_mode            TINYINT(1) NOT NULL DEFAULT 1,
    team_mode              TINYINT(1) NOT NULL DEFAULT 0,
    speed_mode             TINYINT(1) NOT NULL DEFAULT 0,
    turn_timer_seconds     INT        NOT NULL DEFAULT 30,
    CONSTRAINT fk_settings_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. players (un user în context de joc)
CREATE TABLE players (
    id            CHAR(36)    NOT NULL PRIMARY KEY,
    game_id       CHAR(36)    NOT NULL,
    user_id       CHAR(36)    NOT NULL,
    display_name  VARCHAR(100) NOT NULL,
    total_score   INT         NOT NULL DEFAULT 0,
    said_uno      TINYINT(1)  NOT NULL DEFAULT 0,
    is_active     TINYINT(1)  NOT NULL DEFAULT 1,
    team          VARCHAR(50) NULL,
    position      INT         NOT NULL,
    joined_at     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE KEY ux_players_game_user (game_id, user_id),
    INDEX idx_players_game (game_id),
    CONSTRAINT fk_players_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    CONSTRAINT fk_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. cards (instanțele dintr-un joc; deck-ul concret)
CREATE TABLE cards (
    id      CHAR(36) NOT NULL PRIMARY KEY,
    game_id CHAR(36) NOT NULL,
    color   ENUM('red','yellow','green','blue') NULL,
    type    ENUM('number','skip','reverse','draw_two','wild','wild_draw_four','blank_wild') NOT NULL,
    value   TINYINT  NULL,
    INDEX idx_cards_game (game_id),
    CONSTRAINT fk_cards_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. player_hands
CREATE TABLE player_hands (
    player_id CHAR(36) NOT NULL,
    card_id   CHAR(36) NOT NULL,
    PRIMARY KEY (player_id, card_id),
    INDEX idx_hand_card (card_id),
    CONSTRAINT fk_hand_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_hand_card   FOREIGN KEY (card_id)   REFERENCES cards(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. draw_pile_cards (poziție = ordine în pile, mai mare = mai aproape de top)
CREATE TABLE draw_pile_cards (
    game_id  CHAR(36) NOT NULL,
    card_id  CHAR(36) NOT NULL,
    position INT      NOT NULL,
    PRIMARY KEY (game_id, card_id),
    INDEX idx_draw_position (game_id, position),
    CONSTRAINT fk_draw_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    CONSTRAINT fk_draw_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. discard_pile_cards (poziție mai mare = mai sus, top = max(position))
CREATE TABLE discard_pile_cards (
    game_id  CHAR(36) NOT NULL,
    card_id  CHAR(36) NOT NULL,
    position INT      NOT NULL,
    PRIMARY KEY (game_id, card_id),
    INDEX idx_discard_position (game_id, position),
    CONSTRAINT fk_discard_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    CONSTRAINT fk_discard_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. turns
CREATE TABLE turns (
    id           CHAR(36)    NOT NULL PRIMARY KEY,
    game_id      CHAR(36)    NOT NULL,
    round_number INT         NOT NULL,
    turn_number  INT         NOT NULL,
    player_id    CHAR(36)    NOT NULL,
    action       ENUM('play_card','draw_card','say_uno','challenge_uno','challenge_wild_draw_four','choose_color','end_turn','stack_draw','swap_hands','pass_all_hands','jump_in') NOT NULL,
    card_id      CHAR(36)    NULL,
    details      JSON        NULL,
    timestamp    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_turns_game_round (game_id, round_number),
    INDEX idx_turns_player (player_id),
    INDEX idx_turns_action (action),
    CONSTRAINT fk_turns_game   FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
    CONSTRAINT fk_turns_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. rounds
CREATE TABLE rounds (
    game_id      CHAR(36)    NOT NULL,
    round_number INT         NOT NULL,
    winner_id    CHAR(36)    NULL,
    started_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    ended_at     DATETIME(3) NULL,
    PRIMARY KEY (game_id, round_number),
    CONSTRAINT fk_rounds_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. round_scores
CREATE TABLE round_scores (
    game_id      CHAR(36) NOT NULL,
    round_number INT      NOT NULL,
    player_id    CHAR(36) NOT NULL,
    score        INT      NOT NULL DEFAULT 0,
    PRIMARY KEY (game_id, round_number, player_id),
    CONSTRAINT fk_rscores_round  FOREIGN KEY (game_id, round_number) REFERENCES rounds(game_id, round_number) ON DELETE CASCADE,
    CONSTRAINT fk_rscores_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 13. penalties
CREATE TABLE penalties (
    id              CHAR(36)    NOT NULL PRIMARY KEY,
    game_id         CHAR(36)    NOT NULL,
    player_id       CHAR(36)    NOT NULL,
    type            ENUM('missed_uno','illegal_wild_draw_four','timeout','forfeit') NOT NULL,
    cards_penalized INT         NOT NULL DEFAULT 0,
    round_number    INT         NULL,
    timestamp       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_penalties_game (game_id),
    INDEX idx_penalties_player (player_id),
    CONSTRAINT fk_pen_game   FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
    CONSTRAINT fk_pen_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- FK separat la sfârșit pt host (referință auto pe sine)
ALTER TABLE games
    ADD CONSTRAINT fk_games_host    FOREIGN KEY (host_player_id)         REFERENCES players(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_games_current FOREIGN KEY (current_turn_player_id) REFERENCES players(id) ON DELETE SET NULL;
