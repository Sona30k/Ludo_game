import { Position, PositionType, BoardConfig, TokenState } from './types';

// Assumptions:
// - Shared circular track of 52 cells.
// - Players have separate home stretches.
// - Stars and safe cells as specified.
// - Captures occur on shared track when landing on opponent's token, unless safe.
// - Home stretch is separate per player, no captures there.

export const DEFAULT_BOARD_CONFIG: BoardConfig = {
  trackLength: 52,
  starPositions: [5, 12, 17, 22, 29, 34, 39, 46], // example positions
  homeStretchLength: 6,
  safePositions: [0, 13, 26, 39], // example safe cells
};

export function isStar(position: Position, config: BoardConfig): boolean {
  return position.type === PositionType.TRACK && config.starPositions.includes(position.index!);
}

export function isSafe(position: Position, config: BoardConfig): boolean {
  return position.type === PositionType.TRACK && config.safePositions.includes(position.index!);
}

export function getNextPosition(current: Position, steps: number, playerId: string, config: BoardConfig): Position {
  if (current.type === PositionType.BASE) {
    // Moving from base to track start
    return { type: PositionType.TRACK, index: 0 };
  } else if (current.type === PositionType.TRACK) {
    const newIndex = (current.index! + steps) % config.trackLength;
    if (newIndex < current.index!) {
      // Wrapped around, enter home stretch
      return { type: PositionType.HOME_STRETCH, index: 0 };
    }
    return { type: PositionType.TRACK, index: newIndex };
  } else if (current.type === PositionType.HOME_STRETCH) {
    const newIndex = current.index! + steps;
    if (newIndex >= config.homeStretchLength) {
      return { type: PositionType.HOME };
    }
    return { type: PositionType.HOME_STRETCH, index: newIndex };
  }
  return current; // HOME or invalid
}

export function getPathIndices(from: Position, to: Position, config: BoardConfig): number[] {
  // Simplified: only track indices
  if (from.type === PositionType.TRACK && to.type === PositionType.TRACK) {
    const indices = [];
    let current = from.index!;
    const end = to.index!;
    while (current !== end) {
      indices.push(current);
      current = (current + 1) % config.trackLength;
    }
    return indices;
  }
  return [];
}

export function canCapture(attacker: TokenState, defender: TokenState, config: BoardConfig): boolean {
  // Cannot capture on safe cells or if stacked
  if (defender.stackedWith.length > 0 || isSafe(defender.position, config)) {
    return false;
  }
  // Assume different players
  return true;
}