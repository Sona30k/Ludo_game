const pool = require('../config/database');
const { v4: uuidv4 } = require('uuid');
const BotManager = require('./BotManager');

/**
 * Virtual Table Manager
 * Manages virtual table instances (active game matches)
 */
class VirtualTableManager {
    constructor() {
        this.botManager = new BotManager();
        this.WAIT_TIME_SECONDS = 30;
    }

    /**
     * Find or create virtual table for a table_id
     * Priority: 1) User's existing active virtual table, 2) Available WAITING table, 3) Create new
     */
    async findOrCreateVirtualTable(tableId, userId) {
        try {
            // PRIORITY 1: Check if user already in an ACTIVE virtual table for this table_id
            // Check for WAITING status (wait time not expired)
            const [waitingVT] = await pool.execute(
                `SELECT vtp.virtual_table_id, vt.status, vt.wait_end_time
                 FROM virtual_table_players vtp
                 JOIN virtual_tables vt ON vtp.virtual_table_id = vt.id
                 WHERE vtp.user_id = ? 
                   AND vt.table_id = ? 
                   AND vt.status = 'WAITING'
                   AND vt.wait_end_time > NOW()
                 ORDER BY vt.created_at DESC
                 LIMIT 1`,
                [userId, tableId]
            );

            if (waitingVT.length > 0) {
                console.log(`✅ Found user's existing WAITING virtual table: ${waitingVT[0].virtual_table_id}`);
                return waitingVT[0].virtual_table_id; // Reconnect to existing
            }

            // Check for RUNNING status (game hasn't ended)
            const [runningVT] = await pool.execute(
                `SELECT vtp.virtual_table_id, vt.status, vt.end_time
                 FROM virtual_table_players vtp
                 JOIN virtual_tables vt ON vtp.virtual_table_id = vt.id
                 WHERE vtp.user_id = ? 
                   AND vt.table_id = ? 
                   AND vt.status = 'RUNNING'
                   AND (vt.end_time IS NULL OR vt.end_time > NOW())
                 ORDER BY vt.created_at DESC
                 LIMIT 1`,
                [userId, tableId]
            );

            if (runningVT.length > 0) {
                console.log(`✅ Found user's existing RUNNING virtual table: ${runningVT[0].virtual_table_id}`);
                return runningVT[0].virtual_table_id; // Reconnect to existing game
            }

            // PRIORITY 2: Find available WAITING virtual table (not expired, not full, same table_id)
            const [availableVT] = await pool.execute(
                `SELECT vt.id, COUNT(vtp.id) as player_count, t.type
                 FROM virtual_tables vt
                 JOIN tables t ON vt.table_id = t.id
                 LEFT JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id
                 WHERE vt.table_id = ? 
                   AND vt.status = 'WAITING'
                   AND vt.wait_end_time > NOW()
                 GROUP BY vt.id, t.type
                 HAVING player_count < (CASE WHEN t.type = '2-player' THEN 2 ELSE 4 END)
                 ORDER BY vt.created_at ASC
                 LIMIT 1`,
                [tableId]
            );

            if (availableVT.length > 0) {
                console.log(`✅ Found available WAITING virtual table: ${availableVT[0].id}`);
                return availableVT[0].id; // Join existing virtual table
            }

            // PRIORITY 3: Create new virtual table
            console.log(`🆕 Creating new virtual table for table ${tableId}`);
            return await this.createVirtualTable(tableId);

        } catch (error) {
            console.error('Error finding/creating virtual table:', error);
            throw error;
        }
    }

    /**
     * Create a new virtual table
     */
    async createVirtualTable(tableId) {
        try {
            // Get table configuration
            const [tables] = await pool.execute(
                'SELECT id, type, time_limit, entry_points FROM tables WHERE id = ?',
                [tableId]
            );

            if (tables.length === 0) {
                throw new Error('Table not found');
            }

            const table = tables[0];
            const virtualTableId = uuidv4();

            // Calculate wait end time (30 seconds from now)
            const waitEndTime = new Date();
            waitEndTime.setSeconds(waitEndTime.getSeconds() + this.WAIT_TIME_SECONDS);

            // Parse time limit to seconds
            const durationSeconds = this.parseTimeLimit(table.time_limit);

            // Create virtual table
            await pool.execute(
                `INSERT INTO virtual_tables 
                 (id, table_id, status, wait_end_time, dice_mode, created_at) 
                 VALUES (?, ?, 'WAITING', ?, 'FAIR', NOW())`,
                [virtualTableId, tableId, waitEndTime]
            );

            console.log(`✅ Created virtual table ${virtualTableId} for table ${tableId}`);
            return virtualTableId;

        } catch (error) {
            console.error('Error creating virtual table:', error);
            throw error;
        }
    }

