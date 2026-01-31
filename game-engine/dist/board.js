"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.DEFAULT_BOARD_CONFIG = void 0;
exports.isStar = isStar;
exports.isSafe = isSafe;
exports.getNextPosition = getNextPosition;
exports.getPathIndices = getPathIndices;
exports.canCapture = canCapture;
const types_1 = require("./types");
// Assumptions:
// - Shared circular track of 52 cells.
// - Players have separate home stretches.
// - Stars and safe cells as specified.
// - Captures occur on shared track when landing on opponent's token, unless safe.
// - Home stretch is separate per player, no captures there.
exports.DEFAULT_BOARD_CONFIG = {
    trackLength: 52,
    starPositions: [5, 12, 17, 22, 29, 34, 39, 46], // example positions
    homeStretchLength: 6,
    safePositions: [0, 13, 26, 39], // example safe cells
};
function isStar(position, config) {
    return position.type === types_1.PositionType.TRACK && config.starPositions.includes(position.index);
}
function isSafe(position, config) {
    return position.type === types_1.PositionType.TRACK && config.safePositions.includes(position.index);
}
function getNextPosition(current, steps, playerId, config) {
    if (current.type === types_1.PositionType.BASE) {
        // Moving from base to track start
        return { type: types_1.PositionType.TRACK, index: 0 };
    }
    else if (current.type === types_1.PositionType.TRACK) {
        const newIndex = (current.index + steps) % config.trackLength;
        if (newIndex < current.index) {
            // Wrapped around, enter home stretch
            return { type: types_1.PositionType.HOME_STRETCH, index: 0 };
        }
        return { type: types_1.PositionType.TRACK, index: newIndex };
    }
    else if (current.type === types_1.PositionType.HOME_STRETCH) {
        const newIndex = current.index + steps;
        if (newIndex >= config.homeStretchLength) {
            return { type: types_1.PositionType.HOME };
        }
        return { type: types_1.PositionType.HOME_STRETCH, index: newIndex };
    }
    return current; // HOME or invalid
}
function getPathIndices(from, to, config) {
    // Simplified: only track indices
    if (from.type === types_1.PositionType.TRACK && to.type === types_1.PositionType.TRACK) {
        const indices = [];
        let current = from.index;
        const end = to.index;
        while (current !== end) {
            indices.push(current);
            current = (current + 1) % config.trackLength;
        }
        return indices;
    }
    return [];
}
function canCapture(attacker, defender, config) {
    // Cannot capture on safe cells or if stacked
    if (defender.stackedWith.length > 0 || isSafe(defender.position, config)) {
        return false;
    }
    // Assume different players
    return true;
}
//# sourceMappingURL=board.js.map