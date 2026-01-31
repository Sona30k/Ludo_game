<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';

$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;
if ($table_id <= 0) {
    header('Location: home.php');
    exit;
}

$virtual_table_id = isset($_GET['virtual_table_id']) ? $_GET['virtual_table_id'] : "";
if (empty($virtual_table_id)) {
    header('Location: home.php');
    exit;
}

$is_spectator = !empty($_GET['spectator']);

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT id, username, mobile, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($is_spectator && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    header('Location: home.php');
    exit;
}

// Get table info
$stmt = $pdo->prepare("SELECT t1.id, type, time_limit, entry_points, t2.status FROM tables as t1 JOIN virtual_tables as t2 ON t1.id = t2.table_id WHERE t1.id = ? AND t2.id = ? AND t2.status IN('WAITING', 'RUNNING')");
$stmt->execute([$table_id, $virtual_table_id]);
$table = $stmt->fetch();

if (!$table) {
    header('Location: home.php?error=table_not_found');
    exit;
}

// // Verify user joined this table
// $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE user_id = ? AND  type = 'debit' AND virtual_table_id = ?");
// $stmt->execute([$user_id, $virtual_table_id]);
// $hasJoined = $stmt->fetchColumn() > 0;

// if (!$hasJoined) {
//     header('Location: home.php?error=not_joined');
//     exit;
// }

