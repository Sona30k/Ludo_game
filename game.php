<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';

$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;
if ($table_id <= 0) {
    header('Location: home.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT id, username, mobile FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
//print_r($user);exit;

// Get table info
$stmt = $pdo->prepare("SELECT id, type, time_limit, entry_points, status FROM tables WHERE id = ?");
$stmt->execute([$table_id]);
$table = $stmt->fetch();
//print_r($table);exit;

if (!$table) {
    header('Location: home.php');
    exit;
}

// Verify user joined this table
$stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE user_id = ? AND reason LIKE ? AND type = 'debit'");
$stmt->execute([$user_id, "%table #{$table_id}%"]);
$hasJoined = $stmt->fetchColumn() > 0;

if (!$hasJoined) {
    header('Location: home.php');
    exit;
}

// WebSocket server URL (update this to your WebSocket server URL)
$wsUrl = 'http://localhost:3000';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <link rel="manifest" href="manifest.json" />
    <title>Ludo Game - Table #<?= $table_id ?></title>
    <link href="style.css" rel="stylesheet">
    <style>
        /* Game Board Styles */
        .game-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 10px;
            background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
            min-height: 100vh;
        }

        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .game-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .prize-pool, .timer-display {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
        }

        .timer-display {
            background: rgba(255, 215, 0, 0.2);
        }

        .current-turn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(34, 197, 94, 0.2);
            padding: 8px 15px;
            border-radius: 8px;
        }

        .current-turn .dice-display {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            border: 3px solid #22c55e;
        }

        /* Game Board */
        .ludo-board {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            aspect-ratio: 1;
            position: relative;
            background: #f0f0f0;
            border-radius: 20px;
            padding: 10px;
        }

        .board-grid {
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-columns: repeat(15, 1fr);
            grid-template-rows: repeat(15, 1fr);
            gap: 2px;
        }

        .board-cell {
            background: white;
            border-radius: 4px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .board-cell.safe-spot {
            background: #fef3c7;
        }

        .board-cell.safe-spot::after {
            content: '★';
            color: #f59e0b;
            font-size: 12px;
        }

        .board-cell.home-red { background: #ef4444; }
        .board-cell.home-blue { background: #3b82f6; }
        .board-cell.home-green { background: #10b981; }
        .board-cell.home-yellow { background: #eab308; }
        .board-cell.center { background: #6b7280; }

        /* Pawns */
        .pawn {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            position: absolute;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .pawn:hover {
            transform: scale(1.2);
        }

        .pawn.red { background: #ef4444; }
        .pawn.blue { background: #3b82f6; }
        .pawn.green { background: #10b981; }
        .pawn.yellow { background: #eab308; }

        .pawn.selectable {
            border: 3px solid #22c55e;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Player Panels */
        .players-panel {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .player-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 12px;
            color: white;
        }

        .player-card.active {
            background: rgba(34, 197, 94, 0.3);
            border: 2px solid #22c55e;
        }

        .player-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .player-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .player-score {
            font-size: 18px;
            font-weight: bold;
            color: #fbbf24;
        }

        /* Dice Section */
        .dice-section {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            z-index: 100;
        }

        .dice-button {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 36px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }

        .dice-button:hover:not(:disabled) {
            transform: scale(1.1);
        }

        .dice-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .dice-button.rolling {
            animation: roll 0.5s infinite;
        }

        @keyframes roll {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(90deg); }
            50% { transform: rotate(180deg); }
            75% { transform: rotate(270deg); }
        }

        /* Waiting Screen */
        .waiting-screen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            z-index: 1000;
        }

        .waiting-screen.hidden {
            display: none;
        }

        /* Game Finished Modal */
        .game-finished-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            z-index: 2000;
        }

        .game-finished-modal.show {
            display: flex;
        }

        .ranking-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            min-width: 300px;
        }

        .ranking-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            margin: 8px 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .ranking-item.first {
            background: rgba(255, 215, 0, 0.3);
            border: 2px solid #fbbf24;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <!-- Game Header -->
        <div class="game-header">
            <div class="game-info">
                <button onclick="window.location.href='home.php'" class="bg-white/20 p-2 rounded-full text-white">
                    <i class="ph ph-arrow-left"></i>
                </button>
                <div class="prize-pool">
                    <i class="ph ph-trophy"></i>
                    <span>Prize Pool ₹<span id="prizePool">0</span></span>
                </div>
                <div class="timer-display">
                    <i class="ph ph-clock"></i>
                    <span id="timer">00:00</span>
                </div>
            </div>
            <div class="current-turn" id="currentTurnDisplay" style="display: none;">
                <div class="dice-display" id="currentDice">-</div>
                <span id="currentPlayerName">-</span>
            </div>
        </div>

        <!-- Game Board -->
        <div class="ludo-board" id="gameBoard">
            <!-- Board will be rendered here by JavaScript -->
        </div>

        <!-- Players Panel -->
        <div class="players-panel" id="playersPanel">
            <!-- Players will be rendered here -->
        </div>

        <!-- Dice Section -->
        <div class="dice-section">
            <button class="dice-button" id="rollDiceBtn" onclick="rollDice()" disabled>
                <span id="diceValue">🎲</span>
            </button>
            <p class="text-white text-sm" id="diceStatus">Waiting for your turn...</p>
        </div>

        <!-- Waiting Screen -->
        <div class="waiting-screen" id="waitingScreen">
            <div class="text-center">
                <div class="text-6xl mb-4">🎲</div>
                <h2 class="text-2xl font-bold mb-2">Waiting for Players...</h2>
                <p id="waitingText">Players joined: <span id="playersCount">0</span></p>
            </div>
        </div>

        <!-- Game Finished Modal -->
        <div class="game-finished-modal" id="gameFinishedModal">
            <div class="text-center">
                <h2 class="text-3xl font-bold mb-6">Game Finished!</h2>
                <div class="ranking-list" id="rankingList">
                    <!-- Ranking will be shown here -->
                </div>
                <button onclick="window.location.href='home.php'" class="mt-6 bg-p2 px-8 py-3 rounded-full text-white font-bold">
                    Back to Home
                </button>
            </div>
        </div>
    </div>

    <script>
        // Game State
        let socket = null;
        let gameState = null;
        let myPlayerIndex = -1;
        let tableId = <?= $table_id ?>;
        let virtualTableId = null; // Will be set when joining virtual table
        let userId = <?= $user_id ?>;
        let username = '<?= addslashes($user['username']) ?>';
        const wsUrl = '<?= $wsUrl ?>';

        // Initialize Socket Connection
        function initSocket() {
            socket = io(wsUrl, {
                auth: {
                    userId: userId,
                    username: username
                },
                reconnection: true,
                reconnectionDelay: 1000,
                reconnectionAttempts: 5
            });

            socket.on('connect', () => {
                console.log('✅ Connected to WebSocket server');
                joinTable();
            });

            socket.on('disconnect', () => {
                console.log('❌ Disconnected from server');
            });

            socket.on('connect_error', (error) => {
                console.error('Connection error:', error);
                alert('Failed to connect to game server. Please refresh the page.');
            });

            // Game Events
            socket.on('game_state', (data) => {
                gameState = data.gameState;
                myPlayerIndex = data.yourPlayerIndex;
                virtualTableId = data.virtualTableId || virtualTableId; // Store virtualTableId
                if (data.seatNo !== undefined) {
                    console.log('Assigned seat:', data.seatNo);
                }
                updateGameUI();
            });

            socket.on('player_joined', (data) => {
                console.log('Player joined:', data);
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                if (gameState) {
                    updatePlayersPanel();
                }
            });

            socket.on('game_started', (data) => {
                console.log('Game started!');
                gameState = data.gameState;
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                document.getElementById('waitingScreen').classList.add('hidden');
                updateGameUI();
            });

            socket.on('wait_countdown', (data) => {
                console.log('Wait countdown:', data.remaining);
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                updateWaitCountdown(data.remaining, data.total);
            });

            socket.on('game_cancelled', (data) => {
                console.log('Game cancelled:', data);
                alert('Game cancelled: ' + data.reason + (data.refunded ? ' (Entry fee refunded)' : ''));
                window.location.href = 'home.php';
            });

            socket.on('dice_rolled', (data) => {
                console.log('Dice rolled:', data);
                gameState = data.gameState;
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                updateDiceDisplay(data.diceValue, data.playerId);
                updateGameUI();
            });

            socket.on('pawn_moved', (data) => {
                console.log('Pawn moved:', data);
                gameState = data.gameState;
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                updateBoard();
                updateGameUI();
                
                if (data.moveResult && data.moveResult.gameFinished) {
                    showGameFinished(data.moveResult.ranking);
                }
            });

            socket.on('game_finished', (data) => {
                console.log('Game finished:', data);
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                showGameFinished(data.ranking);
            });

            socket.on('error', (data) => {
                console.error('Error:', data);
                alert(data.message || 'An error occurred');
            });
        }

        // Join Table
        function joinTable() {
            socket.emit('join_table', { tableId: tableId });
        }

        // Roll Dice
        function rollDice() {
            if (!socket || !gameState) return;
            
            const btn = document.getElementById('rollDiceBtn');
            btn.disabled = true;
            btn.classList.add('rolling');
            
            socket.emit('roll_dice', { 
                virtualTableId: virtualTableId,
                tableId: tableId // Keep for backward compatibility
            });
        }

        // Move Pawn
        function movePawn(pawnIndex) {
            if (!socket || !gameState) return;
            
            socket.emit('move_pawn', { 
                virtualTableId: virtualTableId,
                tableId: tableId, // Keep for backward compatibility
                pawnIndex: pawnIndex 
            });
        }

        // Update Game UI
        function updateGameUI() {
            if (!gameState) return;

            // Update timer
            if (gameState.remainingTime !== undefined) {
                updateTimer(gameState.remainingTime);
            }

            // Update prize pool (only real players, not bots)
            const realPlayers = gameState.realPlayers || gameState.players.filter(p => !p.isBot).length;
            const prizePool = gameState.entryPoints * realPlayers;
            document.getElementById('prizePool').textContent = prizePool;

            // Update players panel
            updatePlayersPanel();

            // Update board
            updateBoard();

            // Update dice button state
            updateDiceButton();

            // Update current turn display
            updateCurrentTurn();
        }

        // Update Timer
        function updateTimer(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            document.getElementById('timer').textContent = 
                `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // Update Players Panel
        function updatePlayersPanel() {
            if (!gameState) return;

            const panel = document.getElementById('playersPanel');
            const maxPlayers = gameState.tableType === '2-player' ? 2 : 4;
            
            // Remove duplicates by userId
            const uniquePlayers = [];
            const seenUserIds = new Set();
            
            gameState.players.forEach((player) => {
                const playerId = player.userId || player.id;
                if (!seenUserIds.has(playerId)) {
                    seenUserIds.add(playerId);
                    uniquePlayers.push(player);
                }
            });
            
            let html = '';
            uniquePlayers.forEach((player, index) => {
                const isActive = index === gameState.currentTurn;
                html += `
                    <div class="player-card ${isActive ? 'active' : ''}">
                        <div class="player-info">
                            <div class="player-avatar" style="background: ${getColorGradient(player.color)}">
                                ${player.username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div class="font-semibold">${player.username}</div>
                                <div class="text-xs opacity-70">${getPlayerLabel(index, maxPlayers)}</div>
                            </div>
                        </div>
                        <div class="player-score">Score: ${player.points}</div>
                    </div>
                `;
            });

            panel.innerHTML = html;
            document.getElementById('playersCount').textContent = uniquePlayers.length;
        }

        // Get Player Label
        function getPlayerLabel(index, maxPlayers) {
            const labels = ['First Mover', 'Second Mover', 'Third Mover', 'Fourth Mover'];
            return labels[index] || `Player ${index + 1}`;
        }

        // Get Color Gradient
        function getColorGradient(color) {
            const gradients = {
                red: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                blue: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
                green: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                yellow: 'linear-gradient(135deg, #eab308 0%, #ca8a04 100%)'
            };
            return gradients[color] || gradients.red;
        }

        // Update Board (Simplified - you'll need to implement full board rendering)
        function updateBoard() {
            // This is a placeholder - you'll need to implement full board rendering
            // based on pawn positions from gameState
            const board = document.getElementById('gameBoard');
            // For now, just show a simple message
            if (!board.querySelector('.board-placeholder')) {
                board.innerHTML = '<div class="board-placeholder text-white text-center p-8">Game Board - Full rendering coming soon</div>';
            }
        }

        // Update Dice Button
        function updateDiceButton() {
            if (!gameState) return;

            const btn = document.getElementById('rollDiceBtn');
            const status = document.getElementById('diceStatus');
            const currentPlayer = gameState.players[gameState.currentTurn];

            // Use loose comparison for userId (handles string/number mismatch)
            const isMyTurn = currentPlayer && (currentPlayer.userId == userId || currentPlayer.userId === userId);
            
            if (isMyTurn) {
                if (gameState.diceValue === null) {
                    btn.disabled = false;
                    status.textContent = 'Your turn! Roll the dice';
                } else {
                    btn.disabled = true;
                    status.textContent = 'Select a pawn to move';
                }
            } else {
                btn.disabled = true;
                status.textContent = `Waiting for ${currentPlayer?.username || 'player'}...`;
            }
        }

        // Update Current Turn Display
        function updateCurrentTurn() {
            if (!gameState) return;

            const display = document.getElementById('currentTurnDisplay');
            const currentPlayer = gameState.players[gameState.currentTurn];

            if (currentPlayer) {
                display.style.display = 'flex';
                document.getElementById('currentPlayerName').textContent = currentPlayer.username;
                
                if (gameState.diceValue) {
                    document.getElementById('currentDice').textContent = gameState.diceValue;
                } else {
                    document.getElementById('currentDice').textContent = '-';
                }
            } else {
                display.style.display = 'none';
            }
        }

        // Update Dice Display
        function updateDiceDisplay(value, playerId) {
            const btn = document.getElementById('rollDiceBtn');
            btn.classList.remove('rolling');
            
            if (playerId == userId || playerId === userId) {
                btn.textContent = value;
            }
        }

        // Update Wait Countdown
        function updateWaitCountdown(remaining, total) {
            const waitingScreen = document.getElementById('waitingScreen');
            const waitingText = document.getElementById('waitingText');
            
            if (waitingText) {
                const percentage = Math.round((total - remaining) / total * 100);
                waitingText.innerHTML = `
                    <p>Waiting for players... ${remaining}s</p>
                    <div class="w-full bg-white/20 rounded-full h-2 mt-2">
                        <div class="bg-p2 h-2 rounded-full transition-all" style="width: ${percentage}%"></div>
                    </div>
                `;
            }
        }

        // Show Game Finished
        function showGameFinished(ranking) {
            const modal = document.getElementById('gameFinishedModal');
            const list = document.getElementById('rankingList');

            let html = '';
            ranking.forEach((player, index) => {
                const isWinner = index === 0;
                html += `
                    <div class="ranking-item ${isWinner ? 'first' : ''}">
                        <div>
                            <div class="font-bold">${index + 1}. ${player.username}</div>
                            <div class="text-sm opacity-70">${player.points} points</div>
                            ${player.isBot ? '<div class="text-xs opacity-50 italic">Bot</div>' : ''}
                        </div>
                        ${isWinner ? '<div class="text-2xl">🏆</div>' : ''}
                    </div>
                `;
            });

            list.innerHTML = html;
            modal.classList.add('show');
            
            // Update virtual table status if using virtual tables
            if (virtualTableId) {
                console.log('Game finished for virtual table:', virtualTableId);
            }
        }

        // Reconnect to active virtual table on page load
        async function checkActiveVirtualTable() {
            try {
                const response = await fetch(`api/tables/get-active-virtual-table.php?table_id=${tableId}`);
                const data = await response.json();
                
                if (data.success && data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                    console.log('✅ Found active virtual table:', virtualTableId);
                    return true;
                }
            } catch (error) {
                console.error('Error checking active virtual table:', error);
            }
            return false;
        }

        // Request game state (for reconnection)
        function requestGameState() {
            if (!socket) return;
            
            if (virtualTableId) {
                socket.emit('get_game_state', { 
                    virtualTableId: virtualTableId,
                    tableId: tableId 
                });
            } else {
                socket.emit('get_game_state', { tableId: tableId });
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', async () => {
            const hasVirtualTable = await checkActiveVirtualTable();
            initSocket();
            
            // Request game state after connection if we have virtual table
            if (hasVirtualTable) {
                socket.on('connect', () => {
                    setTimeout(requestGameState, 500); // Wait a bit for connection to stabilize
                });
            }
        });
    </script>
</body>
</html>
