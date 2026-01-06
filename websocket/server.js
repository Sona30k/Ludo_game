const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
require('dotenv').config();

const GameManager = require('./game/GameManager');
const VirtualTableManager = require('./game/VirtualTableManager');
const pool = require('./config/database');

// Initialize Managers
const gameManager = new GameManager();
const virtualTableManager = new VirtualTableManager();

// Start virtual table checker (runs every 2 seconds)
setInterval(async () => {
    try {
        await virtualTableManager.checkAndStartVirtualTables();
    } catch (error) {
        console.error('Virtual table checker error:', error);
    }
}, 2000);

// Update current_duration for RUNNING games (runs every 5 seconds)
setInterval(async () => {
    try {
        await virtualTableManager.updateCurrentDuration();
    } catch (error) {
        console.error('Duration updater error:', error);
    }
}, 5000);

/**
 * Handle bot turn (auto-play)
 */
async function handleBotTurn(gameId, io, virtualTableId = null) {
    const gameState = gameManager.getGameState(gameId);
    if (!gameState || gameState.gameStatus !== 'playing') return;

    const currentPlayer = gameState.players[gameState.currentTurn];
    if (!currentPlayer || !gameManager.botManager.isBot(currentPlayer.userId)) {
        return; // Not a bot's turn
    }

    // Wait a bit for visual effect (1-2 seconds)
    await new Promise(resolve => setTimeout(resolve, 1500));

    try {
        // Bot rolls dice
        const diceValue = await gameManager.rollDice(gameId, currentPlayer.userId);
        
        // Log dice roll if using virtual tables
        if (virtualTableId) {
            const virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
            if (virtualTable) {
                const botPlayer = virtualTable.players.find(p => p.botId === currentPlayer.userId);
                if (botPlayer) {
                    await virtualTableManager.logDiceRoll(
                        virtualTableId,
                        null,
                        botPlayer.botId,
                        diceValue,
                        false,
                        null,
                        gameState.currentTurn || 0
                    );
                }
            }
        }
        
        // Emit dice roll
        const roomName = virtualTableId ? `virtual_table_${virtualTableId}` : `table_${gameId}`;
        io.to(roomName).emit('dice_rolled', {
            playerId: currentPlayer.userId,
            diceValue,
            gameState: gameManager.getGameState(gameId),
            virtualTableId
        });

        // Wait a bit more
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Bot makes move
        const pawnIndex = gameManager.botManager.getBotMove(
            gameState,
            gameState.currentTurn,
            diceValue
        );

        if (pawnIndex !== null) {
            const moveResult = await gameManager.movePawn(gameId, currentPlayer.userId, pawnIndex);
            const updatedState = gameManager.getGameState(gameId);

            // Emit pawn move
            io.to(roomName).emit('pawn_moved', {
                playerId: currentPlayer.userId,
                pawnIndex,
                moveResult,
                gameState: updatedState,
                virtualTableId
            });

            // If game finished
            if (moveResult.gameFinished) {
                io.to(roomName).emit('game_finished', {
                    ranking: moveResult.ranking,
                    gameState: updatedState,
                    virtualTableId
                });
            } else {
                // Check if next player is also a bot (recursive)
                await handleBotTurn(gameId, io, virtualTableId);
            }
        } else {
            // No valid moves, skip turn
            gameManager.gameLogic.nextTurn(gameState);
            gameState.diceValue = null;
            
            // Check next player
            await handleBotTurn(gameId, io, virtualTableId);
        }
    } catch (error) {
        console.error('Bot turn error:', error);
    }
}

// Create HTTP server
const server = http.createServer();

// Initialize Socket.IO with CORS
const io = new Server(server, {
    cors: {
        origin: process.env.CORS_ORIGIN || "http://localhost",
        methods: ["GET", "POST"],
        credentials: true
    }
});

// Middleware: Authenticate user
io.use(async (socket, next) => {
    const userId = socket.handshake.auth.userId;
    const username = socket.handshake.auth.username;

    if (!userId || !username) {
        return next(new Error('Authentication required'));
    }

    // Verify user exists in database
    try {
        const [users] = await pool.execute(
            'SELECT id, username FROM users WHERE id = ?',
            [userId]
        );

        if (users.length === 0) {
            return next(new Error('User not found'));
        }

        socket.userId = userId;
        socket.username = username;
        next();
    } catch (error) {
        console.error('Auth error:', error);
        next(new Error('Authentication failed'));
    }
});

