const { 
    calculateMovePoints, 
    isSafeSpot,
    getStarPositions
} = require('../utils/helpers');

/**
 * LUDO SCORING SYSTEM (Standard Rules)
 * =====================================
 * 1. Movement Points: 1 point per square moved (only on main track)
 * 2. Pawn Out Points: 5 points for bringing a pawn out of home (rolling a 6)
 * 3. Pawn Home Points: 10 points when a pawn reaches home
 * 4. Final Home Points: 50 points for the last pawn reaching home
 * 5. Capture Points: 25 points for capturing an opponent's pawn
 * 6. Completion Bonus (awarded when all 4 pawns are home):
 *    - 1st to finish: +60 points
 *    - 2nd to finish: +40 points
 *    - 3rd to finish: +20 points
 *    - 4th to finish: +10 points
 */


class GameLogic {
    /**
     * Game Logic Class
     * Handles all game rules, pawn movements, and scoring
     */
    constructor() {
        // Board constants
        this.mainTrackLength = 52;  // Main circular track (positions 1-52)
        this.maxPosition = 57;      // Final position in home stretch (0-indexed, so position 57)
        
        this.startOffsets = {
            red: 0,       // Red enters at position 1
            green: 13,    // Green enters at position 14
            yellow: 26,   // Yellow enters at position 27
            blue: 39      // Blue enters at position 40
        };
    }
    
    /**
     * Initialize game state for a new game
     */
    initializeGameState(players) {
        const gameState = {
            players: players.map((player, index) => ({
                userId: player.userId || player.id || player.botId, // Support userId, id, or botId
                username: player.username,
                isBot: player.isBot || false, // Preserve isBot flag from player object
                color: player.color || this.getColorByIndex(index),
                pawns: [
                    { position: 0, isHome: true, isFinished: false },
                    { position: 0, isHome: true, isFinished: false },
                    { position: 0, isHome: true, isFinished: false },
                    { position: 0, isHome: true, isFinished: false }
                ],
                points: player.points || 0, // Preserve existing points if any
                captures: 0,
                isActive: true,
                turnOrder: index
            })),
            currentTurn: 0, // Index of current player
            diceValue: null,
            lastEvents: [],
            gameStatus: 'waiting', // waiting, playing, finished
            startedAt: null,
            lastAction: null
        };

        return gameState;
    }

    /**
     * Get color by player index
     */
    getColorByIndex(index) {
        const colors = ['red', 'blue', 'green', 'yellow'];
        return colors[index] || 'red';
    }

    /**
     * Get starting path position for a player leaving home
     */
    getStartPosition(player) {
        const color = player?.color || 'red';
        const offset = this.startOffsets[color] ?? 0;
        return offset + 1;
    }

    /**
     * Roll dice (or use admin override)
     */
    rollDice(overrideValue = null) {
        if (overrideValue !== null && overrideValue >= 1 && overrideValue <= 6) {
            return overrideValue;
        }
        return Math.floor(Math.random() * 6) + 1;
    }

    /**
     * Get valid moves for a player
     */
    getValidMoves(gameState, playerIndex, diceValue) {
        const player = gameState.players[playerIndex];
        const validMoves = [];

        // Check each pawn
        player.pawns.forEach((pawn, pawnIndex) => {
            if (this.canMovePawn(gameState, playerIndex, pawnIndex, diceValue)) {
                validMoves.push({
                    pawnIndex,
                    newPosition: this.calculateNewPosition(
                        gameState, 
                        playerIndex, 
                        pawnIndex, 
                        diceValue
                    ),
                    willKill: this.willKillOpponent(
                        gameState, 
                        playerIndex, 
                        pawnIndex, 
                        diceValue
                    )
                });
            }
        });

        return validMoves;
    }

