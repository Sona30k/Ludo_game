const GameLogic = require('./GameLogic');
const BotManager = require('./BotManager');
const pool = require('../config/database');
const { parseTimeLimit, formatTime, getMaxPlayers } = require('../utils/helpers');

/**
 * Game Manager Class
 * Manages all active games, players, and game state
 */
class GameManager {
    constructor() {
        this.activeGames = new Map(); // virtualTableId -> gameState
        this.playerSockets = new Map(); // userId -> socketId
        this.tableTimers = new Map(); // virtualTableId -> timer
        this.waitTimers = new Map(); // virtualTableId -> waitTimer
        this.gameLogic = new GameLogic();
        this.botManager = new BotManager();
        this.WAIT_TIME = 30; // 30 seconds wait time
        this.MIN_REAL_PLAYERS = 1; // Minimum real players required
    }

    /**
     * Parse time limit string to seconds
     */
    parseTimeLimit(timeLimit) {
        return parseTimeLimit(timeLimit);
    }

    /**
     * Initialize game from virtual table
     */
    async initializeGameFromVirtualTable(virtualTableId, virtualTable, players) {
        try {
            const gameState = this.gameLogic.initializeGameState(players);
            
            // Add virtual table info
            gameState.virtualTableId = virtualTableId;
            gameState.tableId = virtualTable.table_id;
            gameState.tableType = virtualTable.type;
            gameState.timeLimit = this.parseTimeLimit(virtualTable.time_limit);
            gameState.entryPoints = virtualTable.entry_points;
            gameState.realPlayers = players.filter(p => !p.isBot).length;
            
            // Calculate remaining time based on current_duration (for resuming games)
            if (virtualTable.status === 'RUNNING' && virtualTable.total_duration && virtualTable.current_duration !== null) {
                // Game is running - calculate remaining time from current_duration
                gameState.remainingTime = Math.max(0, virtualTable.total_duration - virtualTable.current_duration);
                gameState.currentDuration = virtualTable.current_duration;
                gameState.totalDuration = virtualTable.total_duration;
            } else {
                // Game not started yet - use full time limit
                gameState.remainingTime = gameState.timeLimit;
                gameState.currentDuration = 0;
                gameState.totalDuration = gameState.timeLimit;
            }
            
            // Calculate wait time remaining
            if (virtualTable.wait_end_time) {
                const waitEndTime = new Date(virtualTable.wait_end_time);
                const now = new Date();
                gameState.waitTimeRemaining = Math.max(0, Math.floor((waitEndTime - now) / 1000));
            } else {
                gameState.waitTimeRemaining = 0;
            }
            
            gameState.waitTimerStarted = virtualTable.status !== 'WAITING';
            gameState.gameStatus = virtualTable.status.toLowerCase(); // 'waiting', 'running', etc.

            // Store game state (use virtualTableId as key)
            this.activeGames.set(virtualTableId, gameState);

            console.log(`✅ Game initialized from virtual table ${virtualTableId} (remaining: ${gameState.remainingTime}s, current: ${gameState.currentDuration}s)`);
            return gameState;

        } catch (error) {
            console.error('Error initializing game from virtual table:', error);
            throw error;
        }
    }

