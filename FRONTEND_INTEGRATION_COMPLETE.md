# Frontend Integration - Virtual Tables System ✅

## ✅ Completed Updates

### 1. **game.php** - Main Game Page
- ✅ Added `virtualTableId` variable to track virtual table
- ✅ Updated all WebSocket events to handle `virtualTableId`
- ✅ Updated `rollDice()` to send `virtualTableId`
- ✅ Updated `movePawn()` to send `virtualTableId`
- ✅ Updated `updateGameUI()` to calculate prize pool from real players only
- ✅ Added `checkActiveVirtualTable()` to find active virtual table on page load
- ✅ Added `requestGameState()` for reconnection
- ✅ Updated `showGameFinished()` to show bot indicator
- ✅ Updated player comparison to use loose equality (`==`) for type safety

### 2. **api/tables/get-active-virtual-table.php** - New API
- ✅ Created API to find active virtual table for user
- ✅ Used for reconnection when user refreshes page

### 3. **websocket/server.js** - WebSocket Server
- ✅ Updated `join_table` handler to use VirtualTableManager
- ✅ Updated `roll_dice` to support `virtualTableId` and log to database
- ✅ Updated `move_pawn` to support `virtualTableId` and log moves
- ✅ Updated `get_game_state` to support virtual table reconnection
- ✅ Updated `get_valid_moves` to support `virtualTableId`
- ✅ Updated `handleBotTurn` to support `virtualTableId` and log dice/moves

### 4. **websocket/game/GameManager.js**
- ✅ Added `initializeGameFromVirtualTable()` method
- ✅ Updated `addPlayerToGame()` to support both tableId and virtualTableId
- ✅ Added `parseTimeLimit()` method

## 🔄 How It Works Now

### Join Flow:
1. User clicks "Join Now" → Entry fee deducted → Redirected to `game.php`
2. `game.php` loads → Checks for active virtual table via API
3. Connects to WebSocket → Emits `join_table` with `tableId`
4. Server:
   - Finds or creates virtual table
   - Adds player to `virtual_table_players`
   - Initializes game state
   - Returns `virtualTableId` to client
5. Client stores `virtualTableId` and uses it for all game events

### Game Events:
- All events now support both `virtualTableId` (new) and `tableId` (backward compatibility)
- Dice rolls logged to `virtual_table_dice_log`
- Moves logged to `virtual_table_moves`
- Bot actions also logged

### Reconnection:
- On page load, checks for active virtual table
- If found, requests game state using `virtualTableId`
- Server rebuilds game state from virtual table data
- Player can continue playing seamlessly

## 📋 Testing Checklist

- [ ] Install database schema: `mysql -u root -p ludo_platform < database/virtual_tables_schema.sql`
- [ ] Install UUID package: `cd websocket && npm install uuid`
- [ ] Start WebSocket server: `npm start`
- [ ] Join a table from home page
- [ ] Verify virtual table is created in database
- [ ] Verify player is added to `virtual_table_players`
- [ ] Test dice roll (check `virtual_table_dice_log`)
- [ ] Test pawn move (check `virtual_table_moves`)
- [ ] Test reconnection (refresh page, should reconnect)
- [ ] Test bot filling (wait 30 seconds)
- [ ] Test game start

## 🎯 Key Features

1. **Multiple Instances**: One table can have multiple virtual tables (games)
2. **Proper Tracking**: Each game instance tracked separately
3. **Reconnection**: Easy to find and reconnect to active games
4. **Dice Logging**: All dice rolls logged with forced/admin info
5. **Move History**: All moves logged for game history
6. **Backward Compatible**: Still supports old `tableId` system

## ⚠️ Important Notes

- Virtual tables use UUID as primary key
- Game state in memory uses `virtualTableId` as key (not `tableId`)
- All WebSocket events support both systems for backward compatibility
- Prize pool calculation uses `realPlayers` only (not total players including bots)

