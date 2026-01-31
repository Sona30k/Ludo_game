"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.LudoEngine = void 0;
const types_1 = require("./types");
const board_1 = require("./board");
const scoring_1 = require("./scoring");
class LudoEngine {
    static createMatch(config, playerAId, playerBId, seed) {
        const boardConfig = { ...board_1.DEFAULT_BOARD_CONFIG, ...config };
        const players = [
            {
                playerId: playerAId,
                score: 0,
                captures: 0,
                tokensHome: 0,
                lastCaptureByOpponentMap: new Map(),
                comboState: { lastActionTime: 0, chain: [] },
                consecutiveSixCount: 0,
            },
            {
                playerId: playerBId,
                score: 0,
                captures: 0,
                tokensHome: 0,
                lastCaptureByOpponentMap: new Map(),
                comboState: { lastActionTime: 0, chain: [] },
                consecutiveSixCount: 0,
            },
        ];
        const tokens = [];
        for (const player of players) {
            for (let i = 0; i < 4; i++) {
                tokens.push({
                    tokenId: `${player.playerId}-token-${i}`,
                    position: { type: types_1.PositionType.BASE },
                    stackedWith: [],
                });
            }
        }
        return {
            matchId: `match-${Date.now()}`,
            phase: types_1.Phase.NORMAL_PLAY,
            startTime: 0,
            remainingMs: 150000, // 150 seconds
            currentPlayerId: playerAId,
            players,
            board: { config: boardConfig, tokens },
            logs: [],
            seed,
        };
    }
    static startMatch(state, nowMs) {
        state.startTime = nowMs;
        const events = [{
                type: types_1.EventType.MATCH_STARTED,
                timestamp: nowMs,
                payload: { matchId: state.matchId }
            }];
        return { state, events };
    }
    static tick(state, nowMs) {
        const elapsed = nowMs - state.startTime;
        state.remainingMs = Math.max(0, 150000 - elapsed);
        const events = [];
        if (state.remainingMs === 0 && state.phase === types_1.Phase.NORMAL_PLAY) {
            state.phase = types_1.Phase.FINAL_MOVES;
            events.push({
                type: types_1.EventType.FINAL_MOVES_STARTED,
                timestamp: nowMs,
                payload: {}
            });
        }
        return { state, events };
    }
    static rollDice(state, playerId, nowMs, overrideValue) {
        if (state.currentPlayerId !== playerId)
            throw new Error('Not your turn');
        if (state.phase === types_1.Phase.MATCH_ENDED)
            throw new Error('Match ended');
        // Deterministic dice roll based on seed and turn, or use override
        let diceValue;
        if (overrideValue !== undefined && overrideValue >= 1 && overrideValue <= 6) {
            diceValue = overrideValue;
        }
        else {
            const turnNumber = state.logs.filter(e => e.type === types_1.EventType.DICE_ROLLED).length;
            diceValue = (parseInt(state.seed, 36) + turnNumber) % 6 + 1;
        }
        const player = state.players.find(p => p.playerId === playerId);
        let extraTurn = false;
        let bonus = 0;
        const events = [{
                type: types_1.EventType.DICE_ROLLED,
                timestamp: nowMs,
                playerId,
                payload: { diceValue }
            }];
        if (diceValue === 6) {
            player.consecutiveSixCount++;
            if (player.consecutiveSixCount === 2) {
                bonus += 15;
                events.push({
                    type: types_1.EventType.DOUBLE_SIX_BONUS,
                    timestamp: nowMs,
                    playerId,
                    payload: { bonus: 15 }
                });
            }
            else if (player.consecutiveSixCount === 3) {
                bonus -= 10;
                events.push({
                    type: types_1.EventType.THIRD_SIX_PENALTY,
                    timestamp: nowMs,
                    playerId,
                    payload: { bonus: -10 }
                });
                extraTurn = false; // no extra turn
            }
            else {
                extraTurn = state.phase === types_1.Phase.NORMAL_PLAY;
            }
        }
        else {
            player.consecutiveSixCount = 0;
        }
        if (bonus !== 0) {
            events.push((0, scoring_1.addScore)(player, bonus, 'Dice bonus/penalty', nowMs));
        }
        if (!extraTurn || state.phase === types_1.Phase.FINAL_MOVES) {
            // Switch turn
            const otherPlayerId = state.players.find(p => p.playerId !== playerId).playerId;
            state.currentPlayerId = otherPlayerId;
            events.push({
                type: types_1.EventType.TURN_CHANGED,
                timestamp: nowMs,
                payload: { newPlayer: otherPlayerId }
            });
        }
        return { state, diceValue, events };
    }
    static getLegalMoves(state, playerId, diceValue) {
        const tokens = state.board.tokens.filter(t => t.tokenId.startsWith(playerId));
        const moves = [];
        for (const token of tokens) {
            if (token.position.type === types_1.PositionType.BASE && diceValue === 6) {
                const to = (0, board_1.getNextPosition)(token.position, diceValue, playerId, state.board.config);
                moves.push({
                    tokenId: token.tokenId,
                    from: token.position,
                    to,
                    pathIndicesTraversed: (0, board_1.getPathIndices)(token.position, to, state.board.config)
                });
            }
            else if (token.position.type !== types_1.PositionType.BASE && token.position.type !== types_1.PositionType.HOME) {
                const to = (0, board_1.getNextPosition)(token.position, diceValue, playerId, state.board.config);
                moves.push({
                    tokenId: token.tokenId,
                    from: token.position,
                    to,
                    pathIndicesTraversed: (0, board_1.getPathIndices)(token.position, to, state.board.config)
                });
            }
        }
        return moves;
    }
    static applyMove(state, playerId, move, nowMs) {
        const token = state.board.tokens.find(t => t.tokenId === move.tokenId);
        const player = state.players.find(p => p.playerId === playerId);
        const events = [];
        // Move token
        token.position = move.to;
        events.push({
            type: types_1.EventType.TOKEN_MOVED,
            timestamp: nowMs,
            playerId,
            payload: { tokenId: move.tokenId, from: move.from, to: move.to }
        });
        // Scoring
        const moveScore = (0, scoring_1.calculateMovementScore)(move, state.board.config);
        if (moveScore > 0) {
            events.push((0, scoring_1.addScore)(player, moveScore, 'Movement', nowMs));
        }
        // Capture
        const captured = state.board.tokens.filter(t => t.tokenId !== move.tokenId &&
            t.position.type === move.to.type &&
            t.position.index === move.to.index &&
            !t.tokenId.startsWith(playerId));
        if (captured.length > 0) {
            for (const cap of captured) {
                cap.position = { type: types_1.PositionType.BASE };
                player.captures++;
                const isRevenge = player.lastCaptureByOpponentMap.get(cap.tokenId.split('-')[0]) || false;
                const capScore = (0, scoring_1.calculateCaptureScore)(captured.length, false, isRevenge); // assume not on star
                events.push((0, scoring_1.addScore)(player, capScore, 'Capture', nowMs));
                events.push({
                    type: types_1.EventType.TOKEN_CAPTURED,
                    timestamp: nowMs,
                    playerId,
                    payload: { capturedTokenId: cap.tokenId, score: capScore }
                });
                // Mark revenge for opponent
                const opponentId = cap.tokenId.split('-')[0];
                const opponent = state.players.find(p => p.playerId === opponentId);
                opponent.lastCaptureByOpponentMap.set(playerId, true);
            }
        }
        // Home
        if (move.to.type === types_1.PositionType.HOME) {
            const tokensHomeBefore = player.tokensHome;
            player.tokensHome++;
            const { score, events: homeEvents } = (0, scoring_1.calculateHomeBonuses)(tokensHomeBefore, player.tokensHome, state.remainingMs);
            events.push(...homeEvents.map(e => ({ ...e, playerId })));
            if (score > 0) {
                events.push((0, scoring_1.addScore)(player, score, 'Home bonus', nowMs));
            }
        }
        // Combo
        const comboResult = (0, scoring_1.checkCombo)(player, 'MOVE', nowMs);
        if (comboResult.bonus > 0) {
            events.push((0, scoring_1.addScore)(player, comboResult.bonus, 'Combo', nowMs));
            events.push(comboResult.event);
        }
        // Final moves
        if (state.phase === types_1.Phase.FINAL_MOVES) {
            player.finalMoveTimeMs = nowMs - (state.startTime + 150000);
            // After both have moved, end match
            const bothMoved = state.players.every(p => p.finalMoveTimeMs !== undefined);
            if (bothMoved) {
                state.phase = types_1.Phase.MATCH_ENDED;
                const { winnerId, ranking, events: endEvents } = LudoEngine.endMatch(state, nowMs);
                events.push(...endEvents);
            }
        }
        state.logs.push(...events);
        return { state, events };
    }
    static endMatch(state, nowMs) {
        const ranking = LudoEngine.getRanking(state);
        const winnerId = ranking[0].playerId;
        const events = [{
                type: types_1.EventType.MATCH_ENDED,
                timestamp: nowMs,
                payload: { winnerId, ranking }
            }];
        return { winnerId, ranking, events };
    }
    static getRanking(state) {
        const rankings = state.players.map(p => ({
            playerId: p.playerId,
            score: p.score,
            captures: p.captures,
            tokensHome: p.tokensHome,
            finalMoveTimeMs: p.finalMoveTimeMs,
            highestComboBonus: 0, // TODO: track max combo
            rank: 0
        }));
        rankings.sort((a, b) => {
            if (a.score !== b.score)
                return b.score - a.score;
            if (a.captures !== b.captures)
                return b.captures - a.captures;
            if (a.tokensHome !== b.tokensHome)
                return b.tokensHome - a.tokensHome;
            if (a.finalMoveTimeMs && b.finalMoveTimeMs)
                return a.finalMoveTimeMs - b.finalMoveTimeMs;
            if (a.highestComboBonus !== b.highestComboBonus)
                return b.highestComboBonus - a.highestComboBonus;
            // Coin flip
            const seed = parseInt(state.seed, 36) + a.playerId.charCodeAt(0);
            return (seed % 2 === 0) ? -1 : 1;
        });
        rankings.forEach((r, i) => r.rank = i + 1);
        return rankings;
    }
}
exports.LudoEngine = LudoEngine;
//# sourceMappingURL=engine.js.map