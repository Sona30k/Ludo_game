# Ludo Platform WebSocket Server

Real-time multiplayer WebSocket server for Ludo Supreme Platform using Socket.IO.

## Features

- ✅ Real-time multiplayer gameplay
- ✅ Turn-based game logic
- ✅ Dice roll system with admin override support
- ✅ Timer-based matches (3-min, 5-min, 10-min)
- ✅ Points-based scoring system
- ✅ Auto-play for inactive players
- ✅ Reconnection/resume support
- ✅ Game state management
- ✅ Prize distribution

## Installation

1. Install dependencies:
```bash
npm install
```

2. Copy `.env.example` to `.env` and configure:
```bash
cp .env.example .env
```

3. Update `.env` with your database credentials:
```
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=ludo_platform
PORT=3000
CORS_ORIGIN=http://localhost
```

## Running the Server

### Development Mode (with auto-reload):
```bash
npm run dev
```

### Production Mode:
```bash
npm start
```

The server will start on port 3000 (or the port specified in `.env`).

## Socket.IO Events

### Client → Server Events

#### `join_table`
Join a game table.
```javascript
socket.emit('join_table', { tableId: 123 });
```

#### `roll_dice`
Roll dice for current turn.
```javascript
socket.emit('roll_dice', { tableId: 123 });
```

#### `move_pawn`
Move a pawn.
```javascript
socket.emit('move_pawn', { tableId: 123, pawnIndex: 0 });
```

#### `get_valid_moves`
Get valid moves for current player.
```javascript
socket.emit('get_valid_moves', { tableId: 123 });
```

#### `get_game_state`
Request current game state (for reconnection).
```javascript
socket.emit('get_game_state', { tableId: 123 });
```

### Server → Client Events

#### `game_state`
Current game state sent to player.
```javascript
socket.on('game_state', (data) => {
    const { gameState, yourPlayerIndex } = data;
});
```

#### `player_joined`
Notification when a player joins.
```javascript
socket.on('player_joined', (data) => {
    const { player, totalPlayers } = data;
});
```

#### `game_started`
Notification when game starts.
```javascript
socket.on('game_started', (data) => {
    const { gameState } = data;
});
```

#### `dice_rolled`
Notification when dice is rolled.
```javascript
socket.on('dice_rolled', (data) => {
    const { playerId, diceValue, gameState } = data;
});
```

#### `pawn_moved`
Notification when a pawn is moved.
```javascript
socket.on('pawn_moved', (data) => {
    const { playerId, pawnIndex, moveResult, gameState } = data;
});
```

#### `game_finished`
Notification when game ends.
```javascript
socket.on('game_finished', (data) => {
    const { ranking, gameState } = data;
});
```

#### `error`
Error notification.
```javascript
socket.on('error', (data) => {
    const { message } = data;
});
```

## Authentication

When connecting, provide user credentials:
```javascript
const socket = io('http://localhost:3000', {
    auth: {
        userId: 123,
        username: 'player1'
    }
});
```

## Game Logic

### Points System
- **Move Points**: 1 point per block moved
- **Kill Points**: +10 points for killing opponent pawn
- **Kill Penalty**: -5 points when your pawn is killed

### Safe Spots
Positions where pawns cannot be killed: 1, 9, 14, 22, 27, 35, 40, 48, 52

### Dice Override
Admin can set dice values via `dice_override` table. The server checks for unused overrides before rolling random dice.

## Database Requirements

The server expects the following tables:
- `users` - User accounts
- `tables` - Game tables
- `games` - Game sessions
- `wallets` - User wallets
- `wallet_transactions` - Transaction history
- `dice_override` - Admin dice control

## Project Structure

```
websocket/
├── server.js           # Main WebSocket server
├── config/
│   └── database.js     # Database connection
├── game/
│   ├── GameManager.js   # Game state management
│   └── GameLogic.js    # Game rules and logic
├── utils/
│   └── helpers.js      # Utility functions
├── package.json
├── .env.example
└── README.md
```

## Next Steps

1. Integrate WebSocket client in frontend
2. Create game UI page
3. Connect with PHP backend APIs
4. Test multiplayer gameplay

