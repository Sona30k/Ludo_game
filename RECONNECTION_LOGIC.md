# Virtual Table Reconnection Logic - Fixed ✅

## Problem
When user refreshes page, a new virtual table was being created instead of reconnecting to existing one.

## Solution Implemented

### Priority Order (in `findOrCreateVirtualTable`):

1. **PRIORITY 1: User's Existing Active Virtual Table**
   - Check if user is in a WAITING virtual table (wait_end_time > NOW)
   - Check if user is in a RUNNING virtual table (end_time > NOW or NULL)
   - If found → **RECONNECT** (return existing virtual_table_id)

2. **PRIORITY 2: Available WAITING Virtual Table**
   - Find WAITING virtual table (same table_id, not expired, not full)
   - If found → **JOIN** existing virtual table

3. **PRIORITY 3: Create New Virtual Table**
   - Only if no active virtual table exists

## Key Changes

### 1. `VirtualTableManager.findOrCreateVirtualTable()`
- ✅ Now checks both WAITING and RUNNING status
- ✅ Checks `wait_end_time` for WAITING games
- ✅ Checks `end_time` for RUNNING games
- ✅ Prioritizes user's existing virtual table first

### 2. `VirtualTableManager.addPlayerToVirtualTable()`
- ✅ Detects if player already exists (reconnection)
- ✅ Updates `is_connected = 1` and `last_action_at` on reconnect
- ✅ Logs reconnection vs new join

### 3. `VirtualTableManager.checkAndStartVirtualTables()`
- ✅ Auto-marks RUNNING games as ENDED if `end_time` passed
- ✅ Prevents reconnection to expired games

### 4. `api/tables/get-active-virtual-table.php`
- ✅ Updated to check both WAITING and RUNNING
- ✅ Uses proper time checks for each status

## Flow Example

### Scenario 1: User Refreshes During Wait
1. User joins → Virtual table created (WAITING, wait_end_time = +30s)
2. User refreshes page
3. `findOrCreateVirtualTable()` finds user's existing WAITING virtual table
4. Returns same `virtual_table_id` → **RECONNECT** ✅

### Scenario 2: User Refreshes During Game
1. Game started → Virtual table status = RUNNING, end_time = +3min
2. User refreshes page
3. `findOrCreateVirtualTable()` finds user's existing RUNNING virtual table
4. Returns same `virtual_table_id` → **RECONNECT** ✅
5. Game state restored from virtual table

### Scenario 3: Game Ended
1. Game ended → `end_time` passed
2. Cron job marks status = ENDED
3. User tries to refresh
4. No active virtual table found → Can't reconnect (game finished) ✅

## Database Checks

### For WAITING Status:
```sql
vt.status = 'WAITING' 
AND vt.wait_end_time > NOW()
```

### For RUNNING Status:
```sql
vt.status = 'RUNNING' 
AND (vt.end_time IS NULL OR vt.end_time > NOW())
```

## Testing

1. Join table → Note virtual_table_id
2. Refresh page → Should get same virtual_table_id
3. Check console logs → Should see "Reconnecting player..."
4. Check database → `is_connected = 1`, `last_action_at` updated

## Result

✅ **No more duplicate virtual tables on refresh**
✅ **Seamless reconnection to active games**
✅ **Proper time-based validation**

