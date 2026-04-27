#!/usr/bin/env bash
#
# Smoke test end-to-end pentru sprint #2.
# Validează: login → create → join → start → play → round end → leaderboard.
#
# Reset DB recomandat înainte: mysql -u root < database/schema.sql && mysql -u root < database/seed.sql

set -e
BASE=http://localhost/uno_extended/public
PHP="C:/xampp/php/php.exe"
MYSQL="C:/xampp/mysql/bin/mysql.exe"

fail() { echo "FAIL: $1" >&2; exit 1; }
ok()   { echo "  ✓ $1"; }

echo "─── 1. Login Alice/Bob/Charlie ───"
for email in alice bob charlie; do
    TOK=$(curl -sS -X POST $BASE/auth/login -H "Content-Type: application/json" \
        -d "{\"email\":\"${email}@uno.test\",\"password\":\"demo1234\"}" | $PHP -r 'echo json_decode(stream_get_contents(STDIN), true)["token"] ?? "";')
    [ -n "$TOK" ] || fail "login $email"
    eval "TOK_${email}=\"$TOK\""
    ok "$email logged in"
done

echo "─── 2. /me cu token Alice ───"
ME=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/me)
echo "$ME" | grep -q "11111111" || fail "/me sub mismatch"
ok "/me returnează userId-ul Alice"

echo "─── 3. /me FĂRĂ token → 401 ───"
CODE=$(curl -sS -o /dev/null -w "%{http_code}" $BASE/me)
[ "$CODE" = "401" ] || fail "expected 401, got $CODE"
ok "401 fără token"

echo "─── 4. Leaderboard (public, fără token) ───"
LB=$(curl -sS $BASE/leaderboard)
echo "$LB" | grep -q "Alice" || fail "leaderboard nu conține Alice"
ok "leaderboard conține Alice"

echo "─── 5. Create game (Alice host) ───"
GID=$(curl -sS -X POST -H "Authorization: Bearer $TOK_alice" -H "Content-Type: application/json" \
    -d '{"name":"Smoke","maxPlayers":3,"pointsToWin":1,"settings":{"allowStacking":true}}' \
    $BASE/games | $PHP -r 'echo json_decode(stream_get_contents(STDIN), true)["id"] ?? "";')
[ -n "$GID" ] || fail "create game"
ok "game created $GID"

echo "─── 6. Bob și Charlie join ───"
for tok in "$TOK_bob" "$TOK_charlie"; do
    CODE=$(curl -sS -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $tok" $BASE/games/$GID/players)
    [ "$CODE" = "201" ] || fail "join code=$CODE"
done
ok "Bob și Charlie au intrat"

echo "─── 7. Bob join AGAIN → 409 ───"
CODE=$(curl -sS -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $TOK_bob" $BASE/games/$GID/players)
[ "$CODE" = "409" ] || fail "expected 409, got $CODE"
ok "409 already_joined"

echo "─── 8. List players (3) ───"
N=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID/players | $PHP -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j);')
[ "$N" = "3" ] || fail "expected 3 players, got $N"
ok "3 players"

echo "─── 9. Bob încearcă start → 403 ───"
CODE=$(curl -sS -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $TOK_bob" $BASE/games/$GID/actions/start)
[ "$CODE" = "403" ] || fail "expected 403, got $CODE"
ok "403 not host"

echo "─── 10. Alice (host) pornește jocul ───"
RES=$(curl -sS -X POST -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID/actions/start)
echo "$RES" | grep -q '"success":true' || fail "start failed: $RES"
ok "game started"

echo "─── 11. /games/{id} cu phase=playing ───"
PHASE=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID | $PHP -r 'echo json_decode(stream_get_contents(STDIN),true)["phase"];')
[ "$PHASE" = "playing" ] || fail "phase=$PHASE"
ok "phase=playing"

echo "─── 12. Verify deck integrity (108 = draw + 21 hands + 1 discard) ───"
SUMS=$($MYSQL -u root uno_extended -B -N -e "SELECT
  (SELECT COUNT(*) FROM cards WHERE game_id='$GID') AS total,
  (SELECT COUNT(*) FROM draw_pile_cards WHERE game_id='$GID') AS draw,
  (SELECT COUNT(*) FROM discard_pile_cards WHERE game_id='$GID') AS disc,
  (SELECT COUNT(*) FROM player_hands ph JOIN players p ON p.id=ph.player_id WHERE p.game_id='$GID') AS hands
")
TOTAL=$(echo "$SUMS" | awk '{print $1}')
DRAW=$(echo "$SUMS" | awk '{print $2}')
DISC=$(echo "$SUMS" | awk '{print $3}')
HANDS=$(echo "$SUMS" | awk '{print $4}')
[ "$TOTAL" = "108" ] || fail "total cards = $TOTAL"
SUM=$((DRAW + DISC + HANDS))
[ "$SUM" = "$TOTAL" ] || fail "draw+disc+hands ($SUM) != total ($TOTAL)"
ok "108 = $DRAW (draw) + $DISC (discard) + $HANDS (hands)"

echo "─── 13. Hand-ul propriu ──"
PID_A=$($MYSQL -u root uno_extended -B -N -e "SELECT id FROM players WHERE game_id='$GID' AND user_id='11111111-1111-1111-1111-111111111111'")
PID_B=$($MYSQL -u root uno_extended -B -N -e "SELECT id FROM players WHERE game_id='$GID' AND user_id='22222222-2222-2222-2222-222222222222'")
HAND=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID/players/$PID_A/hand)
COUNT=$(echo "$HAND" | $PHP -r 'echo json_decode(stream_get_contents(STDIN),true)["count"];')
[ "$COUNT" = "7" ] || fail "Alice hand count=$COUNT"
ok "Alice hand = 7"

echo "─── 14. Alice tries Bob's hand → 403 ───"
CODE=$(curl -sS -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID/players/$PID_B/hand)
[ "$CODE" = "403" ] || fail "expected 403, got $CODE"
ok "403 not_own_hand"

echo "─── 15. Discard pile + draw pile ───"
TOP=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID/discard-pile | $PHP -r 'echo json_decode(stream_get_contents(STDIN),true)["topCard"]["type"];')
[ -n "$TOP" ] || fail "no top"
ok "top card type=$TOP"
DRAWCNT=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID/draw-pile | $PHP -r 'echo json_decode(stream_get_contents(STDIN),true)["cardCount"];')
ok "draw pile = $DRAWCNT cards"

