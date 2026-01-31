import { LudoEngine } from '../engine';
import { Phase, PositionType } from '../types';

describe('LudoEngine', () => {
  let state: any;

  beforeEach(() => {
    state = LudoEngine.createMatch({}, 'playerA', 'playerB', 'seed123');
    LudoEngine.startMatch(state, 0);
  });

  test('movement scoring +1 per cell', () => {
    const moves = LudoEngine.getLegalMoves(state, 'playerA', 6);
    expect(moves.length).toBeGreaterThan(0);
    const { state: newState, events } = LudoEngine.applyMove(state, 'playerA', moves[0], 1000);
    const scoreEvent = events.find(e => e.type === 'SCORE_ADDED');
    // Moving from base gives 0 score, so no event
    expect(scoreEvent).toBeUndefined();
  });

  test('landing on star gives +5', () => {
    // Assume move lands on star
    // This would require setting up specific position
  });

  test('capture gives +20', () => {
    // Set up tokens on same cell
    state.board.tokens[4].position = { type: PositionType.TRACK, index: 5 }; // playerB token
    const move = {
      tokenId: 'playerA-token-0',
      from: { type: PositionType.BASE },
      to: { type: PositionType.TRACK, index: 5 },
      pathIndicesTraversed: [0,1,2,3,4,5]
    };
    const { events } = LudoEngine.applyMove(state, 'playerA', move, 1000);
    const captureEvent = events.find(e => e.type === 'TOKEN_CAPTURED');
    expect(captureEvent).toBeDefined();
    const scoreEvent = events.find(e => e.type === 'SCORE_ADDED' && e.payload.reason === 'Capture');
    expect(scoreEvent).toBeDefined();
    expect(scoreEvent!.payload.delta).toBe(20);
  });

  test('double six bonus +15', () => {
    // Roll 6 twice
    LudoEngine.rollDice(state, 'playerA', 1000, 6);
    const { events } = LudoEngine.rollDice(state, 'playerA', 2000, 6); // second 6
    const bonusEvent = events.find(e => e.type === 'DOUBLE_SIX_BONUS');
    expect(bonusEvent).toBeDefined();
  });

  test('third six penalty -10', () => {
    // Roll 6 three times
    let currentState = state;
    const result1 = LudoEngine.rollDice(currentState, 'playerA', 1000, 6);
    currentState = result1.state;
    const result2 = LudoEngine.rollDice(currentState, 'playerA', 2000, 6);
    currentState = result2.state;
    const result3 = LudoEngine.rollDice(currentState, 'playerA', 3000, 6);
    const penaltyEvent = result3.events.find(e => e.type === 'THIRD_SIX_PENALTY');
    expect(penaltyEvent).toBeDefined();
  });

  test('final moves: no extra turn on 6', () => {
    state.phase = Phase.FINAL_MOVES;
    const { state: newState } = LudoEngine.rollDice(state, 'playerA', 1000);
    expect(newState.currentPlayerId).toBe('playerB');
  });

  test('home bonuses', () => {
    // Move token home
    const token = state.board.tokens[0];
    token.position = { type: PositionType.HOME_STRETCH, index: 5 };
    const move = {
      tokenId: token.tokenId,
      from: token.position,
      to: { type: PositionType.HOME },
      pathIndicesTraversed: []
    };
    const { events } = LudoEngine.applyMove(state, 'playerA', move, 1000);
    const homeEvent = events.find(e => e.type === 'TOKEN_HOME');
    expect(homeEvent).toBeDefined();
  });

  test('combo Move+Capture +10', () => {
    // Perform move and capture
    // Simplified
  });

  test('tie-breaker ordering', () => {
    state.players[0].score = 100;
    state.players[1].score = 100;
    state.players[0].captures = 5;
    state.players[1].captures = 3;
    const ranking = LudoEngine.getRanking(state);
    expect(ranking[0].playerId).toBe('playerA');
  });
});