    /**
     * Check if a pawn can be moved
     */
    canMovePawn(gameState, playerIndex, pawnIndex, diceValue) {
        const player = gameState.players[playerIndex];
        const pawn = player.pawns[pawnIndex];

        // If pawn is finished, can't move
        if (pawn.isFinished) {
            return false;
        }

        // If pawn is at home, can only move on a 6
        if (pawn.position === 0) {
            return diceValue === 6;
        }

        return this.getMoveTarget(gameState, playerIndex, pawnIndex, diceValue).isValid;
    }

    /**
     * Calculate new position after move
     */
    calculateNewPosition(gameState, playerIndex, pawnIndex, diceValue) {
        const result = this.getMoveTarget(gameState, playerIndex, pawnIndex, diceValue);
        return result.newPosition;
    }

    /**
     * Check if move will kill an opponent pawn
     */
    willKillOpponent(gameState, playerIndex, pawnIndex, diceValue) {
        const newPosition = this.calculateNewPosition(
            gameState, 
            playerIndex, 
            pawnIndex, 
            diceValue
        );

        if (isSafeSpot(newPosition)) {
            return false;
        }

        return this.getOpponentPawnCountAt(gameState, playerIndex, newPosition) >= 1;
    }

    /**
     * Move a pawn
     */
    movePawn(gameState, playerIndex, pawnIndex, diceValue) {
        const player = gameState.players[playerIndex];
        const pawn = player.pawns[pawnIndex];
        const oldPosition = pawn.position;
        const events = [];
        const isFromHome = oldPosition === 0;

        console.log(`🎯 movePawn called: player=${player.username}, pawn=${pawnIndex}, oldPos=${oldPosition}, diceValue=${diceValue}`);

        const moveTarget = this.getMoveTarget(gameState, playerIndex, pawnIndex, diceValue);
        if (!moveTarget.isValid) {
            console.log(`❌ Move invalid for pawn ${pawnIndex}`);
            return { success: false };
        }
        const newPosition = moveTarget.newPosition;
        
        console.log(`✅ Move valid: ${oldPosition} → ${newPosition} (distance=${newPosition - oldPosition})`);
        
        // Calculate blocks moved (not including bringing pawn out from home)
        // When pawn is brought out, it moves to start position, but we only count the dice value
        const blocksMoved = isFromHome ? diceValue : (newPosition - oldPosition);

        // Update pawn position
        pawn.position = newPosition;
        pawn.isHome = newPosition === 0;

        // Note: Capture logic is handled in checkAndKillOpponents() below
        // This ensures proper blockade checking (2+ pawns = blockade, can't capture)

        // Award points for moving blocks (1 point per square moved on the track)
        // Only count actual movement on the board, not bringing out from home
        if (blocksMoved > 0 && !isFromHome) {
            player.points += blocksMoved;
            events.push({ type: 'MOVE', points: blocksMoved });
        }

        // Award points for bringing pawn out of home (only when rolling a 6)
        if (isFromHome && diceValue === 6) {
            player.points += 5;
            events.push({ type: 'PAWN_OUT', points: 5 });
        }

        // Check if pawn finished
        if (newPosition >= this.maxPosition) {
            pawn.isFinished = true;
            // Award points for bringing home a pawn (10 points)
            player.points += 10;
            events.push({ type: 'PAWN_HOME', points: 10 });
        }

        // Only award points for reaching final home cell
        if (newPosition === this.maxPosition) {
            player.points += 50;
            events.push({ type: 'FINAL_HOME', points: 50 });
        }

        // ✅ CRITICAL: Check for kills (capture) - ALWAYS happens
        const killedPawns = this.checkAndKillOpponents(
            gameState,
            playerIndex,
            newPosition
        );
        if (killedPawns.length > 0) {
            console.log(`🎉 ${player.username} captured ${killedPawns.length} pawn(s)! +${25 * killedPawns.length} points`);
            
            // ✅ Validate all captured pawns are at home
            this.validateCapturedPawns(killedPawns, gameState);
            
            player.captures += killedPawns.length;
            player.points += 25 * killedPawns.length;
            events.push({ type: 'CAPTURE', points: 25 * killedPawns.length, killedCount: killedPawns.length });
            // Note: No penalty for killed players in standard Ludo rules
        }

        // No home bonuses in simple Ludo

        // No combo or event bonuses in simple Ludo

        return {
            success: true,
            oldPosition,
            newPosition,
            blocksMoved,
            killedPawns,
            pointsEarned: events.reduce((sum, evt) => sum + (evt.points || 0), 0),
            events
        };
    }

