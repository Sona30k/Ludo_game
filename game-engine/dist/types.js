"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EventType = exports.PositionType = exports.Phase = void 0;
var Phase;
(function (Phase) {
    Phase["NORMAL_PLAY"] = "NORMAL_PLAY";
    Phase["FINAL_MOVES"] = "FINAL_MOVES";
    Phase["MATCH_ENDED"] = "MATCH_ENDED";
})(Phase || (exports.Phase = Phase = {}));
var PositionType;
(function (PositionType) {
    PositionType["BASE"] = "BASE";
    PositionType["TRACK"] = "TRACK";
    PositionType["HOME_STRETCH"] = "HOME_STRETCH";
    PositionType["HOME"] = "HOME";
})(PositionType || (exports.PositionType = PositionType = {}));
var EventType;
(function (EventType) {
    EventType["MATCH_STARTED"] = "MATCH_STARTED";
    EventType["TURN_CHANGED"] = "TURN_CHANGED";
    EventType["DICE_ROLLED"] = "DICE_ROLLED";
    EventType["TOKEN_MOVED"] = "TOKEN_MOVED";
    EventType["SCORE_ADDED"] = "SCORE_ADDED";
    EventType["TOKEN_CAPTURED"] = "TOKEN_CAPTURED";
    EventType["TOKEN_HOME"] = "TOKEN_HOME";
    EventType["COMBO_BONUS_AWARDED"] = "COMBO_BONUS_AWARDED";
    EventType["DOUBLE_SIX_BONUS"] = "DOUBLE_SIX_BONUS";
    EventType["THIRD_SIX_PENALTY"] = "THIRD_SIX_PENALTY";
    EventType["FINAL_MOVES_STARTED"] = "FINAL_MOVES_STARTED";
    EventType["MATCH_ENDED"] = "MATCH_ENDED";
})(EventType || (exports.EventType = EventType = {}));
//# sourceMappingURL=types.js.map