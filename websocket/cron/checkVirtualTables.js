/**
 * Cron Job: Check and Start Virtual Tables
 * Run this every 1-5 seconds to check for virtual tables that need to start
 * 
 * Usage:
 * - Add to cron: node websocket/cron/checkVirtualTables.js
 * - Or use setInterval in server.js
 */

const VirtualTableManager = require('../game/VirtualTableManager');

const virtualTableManager = new VirtualTableManager();

async function checkVirtualTables() {
    try {
        await virtualTableManager.checkAndStartVirtualTables();
    } catch (error) {
        console.error('Cron error:', error);
    }
}

// Run immediately
checkVirtualTables();

// Run every 2 seconds
setInterval(checkVirtualTables, 2000);

console.log('✅ Virtual table checker started (runs every 2 seconds)');

