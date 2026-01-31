export enum Phase {
  NORMAL_PLAY = 'NORMAL_PLAY',
  FINAL_MOVES = 'FINAL_MOVES',
  MATCH_ENDED = 'MATCH_ENDED',
}

export enum PositionType {
  BASE = 'BASE',
  TRACK = 'TRACK',
  HOME_STRETCH = 'HOME_STRETCH',
  HOME = 'HOME',
}

export interface Position {
  type: PositionType;
  index?: number; // for TRACK and HOME_STRETCH
}

export interface TokenState {
  tokenId: string;
  position: Position;
  stackedWith: string[]; // other tokenIds on same cell
}

export interface PlayerState {
  playerId: string;
  score: number;
  captures: number;
  tokensHome: number;
  lastCaptureByOpponentMap: Map<string, boolean>; // opponentId -> hasCapturedMe
  comboState: {
    lastActionTime: number;
    chain: string[]; // action types in combo
  };
  consecutiveSixCount: number;
  finalMoveTimeMs?: number;
}

export interface BoardConfig {
  trackLength: number; // e.g., 52
  starPositions: number[]; // indices on track that are stars
  homeStretchLength: number; // e.g., 6
  safePositions: number[]; // indices on track that are safe
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
    tokens: TokenState[]; // all tokens
  };
  logs: GameEvent[];
  seed: string; // for deterministic RNG
}

export enum EventType {
  MATCH_STARTED = 'MATCH_STARTED',
  TURN_CHANGED = 'TURN_CHANGED',
  DICE_ROLLED = 'DICE_ROLLED',
  TOKEN_MOVED = 'TOKEN_MOVED',
  SCORE_ADDED = 'SCORE_ADDED',
  TOKEN_CAPTURED = 'TOKEN_CAPTURED',
  TOKEN_HOME = 'TOKEN_HOME',
  COMBO_BONUS_AWARDED = 'COMBO_BONUS_AWARDED',
  DOUBLE_SIX_BONUS = 'DOUBLE_SIX_BONUS',
  THIRD_SIX_PENALTY = 'THIRD_SIX_PENALTY',
  FINAL_MOVES_STARTED = 'FINAL_MOVES_STARTED',
  MATCH_ENDED = 'MATCH_ENDED',
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
  pathIndicesTraversed: number[]; // track indices passed
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