    /**
     * Initialize a new game (legacy - for backward compatibility)
     */
    async initializeGame(tableId, players) {
        try {
            // Get table info from database
            const [tables] = await pool.execute(
                'SELECT type, time_limit, entry_points FROM tables WHERE id = ?',
                [tableId]
            );

            if (tables.length === 0) {
                throw new Error('Table not found');
            }

            const table = tables[0];
            const gameState = this.gameLogic.initializeGameState(players);
            
            // Count real players (exclude bots)
            const realPlayers = players.filter(p => !this.botManager.isBot(p.id || p.userId)).length;
            
            // Add table info to game state
            gameState.tableId = tableId;
            gameState.tableType = table.type;
            gameState.timeLimit = parseTimeLimit(table.time_limit);
            gameState.remainingTime = gameState.timeLimit;
            gameState.entryPoints = table.entry_points;
            gameState.realPlayers = realPlayers;
            gameState.waitTimeRemaining = this.WAIT_TIME;
            gameState.waitTimerStarted = false;

            // Store game state
            this.activeGames.set(tableId, gameState);

            // IMPORTANT: Keep table status as "open" until game actually starts
            // This allows other players to see and join the table
            // Only the games table tracks the waiting/ongoing status
            
            // Check if game record already exists
            const [existingGames] = await pool.execute(
                'SELECT id FROM games WHERE table_id = ? AND status IN (?, ?) ORDER BY id DESC LIMIT 1',
                [tableId, 'waiting', 'ongoing']
            );

            let gameId;
            if (existingGames.length > 0) {
                // Use existing game record
                gameId = existingGames[0].id;
                await pool.execute(
                    'UPDATE games SET status = ? WHERE id = ?',
                    ['waiting', gameId]
                );
            } else {
                // Create new game record
                const [result] = await pool.execute(
                    'INSERT INTO games (table_id, status) VALUES (?, ?)',
                    [tableId, 'waiting']
                );
                gameId = result.insertId;
            }

            gameState.gameId = gameId;

            console.log(`✅ Game initialized for table ${tableId} with ${gameState.realPlayers} real players`);
            return gameState;
        } catch (error) {
            console.error('Error initializing game:', error);
            throw error;
        }
    }

    /**
     * Start wait timer (30 seconds)
     */
    startWaitTimer(tableId, io) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        // Don't start if already started
        if (gameState.waitTimerStarted) return;
        gameState.waitTimerStarted = true;

        // Clear existing wait timer if any
        if (this.waitTimers.has(tableId)) {
            clearInterval(this.waitTimers.get(tableId));
        }

        console.log(`⏳ Starting 30s wait timer for table ${tableId}`);

        // Update wait time every second
        const waitInterval = setInterval(() => {
            if (gameState.waitTimeRemaining > 0) {
                gameState.waitTimeRemaining--;
                
                // Emit countdown to all players
                if (io) {
                    io.to(`table_${tableId}`).emit('wait_countdown', {
                        remaining: gameState.waitTimeRemaining,
                        total: this.WAIT_TIME
                    });
                }
            } else {
                clearInterval(waitInterval);
                this.waitTimers.delete(tableId);
                this.checkAndStartGame(tableId, io);
            }
        }, 1000);