    /**
     * Add player to virtual table
     */
    async addPlayerToVirtualTable(virtualTableId, userId, username, isBot = false, botId = null) {
        try {
            // Check if player already in this virtual table
            const [existing] = await pool.execute(
                `SELECT id, seat_no FROM virtual_table_players 
                 WHERE virtual_table_id = ? AND (user_id = ? OR bot_id = ?)`,
                [virtualTableId, userId, botId]
            );

            if (existing.length > 0) {
                // Player already exists - RECONNECT
                console.log(`🔄 Reconnecting ${isBot ? 'bot' : 'player'} ${username} to virtual table ${virtualTableId} at seat ${existing[0].seat_no}`);
                
                // Update connection status and last action
                await pool.execute(
                    `UPDATE virtual_table_players 
                     SET is_connected = 1, last_action_at = NOW() 
                     WHERE id = ?`,
                    [existing[0].id]
                );
                return existing[0].seat_no;
            }

            // Get next available seat
            const [seats] = await pool.execute(
                `SELECT seat_no FROM virtual_table_players 
                 WHERE virtual_table_id = ? 
                 ORDER BY seat_no ASC`,
                [virtualTableId]
            );

            const usedSeats = seats.map(s => s.seat_no);
            let seatNo = 0;
            while (usedSeats.includes(seatNo)) {
                seatNo++;
            }

            // Add player
            await pool.execute(
                `INSERT INTO virtual_table_players 
                 (virtual_table_id, user_id, bot_id, is_bot, seat_no, is_connected, created_at) 
                 VALUES (?, ?, ?, ?, ?, 1, NOW())`,
                [virtualTableId, isBot ? null : userId, botId, isBot ? 1 : 0, seatNo]
            );

            console.log(`✅ Added ${isBot ? 'bot' : 'player'} to virtual table ${virtualTableId} at seat ${seatNo}`);
            return seatNo;

        } catch (error) {
            console.error('Error adding player to virtual table:', error);
            throw error;
        }
    }

    /**
     * Get virtual table with players
     */
    async getVirtualTable(virtualTableId) {
        try {
            const [virtualTables] = await pool.execute(
                `SELECT vt.*, t.type, t.entry_points, t.time_limit
                 FROM virtual_tables vt
                 JOIN tables t ON vt.table_id = t.id
                 WHERE vt.id = ?`,
                [virtualTableId]
            );

            if (virtualTables.length === 0) {
                return null;
            }

            const vt = virtualTables[0];

            // Get players
            const [players] = await pool.execute(
                `SELECT vtp.*, u.username
                 FROM virtual_table_players vtp
                 LEFT JOIN users u ON vtp.user_id = u.id
                 WHERE vtp.virtual_table_id = ?
                 ORDER BY vtp.seat_no ASC`,
                [virtualTableId]
            );

            vt.players = players.map(p => ({
                id: p.id,
                userId: p.user_id,
                botId: p.bot_id,
                username: p.username || p.bot_id, // Use bot_id as username for bots
                isBot: p.is_bot === 1,
                score: p.score,
                seatNo: p.seat_no,
                isConnected: p.is_connected === 1
            }));

            return vt;

        } catch (error) {
            console.error('Error getting virtual table:', error);
            throw error;
        }
    }