// Socket connection handling
io.on('connection', (socket) => {
    console.log(`✅ User connected: ${socket.username} (${socket.userId})`);

    // Reconnect player
    gameManager.reconnectPlayer(socket.userId, socket.id);

    /**
     * Join a game table (using Virtual Table System)
     */
    socket.on('join_table', async (data) => {
        try {
            const { tableId } = data;

            // Verify user joined the table in database
            const [userJoined] = await pool.execute(
                `SELECT COUNT(*) as count FROM wallet_transactions 
                 WHERE user_id = ? AND reason LIKE ? AND type = 'debit'`,
                [socket.userId, `%table #${tableId}%`]
            );

            if (userJoined[0].count === 0) {
                socket.emit('error', { message: 'You have not joined this table' });
                return;
            }

            // Check table exists and is available
            const [tables] = await pool.execute(
                'SELECT id, type, time_limit, status FROM tables WHERE id = ? AND status = ?',
                [tableId, 'open']
            );

            if (tables.length === 0) {
                socket.emit('error', { message: 'Table not found or not available' });
                return;
            }

            // Find or create virtual table (prioritizes user's existing active virtual table)
            const virtualTableId = await virtualTableManager.findOrCreateVirtualTable(tableId, socket.userId);
            
            // Add player to virtual table (or reconnect if already exists)
            const seatNo = await virtualTableManager.addPlayerToVirtualTable(
                virtualTableId,
                socket.userId,
                socket.username,
                false,
                null
            );
            
            console.log(`👤 Player ${socket.username} ${seatNo !== undefined ? 'reconnected to' : 'joined'} virtual table ${virtualTableId}`);

            // Get virtual table details
            const virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
            
            // Get or create game state in memory
            let gameState = gameManager.getGameState(virtualTableId);
            
            if (!gameState) {
                // Initialize game state from virtual table
                const players = virtualTable.players.map(p => ({
                    userId: p.userId || p.botId,
                    username: p.username,
                    isBot: p.isBot
                }));

                gameState = await gameManager.initializeGameFromVirtualTable(virtualTableId, virtualTable, players);
                
                // Start timer if game is already RUNNING (resuming)
                if (virtualTable.status === 'RUNNING' && gameState.gameStatus === 'running') {
                    gameManager.startTimer(virtualTableId);
                }
            }

            // Add player to game state (if not already added)
            const player = gameManager.addPlayerToGame(
                virtualTableId, // Use virtualTableId instead of tableId
                socket.userId,
                socket.username,
                socket.id
            );

            // Join room (use virtual table ID)
            socket.join(`virtual_table_${virtualTableId}`);

            // Send current game state
            socket.emit('game_state', {
                gameState,
                virtualTableId,
                yourPlayerIndex: gameState.players.findIndex(p => p.userId == socket.userId),
                seatNo
            });

            // Notify other players
            socket.to(`virtual_table_${virtualTableId}`).emit('player_joined', {
                player,
                totalPlayers: gameState.players.length,
                realPlayers: gameState.realPlayers,
                virtualTableId
            });

            // Emit wait countdown if waiting
            if (virtualTable.status === 'WAITING') {
                const waitEndTime = new Date(virtualTable.wait_end_time);
                const now = new Date();
                const remaining = Math.max(0, Math.floor((waitEndTime - now) / 1000));
                
                socket.emit('wait_countdown', {
                    remaining,
                    total: 30,
                    virtualTableId
                });
            }

        } catch (error) {
            console.error('Join table error:', error);
            socket.emit('error', { message: error.message });
        }
    });

    /**
     * Roll dice
     */
    socket.on('roll_dice', async (data) => {
        try {
            const { virtualTableId, tableId } = data;
            const gameId = virtualTableId || tableId; // Support both
            
            const diceValue = await gameManager.rollDice(gameId, socket.userId);
            const gameState = gameManager.getGameState(gameId);
            
            // Get virtual table info for dice logging
            if (virtualTableId) {
                const virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
                if (virtualTable) {
                    // Find player in virtual table
                    const player = virtualTable.players.find(p => p.userId == socket.userId);
                    if (player) {
                        await virtualTableManager.logDiceRoll(
                            virtualTableId,
                            player.userId,
                            null,
                            diceValue,
                            false,
                            null,
                            gameState.currentTurn || 0
                        );
                    }
                }
            }
            
            // Emit to all players
            const roomName = virtualTableId ? `virtual_table_${virtualTableId}` : `table_${tableId}`;
            io.to(roomName).emit('dice_rolled', {
                playerId: socket.userId,
                diceValue,
                gameState,
                virtualTableId
            });

            // If it's a bot's turn, auto-play
            await handleBotTurn(gameId, io, virtualTableId);

        } catch (error) {
            console.error('Roll dice error:', error);
            socket.emit('error', { message: error.message });
        }
    });

    /**
     * Move pawn
     */
    socket.on('move_pawn', async (data) => {
        try {
            const { virtualTableId, tableId, pawnIndex } = data;
            const gameId = virtualTableId || tableId; // Support both
            
            const moveResult = await gameManager.movePawn(gameId, socket.userId, pawnIndex);
            const gameState = gameManager.getGameState(gameId);

            // Log move to virtual table if using virtual tables
            if (virtualTableId) {
                const virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
                if (virtualTable && moveResult.newPosition !== undefined) {
                    const player = virtualTable.players.find(p => p.userId == socket.userId);
                    if (player) {
                        await pool.execute(
                            `INSERT INTO virtual_table_moves 
                             (virtual_table_id, player_id, bot_id, pawn_index, from_position, to_position, points_earned, killed_opponent, turn_no, created_at)
                             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, NOW())`,
                            [
                                virtualTableId,
                                player.userId,
                                pawnIndex,
                                moveResult.oldPosition || 0,
                                moveResult.newPosition,
                                moveResult.pointsEarned || 0,
                                moveResult.killedPawns?.length > 0 ? 1 : 0,
                                gameState.currentTurn || 0
                            ]
                        );
                    }
                }
            }

            // Emit to all players
            const roomName = virtualTableId ? `virtual_table_${virtualTableId}` : `table_${tableId}`;
            io.to(roomName).emit('pawn_moved', {
                playerId: socket.userId,
                pawnIndex,
                moveResult,
                gameState,
                virtualTableId
            });

            // If game finished
            if (moveResult.gameFinished) {
                io.to(roomName).emit('game_finished', {
                    ranking: moveResult.ranking,
                    gameState,
                    virtualTableId
                });
            } else {
                // Check if next player is a bot
                await handleBotTurn(gameId, io, virtualTableId);
            }

        } catch (error) {
            console.error('Move pawn error:', error);
            socket.emit('error', { message: error.message });
        }
    });

    /**
     * Get valid moves
     */
    socket.on('get_valid_moves', (data) => {
        try {
            const { virtualTableId, tableId } = data;
            const gameId = virtualTableId || tableId;
            const gameState = gameManager.getGameState(gameId);
            
            if (!gameState || !gameState.diceValue) {
                socket.emit('valid_moves', { moves: [] });
                return;
            }

            const playerIndex = gameState.players.findIndex(p => p.userId == socket.userId);
            if (playerIndex === -1) {
                socket.emit('error', { message: 'Player not in game' });
                return;
            }

            const validMoves = gameManager.gameLogic.getValidMoves(
                gameState,
                playerIndex,
                gameState.diceValue
            );

            socket.emit('valid_moves', { 
                moves: validMoves,
                virtualTableId 
            });

        } catch (error) {
            console.error('Get valid moves error:', error);
            socket.emit('error', { message: error.message });
        }
    });

    /**
     * Request game state (for reconnection)
     */
    socket.on('get_game_state', (data) => {
        try {
            const { tableId } = data;
            const gameState = gameManager.getGameState(tableId);

            if (!gameState) {
                socket.emit('error', { message: 'Game not found' });
                return;
            }

            socket.emit('game_state', {
                gameState,
                yourPlayerIndex: gameState.players.findIndex(p => p.userId === socket.userId)
            });

        } catch (error) {
            console.error('Get game state error:', error);
            socket.emit('error', { message: error.message });
        }
    });

    /**
     * Handle disconnect
     */
    socket.on('disconnect', async () => {
        console.log(`❌ User disconnected: ${socket.username} (${socket.userId})`);
        await gameManager.removePlayer(socket.userId, io, virtualTableManager);
    });
});

// Start server
const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`🚀 WebSocket server running on port ${PORT}`);
    console.log(`📡 Socket.IO ready for connections`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('SIGTERM received, shutting down gracefully...');
    server.close(() => {
        console.log('Server closed');
        pool.end();
        process.exit(0);
    });
});

module.exports = { io, gameManager };

