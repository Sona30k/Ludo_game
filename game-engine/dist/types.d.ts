export declare enum Phase {
    NORMAL_PLAY = "NORMAL_PLAY",
    FINAL_MOVES = "FINAL_MOVES",
    MATCH_ENDED = "MATCH_ENDED"
}
export declare enum PositionType {
    BASE = "BASE",
    TRACK = "TRACK",
    HOME_STRETCH = "HOME_STRETCH",
    HOME = "HOME"
}
export interface Position {
    type: PositionType;
    index?: number;
}
export interface TokenState {
    tokenId: string;
    position: Position;
    stackedWith: string[];
}
export interface PlayerState {
    playerId: string;
    score: number;
    captures: number;
    tokensHome: number;
    lastCaptureByOpponentMap: Map<string, boolean>;
    comboState: {
        lastActionTime: number;
        chain: string[];
    };
    consecutiveSixCount: number;
    finalMoveTimeMs?: number;
}
export interface BoardConfig {
    trackLength: number;
    starPositions: number[];
    homeStretchLength: number;
    safePositions: number[];
}
export interface MatchState {
    matchId: string;
    phase: Phase;
    startTime: number;
    remainingMs: number;
    currentPlayerId: string;
    players: PlayerState[];
    board: {
        config: BoardConfig;
        tokens: TokenState[];
    };
    logs: GameEvent[];
    seed: string;
}
export declare enum EventType {
    MATCH_STARTED = "MATCH_STARTED",
    TURN_CHANGED = "TURN_CHANGED",
    DICE_ROLLED = "DICE_ROLLED",
    TOKEN_MOVED = "TOKEN_MOVED",
    SCORE_ADDED = "SCORE_ADDED",
    TOKEN_CAPTURED = "TOKEN_CAPTURED",
    TOKEN_HOME = "TOKEN_HOME",
    COMBO_BONUS_AWARDED = "COMBO_BONUS_AWARDED",
    DOUBLE_SIX_BONUS = "DOUBLE_SIX_BONUS",
    THIRD_SIX_PENALTY = "THIRD_SIX_PENALTY",
    FINAL_MOVES_STARTED = "FINAL_MOVES_STARTED",
    MATCH_ENDED = "MATCH_ENDED"
}
export interface GameEvent {
    type: EventType;
    timestamp: number;
    playerId?: string;
    payload: any;
}
export interface Move {
    tokenId: string;
    from: Position;
    to: Position;
    pathIndicesTraversed: number[];
}
export interface Ranking {
    playerId: string;
    score: number;
    captures: number;
    tokensHome: number;
    finalMoveTimeMs?: number;
    highestComboBonus: number;
    rank: number;
}
//# sourceMappingURL=types.d.ts.map