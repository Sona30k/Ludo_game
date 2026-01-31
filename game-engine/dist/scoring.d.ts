import { PlayerState, GameEvent, Move, BoardConfig } from './types';
export declare function calculateMovementScore(move: Move, config: BoardConfig): number;
export declare function calculateCaptureScore(capturedTokens: number, isOnStar: boolean, isRevenge: boolean): number;
export declare function calculateHomeBonuses(tokensHomeBefore: number, tokensHomeAfter: number, timerRemaining: number): {
    score: number;
    events: GameEvent[];
};
export declare function checkCombo(player: PlayerState, action: string, nowMs: number): {
    bonus: number;
    event?: GameEvent;
};
export declare function addScore(player: PlayerState, delta: number, reason: string, nowMs: number): GameEvent;
//# sourceMappingURL=scoring.d.ts.map