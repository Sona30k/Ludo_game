/**
 * Bot Manager Class
 * Handles bot creation, naming, and auto-play logic
 */
class BotManager {
    constructor() {
        // Bot name pools for variety
        this.namePrefixes = ['Player', 'Gamer', 'Pro', 'Master', 'Champ', 'Ace', 'Star', 'Elite'];
        this.nameSuffixes = ['123', '456', '789', '007', '99', '88', '77', '66', '55', '44'];
        this.indianNames = ['Ravi', 'Amit', 'Raj', 'Priya', 'Sneha', 'Karan', 'Anjali', 'Vikram', 'Neha', 'Rohit'];
    }

    /**
     * Generate a bot name (neutral, looks like real player)
     */
    generateBotName(existingNames = []) {
        let name;
        let attempts = 0;
        
        do {
            // Mix of patterns: "Player_123", "Ravi", "Gamer456"
            const pattern = Math.random();
            
            if (pattern < 0.4) {
                // Pattern: "Player_123"
                const prefix = this.namePrefixes[Math.floor(Math.random() * this.namePrefixes.length)];
                const suffix = this.nameSuffixes[Math.floor(Math.random() * this.nameSuffixes.length)];
                name = `${prefix}_${suffix}`;
            } else if (pattern < 0.7) {
                // Pattern: "Ravi"
                name = this.indianNames[Math.floor(Math.random() * this.indianNames.length)];
            } else {
                // Pattern: "Gamer456"
                const prefix = this.namePrefixes[Math.floor(Math.random() * this.namePrefixes.length)];
                const suffix = Math.floor(Math.random() * 1000);
                name = `${prefix}${suffix}`;
            }
            
            attempts++;
        } while (existingNames.includes(name) && attempts < 50);
        
        return name;
    }

    /**
     * Create bot players
     * @param {number} count - Number of bots to create
     * @param {Array} existingPlayers - Existing player names to avoid duplicates
     * @returns {Array} Array of bot player objects
     */
    createBots(count, existingPlayers = []) {
        const bots = [];
        const existingNames = existingPlayers.map(p => p.username || p.name);
        
        for (let i = 0; i < count; i++) {
            const botName = this.generateBotName(existingNames);
            existingNames.push(botName);
            
            bots.push({
                userId: `bot_${Date.now()}_${i}`, // Unique bot ID
                username: botName,
                isBot: true,
                color: null, // Will be assigned by game logic
                pawns: [
                    { position: 0, isHome: false, isFinished: false },
                    { position: 0, isHome: false, isFinished: false },
                    { position: 0, isHome: false, isFinished: false },
                    { position: 0, isHome: false, isFinished: false }
                ],
                points: 0,
                isActive: true,
                turnOrder: -1 // Will be set when added to game
            });
        }
        
        return bots;
    }

    /**
     * Get bot move decision (simple AI logic)
     * @param {Object} gameState - Current game state
     * @param {number} botPlayerIndex - Index of bot player
     * @param {number} diceValue - Dice value rolled
     * @returns {number|null} Pawn index to move, or null if no valid move
     */
    getBotMove(gameState, botPlayerIndex, diceValue) {
        const bot = gameState.players[botPlayerIndex];
        if (!bot || !bot.isBot) return null;

        const gameLogic = require('./GameLogic');
        const logic = new gameLogic();
        
        // Get valid moves
        const validMoves = logic.getValidMoves(gameState, botPlayerIndex, diceValue);
        
        if (validMoves.length === 0) {
            return null; // No valid moves
        }

        // Simple AI strategy:
        // 1. Prefer moves that kill opponents
        // 2. Prefer moves that get pawns out of home
        // 3. Prefer moves that finish pawns
        // 4. Otherwise, move first available pawn

        // Priority 1: Moves that kill opponents
        const killMoves = validMoves.filter(m => m.willKill);
        if (killMoves.length > 0) {
            return killMoves[0].pawnIndex;
        }

        // Priority 2: Get pawn out of home (if dice is 6)
        if (diceValue === 6) {
            const homeMoves = validMoves.filter(m => {
                const pawn = bot.pawns[m.pawnIndex];
                return pawn.position === 0;
            });
            if (homeMoves.length > 0) {
                return homeMoves[0].pawnIndex;
            }
        }

        // Priority 3: Moves that finish pawns (get to end)
        const finishMoves = validMoves.filter(m => {
            const newPos = m.newPosition;
            return newPos >= 57; // Close to finish
        });
        if (finishMoves.length > 0) {
            // Choose the one closest to finish
            finishMoves.sort((a, b) => b.newPosition - a.newPosition);
            return finishMoves[0].pawnIndex;
        }

        // Priority 4: Move pawn that's furthest along
        validMoves.sort((a, b) => {
            const posA = bot.pawns[a.pawnIndex].position;
            const posB = bot.pawns[b.pawnIndex].position;
            return posB - posA;
        });

        return validMoves[0].pawnIndex;
    }

    /**
     * Check if a player is a bot
     */
    isBot(userId) {
        return typeof userId === 'string' && userId.startsWith('bot_');
    }
}

module.exports = BotManager;