    /**
     * Check and kill opponent pawns at a position
     * LUDO RULE: Can only capture if:
     * 1. Position is NOT a safe spot
     * 2. There is NO blockade (2+ pawns of same player on that spot)
     * 3. Only single pawns can be captured
     * GUARANTEE: Captured pawns ALWAYS go to home (position 0)
     */
    checkAndKillOpponents(gameState, playerIndex, position) {
        // ✅ CRITICAL: Check safe spots FIRST
        if (isSafeSpot(position)) {
            console.log(`🛡️ Position ${position} is SAFE SPOT - no capture allowed`);
            return [];
        }

        // Can't kill if position is in home stretch (position > mainTrackLength)
        // Home stretch is safe - no captures allowed there
        if (position > this.mainTrackLength) {
            console.log(`🏠 Position ${position} is in HOME STRETCH - no capture allowed`);
            return [];
        }

        const killedPawns = [];
        console.log(`🎯 [CAPTURE CHECK] Position ${position}, Player ${playerIndex}`);

        // Check all other players
        for (let i = 0; i < gameState.players.length; i++) {
            if (i === playerIndex) continue;

            const opponent = gameState.players[i];
            
            // Count how many opponent pawns are at this position
            const opponentPawnsAtPosition = opponent.pawns.filter(p => 
                p.position === position && 
                p.position !== 0 && 
                !p.isFinished
            );
            
            // LUDO RULE: If 2+ pawns of same player on same spot = BLOCKADE
            // Blockades cannot be captured or passed
            if (opponentPawnsAtPosition.length >= 2) {
                // This is a blockade - cannot capture
                continue;
            }
            
            // Only single pawns can be captured
            if (opponentPawnsAtPosition.length === 1) {
                const pawnToKill = opponentPawnsAtPosition[0];
                const pawnIndex = opponent.pawns.indexOf(pawnToKill);
                
                // ✅ GUARANTEED: Pawn ALWAYS goes to home
                console.log(`💥 CAPTURE HIT: ${opponent.username} pawn #${pawnIndex} at pos ${position}`);
                console.log(`   BEFORE: pos=${pawnToKill.position}, isHome=${pawnToKill.isHome}, isFinished=${pawnToKill.isFinished}`);
                
                pawnToKill.position = 0;
                pawnToKill.isHome = true;
                pawnToKill.isFinished = false;
                
                console.log(`   AFTER: pos=${pawnToKill.position}, isHome=${pawnToKill.isHome}, isFinished=${pawnToKill.isFinished}`);
                
                killedPawns.push({
                    playerIndex: i,
                    pawnIndex,
                    userId: opponent.userId
                });
            }
        }

        if (killedPawns.length > 0) {
            console.log(`✅ [CAPTURE COMPLETE] Total: ${killedPawns.length} pawns sent to HOME(0)`);
        }
        return killedPawns;
    }

