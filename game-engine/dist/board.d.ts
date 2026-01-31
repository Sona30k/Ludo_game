import { Position, BoardConfig, TokenState } from './types';
export declare const DEFAULT_BOARD_CONFIG: BoardConfig;
export declare function isStar(position: Position, config: BoardConfig): boolean;
export declare function isSafe(position: Position, config: BoardConfig): boolean;
export declare function getNextPosition(current: Position, steps: number, playerId: string, config: BoardConfig): Position;
export declare function getPathIndices(from: Position, to: Position, config: BoardConfig): number[];
export declare function canCapture(attacker: TokenState, defender: TokenState, config: BoardConfig): boolean;
//# sourceMappingURL=board.d.ts.map