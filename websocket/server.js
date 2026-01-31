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
        const startedVirtualTables = await virtualTableManager.checkAndStartVirtualTables();
        
        // For each virtual table that just started, update game state and trigger bot turns
        if (startedVirtualTables && startedVirtualTables.length > 0) {
            for (const virtualTableId of startedVirtualTables) {
                // Get virtual table to get players
                const virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
                if (!virtualTable) continue;
                
                // Get or initialize game state
                let gameState = gameManager.getGameState(virtualTableId);
                if (!gameState) {
                    // Initialize game state from virtual table
                    const players = virtualTable.players.map(p => ({
                        userId: p.userId || p.botId,
                        username: p.username,
                        isBot: p.isBot || false,
                        botId: p.botId || null,
                        points: p.score || 0
                    }));
                    gameState = await gameManager.initializeGameFromVirtualTable(virtualTableId, virtualTable, players);
                }
                
                // Update game status to 'running'
                gameState.gameStatus = 'running';
                gameState.startedAt = new Date();
                
                // Start timer
                gameManager.startTimer(virtualTableId, io);
                
                // Emit game_started to all players in the room
                const roomName = `virtual_table_${virtualTableId}`;
                io.to(roomName).emit('game_started', {
                    gameState,
                    virtualTableId
                });
                
                console.log(`🎮 Game started for virtual table ${virtualTableId}, emitting to room ${roomName}`);
                
                // If first player is a bot, trigger bot turn
                if (gameState.players.length > 0 && gameState.currentTurn === 0) {
                    const firstPlayer = gameState.players[0];
                    if (gameManager.botManager.isBot(firstPlayer.userId)) {
                        console.log(`🤖 First player is a bot (${firstPlayer.username}), triggering bot turn`);
                        // Small delay to ensure clients receive game_started event first
                        setTimeout(async () => {
                            await handleBotTurn(virtualTableId, io, virtualTableId);
                        }, 500);
                    }
                }
            }
        }
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
}, 1000);

/**
 * Handle bot turn (auto-play)
 * Added recursion depth tracking to prevent infinite loops/freezes
 */