    getMoveTarget(gameState, playerIndex, pawnIndex, diceValue) {
        const player = gameState.players[playerIndex];
        const pawn = player.pawns[pawnIndex];

        if (pawn.isFinished) {
            return { isValid: false, newPosition: pawn.position };
        }

        if (pawn.position === 0) {
            if (diceValue !== 6) {
                console.log(`❌ Pawn at home but dice != 6 (dice=${diceValue})`);
                return { isValid: false, newPosition: pawn.position };
            }
            const startPos = this.getStartPosition(player);
            console.log(`📍 Pawn leaving home: position 0 → ${startPos} (dice=${diceValue})`);
            if (this.isBlockedDestination(gameState, playerIndex, startPos, {
                currentPosition: pawn.position,
                diceValue,
                allowBlockadeBreak: true
            })) {
                return { isValid: false, newPosition: pawn.position };
            }
            return { isValid: true, newPosition: startPos };
        }

        const tentative = pawn.position + diceValue;
        console.log(`🔢 Calculating move: ${pawn.position} + ${diceValue} = ${tentative} (maxPosition=${this.maxPosition})`);
        
        if (tentative > this.maxPosition) {
            console.log(`❌ Move exceeds maxPosition: ${tentative} > ${this.maxPosition}`);
            return { isValid: false, newPosition: pawn.position };
        }

        // Check blockades ONLY on main track (not in home stretch)
        // Home stretch is color-specific, opponent blockades don't block entry
        if (pawn.position <= this.mainTrackLength && tentative <= this.mainTrackLength) {
            const pathEnd = Math.min(tentative, this.mainTrackLength);
            if (this.hasBlockadeOnPath(gameState, pawn.position + 1, pathEnd, playerIndex)) {
                console.log(`❌ Blockade on path from ${pawn.position + 1} to ${pathEnd}`);
                return { isValid: false, newPosition: pawn.position };
            }
        }

        let newPosition = tentative;

        if (this.isBlockedDestination(gameState, playerIndex, newPosition, {
            currentPosition: pawn.position,
            diceValue,
            allowBlockadeBreak: true
        })) {
            console.log(`❌ Destination blocked: ${newPosition}`);
            return { isValid: false, newPosition: pawn.position };
        }

        console.log(`✅ getMoveTarget result: ${pawn.position} → ${newPosition}`);
        return { isValid: true, newPosition };
    }

    hasBlockadeOnPath(gameState, start, end, playerIndex) {
        if (start > end) return false;
        for (let pos = start; pos <= end; pos++) {
            if (pos === end) {
                continue;
            }
            const blockade = this.getBlockadeInfo(gameState, pos);
            if (blockade && blockade.ownerIndex !== playerIndex) {
                return true;
            }
        }
        return false;
    }

    getBlockadeInfo(gameState, position) {
        if (position <= 0 || position > this.mainTrackLength) return null;
        if (isSafeSpot(position)) return null;

        for (let i = 0; i < gameState.players.length; i++) {
            const player = gameState.players[i];
            const pawnsAtPosition = player.pawns.filter(p =>
                p.position === position && !p.isFinished && p.position !== 0
            );
            if (pawnsAtPosition.length >= 2) {
                return {
                    ownerIndex: i,
                    count: pawnsAtPosition.length
                };
            }
        }
        return null;
    }

    getBlockadeOwner(gameState, position) {
        const info = this.getBlockadeInfo(gameState, position);
        return info ? info.ownerIndex : null;
    }

    canBreakBlockade(blockadeInfo, currentPosition, targetPosition, diceValue) {
        if (!blockadeInfo) return false;
        const distance = targetPosition - currentPosition;
        if (distance !== diceValue) return false;
        return true;
    }

    isBlockedDestination(gameState, playerIndex, position, options = {}) {
        const { currentPosition = 0, diceValue = 0, allowBlockadeBreak = false } = options;
        
        // Check home position (0) - home is shared space, no blockades
        if (position === 0) {
            return false; // Home is always accessible
        }
        
        if (position < 0 || position > this.mainTrackLength) {
            return false;
        }

        // Check if position is a safe spot (safe spots can't be blocked)
        if (isSafeSpot(position)) {
            return false;
        }

        const blockade = this.getBlockadeInfo(gameState, position);
        if (blockade && blockade.ownerIndex !== playerIndex) {
            // LUDO RULE: Blockades (2+ pawns) cannot be broken or passed
            // Even if allowBlockadeBreak is true, blockades are permanent
            return true; // Position is blocked
        }

        return false;
    }