    /**
     * Check and start virtual table (called by cron/job)
     * Also marks ENDED games that have passed end_time
     */
    async checkAndStartVirtualTables() {
        try {
            // Mark RUNNING games as ENDED if end_time has passed
            await pool.execute(
                `UPDATE virtual_tables 
                 SET status = 'ENDED' 
                 WHERE status = 'RUNNING' 
                   AND end_time IS NOT NULL 
                   AND end_time <= NOW()`
            );

            // Find all WAITING virtual tables where wait time has ended
            const [waitingVTs] = await pool.execute(
                `SELECT vt.*, t.type, t.entry_points, COUNT(DISTINCT CASE WHEN vtp.is_bot = 0 THEN vtp.id END) as real_player_count
                 FROM virtual_tables vt
                 JOIN tables t ON vt.table_id = t.id
                 LEFT JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id
                 WHERE vt.status = 'WAITING' 
                   AND vt.wait_end_time <= NOW()
                 GROUP BY vt.id, vt.table_id, vt.status, vt.wait_end_time, t.type, t.entry_points
                 HAVING real_player_count >= 1`,
                []
            );

            for (const vt of waitingVTs) {
                const realPlayers = parseInt(vt.real_player_count);
                const maxPlayers = vt.type === '2-player' ? 2 : 4;
                
                // Always add exactly 1 bot (one slot always reserved for bot)
                // 2-player: 1 real player + 1 bot
                // 4-player: 3 real players + 1 bot
                const botsNeeded = 1;

                if (realPlayers >= 1) {
                    // Check if bot already exists
                    const [existingBots] = await pool.execute(
                        'SELECT COUNT(*) as bot_count FROM virtual_table_players WHERE virtual_table_id = ? AND is_bot = 1',
                        [vt.id]
                    );
                    const botCount = existingBots[0]?.bot_count || 0;
                    
                    // Add bot if not already present
                    if (botCount === 0) {
                        await this.addBotsToVirtualTable(vt.id, botsNeeded);
                    }

                    // Start the game
                    await this.startVirtualTable(vt.id);
                } else {
                    // Cancel and refund
                    await this.cancelVirtualTable(vt.id);
                }
            }

        } catch (error) {
            console.error('Error checking virtual tables:', error);
        }
    }

    /**
     * Add bots to virtual table
     */
    async addBotsToVirtualTable(virtualTableId, count) {
        try {
            // Get existing player names
            const [players] = await pool.execute(
                `SELECT username FROM virtual_table_players vtp
                 LEFT JOIN users u ON vtp.user_id = u.id
                 WHERE vtp.virtual_table_id = ?`,
                [virtualTableId]
            );

            const existingNames = players.map(p => p.username).filter(Boolean);
            const bots = this.botManager.createBots(count, existingNames);

            for (let i = 0; i < bots.length; i++) {
                await this.addPlayerToVirtualTable(
                    virtualTableId,
                    null,
                    bots[i].username,
                    true,
                    bots[i].userId
                );
            }

            console.log(`🤖 Added ${count} bots to virtual table ${virtualTableId}`);

        } catch (error) {
            console.error('Error adding bots:', error);
            throw error;
        }
    }

    /**
     * Start virtual table (game begins)
     */
    async startVirtualTable(virtualTableId) {
        try {
            const vt = await this.getVirtualTable(virtualTableId);
            if (!vt) return;

            // Check if already started
            if (vt.status === 'RUNNING') {
                console.log(`⚠️ Virtual table ${virtualTableId} already running`);
                return;
            }

            // Fetch time_limit directly from tables table
            const [tables] = await pool.execute(
                'SELECT time_limit FROM tables WHERE id = ?',
                [vt.table_id]
            );

            if (tables.length === 0) {
                throw new Error('Table configuration not found');
            }

            // Parse time_limit (e.g., "3-min", "5-min", "10-min") to seconds
            // Extract integer and multiply by 60
            const timeLimit = tables[0].time_limit;
            const durationSeconds = this.parseTimeLimit(timeLimit);

            const startTime = new Date();
            const endTime = new Date(startTime);
            endTime.setSeconds(endTime.getSeconds() + durationSeconds);

            // Set start_time, total_duration, and initialize current_duration to 0
            await pool.execute(
                `UPDATE virtual_tables 
                 SET status = 'RUNNING', start_time = ?, end_time = ?, 
                     total_duration = ?, current_duration = 0, current_turn_index = 0
                 WHERE id = ?`,
                [startTime, endTime, durationSeconds, virtualTableId]
            );

            console.log(`🎮 Started virtual table ${virtualTableId} (${durationSeconds}s duration from ${timeLimit}, ends at ${endTime.toISOString()})`);

        } catch (error) {
            console.error('Error starting virtual table:', error);
            throw error;
        }
    }

