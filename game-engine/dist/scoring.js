"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.calculateMovementScore = calculateMovementScore;
exports.calculateCaptureScore = calculateCaptureScore;
exports.calculateHomeBonuses = calculateHomeBonuses;
exports.checkCombo = checkCombo;
exports.addScore = addScore;
const types_1 = require("./types");
const board_1 = require("./board");
function calculateMovementScore(move, config) {
    let score = 0;
    // +1 per cell moved
    const cellsMoved = move.pathIndicesTraversed.length;
    score += cellsMoved;
    // Land on safe/star: +5
    if ((0, board_1.isStar)(move.to, config)) {
        score += 5;
    }
    // Pass over safe/star: +2 for each star passed
    const starsPassed = move.pathIndicesTraversed.filter(idx => config.starPositions.includes(idx)).length;
    score += starsPassed * 2;
    // Entering home stretch: +10 (first time)
    if (move.from.type === types_1.PositionType.TRACK && move.to.type === types_1.PositionType.HOME_STRETCH) {
        score += 10;
    }
    // Final home: +50 (handled in home bonuses)
    return score;
}
function calculateCaptureScore(capturedTokens, isOnStar, isRevenge) {
    let score = 0;
    if (capturedTokens === 1) {
        score += isOnStar ? 30 : 20;
    }
    else if (capturedTokens === 2) {
        score += 40; // double capture
    }
    if (isRevenge) {
        score += 25;
    }
    return score;
}
function calculateHomeBonuses(tokensHomeBefore, tokensHomeAfter, timerRemaining) {
    const events = [];
    let score = 0;
    if (tokensHomeAfter > tokensHomeBefore) {
        const newTokens = tokensHomeAfter - tokensHomeBefore;
        for (let i = 0; i < newTokens; i++) {
            const bonus = tokensHomeBefore + i === 0 ? 50 : 40;
            score += bonus;
            events.push({
                type: types_1.EventType.TOKEN_HOME,
                timestamp: Date.now(),
                playerId: '', // set later
                payload: { bonus, tokenIndex: tokensHomeBefore + i }
            });
        }
    }
    if (tokensHomeAfter === 4) {
        score += 100; // completion bonus
        if (timerRemaining > 0) {
            score += 30; // speed bonus
        }
    }
    return { score, events };
}
function checkCombo(player, action, nowMs) {
    const comboWindow = 5000; // 5 seconds
    if (nowMs - player.comboState.lastActionTime > comboWindow) {
        player.comboState.chain = [action];
    }
    else {
        player.comboState.chain.push(action);
    }
    player.comboState.lastActionTime = nowMs;
    let bonus = 0;
    if (player.comboState.chain.includes('MOVE') && player.comboState.chain.includes('CAPTURE')) {
        bonus += 10;
    }
    if (player.comboState.chain.includes('CAPTURE') && player.comboState.chain.includes('SAFE')) {
        bonus += 8;
    }
    if (player.comboState.chain.includes('CAPTURE') && player.comboState.chain.includes('HOME')) {
        bonus += 20;
    }
    if (player.comboState.chain.length >= 3) {
        bonus += 30;
    }
    if (bonus > 0) {
        return {
            bonus,
            event: {
                type: types_1.EventType.COMBO_BONUS_AWARDED,
                timestamp: nowMs,
                playerId: player.playerId,
                payload: { bonus, chain: [...player.comboState.chain] }
            }
        };
    }
    return { bonus: 0 };
}
function addScore(player, delta, reason, nowMs) {
    player.score += delta;
    return {
        type: types_1.EventType.SCORE_ADDED,
        timestamp: nowMs,
        playerId: player.playerId,
        payload: { delta, reason, newScore: player.score }
    };
}
//# sourceMappingURL=scoring.js.map