// WebSocket server URL (update this to your WebSocket server URL)
$envPath = __DIR__ . '/websocket/.env';
$wsPort = 3000;
if (is_readable($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === 'PORT') {
            $wsPort = (int) trim($value, "\"'");
            break;
        }
    }
}
$wsUrl = "http://91.108.104.181";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <link rel="manifest" href="manifest.json" />
    <title>Ludo Game - Table #<?= $table_id ?></title>
    <link href="style.css" rel="stylesheet">
    <style>
        :root {
            --bg-1: #0b1a4b;
            --bg-2: #132b6e;
            --bg-3: #0b1a4b;
            --panel: #1a2f6f;
            --panel-dark: #10245e;
            --panel-light: #2c4b9a;
            --accent: #64ff5a;
            --accent-2: #f6c53b;
            --text: #e8f1ff;
            --muted: #9db1e5;
            --board-frame: #1c2b66;
            --board-inner: #dadada;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: radial-gradient(circle at 30% 0%, #1b3b8f 0%, #0b1a4b 55%, #08123a 100%);
            color: var(--text);
            font-family: "Trebuchet MS", "Lucida Sans Unicode", "Arial Rounded MT Bold", Arial, sans-serif;
        }

        .game-container {
            min-height: 100vh;
            padding: 10px 12px 80px;
            background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 45%, var(--bg-3) 100%);
        }

        .game-shell {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .hud {
            background: linear-gradient(180deg, #214aa6 0%, #0f2f78 100%);
            border-radius: 18px;
            padding: 10px 12px 8px;
            box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.25), 0 8px 18px rgba(5, 12, 38, 0.4);
            border: 1px solid rgba(84, 120, 220, 0.6);
        }

        .hud-top,
        .hud-bottom {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 8px;
        }

        .hud-top {
            padding-bottom: 6px;
        }

        .hud-left,
        .hud-right {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .icon-btn {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #0c1f57;
            border: 2px solid #1f3f90;
            color: #7ac7ff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.2);
        }

        .signal {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #0c1f57;
            border: 2px solid #1f3f90;
            display: grid;
            place-items: center;
        }

        .signal span {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #3dd26c;
            border-radius: 2px;
            box-shadow: 0 -6px 0 #3dd26c, 0 6px 0 #3dd26c;
        }

        .prize-pill {
            margin: 0 auto;
            padding: 6px 12px 4px;
            border-radius: 16px;
            background: linear-gradient(180deg, #1f4db4 0%, #16358b 100%);
            border: 2px solid #3561c9;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 12px;
            color: #eaf3ff;
            box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.2);
        }

        .prize-pill .coin {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffe8a1 0%, #f1b735 65%, #b37b12 100%);
            border: 2px solid #ffd36a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #8a4b00;
            font-size: 12px;
        }

        .hud-bottom .timer-display {
            justify-self: center;
            background: #0c1f57;
            border: 2px solid #243f94;
            color: #6dff6b;
            font-weight: 800;
            letter-spacing: 2px;
            padding: 4px 12px;
            border-radius: 10px;
            min-width: 86px;
            text-align: center;
        }

        .room-badge {
            justify-self: end;
            background: #10265f;
            border: 2px solid #2d4b9e;
            padding: 4px 8px;
            border-radius: 14px;
            color: #cfe2ff;
            font-size: 11px;
        }

        .turn-indicator {
            margin: 8px 4px 0 auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .turn-dice {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #dfe7f7;
            border: 3px solid #46ff62;
            color: #2f4b7a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
        }

        .turn-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffe18a 0%, #e4b43f 60%, #a8761a 100%);
            border: 3px solid #2fe45a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a2a55;
            font-size: 18px;
            font-weight: 700;
        }

        .turn-name {
            display: none;
        }

        .game-layout {
            margin-top: 10px;
            display: grid;
            gap: 10px;
        }

        .board-frame {
            background: linear-gradient(180deg, #3b58c0 0%, #2a3f8a 100%);
            border-radius: 16px;
            padding: 10px;
            box-shadow: 0 8px 18px rgba(5, 12, 38, 0.45);
            border: 2px solid rgba(88, 120, 212, 0.55);
        }

        .ludo-board {
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            aspect-ratio: 1;
            position: relative;
            background: var(--board-inner);
            border-radius: 14px;
            padding: 6px;
            overflow: hidden;
            border: 4px solid #c9d3f0;
            box-shadow: inset 0 0 0 3px #2c4b9a;
            --cell: 40px;
        }

        .board-svg-container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .board-svg {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            position: relative;
            z-index: 1;
        }

        .board-highlights {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }

        .safe-spot {
            position: absolute;
            border-radius: 50%;
            border: 2px solid #64ff5a;
            box-shadow: 0 0 8px rgba(100, 255, 90, 0.6), inset 0 0 4px rgba(100, 255, 90, 0.4);
            opacity: 0.4;
        }

        .blockade-indicator {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 100, 100, 0.2);
            border: 2px solid #ff6464;
            box-shadow: 0 0 10px rgba(255, 100, 100, 0.5);
            opacity: 0.3;
            animation: blockadePulse 1.5s ease-in-out infinite;
        }

        @keyframes blockadePulse {
            0%, 100% { box-shadow: 0 0 10px rgba(255, 100, 100, 0.5); opacity: 0.3; }
            50% { box-shadow: 0 0 15px rgba(255, 100, 100, 0.8); opacity: 0.5; }
        }

        .pawns-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 3;
        }

        .pawns-container .pawn {
            pointer-events: auto;
        }

        .pawn {
            width: calc(var(--cell) * 0.58);
            height: calc(var(--cell) * 0.58);
            border-radius: 50%;
            position: absolute;
            border: 3px solid #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6), inset 0 1px 2px rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 10;
            transform: translate(-50%, -50%) scale(1);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
            align-items: center;
            justify-content: center;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: white;
        }

        .pawn:hover {
            transform: translate(-50%, -50%) scale(1.12);
        }

        .pawn.red { background: #d82e2e; }
        .pawn.blue { background: #2b57d7; }
        .pawn.green { background: #2da85c; }
        .pawn.yellow { background: #f2b227; }

        .pawn.selectable {
            border: 2px solid #40ff68;
            animation: pulse 1s infinite;
        }

        .pawn.turn-highlight {
            box-shadow: 0 0 0 4px rgba(64, 255, 104, 0.35), 0 2px 6px rgba(0, 0, 0, 0.6);
            animation: turnGlow 1.2s ease-in-out infinite;
        }

        .pawn.moving {
            transition: left 0.18s linear, top 0.18s linear;
        }

        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.08); }
        }

        @keyframes turnGlow {
            0%, 100% { box-shadow: 0 0 0 4px rgba(64, 255, 104, 0.35), 0 2px 6px rgba(0, 0, 0, 0.6); }
            50% { box-shadow: 0 0 0 8px rgba(64, 255, 104, 0.2), 0 2px 6px rgba(0, 0, 0, 0.6); }
        }

        @keyframes captureEffect {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
            50% {
                transform: translate(-50%, -50%) scale(1.3);
                opacity: 0.8;
            }
            100% {
                transform: translate(-50%, -50%) scale(0);
                opacity: 0;
            }
        }

        @keyframes finishEffect {
            0% {
                transform: translate(-50%, -50%) scale(1) rotate(0deg);
                opacity: 1;
            }
            25% {
                transform: translate(-50%, -50%) scale(1.2) rotate(10deg);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1) rotate(-10deg);
            }
            75% {
                transform: translate(-50%, -50%) scale(1.15) rotate(5deg);
            }
            100% {
                transform: translate(-50%, -50%) scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .pawn.captured {
            animation: captureEffect 0.6s ease-out forwards;
        }

        .pawn.finished {
            animation: finishEffect 0.8s ease-in-out;
            border-color: #64ff5a;
            box-shadow: 0 0 15px rgba(100, 255, 90, 0.8), 0 2px 6px rgba(0, 0, 0, 0.6);
        }

        .pawn.selectable::after {
            content: "";
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid rgba(64, 255, 104, 0.7);
            box-shadow: 0 0 12px rgba(64, 255, 104, 0.7);
            opacity: 0.7;
        }

        .players-panel {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .player-card {
            background: #162d6b;
            border-radius: 10px;
            padding: 8px;
            color: #d8e6ff;
            border: 2px solid #23418f;
        }

        .player-card.active {
            border-color: #45ff6a;
            box-shadow: 0 0 0 2px rgba(69, 255, 106, 0.2), 0 0 18px rgba(69, 255, 106, 0.4);
        }

        .player-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .player-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffe18a 0%, #e4b43f 65%, #a8761a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a2a55;
            font-weight: 700;
        }

        .player-score {
            font-size: 14px;
            font-weight: 700;
            color: #ffdf7c;
            position: relative;
            display: inline-block;
        }

        .score-popup {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #64ff5a;
            color: #0b1a4b;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 900;
            font-size: 13px;
            white-space: nowrap;
            pointer-events: none;
            animation: scorePopup 1.2s ease-out forwards;
            box-shadow: 0 4px 12px rgba(100, 255, 90, 0.6);
        }

        @keyframes scorePopup {
            0% {
                opacity: 1;
                transform: translateX(-50%) translateY(0) scale(1);
            }
            50% {
                opacity: 1;
            }
            100% {
                opacity: 0;
                transform: translateX(-50%) translateY(-40px) scale(0.8);
            }
        }

        .dice-section {
            position: fixed;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            width: min(420px, 94vw);
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 12px;
            z-index: 100;
            background: linear-gradient(180deg, #0b1f5a 0%, #081648 100%);
            border-radius: 16px;
            padding: 8px 12px;
            border: 2px solid #243f94;
            box-shadow: 0 10px 22px rgba(5, 12, 38, 0.55);
        }

        .admin-dice-panel {
            position: fixed;
            bottom: 98px;
            left: 50%;
            transform: translateX(-50%);
            width: min(420px, 92vw);
            background: rgba(10, 20, 60, 0.92);
            border: 2px solid #2d4b9e;
            border-radius: 14px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 120;
            box-shadow: 0 10px 22px rgba(5, 12, 38, 0.55);
        }

        .admin-dice-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9db1e5;
            white-space: nowrap;
        }

        .admin-dice-buttons {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 6px;
            width: 100%;
        }

        .admin-dice-btn {
            height: 32px;
            border-radius: 10px;
            border: 1px solid #3a58b8;
            background: #1a2f6f;
            color: #e8f1ff;
            font-weight: 700;
            cursor: pointer;
        }

        .admin-dice-btn:hover {
            background: #28439a;
        }

        .dice-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #c9dbff;
            font-size: 12px;
        }

        .dice-button {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            background: #dbe2f5;
            border: 3px solid #617bcd;
            color: #243a6b;
            font-size: 32px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: inset 0 -4px 0 rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease;
            position: relative;
            padding: 0;
        }

        .dice-button:hover:not(:disabled) {
            transform: scale(1.05);
        }

        .dice-button.glow {
            box-shadow: 0 0 16px rgba(125, 165, 255, 0.7), inset 0 -4px 0 rgba(0, 0, 0, 0.2);
        }

        .dice-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .dice-button.rolling {
            animation: dice3DRoll 1.5s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite !important;
        }

        .dice-face {
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, 1fr);
            gap: 4px;
            padding: 10px;
            animation: inherit;
        }

        .pip {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #243a6b;
            opacity: 0;
            place-self: center;
            transition: all 0.1s ease;
        }

        .dice-button.rolling .pip {
            opacity: 0.4;
            animation: pipFlash 0.15s ease-in-out infinite;
            background: #64ff5a;
        }

        @keyframes pipFlash {
            0%, 100% { 
                opacity: 0.3;
                transform: scale(1) rotateZ(0deg);
                background: #243a6b;
            }
            50% { 
                opacity: 1;
                transform: scale(1.3) rotateZ(45deg);
                background: #64ff5a;
                box-shadow: 0 0 8px #64ff5a;
            }
        }

        @keyframes dice3DRoll {
            0% { 
                transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg) scale(1);
                box-shadow: 0 4px 12px rgba(125, 165, 255, 0.3);
            }
            25% { 
                transform: rotateX(360deg) rotateY(180deg) rotateZ(90deg) scale(1.05);
                box-shadow: 0 8px 20px rgba(125, 165, 255, 0.6);
            }
            50% { 
                transform: rotateX(180deg) rotateY(360deg) rotateZ(180deg) scale(1.08);
                box-shadow: 0 10px 24px rgba(100, 255, 90, 0.5);
            }
            75% { 
                transform: rotateX(270deg) rotateY(180deg) rotateZ(270deg) scale(1.05);
                box-shadow: 0 8px 20px rgba(125, 165, 255, 0.6);
            }
            100% { 
                transform: rotateX(360deg) rotateY(360deg) rotateZ(360deg) scale(1);
                box-shadow: 0 4px 12px rgba(125, 165, 255, 0.3);
            }
        }

        @keyframes roll {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(6deg); }
            50% { transform: rotate(0deg); }
            75% { transform: rotate(-6deg); }
        }

        .dice-status {
            justify-self: end;
            font-size: 12px;
            color: #9cb3ee;
        }

        .waiting-screen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(6, 12, 36, 0.92);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            z-index: 1000;
        }

        .reconnect-banner {
            position: fixed;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(10, 18, 50, 0.9);
            color: #e8f1ff;
            border: 2px solid #2c4b9a;
            border-radius: 12px;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 2500;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .reconnect-banner.show {
            opacity: 1;
        }

        .reconnect-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ffd166;
            box-shadow: 0 0 10px rgba(255, 209, 102, 0.8);
            animation: reconnectPulse 1s infinite;
        }

        @keyframes reconnectPulse {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
        }

        .event-toast {
            position: fixed;
            left: 50%;
            top: 120px;
            transform: translateX(-50%);
            background: rgba(6, 14, 40, 0.92);
            color: #ffffff;
            border: 2px solid #2c4b9a;
            border-radius: 12px;
            padding: 10px 16px;
            font-weight: 700;
            letter-spacing: 0.3px;
            opacity: 0;
            z-index: 2600;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .event-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(-6px);
        }

        .final-moves-banner {
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(16, 32, 90, 0.95);
            color: #ffe8a1;
            border: 2px solid #ffd36a;
            border-radius: 14px;
            padding: 8px 16px;
            font-weight: 800;
            z-index: 2500;
            display: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        .final-moves-banner.show {
            display: block;
        }

        .spectator-banner {
            position: fixed;
            top: 70px;
            right: 14px;
            background: rgba(15, 23, 42, 0.9);
            color: #e2e8f0;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 999px;
            padding: 6px 12px;
            font-weight: 800;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            z-index: 2500;
            display: none;
        }

        .waiting-screen.hidden {
            display: none;
        }

        .game-finished-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            z-index: 2000;
        }

        .game-finished-modal.show {
            display: flex;
        }

        .ranking-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            min-width: 300px;
        }

        .ranking-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            margin: 8px 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .ranking-item.first {
            background: rgba(255, 215, 0, 0.3);
            border: 2px solid #fbbf24;
        }

        .winner-banner {
            display: inline-flex;
            flex-direction: column;
            gap: 4px;
            padding: 10px 16px;
            border-radius: 14px;
            background: rgba(20, 35, 90, 0.85);
            border: 1px solid rgba(255, 211, 106, 0.6);
            color: #ffe8a1;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .winner-banner span {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 600;
        }

        @media (min-width: 520px) {
            .game-container {
                display: flex;
                justify-content: center;
            }

            .game-shell {
                width: 420px;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-shell">
            <div class="reconnect-banner" id="reconnectBanner">
                <span class="reconnect-dot"></span>
                <span id="reconnectText">Reconnecting…</span>
            </div>
            <div class="final-moves-banner" id="finalMovesBanner">FINAL MOVES</div>
            <div class="spectator-banner" id="spectatorBanner">Spectator</div>
            <div class="event-toast" id="eventToast"></div>
            <!-- Game Header -->
            <div class="hud">
                <div class="hud-top">
                    <div class="hud-left">
                        <button onclick="window.location.href='home.php'" class="icon-btn" aria-label="Back">
                            <i class="ph ph-arrow-left"></i>
                        </button>
                        <span class="signal" aria-hidden="true"><span></span></span>
                    </div>
                    <div class="prize-pill">
                        <span class="coin">T</span>
                        <span>Prize Pool Rs.<span id="prizePool">0</span></span>
                    </div>
                    <div class="hud-right">
                        <span class="icon-btn" aria-label="Settings">
                            <i class="ph ph-gear"></i>
                        </span>
                    </div>
                </div>
                <div class="hud-bottom">
                    <div class="hud-left"></div>
                    <div class="timer-display" id="timer">00:00</div>
                    <div class="room-badge">Pro/76s</div>
                </div>
            </div>

            <div class="turn-indicator" id="currentTurnDisplay" style="display: none;">
                <div class="turn-dice" id="currentDice">-</div>
                <div class="turn-avatar">P</div>
                <div class="turn-name" id="currentPlayerName">-</div>
            </div>

            <div class="game-layout">
                <!-- Game Board -->
                <div class="board-frame">
                    <div class="ludo-board" id="gameBoard">
                        <!-- Board will be rendered here by JavaScript -->
                    </div>
                </div>

                <!-- Players Panel -->
                <div class="players-panel" id="playersPanel">
                    <!-- Players will be rendered here -->
                </div>
            </div>
        </div>

        <?php if ($is_spectator && isset($user['role']) && $user['role'] === 'admin'): ?>
        <div class="admin-dice-panel" id="adminDicePanel">
            <div class="admin-dice-label">Admin Dice</div>
            <div class="admin-dice-buttons">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <button class="admin-dice-btn" type="button" onclick="forceDice(<?= $i ?>)"><?= $i ?></button>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dice Section -->
        <div class="dice-section">
            <div class="dice-meta">
                <span class="icon-btn" aria-hidden="true"><i class="ph ph-chat-circle-dots"></i></span>
                <span class="icon-btn" aria-hidden="true"><i class="ph ph-smiley"></i></span>
            </div>
            <button class="dice-button" id="rollDiceBtn" onclick="rollDice()" disabled data-value="0">
                <span id="diceValue" class="dice-value">?</span>
                <div class="dice-face" aria-hidden="true">
                    <span class="pip p1"></span>
                    <span class="pip p2"></span>
                    <span class="pip p3"></span>
                    <span class="pip p4"></span>
                    <span class="pip p5"></span>
                    <span class="pip p6"></span>
                    <span class="pip p7"></span>
                    <span class="pip p8"></span>
                    <span class="pip p9"></span>
                </div>
            </button>
            <div class="dice-status" id="diceStatus">Waiting for your turn...</div>
        </div>

        <!-- Waiting Screen -->
        <div class="waiting-screen" id="waitingScreen">
            <div class="text-center">
                <div class="text-6xl mb-4">...</div>
                <h2 class="text-2xl font-bold mb-2">Waiting for Players...</h2>
                <p id="waitingText">Players joined: <span id="playersCount">0</span></p>
            </div>
        </div>

        <!-- Game Finished Modal -->
        <div class="game-finished-modal" id="gameFinishedModal">
            <div class="text-center">
                <h2 class="text-3xl font-bold mb-6">Game Finished!</h2>
                <div class="winner-banner" id="winnerBanner"></div>
                <div class="ranking-list" id="rankingList">
                    <!-- Ranking will be shown here -->
                </div>
                <button onclick="window.location.href='home.php'" class="mt-6 bg-p2 px-8 py-3 rounded-full text-white font-bold">
                    Back to Home
                </button>
            </div>
        </div>
    </div>

    <script>
        // Game State
        let socket = null;
        let gameState = null;
        let myPlayerIndex = -1;
        let tableId = <?= $table_id ?>;
        let virtualTableId = "<?= $virtual_table_id ?>"; // Will be set when joining virtual table
        let userId = <?= $user_id ?>;
        let username = '<?= addslashes($user['username']) ?>';
        let isSpectator = <?= $is_spectator ? 'true' : 'false' ?>;
        let isAdmin = <?= (isset($user['role']) && $user['role'] === 'admin') ? 'true' : 'false' ?>;
        const wsUrl = '<?= $wsUrl ?>';
        let waitCountdownTimer = null; // Timer for wait countdown
        let validMoves = []; // Store valid moves after dice roll
        const lastPawnPositions = new Map();
        const activePawnAnimations = new Map();
        let timerInterval = null;
        let lastTimerSyncMs = 0;
        let lastTimerRemaining = 0;
        let timerSyncInterval = null;
        let lastValidMovesKey = null;
        let audioContext = null;
        let audioUnlocked = false;
        let reconnectTimeout = null;
        let diceRollInterval = null; // Controls dice roll animation

        // Initialize Socket Connection
        function initSocket() {
            socket = io(wsUrl, {
                auth: {
                    userId: userId,
                    username: username
                },
                reconnection: true,
                reconnectionDelay: 1000,
                reconnectionAttempts: 5
            });

            socket.on('connect', () => {
                console.log('Connected to WebSocket server');
                if (isSpectator) {
                    const banner = document.getElementById('spectatorBanner');
                    if (banner) {
                        banner.style.display = 'block';
                    }
                    joinSpectator();
                } else {
                    joinTable();
                }
            });

            socket.on('disconnect', () => {
                console.log('❌ Disconnected from server');
            });

            socket.on('connect_error', (error) => {
                console.error('Connection error:', error);
                alert('Failed to connect to game server. Please refresh the page.');
            });

            // Game Events
            socket.on('game_state', (data) => {
                gameState = data.gameState;
                if (data.yourPlayerIndex !== undefined && data.yourPlayerIndex !== null) {
                    myPlayerIndex = data.yourPlayerIndex;
                }
                if (myPlayerIndex === -1 && gameState?.players?.length) {
                    myPlayerIndex = gameState.players.findIndex(p => p.userId == userId);
                }
                virtualTableId = data.virtualTableId || virtualTableId; // Store virtualTableId
                if (data.seatNo !== undefined) {
                    console.log('Assigned seat:', data.seatNo);
                }
                
                // Debug: Log all players received
                console.log('📋 Game state received with players:', gameState?.players?.map(p => ({
                    username: p.username,
                    userId: p.userId,
                    isBot: p.isBot,
                    color: p.color
                })));
                
                // Update player count immediately when game_state is received
                if (gameState && gameState.players && gameState.players.length > 0) {
                    const playersCountEl = document.getElementById('playersCount');
                    if (playersCountEl) {
                        const realPlayers = gameState.players.filter(p => !p.isBot).length;
                        playersCountEl.textContent = realPlayers || gameState.players.length;
                    }
                }
                
                // Hide waiting screen if game is already running
                if (gameState && gameState.gameStatus === 'running') {
                    hideWaitingScreen();
                }
                
                updateGameUI();
                syncTimer(deriveRemainingTime());
                hideReconnectBanner();
                requestValidMovesIfNeeded();
            });

            socket.on('player_joined', (data) => {
                console.log('Player joined:', data);
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                // Update player count immediately
                if (data.totalPlayers !== undefined) {
                    const playersCountEl = document.getElementById('playersCount');
                    if (playersCountEl && data.realPlayers !== undefined) {
                        playersCountEl.textContent = data.realPlayers;
                    } else if (playersCountEl) {
                        playersCountEl.textContent = data.totalPlayers;
                    }
                }
                if (gameState) {
                    updatePlayersPanel();
                }
            });

            socket.on('game_started', (data) => {
                console.log('Game started!');
                gameState = data.gameState;
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                
                // Clear wait countdown timer
                if (waitCountdownTimer) {
                    clearInterval(waitCountdownTimer);
                    waitCountdownTimer = null;
                }
                
                // Hide waiting screen
                hideWaitingScreen();
                
                updateGameUI();
                syncTimer(deriveRemainingTime());
                hideReconnectBanner();
            });

            socket.on('wait_countdown', (data) => {
                console.log('Wait countdown:', data.remaining);
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                
                // Clear any existing countdown timer
                if (waitCountdownTimer) {
                    clearInterval(waitCountdownTimer);
                }
                
                // Start countdown timer
                let remaining = data.remaining;
                updateWaitCountdown(remaining, data.total);
                
                waitCountdownTimer = setInterval(() => {
                    remaining--;
                    if (remaining > 0) {
                        updateWaitCountdown(remaining, data.total);
                    } else {
                        // Countdown reached 0
                        updateWaitCountdown(0, data.total);
                        clearInterval(waitCountdownTimer);
                        waitCountdownTimer = null;
                        
                        // Hide waiting screen when countdown reaches 0
                        // Give server a moment to send game_started, then force hide
                        setTimeout(() => {
                            console.log('Countdown ended, checking game status:', gameState?.gameStatus);
                            hideWaitingScreen();
                        }, 1500);
                    }
                }, 1000);
            });

            socket.on('game_cancelled', (data) => {
                console.log('Game cancelled:', data);
                alert('Game cancelled: ' + data.reason + (data.refunded ? ' (Entry fee refunded)' : ''));
                window.location.href = 'home.php';
            });

            socket.on('dice_rolled', (data) => {
                console.log('Dice rolled event received, dice:', data.diceValue, 'isForced:', data.isForced);
                gameState = data.gameState;
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                
                // ADMIN OVERRIDE WINDOW: Wait 3 seconds before showing final result
                // This gives admins time to force a different dice value
                const ADMIN_OVERRIDE_WINDOW = 3000; // 3 seconds for admin to override
                
                // If admin forced the dice, show it immediately without animation
                if (data.isForced) {
                    console.log('⚡ Admin forced dice value:', data.diceValue);
                    updateDiceDisplay(data.diceValue, data.playerId);
                    updateGameUI();
                    
                    // Show visual indicator that this is admin-forced
                    const btn = document.getElementById('rollDiceBtn');
                    if (btn) {
                        btn.style.borderColor = '#ff6464';
                        btn.style.boxShadow = '0 0 20px rgba(255, 100, 100, 0.8)';
                        setTimeout(() => {
                            btn.style.borderColor = '';
                            btn.style.boxShadow = '';
                        }, 2000);
                    }
                    
                    playSound('roll');
                    playHaptic('roll');
                    renderEvents(data.events);
                    
                    // Emit valid moves immediately
                    if (!isSpectator) {
                        socket.emit('get_valid_moves', { 
                            virtualTableId: virtualTableId,
                            tableId: tableId 
                        });
                    } else {
                        validMoves = [];
                    }
                } else {
                    // Normal dice animation for random rolls
                    startDiceAnimation();
                    
                    setTimeout(() => {
                        stopDiceAnimation(data.diceValue);
                        updateDiceDisplay(data.diceValue, data.playerId);
                        updateGameUI();
                        playSound('roll');
                        playHaptic('roll');
                        
                        // Add celebration effect for 6
                        if (data.diceValue === 6) {
                            const btn = document.getElementById('rollDiceBtn');
                            if (btn) {
                                btn.style.animation = 'dice3DRoll 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55)';
                                setTimeout(() => {
                                    btn.style.animation = '';
                                }, 600);
                            }
                            playSound('six');
                        }
                        
                        renderEvents(data.events);
                    }, 1600 + ADMIN_OVERRIDE_WINDOW); // Animation duration + admin override window
                    
                    if (!isSpectator) {
                        // Get valid moves for the rolled dice
                        console.log('Emitting get_valid_moves for dice', data.diceValue);
                        setTimeout(() => {
                            socket.emit('get_valid_moves', { 
                                virtualTableId: virtualTableId,
                                tableId: tableId 
                            });
                        }, 1600 + ADMIN_OVERRIDE_WINDOW);
                    } else {
                        validMoves = [];
                    }
                }
            });

            socket.on('pawn_moved', (data) => {
                console.log('Pawn moved event:', data);
                console.log('moveResult:', data.moveResult);
                console.log('oldPosition:', data.moveResult?.oldPosition, 'newPosition:', data.moveResult?.newPosition);
                
                gameState = data.gameState;
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                validMoves = []; // Clear valid moves after move
                
                // IMPORTANT: Get old position from moveResult BEFORE calling updateBoard()
                const oldPosition = data.moveResult?.oldPosition;
                const newPosition = data.moveResult?.newPosition;
                
                console.log(`📍 Movement: fromPos=${oldPosition} → toPos=${newPosition} (distance=${newPosition - oldPosition})`);
                
                updateBoard();
                updateGameUI();
                
                // Handle captured pawns animation
                if (data.moveResult && data.moveResult.killedPawns && data.moveResult.killedPawns.length > 0) {
                    playSound('capture');
                    playHaptic('capture');
                    
                    // Animate captured pawns returning to home
                    data.moveResult.killedPawns.forEach(killedPawn => {
                        const killedPlayerIndex = gameState.players.findIndex(p => p.userId == killedPawn.userId);
                        if (killedPlayerIndex !== -1) {
                            const pawnEl = document.getElementById(`pawn-${killedPawn.userId}-${killedPawn.pawnIndex}`);
                            if (pawnEl) {
                                pawnEl.classList.add('captured');
                                setTimeout(() => {
                                    pawnEl.classList.remove('captured');
                                    animatePawnStepwise(killedPlayerIndex, killedPawn.pawnIndex, 
                                        gameState.players[killedPlayerIndex].pawns[killedPawn.pawnIndex].position, 
                                        0);
                                }, 600);
                            }
                        }
                    });
                } else {
                    playSound('move');
                    playHaptic('move');
                }
                renderEvents(data.moveResult?.events);
                
                if (data.playerId !== undefined && data.pawnIndex !== undefined) {
                    const movedPlayerIndex = gameState.players.findIndex(p => p.userId == data.playerId);
                    if (movedPlayerIndex !== -1) {
                        // Use the actual old and new positions from moveResult
                        const fromPos = oldPosition;
                        const toPos = newPosition;
                        
                        console.log(`🎯 Animating pawn ${data.pawnIndex} from ${fromPos} to ${toPos}`);
                        
                        // Check if this pawn finished
                        if (toPos >= 57) {
                            const pawnEl = document.getElementById(`pawn-${data.playerId}-${data.pawnIndex}`);
                            if (pawnEl) {
                                pawnEl.classList.add('finished');
                                playSound('finish');
                                playHaptic('finish');
                            }
                        }
                        
                        animatePawnStepwise(movedPlayerIndex, data.pawnIndex, fromPos, toPos);
                    }
                }
                
                if (data.moveResult && data.moveResult.gameFinished) {
                    showGameFinished(data.moveResult.ranking);
                }
            });

            socket.on('valid_moves', (data) => {
                console.log('Valid moves received:', data);
                validMoves = data.moves || [];
                updateBoard(); // Re-render board with valid moves
            });

            socket.on('game_finished', (data) => {
                console.log('Game finished:', data);
                if (data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                }
                playSound('win');
                playHaptic('win');
                showGameFinished(data.ranking);
            });

            socket.on('final_moves_started', (data) => {
                gameState = data.gameState;
                showFinalMovesBanner(true);
                renderEvents([{ type: 'FINAL_MOVES' }]);
                updateGameUI();
            });

            socket.on('error', (data) => {
                console.error('Error:', data);
                alert(data.message || 'An error occurred');
                setDiceButtonLoading(false);
                if (!isSpectator && gameState) {
                    updateDiceButton();
                }
            });
        }

        // Hide Waiting Screen
        function hideWaitingScreen() {
            const waitingScreen = document.getElementById('waitingScreen');
            if (waitingScreen) {
                waitingScreen.classList.add('hidden');
                waitingScreen.style.display = 'none'; // Fallback
                waitingScreen.style.visibility = 'hidden'; // Additional fallback
                waitingScreen.style.opacity = '0'; // Additional fallback
                console.log('Waiting screen hidden');
            } else {
                console.warn('Waiting screen element not found');
            }
        }

        // Join Table
        function joinTable() {
            console.log('Joining table:', { tableId, virtualTableId, userId });
            socket.emit('join_table', { tableId: tableId, virtualTableId: virtualTableId, userId: userId });
        }

        function joinSpectator() {
            socket.emit('join_spectator', { tableId: tableId, virtualTableId: virtualTableId });
        }

        // Roll Dice
        function rollDice() {
            if (isSpectator) return;
            if (!socket || !gameState) return;
            
            const btn = document.getElementById('rollDiceBtn');
            btn.disabled = true;
            btn.dataset.value = "0";
            setDiceButtonLoading(true);
            
            socket.emit('roll_dice', { 
                virtualTableId: virtualTableId,
                tableId: tableId // Keep for backward compatibility
            });
        }

        function setDiceButtonLoading(isLoading) {
            const btn = document.getElementById('rollDiceBtn');
            if (!btn) return;
            if (isLoading) {
                btn.classList.add('rolling');
                startDiceAnimation();
            } else {
                btn.classList.remove('rolling');
                stopDiceAnimation();
            }
        }

        // Dice animation helpers
        function startDiceAnimation() {
            stopDiceAnimation();
            const btn = document.getElementById('rollDiceBtn');
            const valueEl = document.getElementById('diceValue');
            
            if (!btn) return;
            
            // Add visual effects
            btn.style.perspective = '1000px';
            
            // Cycle through dice values at slower speed
            diceRollInterval = setInterval(() => {
                const randomValue = Math.floor(Math.random() * 6) + 1;
                btn.dataset.value = randomValue;
                if (valueEl) {
                    valueEl.textContent = randomValue;
                }
                
                // Add pulse effect
                btn.style.boxShadow = `0 0 ${8 + Math.random() * 16}px rgba(100, 255, 90, ${0.4 + Math.random() * 0.4}), inset 0 -4px 0 rgba(0, 0, 0, 0.2)`;
            }, 130);
        }

        function stopDiceAnimation(finalValue = null) {
            if (diceRollInterval) {
                clearInterval(diceRollInterval);
                diceRollInterval = null;
            }
            const btn = document.getElementById('rollDiceBtn');
            const valueEl = document.getElementById('diceValue');
            
            if (finalValue !== null) {
                if (btn) btn.dataset.value = finalValue;
                if (valueEl) valueEl.textContent = finalValue;
                
                // Show final result with glow effect
                if (btn) {
                    btn.style.boxShadow = '0 0 20px rgba(100, 255, 90, 0.8), inset 0 -4px 0 rgba(0, 0, 0, 0.2)';
                    // Remove glow after 1 second
                    setTimeout(() => {
                        btn.style.boxShadow = '0 4px 12px rgba(125, 165, 255, 0.3), inset 0 -4px 0 rgba(0, 0, 0, 0.2)';
                    }, 1000);
                }
            }
        }

        async function forceDice(value) {
            if (!isSpectator || !isAdmin) return;
            if (!virtualTableId || !tableId) {
                showEventToast('Missing table info');
                return;
            }
            try {
                const res = await fetch('api/admin/force-dice.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        table_id: tableId,
                        virtual_table_id: virtualTableId,
                        dice_value: value
                    })
                });
                const result = await res.json();
                if (result.success) {
                    showEventToast(`ADMIN DICE: ${value}`);
                } else {
                    showEventToast(result.error || 'Dice override failed');
                }
            } catch (error) {
                showEventToast('Dice override failed');
            }
        }

        // Move Pawn
        function movePawn(pawnIndex) {
            if (isSpectator) return;
            if (!socket || !gameState) return;
            
            console.log('Emitting move_pawn for pawn', pawnIndex);
            socket.emit('move_pawn', { 
                virtualTableId: virtualTableId,
                tableId: tableId, // Keep for backward compatibility
                pawnIndex: pawnIndex 
            });
        }

        // Update Game UI
        function updateGameUI() {
            if (!gameState) return;

            // Hide waiting screen if game is active
            if (gameState.gameStatus === 'running' || gameState.gameStatus === 'playing' || gameState.gameStatus === 'final_moves') {
                hideWaitingScreen();
            }

            showFinalMovesBanner(gameState.gameStatus === 'final_moves');

            // Update timer
            syncTimer(deriveRemainingTime());

            // Update prize pool (only real players, not bots)
            const realPlayers = gameState.realPlayers || gameState.players.filter(p => !p.isBot).length;
            const prizePool = gameState.entryPoints * realPlayers;
            document.getElementById('prizePool').textContent = prizePool;

            // Update players panel
            updatePlayersPanel();

            // Update board
            updateBoard();

            // Update dice button state
            updateDiceButton();

            // Update current turn display
            updateCurrentTurn();
        }

        // Update Timer
        function updateTimer(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            document.getElementById('timer').textContent = 
                `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function initAudio() {
            if (audioContext) return;
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            audioContext = new Ctx();
        }

        function unlockAudio() {
            if (audioUnlocked) return;
            initAudio();
            if (!audioContext) return;
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }
            audioUnlocked = true;
        }

        function playTone(freq, duration, type = 'sine', gainValue = 0.08) {
            if (!audioContext || !audioUnlocked) return;
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            osc.type = type;
            osc.frequency.value = freq;
            gain.gain.value = gainValue;
            osc.connect(gain);
            gain.connect(audioContext.destination);
            osc.start();
            osc.stop(audioContext.currentTime + duration);
        }

        function playSound(kind) {
            if (!audioUnlocked) return;
            if (kind === 'roll') {
                playTone(520, 0.08, 'square', 0.06);
                setTimeout(() => playTone(740, 0.08, 'square', 0.06), 90);
            } else if (kind === 'move') {
                playTone(440, 0.1, 'sine', 0.05);
            } else if (kind === 'capture') {
                playTone(220, 0.12, 'sawtooth', 0.08);
                setTimeout(() => playTone(160, 0.12, 'sawtooth', 0.06), 120);
            } else if (kind === 'finish') {
                playTone(659, 0.15, 'triangle', 0.08);
                setTimeout(() => playTone(784, 0.15, 'triangle', 0.06), 140);
                setTimeout(() => playTone(659, 0.15, 'triangle', 0.05), 280);
            } else if (kind === 'blockade') {
                playTone(330, 0.08, 'square', 0.05);
                setTimeout(() => playTone(330, 0.08, 'square', 0.05), 100);
            } else if (kind === 'win') {
                playTone(523, 0.15, 'triangle', 0.06);
                setTimeout(() => playTone(659, 0.15, 'triangle', 0.06), 170);
                setTimeout(() => playTone(784, 0.2, 'triangle', 0.06), 340);
            }
        }

        function playHaptic(kind) {
            if (!navigator.vibrate) return;
            if (kind === 'roll') {
                navigator.vibrate([20, 30, 20]);
            } else if (kind === 'move') {
                navigator.vibrate(15);
            } else if (kind === 'capture') {
                navigator.vibrate([30, 40, 30]);
            } else if (kind === 'finish') {
                navigator.vibrate([50, 30, 50, 30, 50]);
            } else if (kind === 'blockade') {
                navigator.vibrate([10, 20, 10, 20]);
            } else if (kind === 'win') {
                navigator.vibrate([40, 60, 40, 60, 80]);
            }
        }

        let toastQueue = [];
        let toastActive = false;

        function showEventToast(message) {
            if (!message) return;
            toastQueue.push(message);
            if (!toastActive) {
                displayNextToast();
            }
        }

        function displayNextToast() {
            const toast = document.getElementById('eventToast');
            if (!toast || toastQueue.length === 0) {
                toastActive = false;
                return;
            }
            toastActive = true;
            const message = toastQueue.shift();
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(displayNextToast, 250);
            }, 1300);
        }

        function showFinalMovesBanner(show) {
            const banner = document.getElementById('finalMovesBanner');
            if (!banner) return;
            banner.classList.toggle('show', Boolean(show));
        }

        function renderEvents(events) {
            if (!events || !Array.isArray(events)) return;
            events.forEach(evt => {
                switch (evt.type) {
                    case 'CAPTURE':
                        showEventToast(`CAPTURE! +${evt.points}`);
                        break;
                    case 'FINAL_HOME':
                        showEventToast(`FINAL HOME +${evt.points}`);
                        break;
                    default:
                        break;
                }
            });
        }

        function showReconnectBanner(message) {
            const banner = document.getElementById('reconnectBanner');
            const text = document.getElementById('reconnectText');
            if (!banner || !text) return;
            text.textContent = message;
            banner.classList.add('show');
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
            }
            reconnectTimeout = setTimeout(() => {
                banner.classList.remove('show');
            }, 8000);
        }

        function hideReconnectBanner() {
            const banner = document.getElementById('reconnectBanner');
            if (!banner) return;
            banner.classList.remove('show');
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
                reconnectTimeout = null;
            }
        }

        function deriveRemainingTime() {
            if (!gameState) return null;
            if (Number.isFinite(gameState.remainingTime)) {
                return gameState.remainingTime;
            }
            if (Number.isFinite(gameState.totalDuration) && Number.isFinite(gameState.currentDuration)) {
                return Math.max(0, gameState.totalDuration - gameState.currentDuration);
            }
            if (gameState.startedAt && Number.isFinite(gameState.timeLimit)) {
                const startedAtMs = new Date(gameState.startedAt).getTime();
                if (!Number.isNaN(startedAtMs)) {
                    const elapsed = Math.floor((Date.now() - startedAtMs) / 1000);
                    return Math.max(0, gameState.timeLimit - elapsed);
                }
            }
            return null;
        }

        function syncTimer(seconds) {
            if (!Number.isFinite(seconds)) return;
            const normalized = Math.max(0, Math.floor(seconds));
            const drift = Math.abs(normalized - lastTimerRemaining);
            if (drift > 1 || !timerInterval) {
                lastTimerSyncMs = Date.now();
                lastTimerRemaining = normalized;
                updateTimer(lastTimerRemaining);
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                timerInterval = setInterval(() => {
                    const elapsed = Math.floor((Date.now() - lastTimerSyncMs) / 1000);
                    const remaining = Math.max(0, lastTimerRemaining - elapsed);
                    updateTimer(remaining);
                    if (remaining <= 0) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                    }
                }, 250);
            }
        }

        // Update Players Panel
        // Track previous scores for animation
        const previousScores = new Map();

        // Animate score change
        function animateScoreChange(playerIndex, newScore, pointsGained) {
            setTimeout(() => {
                const panel = document.getElementById('playersPanel');
                if (!panel) return;
                
                const scoreEls = panel.querySelectorAll('.player-score');
                if (playerIndex >= scoreEls.length) return;
                
                const scoreEl = scoreEls[playerIndex];
                
                // Create popup
                const popup = document.createElement('div');
                popup.className = 'score-popup';
                popup.textContent = `+${pointsGained}`;
                scoreEl.appendChild(popup);
                
                // Remove after animation
                setTimeout(() => popup.remove(), 1200);
            }, 50);
        }

        function updatePlayersPanel() {
            if (!gameState) return;

            const panel = document.getElementById('playersPanel');
            if (!panel) return;
            const maxPlayers = gameState.tableType === '2-player' ? 2 : 4;
            
            // Debug: Log all players before filtering
            console.log('🎮 All players in gameState:', gameState.players?.map(p => ({
                username: p.username,
                userId: p.userId,
                isBot: p.isBot,
                color: p.color
            })));
            
            // Remove duplicates by userId (include bots)
            const uniquePlayers = [];
            const seenUserIds = new Set();
            
            gameState.players.forEach((player, idx) => {
                // For bots, userId might be botId string, for real players it's a number
                const playerId = player.userId || player.id || player.botId;
                console.log(`Player ${idx}:`, {
                    username: player.username,
                    userId: player.userId,
                    id: player.id,
                    botId: player.botId,
                    isBot: player.isBot,
                    resolvedId: playerId
                });
                
                if (playerId && !seenUserIds.has(playerId)) {
                    seenUserIds.add(playerId);
                    uniquePlayers.push(player);
                } else if (!playerId) {
                    console.warn('⚠️ Player without ID:', player);
                } else {
                    console.log('⚠️ Duplicate player ID:', playerId, player);
                }
            });
            
            const displayPlayers = uniquePlayers.slice(0, maxPlayers);
            console.log('✅ Unique players to display:', displayPlayers.length, displayPlayers.map(p => p.username));
            
            let html = '';
            displayPlayers.forEach((player, index) => {
                // Check for score increase
                const prevScore = previousScores.get(index) || 0;
                const currScore = player.points || 0;
                if (currScore > prevScore) {
                    const gained = currScore - prevScore;
                    animateScoreChange(index, currScore, gained);
                }
                previousScores.set(index, currScore);
                
                const isActive = index === gameState.currentTurn;
                html += `
                    <div class="player-card ${isActive ? 'active' : ''}">
                        <div class="player-info">
                            <div class="player-avatar" style="background: ${getColorGradient(player.color)}">
                                ${player.username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div class="font-semibold">${player.username}</div>
                                <div class="text-xs opacity-70">${getPlayerLabel(index, maxPlayers)}</div>
                            </div>
                        </div>
                        <div class="player-score">Score: ${player.points}</div>
                    </div>
                `;
            });

            panel.innerHTML = html;
            const playersCountEl = document.getElementById('playersCount');
            if (playersCountEl) {
                // Count only real players (not bots) for the waiting screen
                const realPlayersCount = displayPlayers.filter(p => !p.isBot).length;
                playersCountEl.textContent = realPlayersCount || displayPlayers.length;
            }
        }

        // Get Player Label
        function getPlayerLabel(index, maxPlayers) {
            const labels = ['First Mover', 'Second Mover', 'Third Mover', 'Fourth Mover'];
            return labels[index] || `Player ${index + 1}`;
        }

        // Get Color Gradient
        function getColorGradient(color) {
            const gradients = {
                red: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                blue: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
                green: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                yellow: 'linear-gradient(135deg, #eab308 0%, #ca8a04 100%)'
            };
            return gradients[color] || gradients.red;
        }

        // Initialize Board with SVG
        function initializeBoard() {
            const board = document.getElementById('gameBoard');
            if (!board) return;
            
            // Create SVG container and pawns container
            const html = `
                <div class="board-svg-container">
                    <img src="assets/images/pawns/ludo-board.svg" 
                         alt="Ludo Board" 
                         class="board-svg" 
                         id="boardSvg">
                    <div class="board-highlights" id="boardHighlights"></div>
                    <div class="pawns-container" id="pawnsContainer"></div>
                </div>
            `;
            
            board.innerHTML = html;
            
            // Render highlights after SVG is loaded
            setTimeout(() => {
                renderBoardHighlights();
            }, 200);
        }

        // Render safe spots and blockade indicators on the board
        function renderBoardHighlights() {
            const highlightsLayer = document.getElementById('boardHighlights');
            const svg = document.getElementById('boardSvg');
            if (!highlightsLayer || !svg) return;
            
            // Clear existing highlights
            highlightsLayer.innerHTML = '';
            
            // Safe spots positions (in the path arrays)
            const safeSpots = [1, 9, 14, 22, 27, 35, 40, 48, 52];
            
            // Get board dimensions
            const svgRect = svg.getBoundingClientRect();
            const container = document.querySelector('.board-svg-container');
            const containerRect = container.getBoundingClientRect();
            const scaleX = containerRect.width / svgRect.width;
            const scaleY = containerRect.height / svgRect.height;
            
            const cell = 30;
            const boardDims = [[6, 0], [6, 1], [6, 2], [6, 3], [6, 4], [6, 5], [5, 5], [4, 5], [3, 5], [2, 5], [1, 5], [0, 5], [0, 4], [0, 3], [0, 2], [0, 1], [0, 0], [1, 0], [2, 0], [3, 0], [4, 0], [5, 0], [5, 1], [5, 2], [5, 3], [5, 4], [5, 5]];
            
            // Render safe spots
            safeSpots.forEach(pos => {
                if (pos - 1 >= 0 && pos - 1 < boardDims.length) {
                    const dim = boardDims[pos - 1];
                    const x = (dim[1] * cell + cell / 2) * scaleX;
                    const y = (dim[0] * cell + cell / 2) * scaleY;
                    const size = cell * 0.7 * scaleX;
                    
                    const spotlight = document.createElement('div');
                    spotlight.className = 'safe-spot';
                    spotlight.style.left = `${x - size / 2}px`;
                    spotlight.style.top = `${y - size / 2}px`;
                    spotlight.style.width = `${size}px`;
                    spotlight.style.height = `${size}px`;
                    highlightsLayer.appendChild(spotlight);
                }
            });
        }

        // Update blockade indicators based on game state
        function updateBlockadeHighlights() {
            if (!gameState) return;
            
            const highlightsLayer = document.getElementById('boardHighlights');
            if (!highlightsLayer) return;
            
            // Clear existing blockade indicators
            highlightsLayer.querySelectorAll('.blockade-indicator').forEach(el => el.remove());
            
            // Find positions with blockades (2+ pawns of same color)
            const positionMap = new Map();
            
            gameState.players.forEach((player, playerIndex) => {
                if (!player.pawns) return;
                player.pawns.forEach(pawn => {
                    if (pawn.position > 0) { // Ignore home pawns
                        const key = `${pawn.position}`;
                        if (!positionMap.has(key)) {
                            positionMap.set(key, []);
                        }
                        positionMap.get(key).push({ playerIndex, color: player.color });
                    }
                });
            });
            
            // Get board dimensions for positioning
            const svg = document.getElementById('boardSvg');
            if (!svg) return;
            
            const container = document.querySelector('.board-svg-container');
            const containerRect = container.getBoundingClientRect();
            const svgRect = svg.getBoundingClientRect();
            const scaleX = containerRect.width / svgRect.width;
            const scaleY = containerRect.height / svgRect.height;
            
            const cell = 30;
            const boardDims = [[6, 0], [6, 1], [6, 2], [6, 3], [6, 4], [6, 5], [5, 5], [4, 5], [3, 5], [2, 5], [1, 5], [0, 5], [0, 4], [0, 3], [0, 2], [0, 1], [0, 0], [1, 0], [2, 0], [3, 0], [4, 0], [5, 0], [5, 1], [5, 2], [5, 3], [5, 4], [5, 5]];
            
            // Render blockade indicators for positions with 2+ pawns
            positionMap.forEach((pawns, posKey) => {
                if (pawns.length >= 2) {
                    const pos = parseInt(posKey);
                    if (pos - 1 >= 0 && pos - 1 < boardDims.length) {
                        const dim = boardDims[pos - 1];
                        const x = (dim[1] * cell + cell / 2) * scaleX;
                        const y = (dim[0] * cell + cell / 2) * scaleY;
                        const size = cell * 0.85 * scaleX;
                        
                        const blockadeEl = document.createElement('div');
                        blockadeEl.className = 'blockade-indicator';
                        blockadeEl.style.left = `${x - size / 2}px`;
                        blockadeEl.style.top = `${y - size / 2}px`;
                        blockadeEl.style.width = `${size}px`;
                        blockadeEl.style.height = `${size}px`;
                        highlightsLayer.appendChild(blockadeEl);
                    }
                }
            });
        }
        function updateBoard() {
            if (!gameState) {
                console.warn('⚠️ updateBoard: No gameState available');
                return;
            }
            
            if (!gameState.players || gameState.players.length === 0) {
                console.warn('⚠️ updateBoard: No players in gameState');
                return;
            }
            
            console.log(`🔄 updateBoard called with ${gameState.players.length} players (including ${gameState.players.filter(p => p.isBot).length} bots)`);
            
            const board = document.getElementById('gameBoard');
            if (!board) return;
            
            // Initialize board if not already done
            if (!board.querySelector('.board-svg-container')) {
                initializeBoard();
            }
            
            // Wait for SVG to load before positioning pawns
            const svg = document.getElementById('boardSvg');
            const pawnsContainer = document.getElementById('pawnsContainer');
            
            if (!svg || !pawnsContainer) {
                console.warn('⚠️ updateBoard: SVG or pawnsContainer not found');
                return;
            }

            // Inner helper to actually render all pawns once the SVG is ready
            function renderPawns() {
                // Remove all existing pawns
                pawnsContainer.innerHTML = '';
                
                if (!gameState || !gameState.players) {
                    console.warn('⚠️ No game state or players found');
                    return;
                }
                
                console.log(`🎨 Rendering pawns for ${gameState.players.length} players:`, 
                    gameState.players.map(p => ({ 
                        username: p.username, 
                        isBot: p.isBot, 
                        color: p.color,
                        pawnsCount: p.pawns?.length || 0
                    }))
                );
                
                // Render pawns for each player (including bots)
                gameState.players.forEach((player, playerIndex) => {
                    const color = player.color || ['red', 'blue', 'green', 'yellow'][playerIndex];
                    
                    console.log(`  → Rendering ${player.isBot ? 'BOT' : 'PLAYER'}: ${player.username} (${color}), pawns:`, player.pawns);
                    
                    // Always render all 4 pawns for each player
                    const pawnCount = player.pawns && Array.isArray(player.pawns) ? player.pawns.length : 4;
                    for (let pawnIndex = 0; pawnIndex < pawnCount; pawnIndex++) {
                        const pawn = player.pawns?.[pawnIndex];
                        // Use pawn position if available, otherwise assume it's in home (position < 0)
                        // For initial display, if position is 0 and game hasn't started, treat as home
                        let pawnPosition = (pawn && pawn.position !== undefined && pawn.position !== null) 
                            ? pawn.position 
                            : -1; // -1 means in home
                        
                        // Keep home pawns in the home area regardless of game state
                        if (pawnPosition < 0) {
                            pawnPosition = -1;
                        }
                        
                        // Convert position to pixel coordinates on the SVG
                        const { x, y } = getStackedCoordinates(pawnPosition, playerIndex, pawnIndex);
                        renderPawn(x, y, color, pawnIndex, player.userId, playerIndex);
                        lastPawnPositions.set(getPawnKey(playerIndex, pawnIndex), pawnPosition);
                    }
                });
            }
            
            // If SVG is already loaded, render immediately
            if (svg.complete && svg.naturalHeight !== 0) {
                renderPawns();
                updateBlockadeHighlights();
            } else {
                // Wait for SVG to load
                svg.onload = () => {
                    renderPawns();
                    updateBlockadeHighlights();
                };
            }
        }

        // Convert position number to pixel coordinates on the SVG board
        // The SVG is 1000x1000 viewBox, so we'll map positions to SVG pixels
        function positionToPixelCoordinates(position, playerIndex, pawnIndex) {
            const svg = document.getElementById('boardSvg');
            if (!svg) return { x: 50, y: 50 };
            
            // Get board dimensions
            const { width, height, offsetX, offsetY } = getBoardMetrics();
            
            // Position < 0 means pawn is in home area (hasn't started)
            // Position 0 means pawn is at starting position on the path (just entered)
            // For initial display, if position is 0 and game hasn't started, show in home
            // Also check if pawn.isHome flag is set
            const pawn = gameState?.players?.[playerIndex]?.pawns?.[pawnIndex];
            const isInHome = position < 0 || (pawn && pawn.isHome);
            
            if (isInHome) {
                const pos = getHomePosition(playerIndex, pawnIndex, width, height);
                return { x: pos.x + offsetX, y: pos.y + offsetY };
            }
            
            // Position 0-51 is on the main path (circular path)
            // Position 52+ is in the home stretch (final path to center)
            if (position <= 52) {
                const pos = getMainPathPosition(position, playerIndex, width, height);
                return { x: pos.x + offsetX, y: pos.y + offsetY };
            } else {
                const pos = getHomeStretchPosition(position, playerIndex, width, height);
                return { x: pos.x + offsetX, y: pos.y + offsetY };
            }
        }

        function getBoardMetrics() {
            const svg = document.getElementById('boardSvg');
            const board = document.getElementById('gameBoard');
            if (!svg || !board) {
                return { width: 1000, height: 1000, offsetX: 0, offsetY: 0, cell: 62.08789 };
            }
            const svgRect = svg.getBoundingClientRect();
            const boardRect = board.getBoundingClientRect();
            const cell = (62.08789 / 1000) * svgRect.width;
            board.style.setProperty('--cell', `${cell}px`);
            return {
                width: svgRect.width,
                height: svgRect.height,
                offsetX: svgRect.left - boardRect.left,
                offsetY: svgRect.top - boardRect.top,
                cell
            };
        }
        
        // Get position in home area (when pawn hasn't started)
        // Returns SVG viewBox coordinates (not pixel coordinates - offsets are added by positionToPixelCoordinates)
        function getHomePosition(playerIndex, pawnIndex, boardWidth, boardHeight) {
            const cell = 62.08789;
            const scaleX = boardWidth / 1000;
            const scaleY = boardHeight / 1000;
            const spawnNudgeX = cell * 0.1;
            const spawnNudgeY = cell * 0.08;

            const homeGrid = [
                // Red (bottom-left)
                [[11, 2], [11, 3], [12, 2], [12, 3]],
                // Blue (bottom-right)
                [[11, 11], [11, 12], [12, 11], [12, 12]],
                // Green (top-left)
                [[2, 2], [2, 3], [3, 2], [3, 3]],
                // Yellow (top-right)
                [[2, 11], [2, 12], [3, 11], [3, 12]]
            ];

            const pos = homeGrid[playerIndex]?.[pawnIndex] || [7, 7];
            const row = pos[0];
            const col = pos[1];
            return {
                x: Math.round((col * cell + cell / 2 + spawnNudgeX) * scaleX),
                y: Math.round((row * cell + cell / 2 + spawnNudgeY) * scaleY)
            };
        }
        
        // Get position on main path (positions 1-52)
        // NOTE: position is already an absolute index around the shared track:
        // 1 = red start, 14 = green start, 27 = yellow start, 40 = blue start.
        // We do NOT apply any extra color-based offset here; doing so would spawn
        // colors onto each other's starting squares.
        function getMainPathCoords(position, playerColor, boardWidth, boardHeight) {
            const cell = 62.08789;
            const scaleX = boardWidth / 1000;
            const scaleY = boardHeight / 1000;

            const path = [
                [14, 6], [13, 6], [12, 6], [11, 6], [10, 6], [9, 6],
                [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0],
                [7, 0], [6, 0], [6, 1], [6, 2], [6, 3], [6, 4],
                [6, 5], [5, 6], [4, 6], [3, 6], [2, 6], [1, 6],
                [0, 6], [0, 7], [0, 8], [1, 8], [2, 8], [3, 8],
                [4, 8], [5, 8], [6, 9], [6, 10], [6, 11], [6, 12],
                [6, 13], [6, 14], [7, 14], [8, 14], [8, 13], [8, 12],
                [8, 11], [8, 10], [8, 9], [9, 8], [10, 8], [11, 8],
                [12, 8], [13, 8], [14, 8], [14, 7]
            ];

            // Position 1 should map to index 0 in the path array, 2 → 1, ... 52 → 51
            const adjustedPosition = (position - 1 + 52) % 52;
            const cellPos = path[adjustedPosition];

            if (!cellPos) {
                return { x: boardWidth / 2, y: boardHeight / 2 };
            }

            const row = cellPos[0];
            const col = cellPos[1];
            const x = Math.round((col * cell + cell / 2) * scaleX);
            const y = Math.round((row * cell + cell / 2) * scaleY);
            return { x, y };
        }

        function getMainPathPosition(position, playerIndex, boardWidth, boardHeight) {
            const playerColor = gameState?.players?.[playerIndex]?.color || 'red';
            return getMainPathCoords(position, playerColor, boardWidth, boardHeight);
        }
        
        // Get position in home stretch (final path to center, positions 52+)
        // Returns SVG viewBox coordinates (not pixel coordinates - offsets are added by positionToPixelCoordinates)
        function getHomeStretchPosition(position, playerIndex, boardWidth, boardHeight) {
            const cell = 62.08789;
            const scaleX = boardWidth / 1000;
            const scaleY = boardHeight / 1000;
            const stretchIndex = position - 53;

            const stretches = {
                red: [
                    [13, 6], [12, 6], [11, 6], [10, 6], [9, 6], [8, 6]
                ],
                green: [
                    [6, 1], [6, 2], [6, 3], [6, 4], [6, 5], [6, 6]
                ],
                yellow: [
                    [1, 7], [2, 7], [3, 7], [4, 7], [5, 7], [6, 7]
                ],
                blue: [
                    [7, 13], [7, 12], [7, 11], [7, 10], [7, 9], [7, 8]
                ]
            };

            const playerColor = gameState?.players?.[playerIndex]?.color || 'red';
            const pos = stretches[playerColor]?.[stretchIndex];

            if (!pos) {
                return { x: boardWidth / 2, y: boardHeight / 2 };
            }

            const row = pos[0];
            const col = pos[1];
            const x = Math.round((col * cell + cell / 2) * scaleX);
            const y = Math.round((row * cell + cell / 2) * scaleY);
            return { x, y };
        }

        // Render a pawn on the board at pixel coordinates
        function renderPawn(x, y, color, pawnIndex, pawnUserId, playerIndex) {
            const pawnsContainer = document.getElementById('pawnsContainer');
            if (!pawnsContainer) return;
            
            const pawn = document.createElement('div');
            pawn.className = `pawn ${color}`;
            pawn.id = `pawn-${pawnUserId}-${pawnIndex}`;
            pawn.dataset.playerIndex = playerIndex;
            pawn.dataset.pawnIndex = pawnIndex;
            pawn.dataset.userId = pawnUserId;
            
            // Position the pawn absolutely
            pawn.style.left = `${x}px`;
            pawn.style.top = `${y}px`;
            
            // Try to use pawn image if available, otherwise use colored circle with number
            const pawnImageUrl = `assets/images/pawns/${color}-pawn.png`;
            const img = document.createElement('img');
            img.src = pawnImageUrl;
            img.alt = `${color} pawn ${pawnIndex + 1}`;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'contain';
            
            // If image fails to load, show number as fallback
            img.onerror = function() {
                pawn.textContent = pawnIndex + 1;
                pawn.style.display = 'flex';
                pawn.style.alignItems = 'center';
                pawn.style.justifyContent = 'center';
            };
            
            pawn.appendChild(img);
            
            // Check if this pawn can be moved (it's the player's turn and dice is rolled and move is valid)
            const currentTurnPlayer = gameState.players?.[gameState.currentTurn];
            const isMyTurn = currentTurnPlayer && (currentTurnPlayer.userId == userId);
            const isMyPawn = (pawnUserId == userId || pawnUserId === userId);
            const hasDiceValue = gameState.diceValue !== null;
            const isValidMove = validMoves.some(move => move.pawnIndex === pawnIndex);

            console.log(`Pawn ${pawnIndex} for player ${playerIndex}: isMyTurn=${isMyTurn}, isMyPawn=${isMyPawn}, hasDice=${hasDiceValue}, isValid=${isValidMove}`);

            // Highlight your pawns when it's your turn (even before rolling)
            if (isMyTurn && isMyPawn) {
                pawn.classList.add('turn-highlight');
            }

            if (isMyTurn && isMyPawn && hasDiceValue && isValidMove) {
                // This pawn has valid moves
                pawn.classList.add('selectable');
                pawn.style.cursor = 'pointer';
                pawn.onclick = () => {
                    console.log('Clicked pawn', pawnIndex);
                    movePawn(pawnIndex);
                };
            }
            
            pawnsContainer.appendChild(pawn);
        }

        function getPawnKey(playerIndex, pawnIndex) {
            return `${playerIndex}-${pawnIndex}`;
        }

        function getStackIndex(position, playerIndex, pawnIndex) {
            if (!gameState || !gameState.players || position <= 0) return 0;
            const same = [];
            gameState.players.forEach((player, pi) => {
                player.pawns?.forEach((pawn, pj) => {
                    const pos = pawn?.position ?? 0;
                    if (pos === position) {
                        same.push({ pi, pj });
                    }
                });
            });
            same.sort((a, b) => (a.pi - b.pi) || (a.pj - b.pj));
            const idx = same.findIndex(item => item.pi === playerIndex && item.pj === pawnIndex);
            return idx === -1 ? 0 : idx;
        }

        function applyStackOffset(x, y, stackIndex) {
            const { cell } = getBoardMetrics();
            const offsetStep = Math.max(3, cell * 0.1);
            const offsets = [
                [0, 0],
                [-offsetStep, -offsetStep],
                [offsetStep, -offsetStep],
                [-offsetStep, offsetStep],
                [offsetStep, offsetStep]
            ];
            if (stackIndex < offsets.length) {
                const offset = offsets[stackIndex];
                return { x: x + offset[0], y: y + offset[1] };
            }
            const angle = (stackIndex - offsets.length) * (Math.PI / 3);
            const radius = Math.max(4, cell * 0.12);
            return { x: x + Math.cos(angle) * radius, y: y + Math.sin(angle) * radius };
        }

        function getStackedCoordinates(position, playerIndex, pawnIndex) {
            const base = positionToPixelCoordinates(position, playerIndex, pawnIndex);
            if (position <= 0) {
                return base;
            }
            const stackIndex = getStackIndex(position, playerIndex, pawnIndex);
            return applyStackOffset(base.x, base.y, stackIndex);
        }

        function animatePawnStepwise(playerIndex, pawnIndex, fromPos, toPos) {
            if (!Number.isInteger(fromPos) || !Number.isInteger(toPos)) {
                console.error(`❌ animatePawnStepwise: Invalid positions - fromPos=${fromPos} (${typeof fromPos}), toPos=${toPos} (${typeof toPos})`);
                return;
            }
            const player = gameState?.players?.[playerIndex];
            if (!player) {
                console.error(`❌ Player not found at index ${playerIndex}`);
                return;
            }
            const pawnEl = document.getElementById(`pawn-${player.userId}-${pawnIndex}`);
            if (!pawnEl) {
                console.error(`❌ Pawn element not found: pawn-${player.userId}-${pawnIndex}`);
                return;
            }

            const key = getPawnKey(playerIndex, pawnIndex);
            if (activePawnAnimations.has(key)) {
                clearInterval(activePawnAnimations.get(key));
                activePawnAnimations.delete(key);
            }

            console.log(`🎬 Starting pawn animation: pawn=${pawnIndex}, from=${fromPos} → to=${toPos}, distance=${toPos - fromPos}`);

            // Direct jump animation from home (fromPos === 0) to start position (>0)
            if (fromPos === 0 && toPos > 0) {
                console.log(`🏠 Home jump: 0 → ${toPos}`);
                const startCoords = getStackedCoordinates(fromPos, playerIndex, pawnIndex);
                const endCoords = getStackedCoordinates(toPos, playerIndex, pawnIndex);
                pawnEl.style.left = `${startCoords.x}px`;
                pawnEl.style.top = `${startCoords.y}px`;
                pawnEl.classList.add('moving');
                setTimeout(() => {
                    pawnEl.style.left = `${endCoords.x}px`;
                    pawnEl.style.top = `${endCoords.y}px`;
                    pawnEl.classList.remove('moving');
                    console.log(`✅ Home jump complete`);
                }, 350); // Animation duration
                return;
            }

            if (toPos <= fromPos) {
                console.warn(`⚠️ Invalid move: toPos (${toPos}) <= fromPos (${fromPos})`);
                return;
            }

            const baseCoords = getStackedCoordinates(fromPos, playerIndex, pawnIndex);
            pawnEl.style.left = `${baseCoords.x}px`;
            pawnEl.style.top = `${baseCoords.y}px`;
            pawnEl.classList.add('moving');

            let current = fromPos;
            let stepCount = 0;
            const stepDuration = 220;

            const animateStep = () => {
                if (current >= toPos) {
                    activePawnAnimations.delete(key);
                    pawnEl.classList.remove('moving');
                    console.log(`✅ Animation complete: ${fromPos} → ${toPos} (${stepCount} steps)`);
                    return;
                }
                const startPos = current;
                const endPos = current + 1;
                const start = getStackedCoordinates(startPos, playerIndex, pawnIndex);
                const end = getStackedCoordinates(endPos, playerIndex, pawnIndex);
                const startTime = performance.now();
                stepCount++;

                console.log(`  Step ${stepCount}: ${startPos} → ${endPos}`);

                const tick = (now) => {
                    const t = Math.min(1, (now - startTime) / stepDuration);
                    const ease = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                    const x = start.x + (end.x - start.x) * ease;
                    const y = start.y + (end.y - start.y) * ease;
                    pawnEl.style.left = `${x}px`;
                    pawnEl.style.top = `${y}px`;
                    if (t < 1) {
                        const rafId = requestAnimationFrame(tick);
                        activePawnAnimations.set(key, rafId);
                    } else {
                        current = endPos;
                        animateStep();
                    }
                };

                const rafId = requestAnimationFrame(tick);
                activePawnAnimations.set(key, rafId);
            };

            animateStep();
        }

        // Update Dice Button
        function updateDiceButton() {
            if (!gameState) return;
            setDiceButtonLoading(false);

            const btn = document.getElementById('rollDiceBtn');
            const status = document.getElementById('diceStatus');
            const currentPlayer = gameState.players[gameState.currentTurn];

            // Use loose comparison for userId (handles string/number mismatch)
            const isMyTurn = currentPlayer && (currentPlayer.userId == userId || currentPlayer.userId === userId);
            if (isSpectator) {
                btn.disabled = true;
                btn.dataset.value = "0";
                btn.classList.remove('glow');
                status.textContent = 'Spectator mode';
                return;
            }
            
            if (isMyTurn) {
                if (gameState.diceValue === null) {
                    if (gameState.gameStatus === 'final_moves') {
                        btn.disabled = true;
                        status.textContent = 'Final move rolling...';
                        btn.dataset.value = "0";
                        btn.classList.remove('glow');
                        return;
                    }
                    btn.disabled = false;
                    status.textContent = 'Your turn! Roll the dice';
                    btn.dataset.value = "0";
                    btn.classList.add('glow');
                } else {
                    btn.disabled = true;
                    status.textContent = 'Select a pawn to move';
                    btn.dataset.value = String(gameState.diceValue);
                    btn.classList.remove('glow');
                }
            } else {
                btn.disabled = true;
                status.textContent = `Waiting for ${currentPlayer?.username || 'player'}...`;
                if (gameState.diceValue === null) {
                    btn.dataset.value = "0";
                }
                btn.classList.remove('glow');
            }
        }

        // Update Current Turn Display
        function updateCurrentTurn() {
            if (!gameState) return;

            const display = document.getElementById('currentTurnDisplay');
            const currentPlayer = gameState.players[gameState.currentTurn];
            const avatar = document.querySelector('.turn-avatar');

            if (currentPlayer) {
                display.style.display = 'flex';
                document.getElementById('currentPlayerName').textContent = currentPlayer.username;
                if (avatar) {
                    avatar.textContent = (currentPlayer.username || 'P').charAt(0).toUpperCase();
                }
                
                if (gameState.diceValue) {
                    document.getElementById('currentDice').textContent = gameState.diceValue;
                } else {
                    document.getElementById('currentDice').textContent = '-';
                }
            } else {
                display.style.display = 'none';
            }
        }

        // Update Dice Display
        function updateDiceDisplay(value, playerId) {
            const btn = document.getElementById('rollDiceBtn');
            const valueEl = document.getElementById('diceValue');
            btn.classList.remove('rolling');
            btn.dataset.value = String(value);
            stopDiceAnimation(value);
            
            if (playerId == userId || playerId === userId) {
                if (valueEl) {
                    valueEl.textContent = value;
                } else {
                    btn.textContent = value;
                }
            }
        }

        // Update Wait Countdown
        function updateWaitCountdown(remaining, total) {
            const waitingScreen = document.getElementById('waitingScreen');
            const waitingText = document.getElementById('waitingText');
            
            if (waitingText) {
                const percentage = Math.round((total - remaining) / total * 100);
                waitingText.innerHTML = `
                    <p>Waiting for players... ${remaining}s</p>
                    <div class="w-full bg-white/20 rounded-full h-2 mt-2">
                        <div class="bg-p2 h-2 rounded-full transition-all" style="width: ${percentage}%"></div>
                    </div>
                `;
            }
        }

        // Show Game Finished
        function showGameFinished(ranking) {
            const modal = document.getElementById('gameFinishedModal');
            const list = document.getElementById('rankingList');
            const winnerBanner = document.getElementById('winnerBanner');

            let html = '';
            ranking.forEach((player, index) => {
                const isWinner = index === 0;
                html += `
                    <div class="ranking-item ${isWinner ? 'first' : ''}">
                        <div>
                            <div class="font-bold">${index + 1}. ${player.username}</div>
                            <div class="text-sm opacity-70">${player.points} points</div>
                            ${player.isBot ? '<div class="text-xs opacity-50 italic">Bot</div>' : ''}
                        </div>
                        ${isWinner ? '<div class="text-2xl">🏆</div>' : ''}
                    </div>
                `;
            });

            list.innerHTML = html;
            if (winnerBanner && ranking.length > 0) {
                const winner = ranking[0];
                const winnerName = winner?.username || 'Player';
                const prizeText = winner?.isBot ? 'Bot winner (no prize credited)' : 'Prize credited to winner account';
                winnerBanner.innerHTML = `Winner: ${winnerName}<span>${prizeText}</span>`;
            }
            modal.classList.add('show');
            
            // Update virtual table status if using virtual tables
            if (virtualTableId) {
                console.log('Game finished for virtual table:', virtualTableId);
            }
        }

        // Reconnect to active virtual table on page load
        async function checkActiveVirtualTable() {
            try {
                const response = await fetch(`api/tables/get-active-virtual-table.php?table_id=${tableId}`);
                const data = await response.json();
                
                if (data.success && data.virtualTableId) {
                    virtualTableId = data.virtualTableId;
                    console.log('✅ Found active virtual table:', virtualTableId);
                    return true;
                }
            } catch (error) {
                console.error('Error checking active virtual table:', error);
            }
            return false;
        }

        // Request game state (for reconnection)
        function requestGameState() {
            if (!socket) return;
            
            if (virtualTableId) {
                socket.emit('get_game_state', { 
                    virtualTableId: virtualTableId,
                    tableId: tableId 
                });
            } else {
                socket.emit('get_game_state', { tableId: tableId });
            }
        }

        function requestValidMovesIfNeeded() {
            if (isSpectator || !socket || !gameState) return;
            if (gameState.diceValue === null || gameState.diceValue === undefined) return;
            if (myPlayerIndex !== gameState.currentTurn) return;
            const key = `${gameState.currentTurn}-${gameState.diceValue}`;
            if (lastValidMovesKey === key && validMoves.length) return;
            lastValidMovesKey = key;
            socket.emit('get_valid_moves', {
                virtualTableId: virtualTableId,
                tableId: tableId
            });
        }

        function startTimerSync() {
            if (timerSyncInterval) return;
            timerSyncInterval = setInterval(() => {
                if (!socket || !virtualTableId || !gameState) return;
                if (gameState.gameStatus !== 'running' && gameState.gameStatus !== 'playing' && gameState.gameStatus !== 'final_moves') {
                    return;
                }
                requestGameState();
            }, 5000);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', async () => {
            // Initialize the board first
            initializeBoard();
            document.body.addEventListener('click', unlockAudio, { once: true });
            
            const hasVirtualTable = await checkActiveVirtualTable();
            initSocket();
            
            // Request game state after connection if we have virtual table
            if (hasVirtualTable) {
                socket.on('connect', () => {
                console.log('Connected to WebSocket server');
                if (isSpectator) {
                    const banner = document.getElementById('spectatorBanner');
                    if (banner) {
                        banner.style.display = 'block';
                    }
                    joinSpectator();
                } else {
                    joinTable();
                }
                startTimerSync();
            });
            }
        });
    </script>
</body>
</html>






