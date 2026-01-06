const { 
    calculateMovePoints, 
    calculateKillPoints, 
    calculateKillPenalty,
    isSafeSpot 
} = require('../utils/helpers');

/**
 * Game Logic Class
 * Handles all game rules, pawn movements, and scoring
 */
class GameLogic {
    constructor() {
        // Starting positions for each player (4 pawns each)
        this.startPositions = {
            0: [0, 0, 0, 0],  // Red player
            1: [13, 13, 13, 13], // Blue player
            2: [26, 26, 26, 26], // Green player
            3: [39, 39, 39, 39]  // Yellow player
        };
        
        // Home positions (where pawns finish)
        this.homePositions = {
            0: 57, // Red
            1: 11, // Blue
            2: 24, // Green
            3: 37  // Yellow
        };
    }

    /**
     * Initialize game state for a new game
     */
    initializeGameState(players) {
        const gameState = {
            players: players.map((player, index) => ({
                userId: player.userId || player.id, // Support both userId and id from database
                username: player.username,
                isBot: false,
                color: player.color || this.getColorByIndex(index),
                pawns: [
                    { position: 0, isHome: false, isFinished: false },
                    { position: 0, isHome: false, isFinished: false },
                    { position: 0, isHome: false, isFinished: false },
                    { position: 0, isHome: false, isFinished: false }
                ],
                points: 0,
                isActive: true,
                turnOrder: index
            })),
            currentTurn: 0, // Index of current player
            diceValue: null,
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

        // If pawn is at home (position 0) and dice is not 6, can't move
        if (pawn.position === 0 && diceValue !== 6) {
            return false;
        }

        // If pawn is at home and dice is 6, can move
        if (pawn.position === 0 && diceValue === 6) {
            return true;
        }

        // Calculate new position
        const newPosition = this.calculateNewPosition(
            gameState, 
            playerIndex, 
            pawnIndex, 
            diceValue
        );

        // Check if new position is valid (not beyond finish)
        const maxPosition = 57; // Total blocks in Ludo
        if (newPosition > maxPosition) {
            return false;
        }

        return true;
    }

    /**
     * Calculate new position after move
     */
    calculateNewPosition(gameState, playerIndex, pawnIndex, diceValue) {
        const player = gameState.players[playerIndex];
        const pawn = player.pawns[pawnIndex];

        // If pawn is at home and dice is 6, move to start
        if (pawn.position === 0 && diceValue === 6) {
            return 1; // Start position
        }

        // Normal movement
        let newPosition = pawn.position + diceValue;

        // Handle wrap-around (if exceeds board length)
        const boardLength = 52; // Main board length
        if (newPosition > boardLength) {
            // Enter home stretch
            const homeStretch = newPosition - boardLength;
            const homePosition = this.homePositions[playerIndex];
            if (homeStretch <= 6) {
                newPosition = homePosition + homeStretch;
            } else {
                return pawn.position; // Can't move beyond finish
            }
        }

        return newPosition;
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

        // Check if position is a safe spot
        if (isSafeSpot(newPosition)) {
            return false; // Can't kill on safe spot
        }

        // Check all other players' pawns
        for (let i = 0; i < gameState.players.length; i++) {
            if (i === playerIndex) continue;

            const opponent = gameState.players[i];
            for (let j = 0; j < opponent.pawns.length; j++) {
                const opponentPawn = opponent.pawns[j];
                
                // If opponent pawn is at same position and not at home/finished
                if (opponentPawn.position === newPosition && 
                    opponentPawn.position !== 0 && 
                    !opponentPawn.isFinished) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Move a pawn
     */
    movePawn(gameState, playerIndex, pawnIndex, diceValue) {
        const player = gameState.players[playerIndex];
        const pawn = player.pawns[pawnIndex];
        const oldPosition = pawn.position;

        // Calculate new position
        const newPosition = this.calculateNewPosition(
            gameState, 
            playerIndex, 
            pawnIndex, 
            diceValue
        );

        // Update pawn position
        pawn.position = newPosition;
        pawn.isHome = newPosition === 0;

        // Check if pawn finished
        const maxPosition = 57;
        if (newPosition >= maxPosition) {
            pawn.isFinished = true;
        }

        // Calculate points for movement
        const blocksMoved = newPosition - oldPosition;
        if (blocksMoved > 0) {
            player.points += calculateMovePoints(blocksMoved);
        }

        // Check for kills
        const killedPawns = this.checkAndKillOpponents(
            gameState, 
            playerIndex, 
            newPosition
        );

        // Add kill points
        if (killedPawns.length > 0) {
            player.points += calculateKillPoints() * killedPawns.length;
            
            // Deduct points from killed players
            killedPawns.forEach(killed => {
                const killedPlayer = gameState.players[killed.playerIndex];
                killedPlayer.points = Math.max(0, killedPlayer.points - calculateKillPenalty());
            });
        }

        return {
            success: true,
            newPosition,
            blocksMoved,
            killedPawns,
            pointsEarned: calculateMovePoints(blocksMoved) + (killedPawns.length * calculateKillPoints())
        };
    }

    /**
     * Check and kill opponent pawns at a position
     */
    checkAndKillOpponents(gameState, playerIndex, position) {
        // Can't kill on safe spots
        if (isSafeSpot(position)) {
            return [];
        }

        const killedPawns = [];

        // Check all other players
        for (let i = 0; i < gameState.players.length; i++) {
            if (i === playerIndex) continue;

            const opponent = gameState.players[i];
            for (let j = 0; j < opponent.pawns.length; j++) {
                const pawn = opponent.pawns[j];
                
                // If opponent pawn is at this position and not at home/finished
                if (pawn.position === position && 
                    pawn.position !== 0 && 
                    !pawn.isFinished) {
                    
                    // Kill the pawn (send back to home)
                    pawn.position = 0;
                    pawn.isHome = true;
                    
                    killedPawns.push({
                        playerIndex: i,
                        pawnIndex: j,
                        userId: opponent.userId
                    });
                }
            }
        }

        return killedPawns;
    }

    /**
     * Check if game is finished
     */
    checkGameFinished(gameState) {
        // Check if any player has all pawns finished
        for (let i = 0; i < gameState.players.length; i++) {
            const player = gameState.players[i];
            const allFinished = player.pawns.every(pawn => pawn.isFinished);
            
            if (allFinished) {
                return {
                    finished: true,
                    winnerIndex: i,
                    winner: player
                };
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
            finishedPawns: player.pawns.filter(p => p.isFinished).length,
            index
        }));

        // Sort by: finished pawns (desc), then points (desc)
        ranking.sort((a, b) => {
            if (b.finishedPawns !== a.finishedPawns) {
                return b.finishedPawns - a.finishedPawns;
            }
            return b.points - a.points;
        });

        return ranking;
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