        this.waitTimers.set(tableId, waitInterval);
    }

    /**
     * Check if game should start (after wait timer or table full)
     */
    async checkAndStartGame(tableId, io) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        // Count real players
        const realPlayers = gameState.players.filter(p => !this.botManager.isBot(p.userId)).length;

        // Check if we should start or cancel
        if (realPlayers >= this.MIN_REAL_PLAYERS) {
            // Fill with bots and start
            await this.fillBotsAndStart(tableId, io);
        } else {
            // Cancel and refund
            await this.cancelGame(tableId, io);
        }
    }

    /**
     * Fill empty seats with bots and start game
     */
    async fillBotsAndStart(tableId, io) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        const maxPlayers = getMaxPlayers(gameState.tableType);
        const currentPlayers = gameState.players.length;
        const botsNeeded = maxPlayers - currentPlayers;

        if (botsNeeded > 0) {
            // Get existing player names
            const existingNames = gameState.players.map(p => p.username);
            
            // Create bots
            const bots = this.botManager.createBots(botsNeeded, existingNames);
            
            // Add bots to game
            bots.forEach((bot, index) => {
                bot.turnOrder = currentPlayers + index;
                bot.color = this.gameLogic.getColorByIndex(bot.turnOrder);
                gameState.players.push(bot);
            });

            console.log(`🤖 Added ${botsNeeded} bots to table ${tableId}`);
        }

        // Start the game
        await this.startGame(tableId, io);
    }

    /**
     * Cancel game and refund players
     */
    async cancelGame(tableId, io) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        console.log(`❌ Cancelling table ${tableId} - insufficient players`);

        // Refund all real players
        const realPlayers = gameState.players.filter(p => !this.botManager.isBot(p.userId));
        
        for (const player of realPlayers) {
            try {
                // Log refund transaction (balance is calculated from transactions, no wallet table update needed)
                await pool.execute(
                    'INSERT INTO wallet_transactions (user_id, amount, type, reason, table_id) VALUES (?, ?, ?, ?, ?)',
                    [player.userId, gameState.entryPoints, 'credit', `Refund: Table #${tableId} cancelled (insufficient players)`, tableId]
                );
            } catch (error) {
                console.error(`Error refunding player ${player.userId}:`, error);
            }
        }

        // Update database - Reset table to open (so it can be used again)
        await pool.execute(
            'UPDATE tables SET status = ? WHERE id = ?',
            ['open', tableId]
        );

        await pool.execute(
            'UPDATE games SET status = ? WHERE id = ?',
            ['cancelled', gameState.gameId]
        );

        // Notify players
        if (io) {
            io.to(`table_${tableId}`).emit('game_cancelled', {
                reason: 'Insufficient players',
                refunded: true
            });
        }

        // Clean up
        this.activeGames.delete(tableId);
        if (this.waitTimers.has(tableId)) {
            clearInterval(this.waitTimers.get(tableId));
            this.waitTimers.delete(tableId);
        }
    }

    /**
     * Start a game (after bots filled)
     */
    async startGame(tableId, io) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) {
            throw new Error('Game not found');
        }

        if (gameState.gameStatus !== 'waiting') {
            return gameState;
        }

        gameState.gameStatus = 'playing';
        gameState.startedAt = new Date();

        // Start game timer
        this.startTimer(tableId);

        // Update database - NOW set table to ongoing (game is actually starting)
        await pool.execute(
            'UPDATE tables SET status = ? WHERE id = ?',
            ['ongoing', tableId]
        );

        await pool.execute(
            'UPDATE games SET status = ?, started_at = NOW() WHERE id = ?',
            ['ongoing', gameState.gameId]
        );

        console.log(`🎮 Game started for table ${tableId} with ${gameState.players.length} players`);

        // Notify all players
        if (io) {
            io.to(`table_${tableId}`).emit('game_started', {
                gameState: gameState
            });
        }

        return gameState;
    }

    /**
     * Start countdown timer for a game
     */
    startTimer(tableId) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        // Clear existing timer if any
        if (this.tableTimers.has(tableId)) {
            clearInterval(this.tableTimers.get(tableId));
        }

        // Update timer every second
        const timer = setInterval(() => {
            if (gameState.remainingTime > 0) {
                gameState.remainingTime--;
            } else {
                // Time's up - end game
                this.endGameByTimer(tableId);
                clearInterval(timer);
                this.tableTimers.delete(tableId);
            }
        }, 1000);

        this.tableTimers.set(tableId, timer);
    }

    /**
     * End game when timer expires
     */
    async endGameByTimer(tableId) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        console.log(`⏰ Time's up for table ${tableId}`);

        // Get ranking by points
        const ranking = this.gameLogic.getGameRanking(gameState);
        
        // End the game
        await this.endGame(tableId, ranking);
    }

    /**
     * Roll dice for current player
     */
    async rollDice(tableId, userId, overrideValue = null) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) {
            throw new Error('Game not found');
        }

        const currentPlayer = gameState.players[gameState.currentTurn];
        if (currentPlayer.userId !== userId) {
            throw new Error('Not your turn');
        }

        // Check for admin dice override
        let diceValue = overrideValue;
        if (diceValue === null) {
            // Check database for override
            const [overrides] = await pool.execute(
                'SELECT dice_value FROM dice_override WHERE table_id = ? AND used = 0 ORDER BY timestamp DESC LIMIT 1',
                [tableId]
            );

            if (overrides.length > 0) {
                diceValue = overrides[0].dice_value;
                // Mark override as used
                await pool.execute(
                    'UPDATE dice_override SET used = 1 WHERE table_id = ? AND used = 0 ORDER BY timestamp DESC LIMIT 1',
                    [tableId]
                );
            } else {
                // Normal random dice
                diceValue = this.gameLogic.rollDice();
            }
        }

        gameState.diceValue = diceValue;
        gameState.lastAction = {
            type: 'dice_roll',
            playerId: userId,
            diceValue,
            timestamp: new Date()
        };

        return diceValue;
    }

    /**
     * Move a pawn
     */
    async movePawn(tableId, userId, pawnIndex) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) {
            throw new Error('Game not found');
        }

        const currentPlayer = gameState.players[gameState.currentTurn];
        if (currentPlayer.userId !== userId) {
            throw new Error('Not your turn');
        }

        if (!gameState.diceValue) {
            throw new Error('Dice not rolled yet');
        }

        const diceValue = gameState.diceValue;
        const moveResult = this.gameLogic.movePawn(
            gameState,
            gameState.currentTurn,
            pawnIndex,
            diceValue
        );

        if (!moveResult.success) {
            throw new Error('Invalid move');
        }

        // Check if game is finished
        const finishCheck = this.gameLogic.checkGameFinished(gameState);
        if (finishCheck.finished) {
            const ranking = this.gameLogic.getGameRanking(gameState);
            await this.endGame(tableId, ranking);
            return { ...moveResult, gameFinished: true, ranking };
        }

        // If dice is 6, player gets another turn
        // Otherwise, move to next player
        if (diceValue !== 6) {
            this.gameLogic.nextTurn(gameState);
        }

        // Reset dice value
        gameState.diceValue = null;

        return moveResult;
    }

    /**
     * End a game
     */
    async endGame(tableId, ranking) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        // Stop timer
        if (this.tableTimers.has(tableId)) {
            clearInterval(this.tableTimers.get(tableId));
            this.tableTimers.delete(tableId);
        }

        gameState.gameStatus = 'finished';
        gameState.endedAt = new Date();
        gameState.ranking = ranking;

        // Update database
        const winner = ranking[0];
        await pool.execute(
            'UPDATE games SET status = ?, winner_id = ?, ended_at = NOW() WHERE id = ?',
            ['completed', winner.userId, gameState.gameId]
        );

        await pool.execute(
            'UPDATE tables SET status = ? WHERE id = ?',
            ['completed', tableId]
        );

        // Distribute prizes
        await this.distributePrizes(gameState, ranking);

        console.log(`🏁 Game ended for table ${tableId}. Winner: ${winner.username}`);
        
        // Keep game state for a while (for reconnection/review)
        setTimeout(() => {
            this.activeGames.delete(tableId);
        }, 300000); // 5 minutes

        return gameState;
    }

    /**
     * Distribute prizes based on ranking
     * Prize pool = entryFee × realPlayers (NOT total players)
     */
    async distributePrizes(gameState, ranking) {
        // Calculate prize pool based on REAL players only (not bots)
        const realPlayers = gameState.players.filter(p => !this.botManager.isBot(p.userId)).length;
        const totalPrize = gameState.entryPoints * realPlayers;
        
        // Prize distribution: 1st gets 60%, 2nd gets 30%, 3rd gets 10% (if 4 players)
        const prizeDistribution = {
            2: [0.7, 0.3], // 2-player: 70% / 30%
            4: [0.5, 0.3, 0.15, 0.05] // 4-player: 50% / 30% / 15% / 5%
        };

        const distribution = prizeDistribution[gameState.players.length] || [1, 0, 0, 0];

        for (let i = 0; i < ranking.length && i < distribution.length; i++) {
            const player = ranking[i];
            const prize = Math.floor(totalPrize * distribution[i]);

            // Only distribute prizes to REAL players (not bots)
            if (prize > 0 && !this.botManager.isBot(player.userId)) {
                // Log credit transaction (balance is calculated from transactions, no wallet table update needed)
                await pool.execute(
                    'INSERT INTO wallet_transactions (user_id, amount, type, reason, table_id) VALUES (?, ?, ?, ?, ?)',
                    [player.userId, prize, 'credit', `Won ${i + 1}st place in table #${gameState.tableId}`, gameState.tableId]
                );
            }
            // Bots can win but don't receive payouts (winnings go to system)
        }
    }

    /**
     * Get game state
     */
    getGameState(tableId) {
        return this.activeGames.get(tableId);
    }

    /**
     * Add player to game
     */
    addPlayerToGame(tableId, userId, username, socketId) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) {
            throw new Error('Game not found');
        }

        // Check if player already in game (compare as numbers to avoid type mismatch)
        const existingPlayer = gameState.players.find(p => {
            const pUserId = parseInt(p.userId) || p.userId;
            const checkUserId = parseInt(userId) || userId;
            return pUserId == checkUserId; // Use == for loose comparison
        });
        
        if (existingPlayer) {
            existingPlayer.isActive = true;
            this.playerSockets.set(userId, socketId);
            // Update socket ID if changed
            return existingPlayer;
        }

        // Add new player
        const playerIndex = gameState.players.length;
        const newPlayer = {
            userId,
            username,
            isBot: false,
            color: this.gameLogic.getColorByIndex(playerIndex),
            pawns: [
                { position: 0, isHome: false, isFinished: false },
                { position: 0, isHome: false, isFinished: false },
                { position: 0, isHome: false, isFinished: false },
                { position: 0, isHome: false, isFinished: false }
            ],
            points: 0,
            isActive: true,
            turnOrder: playerIndex
        };

        gameState.players.push(newPlayer);
        this.playerSockets.set(userId, socketId);
        
        // Update real players count
        gameState.realPlayers = gameState.players.filter(p => !this.botManager.isBot(p.userId)).length;

        return newPlayer;
    }

    /**
     * Remove player (on disconnect)
     * Updates virtual_table_players.is_connected = 0
     * If game is waiting and no real players left, cancel virtual table
     */
    async removePlayer(userId, io, virtualTableManager = null) {
        // Only process if userId is valid (not undefined)
        if (!userId) {
            console.warn('removePlayer called with undefined userId');
            return;
        }
        
        this.playerSockets.delete(userId);
        
        // Mark player as inactive in all games and update virtual table
        for (const [virtualTableId, gameState] of this.activeGames.entries()) {
            const player = gameState.players.find(p => p.userId == userId);
            if (player) {
                player.isActive = false;
                
                // Update virtual_table_players to mark as disconnected
                // Only update for real users (not bots)
                if (!this.botManager.isBot(userId)) {
                    try {
                        await pool.execute(
                            'UPDATE virtual_table_players SET is_connected = 0 WHERE virtual_table_id = ? AND user_id = ?',
                            [virtualTableId, userId]
                        );
                    } catch (error) {
                        console.error('Error updating player connection status:', error);
                    }
                }
                
                // If game is waiting and no real players left, cancel virtual table
                if (gameState.gameStatus === 'waiting') {
                    const activeRealPlayers = gameState.players.filter(p => 
                        !this.botManager.isBot(p.userId) && p.isActive
                    ).length;
                    
                    if (activeRealPlayers === 0) {
                        // No active real players, cancel virtual table
                        console.log(`🔄 Cancelling virtual table ${virtualTableId} - no active players`);
                        
                        // Stop wait timer
                        if (this.waitTimers.has(virtualTableId)) {
                            clearInterval(this.waitTimers.get(virtualTableId));
                            this.waitTimers.delete(virtualTableId);
                        }
                        
                        // Cancel virtual table (this will refund players)
                        if (virtualTableManager) {
                            try {
                                await virtualTableManager.cancelVirtualTable(virtualTableId);
                            } catch (error) {
                                console.error('Error cancelling virtual table:', error);
                            }
                        }
                        
                        // Remove from active games
                        this.activeGames.delete(virtualTableId);
                    }
                }
            }
        }
    }

    /**
     * Reconnect player
     */
    reconnectPlayer(userId, socketId) {
        this.playerSockets.set(userId, socketId);
        
        // Mark player as active in all games
        for (const [tableId, gameState] of this.activeGames.entries()) {
            const player = gameState.players.find(p => p.userId === userId);
            if (player) {
                player.isActive = true;
            }
        }
    }

    /**
     * Get player's socket ID
     */
    getPlayerSocket(userId) {
        return this.playerSockets.get(userId);
    }

    /**
     * Auto-play for inactive player
     */
    async autoPlay(tableId) {
        const gameState = this.activeGames.get(tableId);
        if (!gameState) return;

        const currentPlayer = gameState.players[gameState.currentTurn];
        
        // If player is inactive, auto-play
        if (!currentPlayer.isActive) {
            // Auto roll dice
            const diceValue = await this.rollDice(tableId, currentPlayer.userId);
            
            // Auto move first available pawn
            const validMoves = this.gameLogic.getValidMoves(
                gameState,
                gameState.currentTurn,
                diceValue
            );

            if (validMoves.length > 0) {
                await this.movePawn(tableId, currentPlayer.userId, validMoves[0].pawnIndex);
            } else {
                // No valid moves, skip turn
                this.gameLogic.nextTurn(gameState);
            }
        }
    }
}

module.exports = GameManager;

