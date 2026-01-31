import { MatchState, GameEvent, Move, Ranking, BoardConfig } from './types';
export declare class LudoEngine {
    static createMatch(config: Partial<BoardConfig>, playerAId: string, playerBId: string, seed: string): MatchState;
    static startMatch(state: MatchState, nowMs: number): {
        state: MatchState;
        events: GameEvent[];
    };
    static tick(state: MatchState, nowMs: number): {
        state: MatchState;
        events: GameEvent[];
    };
    static rollDice(state: MatchState, playerId: string, nowMs: number, overrideValue?: number): {
        state: MatchState;
        diceValue: number;
        events: GameEvent[];
    };
    static getLegalMoves(state: MatchState, playerId: string, diceValue: number): Move[];
    static applyMove(state: MatchState, playerId: string, move: Move, nowMs: number): {
        state: MatchState;
        events: GameEvent[];
    };
    static endMatch(state: MatchState, nowMs: number): {
        winnerId: string;
        ranking: Ranking[];
        events: GameEvent[];
    };
    static getRanking(state: MatchState): Ranking[];
}
//# sourceMappingURL=engine.d.ts.map