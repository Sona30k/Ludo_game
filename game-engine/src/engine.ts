import {
  MatchState,
  PlayerState,
  TokenState,
  Phase,
  Position,
  PositionType,
  GameEvent,
  EventType,
  Move,
  Ranking,
  BoardConfig
} from './types';
import { DEFAULT_BOARD_CONFIG, getNextPosition, getPathIndices, canCapture } from './board';
import {
  calculateMovementScore,
  calculateCaptureScore,
  calculateHomeBonuses,
  checkCombo,
  addScore
} from './scoring';

export class LudoEngine {
  static createMatch(config: Partial<BoardConfig>, playerAId: string, playerBId: string, seed: string): MatchState {
    const boardConfig = { ...DEFAULT_BOARD_CONFIG, ...config };
    const players: PlayerState[] = [
      {
        playerId: playerAId,
        score: 0,
        captures: 0,
        tokensHome: 0,
        lastCaptureByOpponentMap: new Map(),
        comboState: { lastActionTime: 0, chain: [] },
        consecutiveSixCount: 0,
      },
      {
        playerId: playerBId,
        score: 0,
        captures: 0,
        tokensHome: 0,
        lastCaptureByOpponentMap: new Map(),
        comboState: { lastActionTime: 0, chain: [] },
        consecutiveSixCount: 0,
      },
    ];
    const tokens: TokenState[] = [];
    for (const player of players) {
      for (let i = 0; i < 4; i++) {
        tokens.push({
          tokenId: `${player.playerId}-token-${i}`,
          position: { type: PositionType.BASE },
          stackedWith: [],
        });
      }
    }
    return {
      matchId: `match-${Date.now()}`,
      phase: Phase.NORMAL_PLAY,
      startTime: 0,
      remainingMs: 150000, // 150 seconds
      currentPlayerId: playerAId,
      players,
      board: { config: boardConfig, tokens },
      logs: [],
      seed,
    };
  }

  static startMatch(state: MatchState, nowMs: number): { state: MatchState, events: GameEvent[] } {
    state.startTime = nowMs;
    const events: GameEvent[] = [{
      type: EventType.MATCH_STARTED,
      timestamp: nowMs,
      payload: { matchId: state.matchId }
    }];
    return { state, events };
  }

  static tick(state: MatchState, nowMs: number): { state: MatchState, events: GameEvent[] } {
    const elapsed = nowMs - state.startTime;
    state.remainingMs = Math.max(0, 150000 - elapsed);
    const events: GameEvent[] = [];
    if (state.remainingMs === 0 && state.phase === Phase.NORMAL_PLAY) {
      state.phase = Phase.FINAL_MOVES;
      events.push({
        type: EventType.FINAL_MOVES_STARTED,
        timestamp: nowMs,
        payload: {}
      });
    }
    return { state, events };
  }

  static rollDice(state: MatchState, playerId: string, nowMs: number, overrideValue?: number): { state: MatchState, diceValue: number, events: GameEvent[] } {
    if (state.currentPlayerId !== playerId) throw new Error('Not your turn');
    if (state.phase === Phase.MATCH_ENDED) throw new Error('Match ended');

    // Deterministic dice roll based on seed and turn, or use override
    let diceValue: number;
    if (overrideValue !== undefined && overrideValue >= 1 && overrideValue <= 6) {
      diceValue = overrideValue;
    } else {
      const turnNumber = state.logs.filter(e => e.type === EventType.DICE_ROLLED).length;
      diceValue = (parseInt(state.seed, 36) + turnNumber) % 6 + 1;
    }

    const player = state.players.find(p => p.playerId === playerId)!;
    let extraTurn = false;
    let bonus = 0;
    const events: GameEvent[] = [{
      type: EventType.DICE_ROLLED,
      timestamp: nowMs,
      playerId,
      payload: { diceValue }
    }];

    if (diceValue === 6) {
      player.consecutiveSixCount++;
      if (player.consecutiveSixCount === 2) {
        bonus += 15;
        events.push({
          type: EventType.DOUBLE_SIX_BONUS,
          timestamp: nowMs,
          playerId,
          payload: { bonus: 15 }
        });
      } else if (player.consecutiveSixCount === 3) {
        bonus -= 10;
        events.push({
          type: EventType.THIRD_SIX_PENALTY,
          timestamp: nowMs,
          playerId,
          payload: { bonus: -10 }
        });
        extraTurn = false; // no extra turn
      } else {
        extraTurn = state.phase === Phase.NORMAL_PLAY;
      }
    } else {
      player.consecutiveSixCount = 0;
    }

    if (bonus !== 0) {
      events.push(addScore(player, bonus, 'Dice bonus/penalty', nowMs));
    }

    if (!extraTurn || state.phase === Phase.FINAL_MOVES) {
      // Switch turn
      const otherPlayerId = state.players.find(p => p.playerId !== playerId)!.playerId;
      state.currentPlayerId = otherPlayerId;
      events.push({
        type: EventType.TURN_CHANGED,
        timestamp: nowMs,
        payload: { newPlayer: otherPlayerId }
      });
    }

    return { state, diceValue, events };
  }