    getOpponentPawnCountAt(gameState, playerIndex, position) {
        let count = 0;
        for (let i = 0; i < gameState.players.length; i++) {
            if (i === playerIndex) continue;
            const opponent = gameState.players[i];
            count += opponent.pawns.filter(p => p.position === position && p.position !== 0 && !p.isFinished).length;
        }
        return count;
    }

    getPrimaryEventType(flags) {
        if (flags.home) return 'home';
        if (flags.capture) return 'capture';
        if (flags.star) return 'safe';
        return 'move';
    }

    /**
     * Check if game is finished
     * Awards completion bonuses based on finishing order
     */
    checkGameFinished(gameState) {
        const completionBonuses = [60, 40, 20, 10]; // Bonus for 1st, 2nd, 3rd, 4th place
        let finishingCount = 0;

        // Check if any player has all pawns finished
        for (let i = 0; i < gameState.players.length; i++) {
            const player = gameState.players[i];
            
            // Skip if already awarded completion bonus
            if (player.completionBonus !== undefined) {
                continue;
            }

            const allFinished = player.pawns.every(pawn => pawn.isFinished);
            
            if (allFinished) {
                // Award completion bonus
                const bonus = completionBonuses[finishingCount] || 10;
                player.points += bonus;
                player.completionBonus = bonus;
                player.finishingPosition = finishingCount + 1;
                finishingCount++;

                // If this is the first to finish, game is complete
                if (finishingCount === 1) {
                    return {
                        finished: true,
                        winnerIndex: i,
                        winner: player,
                        completionBonus: bonus
                    };
                }
            }
        }

        return { finished: false };
    }

    /**
     * Get game ranking (by points, then by finished pawns)
     */
    getGameRanking(gameState) {
        const ranking = gameState.players.map((player, index) => ({
            userId: player.userId,
            username: player.username,
            points: player.points,
            captures: player.captures || 0,
            tokensHome: player.pawns.filter(p => p.isFinished).length,
            index
        }));

        // Sort by: points, captures, tokens home, seeded coin flip
        ranking.sort((a, b) => {
            if (b.points !== a.points) {
                return b.points - a.points;
            }
            if (b.captures !== a.captures) {
                return b.captures - a.captures;
            }
            if (b.tokensHome !== a.tokensHome) {
                return b.tokensHome - a.tokensHome;
            }
            return this.getSeededTieValue(a.userId) - this.getSeededTieValue(b.userId);
        });

        return ranking;
    }

    getSeededTieValue(userId) {
        const str = String(userId ?? '');
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = (hash * 31 + str.charCodeAt(i)) % 1000003;
        }
        return hash;
    }

    /**
     * Validate that all killed pawns are actually at home (position 0)
     * This is a safety check to ensure captures are consistent
     */
    validateCapturedPawns(killedPawns, gameState) {
        for (const kill of killedPawns) {
            const opponent = gameState.players[kill.playerIndex];
            const pawn = opponent.pawns[kill.pawnIndex];
            
            if (pawn.position !== 0) {
                console.error(`❌ VALIDATION FAILED: Killed pawn NOT at home!`);
                console.error(`   Player: ${opponent.username}, Pawn #${kill.pawnIndex}`);
                console.error(`   Expected position: 0, Got: ${pawn.position}`);
                // Force correct it
                pawn.position = 0;
                pawn.isHome = true;
                pawn.isFinished = false;
                console.log(`   ✅ FORCED CORRECTION: Pawn moved to home`);
            }
        }
    }

    /**
     * Move to next turn
     */
    nextTurn(gameState) {
        gameState.currentTurn = (gameState.currentTurn + 1) % gameState.players.length;
        gameState.diceValue = null;
        return gameState.currentTurn;
    }

}
module.exports = GameLogic;

