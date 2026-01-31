/**
 * Utility helper functions
 */

/**
 * Generate random dice value (1-6)
 */
function rollDice() {
    return Math.floor(Math.random() * 6) + 1;
}

/**
 * Parse time limit string to seconds
 * @param {string} timeLimit - e.g., "3-min", "5-min", "10-min"
 * @returns {number} - seconds
 */
function parseTimeLimit(timeLimit) {
    const match = timeLimit.match(/(\d+)-min/);
    if (match) {
        return parseInt(match[1]) * 60; // Convert minutes to seconds
    }
    return 180; // Default 3 minutes
}

/**
 * Format seconds to MM:SS
 */
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

/**
 * Calculate points for moving a pawn
 * @param {number} blocksMoved - Number of blocks moved
 * @returns {number} - Points earned
 */
function calculateMovePoints(blocksMoved) {
    // Simple: 1 point per block moved
    return blocksMoved;
}

/**
 * Calculate points for killing an opponent pawn
 * @returns {number} - Points earned
 */
function calculateKillPoints() {
    return 10; // Fixed kill points
}

/**
 * Calculate points lost when pawn is killed
 * @returns {number} - Points lost
 */
function calculateKillPenalty() {
    return 5; // Fixed penalty
}

/**
 * Check if position is a safe spot
 * @param {number} position - Current position
 * @returns {boolean}
 */
function isSafeSpot(position) {
    // Safe spots in Ludo (star squares)
    const safeSpots = [1, 9, 14, 22, 27, 35, 40, 48];
    return safeSpots.includes(position);
}

/**
 * Star positions (jump squares)
 * @returns {number[]}
 */
function getStarPositions() {
    return [1, 9, 14, 22, 27, 35, 40, 48];
}

/**
 * Get player color based on position
 * @param {number} playerIndex - 0, 1, 2, or 3
 * @returns {string}
 */
function getPlayerColor(playerIndex) {
    const colors = ['red', 'blue', 'green', 'yellow'];
    return colors[playerIndex] || 'red';
}

/**
 * Validate dice value
 */
function isValidDiceValue(value) {
    return Number.isInteger(value) && value >= 1 && value <= 6;
}

/**
 * Get table type max players
 */
function getMaxPlayers(tableType) {
    return tableType === '2-player' ? 2 : 4;
}

module.exports = {
    rollDice,
    parseTimeLimit,
    formatTime,
    calculateMovePoints,
    calculateKillPoints,
    calculateKillPenalty,
    isSafeSpot,
    getStarPositions,
    getPlayerColor,
    isValidDiceValue,
    getMaxPlayers
};

