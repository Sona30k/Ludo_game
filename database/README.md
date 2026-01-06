# Database Schema - Virtual Tables System

## Installation

1. Run the SQL schema file:
```bash
mysql -u root -p ludo_platform < database/virtual_tables_schema.sql
```

Or import via phpMyAdmin:
- Go to phpMyAdmin
- Select `ludo_platform` database
- Click "Import"
- Select `database/virtual_tables_schema.sql`
- Click "Go"

## Schema Overview

### 1. `tables` (Lobby Configuration)
- Existing table, stores table configurations
- Each table can have multiple virtual instances

### 2. `virtual_tables` (Active Game Instances)
- One row per active game/match
- Tracks game status, timing, dice control
- Links to parent `tables` table

### 3. `virtual_table_players`
- Players in each virtual table
- Supports both real users and bots
- Tracks scores, connection status

### 4. `virtual_table_dice_log`
- Logs all dice rolls
- Tracks forced dice (admin control)
- Useful for game history and debugging

### 5. `virtual_table_moves` (Optional)
- Logs all pawn movements
- For game history and replay

## Key Features

- **One table, multiple instances**: A single table can have multiple virtual tables (games) running
- **Wait time tracking**: `wait_end_time` tracks when wait period ends
- **Bot support**: Bots stored with `bot_id`, humans with `user_id`
- **Dice control**: Per-virtual-table dice control
- **Reconnection**: Easy to find active games for reconnection

## Migration Notes

- Existing `games` table can coexist
- Virtual tables are the new primary system
- Old games table can be migrated or kept for history