echo "─── 16. Force a quick round end: Alice hand=1 only, plays it ───"
# Setup deterministic: 1 card în mâna lui Alice, top potrivit
$MYSQL -u root uno_extended <<EOF >/dev/null
DELETE FROM player_hands WHERE player_id='$PID_A';
DELETE FROM cards WHERE id='ffff1111-ffff-ffff-ffff-ffffffffffff' OR id='ffff2222-ffff-ffff-ffff-ffffffffffff';
INSERT INTO cards (id, game_id, color, type, value) VALUES
  ('ffff1111-ffff-ffff-ffff-ffffffffffff', '$GID', 'red', 'number', 7),
  ('ffff2222-ffff-ffff-ffff-ffffffffffff', '$GID', 'red', 'number', 3);
INSERT INTO player_hands (player_id, card_id) VALUES ('$PID_A', 'ffff1111-ffff-ffff-ffff-ffffffffffff');
DELETE FROM discard_pile_cards WHERE game_id='$GID';
INSERT INTO discard_pile_cards (game_id, card_id, position) VALUES ('$GID', 'ffff2222-ffff-ffff-ffff-ffffffffffff', 0);
UPDATE games SET active_color='red', pending_draw_count=0, pending_color_choice=0, current_turn_player_id='$PID_A', has_acted_this_turn=0, phase='playing' WHERE id='$GID';
EOF
RES=$(curl -sS -X POST -H "Authorization: Bearer $TOK_alice" -H "Content-Type: application/json" \
    -d '{"cardId":"ffff1111-ffff-ffff-ffff-ffffffffffff"}' $BASE/games/$GID/actions/play-card)
echo "$RES" | grep -q '"success":true' || fail "play-card failed: $RES"
ok "play-card OK (last card)"

echo "─── 17. Phase devine 'finished' (pointsToWin=1) ───"
PHASE=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID | $PHP -r 'echo json_decode(stream_get_contents(STDIN),true)["phase"];')
[ "$PHASE" = "finished" ] || fail "phase=$PHASE"
ok "phase=finished"

echo "─── 18. Scoreboard + Rounds + Leaderboard refresh ───"
SB=$(curl -sS -H "Authorization: Bearer $TOK_alice" $BASE/games/$GID/scoreboard)
echo "$SB" | grep -q "Alice" || fail "scoreboard fără Alice"
ok "scoreboard OK"

LB_COUNT=$(curl -sS $BASE/leaderboard | $PHP -r 'echo json_decode(stream_get_contents(STDIN),true)["totalCount"];')
ok "leaderboard total=$LB_COUNT"

echo "─── 19. Twig views ───"
for path in games-view leaderboard-view; do
    CODE=$(curl -sS -o /dev/null -w "%{http_code}" $BASE/$path)
    [ "$CODE" = "200" ] || fail "twig /$path → $CODE"
done
ok "Twig pages OK"

echo "─── 20. Swagger UI ───"
CODE=$(curl -sS -o /dev/null -w "%{http_code}" $BASE/swagger/)
[ "$CODE" = "200" ] || fail "swagger → $CODE"
ok "Swagger UI OK"

echo ""
echo "✓✓✓ ALL SMOKE TESTS PASSED ✓✓✓"
