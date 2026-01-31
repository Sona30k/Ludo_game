/**
 * Bot Manager Class
 * Handles bot creation, naming, and auto-play logic
 */
class BotManager {
    constructor() {
        // Indian name pool only
        this.indianNames = [
            'Aarav', 'Aditi', 'Amit', 'Ananya', 'Anjali', 'Arjun', 'Diya', 'Ishaan',
            'Karan', 'Kavya', 'Meera', 'Neha', 'Nisha', 'Pranav', 'Priya', 'Rahul',
            'Raj', 'Riya', 'Rohit', 'Saanvi', 'Sameer', 'Sanjay', 'Shreya', 'Sneha',
            'Varun', 'Vikram'
        ];

        // Difficulty levels (0-1, where 1 is hardest)
        this.difficultyLevels = {
            easy: 0.3,      // Makes random moves 70% of time
            medium: 0.6,    // Balanced strategy
            hard: 0.95      // Smart strategy 95% of time
        };
    }

    /**
     * Generate a bot name (neutral, looks like real player)
     */
    generateBotName(existingNames = []) {
        let name;
        let attempts = 0;
        
        do {
            const base = this.indianNames[Math.floor(Math.random() * this.indianNames.length)];
            name = base;

            if (existingNames.includes(name)) {
                name = `${base}${Math.floor(Math.random() * 90) + 10}`;
            }

            attempts++;
        } while (existingNames.includes(name) && attempts < 50);
        
        return name;
    }

    /**
     * Create bot players with difficulty level
     * @param {number} count - Number of bots to create
     * @param {Array} existingPlayers - Existing player names to avoid duplicates
     * @param {string} difficulty - 'easy', 'medium', or 'hard'
     * @returns {Array} Array of bot player objects
     */
    createBots(count, existingPlayers = [], difficulty = 'medium') {
        const bots = [];
        const existingNames = existingPlayers.map(p => p.username || p.name);
        
        for (let i = 0; i < count; i++) {
            const botName = this.generateBotName(existingNames);
            existingNames.push(botName);
            
            bots.push({
                userId: `bot_${Date.now()}_${i}`, // Unique bot ID
                username: botName,
                isBot: true,
                difficulty: difficulty || 'medium', // Store difficulty level
                color: null, // Will be assigned by game logic
                pawns: [
                    { position: 0, isHome: true, isFinished: false },
                    { position: 0, isHome: true, isFinished: false },
                    { position: 0, isHome: true, isFinished: false },
                    { position: 0, isHome: true, isFinished: false }
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
            if (!bot || !bot.isBot) {
                console.warn(`[BotManager] Bot not found or not a bot at index ${botPlayerIndex}`);
                return null;
            }

            try {
                const GameLogic = require('./GameLogic');
                const logic = new GameLogic();
            
                // Get valid moves
                const validMoves = logic.getValidMoves(gameState, botPlayerIndex, diceValue);
            
                if (validMoves.length === 0) {
                    console.info(`[BotManager] No valid moves for bot ${bot.username} (ID: ${bot.userId})`);
                    return null; // No valid moves
                }

                // Get difficulty level (default to medium)
                const difficulty = bot.difficulty || 'medium';
                const difficultyScore = this.difficultyLevels[difficulty] || 0.6;

                // Easy bots make random moves often
                if (Math.random() > difficultyScore) {
                    console.info(`[BotManager] Bot ${bot.username} (${difficulty}) making random move`);
                    return validMoves[Math.floor(Math.random() * validMoves.length)].pawnIndex;
                }

                // Strategic moves based on difficulty
                // Priority 1: Moves that kill opponents (all difficulties)
                const killMoves = validMoves.filter(m => m.willKill);
                if (killMoves.length > 0) {
                    console.info(`[BotManager] Bot ${bot.username} (${difficulty}) choosing kill move`);
                    return killMoves[0].pawnIndex;
                }

                // Priority 2: Get pawn out of home (all difficulties)
                const homeMoves = validMoves.filter(m => {
                    const pawn = bot.pawns[m.pawnIndex];
                    return pawn.position === 0;
                });
                if (homeMoves.length > 0) {
                    console.info(`[BotManager] Bot ${bot.username} (${difficulty}) choosing home move`);
                    return homeMoves[0].pawnIndex;
                }

                // Priority 3: Moves that finish pawns (get to end) - Medium & Hard prefer this
                if (difficulty !== 'easy') {
                    const finishMoves = validMoves.filter(m => {
                        const newPos = m.newPosition;
                        return newPos >= 57; // Close to finish
                    });
                    if (finishMoves.length > 0) {
                        finishMoves.sort((a, b) => b.newPosition - a.newPosition);
                        console.info(`[BotManager] Bot ${bot.username} (${difficulty}) choosing finish move`);
                        return finishMoves[0].pawnIndex;
                    }
                }

                // Priority 4: Move pawn that's furthest along
                validMoves.sort((a, b) => {
                    const posA = bot.pawns[a.pawnIndex].position;
                    const posB = bot.pawns[b.pawnIndex].position;
                    return posB - posA;
                });

                console.info(`[BotManager] Bot ${bot.username} (${difficulty}) choosing standard move`);
                return validMoves[0].pawnIndex;
            } catch (error) {
                console.error(`[BotManager] Error in getBotMove for bot ${bot?.username}:`, error);
                return null;
            }
        }

    /**
     * Check if a player is a bot
     */
    isBot(userId) {
        return typeof userId === 'string' && userId.startsWith('bot_');
    }
}

module.exports = BotManager;

