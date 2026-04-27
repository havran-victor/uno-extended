-- Seed minim — 3 useri demo + statisticile lor.
-- Parolele sunt 'demo1234' bcrypt-ate (nu folosi în prod).
USE uno_extended;

-- Cleanup (idempotent)
DELETE FROM penalties;
DELETE FROM round_scores;
DELETE FROM rounds;
DELETE FROM turns;
DELETE FROM player_hands;
DELETE FROM draw_pile_cards;
DELETE FROM discard_pile_cards;
DELETE FROM cards;
DELETE FROM players;
DELETE FROM game_settings;
DELETE FROM games;
DELETE FROM player_stats;
DELETE FROM users;

-- 3 useri demo. Hash-ul corespunde parolei 'demo1234'
INSERT INTO users (id, email, display_name, password_hash) VALUES
    ('11111111-1111-1111-1111-111111111111', 'alice@uno.test',   'Alice',   '$2y$10$08zvEodh7U6JsJAGMV1T7e/PvBnRSjw1gefP0InmGtwPY5c8w/PQO'),
    ('22222222-2222-2222-2222-222222222222', 'bob@uno.test',     'Bob',     '$2y$10$08zvEodh7U6JsJAGMV1T7e/PvBnRSjw1gefP0InmGtwPY5c8w/PQO'),
    ('33333333-3333-3333-3333-333333333333', 'charlie@uno.test', 'Charlie', '$2y$10$08zvEodh7U6JsJAGMV1T7e/PvBnRSjw1gefP0InmGtwPY5c8w/PQO');

INSERT INTO player_stats (user_id, games_played, games_won, total_points, cards_played, uno_calls_made) VALUES
    ('11111111-1111-1111-1111-111111111111', 10, 4, 1200, 80, 3),
    ('22222222-2222-2222-2222-222222222222',  8, 3,  900, 60, 2),
    ('33333333-3333-3333-3333-333333333333',  5, 1,  300, 30, 1);