async function handleBotTurn(gameId, io, virtualTableId = null, recursionDepth = 0) {
    // ✅ CRITICAL: Prevent infinite recursion that freezes game
    const MAX_BOT_TURNS = 20; // Max consecutive bot turns
    if (recursionDepth > MAX_BOT_TURNS) {
        console.warn(`⚠️ [GAME FREEZE PREVENTION] Max bot turn recursion (${MAX_BOT_TURNS}) reached! Stopping bot chain.`);
        return;
    }
    
    const gameState = gameManager.getGameState(gameId);
    if (!gameState) {
        console.log(`⚠️ handleBotTurn: No game state found for ${gameId}`);
        return;
    }
    
    // Check if game is in a playable state (running or playing)
    if (gameState.gameStatus !== 'playing' && gameState.gameStatus !== 'running') {
        console.log(`⚠️ handleBotTurn: Game status is '${gameState.gameStatus}', not playable`);
        return;
    }

    const currentPlayer = gameState.players[gameState.currentTurn];
    if (!currentPlayer) {
        console.log(`⚠️ handleBotTurn: No current player at turn ${gameState.currentTurn}`);
        return;
    }
    
    if (!gameManager.botManager.isBot(currentPlayer.userId)) {
        console.log(`ℹ️ handleBotTurn: Not a bot's turn (${currentPlayer.username})`);
        return; // Not a bot's turn
    }
    
    console.log(`🤖 Bot's turn [depth ${recursionDepth}]: ${currentPlayer.username} (${currentPlayer.userId}) at turn ${gameState.currentTurn}`);

    // Wait a bit longer for visual effect so bots don't move instantly (about 2 seconds)
    await new Promise(resolve => setTimeout(resolve, 2000));

    try {
        // Bot rolls dice - rollDice will automatically check for dice_override first
        // If virtualTableId is provided, it checks dice_override table
        // If no override found, it uses random dice
        console.log(`🤖 Bot ${currentPlayer.username} (${currentPlayer.userId}) rolling dice for virtual table ${virtualTableId || 'N/A'}`);
        const rollResult = await gameManager.rollDice(gameId, virtualTableId || null, currentPlayer.userId);
        const diceValue = rollResult.diceValue;
        console.log(`🎲 Bot rolled: ${diceValue}${virtualTableId ? ' (checked dice_override first)' : ''}`);
        
        // Log dice roll if using virtual tables
        if (virtualTableId) {
            const virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
            if (virtualTable) {
                // Find bot player - botId in virtual table matches userId in gameState for bots
                const botPlayer = virtualTable.players.find(p => p.botId === currentPlayer.userId || (p.isBot && p.userId === currentPlayer.userId));
                if (botPlayer) {
                    // For bots: virtualTablePlayerId is the virtual_table_players.id, botId is the bot's ID string
                    await virtualTableManager.logDiceRoll(
                        virtualTableId,
                        botPlayer.id, // virtual_table_players.id (player_id column)
                        botPlayer.botId || currentPlayer.userId, // bot_id column
                        diceValue,
                        false,
                        null,
                        gameState.currentTurn || 0
                    );
                    console.log(`📝 Logged bot dice roll: virtual_table_players.id=${botPlayer.id}, botId=${botPlayer.botId || currentPlayer.userId}`);
                } else {
                    console.warn(`⚠️ Bot player not found in virtual table for userId: ${currentPlayer.userId}`);
                }
            }
        }
        
        // Emit dice roll
        const roomName = virtualTableId ? `virtual_table_${virtualTableId}` : `table_${gameId}`;
        io.to(roomName).emit('dice_rolled', {
            playerId: currentPlayer.userId,
            diceValue,
            isForced: rollResult.isForced,
            events: rollResult.events,
            gameState: gameManager.getGameState(gameId),
            virtualTableId
        });

        // Wait again before moving pawn so total bot reaction feels like ~3–4 seconds
        await new Promise(resolve => setTimeout(resolve, 2000));

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
                // Check if next player is also a bot (recursive) with depth tracking
                await handleBotTurn(gameId, io, virtualTableId, recursionDepth + 1);
            }
        } else {
            // No valid moves, skip turn
            gameManager.gameLogic.nextTurn(gameState);
            gameState.diceValue = null;
            
            const roomName = virtualTableId ? `virtual_table_${virtualTableId}` : `table_${gameId}`;
            io.to(roomName).emit('game_state', {
                gameState,
                virtualTableId
            });
            
            // Check next player with depth tracking
            await handleBotTurn(gameId, io, virtualTableId, recursionDepth + 1);
        }
    } catch (error) {
        console.error('Bot turn error:', error);
    }
}

// Create HTTP server
const serverStartTime = Date.now();
const server = http.createServer((req, res) => {
    if (req.method === 'GET' && (req.url === '/health' || req.url === '/healthz')) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            uptimeSeconds: Math.floor((Date.now() - serverStartTime) / 1000),
            timestamp: new Date().toISOString()
        }));
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'Not found' }));
});

// Initialize Socket.IO with CORS
const io = new Server(server, {
    cors: {
        origin: process.env.CORS_ORIGIN || "http://localhost:8080",
        methods: ["GET", "POST"],
        credentials: true
    }
});