  static getLegalMoves(state: MatchState, playerId: string, diceValue: number): Move[] {
    const tokens = state.board.tokens.filter(t => t.tokenId.startsWith(playerId));
    const moves: Move[] = [];
    for (const token of tokens) {
      if (token.position.type === PositionType.BASE && diceValue === 6) {
        const to = getNextPosition(token.position, diceValue, playerId, state.board.config);
        moves.push({
          tokenId: token.tokenId,
          from: token.position,
          to,
          pathIndicesTraversed: getPathIndices(token.position, to, state.board.config)
        });
      } else if (token.position.type !== PositionType.BASE && token.position.type !== PositionType.HOME) {
        const to = getNextPosition(token.position, diceValue, playerId, state.board.config);
        moves.push({
          tokenId: token.tokenId,
          from: token.position,
          to,
          pathIndicesTraversed: getPathIndices(token.position, to, state.board.config)
        });
      }
    }
    return moves;
  }

  static applyMove(state: MatchState, playerId: string, move: Move, nowMs: number): { state: MatchState, events: GameEvent[] } {
    const token = state.board.tokens.find(t => t.tokenId === move.tokenId)!;
    const player = state.players.find(p => p.playerId === playerId)!;
    const events: GameEvent[] = [];

    // Move token
    token.position = move.to;
    events.push({
      type: EventType.TOKEN_MOVED,
      timestamp: nowMs,
      playerId,
      payload: { tokenId: move.tokenId, from: move.from, to: move.to }
    });

    // Scoring
    const moveScore = calculateMovementScore(move, state.board.config);
    if (moveScore > 0) {
      events.push(addScore(player, moveScore, 'Movement', nowMs));
    }

    // Capture
    const captured = state.board.tokens.filter(t =>
      t.tokenId !== move.tokenId &&
      t.position.type === move.to.type &&
      t.position.index === move.to.index &&
      !t.tokenId.startsWith(playerId)
    );
    if (captured.length > 0) {
      for (const cap of captured) {
        cap.position = { type: PositionType.BASE };
        player.captures++;
        const isRevenge = player.lastCaptureByOpponentMap.get(cap.tokenId.split('-')[0]) || false;
        const capScore = calculateCaptureScore(captured.length, false, isRevenge); // assume not on star
        events.push(addScore(player, capScore, 'Capture', nowMs));
        events.push({
          type: EventType.TOKEN_CAPTURED,
          timestamp: nowMs,
          playerId,
          payload: { capturedTokenId: cap.tokenId, score: capScore }
        });
        // Mark revenge for opponent
        const opponentId = cap.tokenId.split('-')[0];
        const opponent = state.players.find(p => p.playerId === opponentId)!;
        opponent.lastCaptureByOpponentMap.set(playerId, true);
      }
    }

    // Home
    if (move.to.type === PositionType.HOME) {
      const tokensHomeBefore = player.tokensHome;
      player.tokensHome++;
      const { score, events: homeEvents } = calculateHomeBonuses(tokensHomeBefore, player.tokensHome, state.remainingMs);
      events.push(...homeEvents.map(e => ({ ...e, playerId })));
      if (score > 0) {
        events.push(addScore(player, score, 'Home bonus', nowMs));
      }
    }

    // Combo
    const comboResult = checkCombo(player, 'MOVE', nowMs);
    if (comboResult.bonus > 0) {
      events.push(addScore(player, comboResult.bonus, 'Combo', nowMs));
      events.push(comboResult.event!);
    }

    // Final moves
    if (state.phase === Phase.FINAL_MOVES) {
      player.finalMoveTimeMs = nowMs - (state.startTime + 150000);
      // After both have moved, end match
      const bothMoved = state.players.every(p => p.finalMoveTimeMs !== undefined);
      if (bothMoved) {
        state.phase = Phase.MATCH_ENDED;
        const { winnerId, ranking, events: endEvents } = LudoEngine.endMatch(state, nowMs);
        events.push(...endEvents);
      }
    }

    state.logs.push(...events);
    return { state, events };
  }

  static endMatch(state: MatchState, nowMs: number): { winnerId: string, ranking: Ranking[], events: GameEvent[] } {
    const ranking = LudoEngine.getRanking(state);
    const winnerId = ranking[0].playerId;
    const events: GameEvent[] = [{
      type: EventType.MATCH_ENDED,
      timestamp: nowMs,
      payload: { winnerId, ranking }
    }];
    return { winnerId, ranking, events };
  }

  static getRanking(state: MatchState): Ranking[] {
    const rankings: Ranking[] = state.players.map(p => ({
      playerId: p.playerId,
      score: p.score,
      captures: p.captures,
      tokensHome: p.tokensHome,
      finalMoveTimeMs: p.finalMoveTimeMs,
      highestComboBonus: 0, // TODO: track max combo
      rank: 0
    }));

    rankings.sort((a, b) => {
      if (a.score !== b.score) return b.score - a.score;
      if (a.captures !== b.captures) return b.captures - a.captures;
      if (a.tokensHome !== b.tokensHome) return b.tokensHome - a.tokensHome;
      if (a.finalMoveTimeMs && b.finalMoveTimeMs) return a.finalMoveTimeMs - b.finalMoveTimeMs;
      if (a.highestComboBonus !== b.highestComboBonus) return b.highestComboBonus - a.highestComboBonus;
      // Coin flip
      const seed = parseInt(state.seed, 36) + a.playerId.charCodeAt(0);
      return (seed % 2 === 0) ? -1 : 1;
    });

    rankings.forEach((r, i) => r.rank = i + 1);
    return rankings;
  }
}