    /**
     * Cancel virtual table and refund players
     */
    async cancelVirtualTable(virtualTableId) {
        try {
            const vt = await this.getVirtualTable(virtualTableId);
            if (!vt) return;

            // Refund all real players (only insert transaction, balance calculated from transactions)
            const realPlayers = vt.players.filter(p => !p.isBot);
            for (const player of realPlayers) {
                if (player.userId) {
                    // Log credit transaction (balance is calculated from transactions, no wallet table update needed)
                    await pool.execute(
                        `INSERT INTO wallet_transactions (user_id, amount, type, reason, table_id) 
                         VALUES (?, ?, 'credit', ?, ?)`,
                        [player.userId, vt.entry_points, `Refund: Virtual table ${virtualTableId} cancelled`, vt.table_id]
                    );
                }
            }

            // Update status
            await pool.execute(
                `UPDATE virtual_tables SET status = 'CANCELLED' WHERE id = ?`,
                [virtualTableId]
            );

            console.log(`❌ Cancelled virtual table ${virtualTableId}`);

        } catch (error) {
            console.error('Error cancelling virtual table:', error);
            throw error;
        }
    }

    /**
     * Log dice roll
     */
    async logDiceRoll(virtualTableId, playerId, botId, diceValue, isForced = false, adminId = null, turnNo) {
        try {
            await pool.execute(
                `INSERT INTO virtual_table_dice_log 
                 (virtual_table_id, player_id, bot_id, dice_value, is_forced, forced_by_admin_id, turn_no, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())`,
                [virtualTableId, playerId, botId, diceValue, isForced ? 1 : 0, adminId, turnNo]
            );
        } catch (error) {
            console.error('Error logging dice roll:', error);
        }
    }

    /**
     * Parse time limit string to seconds
     */
    parseTimeLimit(timeLimit) {
        const match = timeLimit.match(/(\d+)-min/);
        if (match) {
            return parseInt(match[1]) * 60;
        }
        return 180; // Default 3 minutes
    }

    /**
     * Update current_duration for all RUNNING virtual tables
     * Called every 5 seconds
     */
    async updateCurrentDuration() {
        try {
            // Get all RUNNING virtual tables with start_time
            const [runningVTs] = await pool.execute(
                `SELECT id, start_time, total_duration 
                 FROM virtual_tables 
                 WHERE status = 'RUNNING' 
                   AND start_time IS NOT NULL 
                   AND total_duration IS NOT NULL`
            );

            for (const vt of runningVTs) {
                const startTime = new Date(vt.start_time);
                const now = new Date();
                const elapsedSeconds = Math.floor((now - startTime) / 1000);
                
                // Update current_duration (don't exceed total_duration)
                const currentDuration = Math.min(elapsedSeconds, vt.total_duration);
                
                await pool.execute(
                    `UPDATE virtual_tables 
                     SET current_duration = ? 
                     WHERE id = ?`,
                    [currentDuration, vt.id]
                );
            }

        } catch (error) {
            console.error('Error updating current duration:', error);
        }
    }

    /**
     * Find active virtual table for user (for reconnection)
     * Checks both WAITING and RUNNING status
     */
    async findActiveVirtualTableForUser(userId, tableId = null) {
        try {
            let query = `
                SELECT vt.id, vt.table_id, vt.status, vt.end_time, vt.wait_end_time
                FROM virtual_tables vt
                JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id
                WHERE vtp.user_id = ? 
                  AND vt.status IN ('WAITING', 'RUNNING')
            `;
            
            const params = [userId];
            
            if (tableId) {
                query += ` AND vt.table_id = ?`;
                params.push(tableId);
            }
            
            // For WAITING: check wait_end_time
            // For RUNNING: check end_time
            query += ` AND (
                (vt.status = 'WAITING' AND vt.wait_end_time > NOW()) OR
                (vt.status = 'RUNNING' AND (vt.end_time IS NULL OR vt.end_time > NOW()))
            )
            ORDER BY vt.created_at DESC
            LIMIT 1`;
            
            const [vts] = await pool.execute(query, params);

            return vts.length > 0 ? vts[0] : null;

        } catch (error) {
            console.error('Error finding active virtual table:', error);
            return null;
        }
    }
}

module.exports = VirtualTableManager;