gameManager.setIO(io);

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
            const { tableId, virtualTableId, userId } = data;
            console.log("join_table", data);
            
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
            let virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
            if (!virtualTable) {
                throw new Error('Virtual table not found');
            }

            // Ensure bots are added to fill remaining seats
            const maxPlayers = virtualTable.type === '2-player' ? 2 : 4;
            await virtualTableManager.trimBotsToMax(virtualTableId, maxPlayers);
            virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);

            const realPlayers = virtualTable.players.filter(p => !p.isBot).length;
            const botCount = virtualTable.players.filter(p => p.isBot).length;
            const botsNeeded = Math.max(0, maxPlayers - realPlayers - botCount);

            if (realPlayers >= 1 && botsNeeded > 0) {
                await virtualTableManager.addBotsToVirtualTable(virtualTableId, botsNeeded);
                virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
            }
            
            console.log(`🔍 Virtual table players from DB:`, virtualTable?.players?.map(p => ({
                username: p.username,
                userId: p.userId,
                botId: p.botId,
                isBot: p.isBot
            })));
            
            // Always reinitialize from database to ensure we have all players (including bots)
            // This ensures bots added after initial gameState creation are included
            let gameState = gameManager.getGameState(virtualTableId);
            console.log("gameState", gameState);
            // Force reinitialize if player count doesn't match or gameState doesn't exist
            if (!gameState) {
                console.log("gameState not found");
                // Initialize game state from virtual table
                // Include ALL players (both real and bots)
                const players = virtualTable.players.map(p => {
                    const mapped = {
                        userId: p.userId || p.botId, // For bots, userId will be botId
                        username: p.username,
                        isBot: p.isBot || false,
                        botId: p.botId || null, // Preserve botId for reference
                        points: p.score || 0 // Preserve score if any
                    };
                    console.log(`  → Mapping player:`, mapped);
                    return mapped;
                });

                console.log(`📋 Mapped ${players.length} players for game initialization:`, players.map(p => ({ username: p.username, isBot: p.isBot, userId: p.userId, botId: p.botId })));

                gameState = await gameManager.initializeGameFromVirtualTable(virtualTableId, virtualTable, players);
                
                // Start timer if game is already RUNNING (resuming)
                if (virtualTable.status === 'RUNNING' && gameState.gameStatus === 'running') {
                    gameManager.startTimer(virtualTableId, io);
                }
            } else {
                console.log(`✅ Using existing game state with ${gameState.players.length} players`);
                // Still update gameState to ensure it has latest data
                gameState = await gameManager.initializeGameFromVirtualTable(virtualTableId, virtualTable, virtualTable.players.map(p => ({
                    userId: p.userId || p.botId,
                    username: p.username,
                    isBot: p.isBot || false,
                    botId: p.botId || null,
                    points: p.score || 0
                })));
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
            console.log(`📤 Sending game_state to client with ${gameState.players.length} players:`, 
                gameState.players.map(p => ({ username: p.username, userId: p.userId, isBot: p.isBot })));
            
            socket.emit('game_state', {
                gameState,
                virtualTableId,
                yourPlayerIndex: gameState.players.findIndex(p => p.userId == socket.userId),
                seatNo,
                virtualTableStatus: virtualTable.status // Include virtual table status
            });

            // Notify other players
            socket.to(`virtual_table_${virtualTableId}`).emit('player_joined', {
                player,
                totalPlayers: gameState.players.length,
                realPlayers: gameState.realPlayers,
                virtualTableId
            });

            // Emit wait countdown ONLY if waiting (not for RUNNING games)
            if (virtualTable.status === 'WAITING') {
                const waitEndTime = new Date(virtualTable.wait_end_time);
                const now = new Date();
                const remaining = Math.max(0, Math.floor((waitEndTime - now) / 1000));
                
                socket.emit('wait_countdown', {
                    remaining,
                    total: 30,
                    virtualTableId
                });
            } else if (virtualTable.status === 'RUNNING') {
                // If game is already running, emit game_started to hide waiting screen
                socket.emit('game_started', {
                    gameState,
                    virtualTableId
                });
            }

        } catch (error) {
            console.error('Join table error:', error);
            socket.emit('error', { message: error.message });
        }
    });

    /**
     * Join as spectator (no player seat)
     */
    socket.on('join_spectator', async (data) => {
        try {
            const { virtualTableId } = data;
            if (!virtualTableId) {
                throw new Error('Missing virtualTableId');
            }

            let virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
            if (!virtualTable) {
                throw new Error('Virtual table not found');
            }

            // Ensure bots are added to fill remaining seats (spectator view should match player view)
            const maxPlayers = virtualTable.type === '2-player' ? 2 : 4;
            await virtualTableManager.trimBotsToMax(virtualTableId, maxPlayers);
            virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);

            const realPlayers = virtualTable.players.filter(p => !p.isBot).length;
            const botCount = virtualTable.players.filter(p => p.isBot).length;
            const botsNeeded = Math.max(0, maxPlayers - realPlayers - botCount);

            if (realPlayers >= 1 && botsNeeded > 0) {
                await virtualTableManager.addBotsToVirtualTable(virtualTableId, botsNeeded);
                virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
            }

            let gameState = gameManager.getGameState(virtualTableId);
            if (!gameState) {
                const players = virtualTable.players.map(p => ({
                    userId: p.userId || p.botId,
                    username: p.username,
                    isBot: p.isBot || false,
                    botId: p.botId || null,
                    points: p.score || 0
                }));
                gameState = await gameManager.initializeGameFromVirtualTable(virtualTableId, virtualTable, players);

                if (virtualTable.status === 'RUNNING' && gameState.gameStatus === 'running') {
                    gameManager.startTimer(virtualTableId, io);
                }
            } else {
                gameState = await gameManager.initializeGameFromVirtualTable(virtualTableId, virtualTable, virtualTable.players.map(p => ({
                    userId: p.userId || p.botId,
                    username: p.username,
                    isBot: p.isBot || false,
                    botId: p.botId || null,
                    points: p.score || 0
                })));
            }

            socket.join(`virtual_table_${virtualTableId}`);

            socket.emit('game_state', {
                gameState,
                virtualTableId,
                yourPlayerIndex: -1,
                seatNo: null,
                virtualTableStatus: virtualTable.status,
                spectator: true
            });

            if (virtualTable.status === 'RUNNING') {
                socket.emit('game_started', {
                    gameState,
                    virtualTableId
                });
            }
        } catch (error) {
            console.error('Join spectator error:', error);
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
            
            const gameState = gameManager.getGameState(gameId);
            if (gameState && gameState.gameStatus === 'final_moves') {
                return;
            }

            const rollResult = await gameManager.rollDice(gameId, virtualTableId || null, socket.userId);
            const diceValue = rollResult.diceValue;
            const currentState = gameManager.getGameState(gameId);

            // Check for valid moves, if none, change turn
            const validMoves = gameManager.gameLogic.getValidMoves(currentState, currentState.currentTurn, diceValue);
            const diceEvents = Array.isArray(rollResult.events) ? [...rollResult.events] : [];
            if (validMoves.length === 0) {
                diceEvents.push({ type: 'NO_MOVES', points: 0 });
                gameManager.gameLogic.nextTurn(currentState);
                currentState.diceValue = null; // Reset dice for next player
            }
            
            // Get virtual table info for dice logging
            if (virtualTableId) {
                const virtualTable = await virtualTableManager.getVirtualTable(virtualTableId);
                if (virtualTable) {
                    // Find player in virtual table
                    const player = virtualTable.players.find(p => p.userId == socket.userId);
                    if (player && player.id) {
                        await virtualTableManager.logDiceRoll(
                            virtualTableId,
                            player.userId, // virtual_table_players.id
                            null,//botId
                            diceValue,
                            false,
                            null,
                            currentState.currentTurn || 0
                        );
                    }
                }
            }
            
            // Emit to all players
            const roomName = virtualTableId ? `virtual_table_${virtualTableId}` : `table_${tableId}`;
            io.to(roomName).emit('dice_rolled', {
                playerId: socket.userId,
                diceValue,
                isForced: rollResult.isForced,
                events: diceEvents,
                gameState: currentState,
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
            
            console.log(`✅ Move result:`, {
                pawnIndex,
                oldPosition: moveResult.oldPosition,
                newPosition: moveResult.newPosition,
                blocksMoved: moveResult.blocksMoved,
                success: moveResult.success
            });

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

            if (gameState.gameStatus === 'final_moves') {
                await gameManager.completeFinalMoveTurn(gameId, io, virtualTableId, socket.userId);
                return;
            }

            // Check if next player is a bot
            await handleBotTurn(gameId, io, virtualTableId);

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
        const { tableId, virtualTableId } = data;
        console.log("get_game_state", data);
        const gameId = virtualTableId || tableId;
        const gameState = gameManager.getGameState(gameId);
            console.log("gameState", gameState);
            if (!gameState) {
                socket.emit('error', { message: 'Game not found5' });
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

