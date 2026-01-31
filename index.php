<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: home.php'); // ya jo aapka dashboard hai
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="assets/css/swiper.min.css" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="manifest" href="manifest.json" />
    <title>Ludo Arena - Sign In</title>
    <link href="style.css" rel="stylesheet">
    <style>
        :root {
            --ink-900: #0e1930;
            --ink-700: #1f2c46;
            --ink-500: #3a4b69;
            --card: #f7f9ff;
            --brand: #2d6cdf;
            --brand-2: #1fcaa4;
            --gold: #f6b73c;
            --stroke: rgba(14, 25, 48, 0.12);
            --shadow: 0 30px 70px rgba(4, 14, 38, 0.25);
        }

        body.login-page {
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background: radial-gradient(1200px 600px at 70% -10%, #3a6de3 0%, rgba(58, 109, 227, 0) 60%),
                radial-gradient(900px 600px at -10% 20%, #19d6b0 0%, rgba(25, 214, 176, 0) 55%),
                linear-gradient(180deg, #0b1430 0%, #0b1e3d 55%, #0a1733 100%);
            color: var(--card);
            min-height: 100vh;
        }

        .login-wrap {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 36px;
            padding: 40px 24px 48px;
            max-width: 1080px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            min-height: calc(100vh - 64px);
            align-items: center;
        }

        .login-hero {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 12px 6px;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .hero-title {
            font-family: "Georgia", "Times New Roman", serif;
            font-size: clamp(30px, 5vw, 44px);
            line-height: 1.1;
            margin: 0;
            max-width: 520px;
        }

        .hero-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 15px;
            line-height: 1.6;
            max-width: 420px;
        }

        .hero-features {
            display: grid;
            gap: 12px;
        }

        .hero-feature {
            display: flex;
            gap: 12px;
            align-items: center;
            background: rgba(9, 20, 45, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 12px 14px;
        }

        .hero-feature i {
            color: var(--gold);
            font-size: 18px;
        }

        .login-card {
            background: var(--card);
            color: var(--ink-900);
            border-radius: 26px;
            padding: 28px 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.4);
            position: relative;
            overflow: hidden;
            max-width: 440px;
            justify-self: center;
        }

        .login-card::before {
            content: "";
            position: absolute;
            inset: -40% auto auto -30%;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(45, 108, 223, 0.25) 0%, rgba(45, 108, 223, 0) 70%);
        }

        .tab-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #eef2ff;
            border-radius: 14px;
            padding: 6px;
            gap: 6px;
        }

        .tab-row a {
            text-align: center;
            padding: 10px 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            color: var(--ink-500);
            background: transparent;
        }

        .tab-row a.active {
            background: white;
            color: var(--ink-900);
            box-shadow: 0 8px 18px rgba(10, 30, 75, 0.12);
        }

        .field {
            display: grid;
            gap: 8px;
            margin-top: 18px;
        }

        .field label {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink-700);
        }

        .input-shell {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--stroke);
            background: #fff;
            box-shadow: inset 0 1px 2px rgba(10, 20, 40, 0.06);
        }

        .input-shell input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            background: transparent;
            color: var(--ink-900);
        }

        .input-shell i {
            color: var(--brand);
        }

        .login-btn {
            margin-top: 26px;
            width: 100%;
            padding: 12px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--brand), #4b88ff);
            color: white;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: none;
            box-shadow: 0 14px 30px rgba(45, 108, 223, 0.35);
        }

        .login-footer {
            text-align: center;
            color: rgba(255, 255, 255, 0.75);
            font-size: 13px;
            padding-bottom: 24px;
        }

        .login-footer a {
            color: var(--gold);
            font-weight: 700;
        }

        .reveal {
            opacity: 0;
            transform: translateY(14px);
            animation: reveal 700ms ease-out forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        @keyframes reveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-page::before {
            content: "";
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 45%),
                radial-gradient(circle at 80% 10%, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0) 35%),
                repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.04) 0px, rgba(255, 255, 255, 0.04) 1px, transparent 1px, transparent 14px);
            opacity: 0.5;
            pointer-events: none;
        }

        /* Dice hero animation */
        .dice-orbit {
            position: relative;
            width: 160px;
            height: 160px;
            margin-top: 18px;
        }

        .dice-orbit-inner {
            position: absolute;
            inset: 0;
            border-radius: 24px;
            background: radial-gradient(circle at 30% 20%, #ffffff 0%, #ffd36a 26%, #f59e0b 60%, #92400e 100%);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
            transform-style: preserve-3d;
            animation: diceSpin 2800ms ease-in-out infinite;
        }

        .dice-orbit-inner::before,
        .dice-orbit-inner::after {
            content: "";
            position: absolute;
            inset: 12px;
            border-radius: 20px;
            background: #fefce8;
            box-shadow: inset 0 0 0 3px rgba(0, 0, 0, 0.12);
        }

        .dice-pip {
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #111827;
            box-shadow: 0 1px 1px rgba(255, 255, 255, 0.3);
        }

        .pip-1 { top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .pip-2 { top: 24%; left: 24%; }
        .pip-3 { bottom: 24%; right: 24%; }
        .pip-4 { top: 24%; right: 24%; }
        .pip-5 { bottom: 24%; left: 24%; }

        .dice-trail {
            position: absolute;
            inset: -10px;
            border-radius: 24px;
            background: radial-gradient(circle at 10% 10%, rgba(250, 204, 21, 0.4) 0%, transparent 60%);
            opacity: 0;
            animation: diceTrail 2800ms ease-out infinite;
        }

        @keyframes diceSpin {
            0%   { transform: rotate3d(1, 1, 0, 0deg); }
            35%  { transform: rotate3d(1, 1, 0, 220deg); }
            55%  { transform: rotate3d(1, 0.3, 0, 260deg); }
            80%  { transform: rotate3d(0.2, 1, 0, 330deg); }
            100% { transform: rotate3d(1, 1, 0, 360deg); }
        }

        @keyframes diceTrail {
            0%   { opacity: 0; transform: scale(0.9); }
            25%  { opacity: 1; transform: scale(1); }
            60%  { opacity: 0.4; transform: scale(1.05); }
            100% { opacity: 0; transform: scale(1.1); }
        }

        @media (max-width: 720px) {
            .login-wrap {
                min-height: auto;
                padding: 24px 16px 32px;
                grid-template-columns: 1fr;
                gap: 22px;
            }

            .login-hero {
                text-align: center;
                align-items: center;
                order: 2;
            }

            .hero-title {
                font-size: clamp(26px, 6vw, 32px);
            }

            .hero-subtitle {
                font-size: 14px;
                max-width: 100%;
            }

            .hero-features {
                width: 100%;
                gap: 10px;
            }

            .login-card {
                width: 100%;
                max-width: 420px;
                padding: 20px 18px;
                box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
                order: 1;
            }

            .tab-row a {
                font-size: 13px;
                padding: 9px 8px;
            }

            .login-btn {
                margin-top: 20px;
                padding: 12px 14px;
            }

            .dice-orbit {
                width: 120px;
                height: 120px;
                margin: 10px auto 0;
            }
        }
    </style>
</head>
<body class="login-page">
    <main class="login-wrap">
        <section class="login-hero reveal delay-1">
            <span class="hero-chip">
                <i class="ph ph-shield-check"></i>
                Real-time Ludo battles
            </span>
            <h1 class="hero-title">Roll the dice. Rule the board.</h1>
            <p class="hero-subtitle">
                Sign in to jump back into your Ludo match, track scores, and enjoy fair, fast multiplayer games with live dice.
            </p>
            <div class="dice-orbit" aria-hidden="true">
                <div class="dice-trail"></div>
                <div class="dice-orbit-inner">
                    <span class="dice-pip pip-1"></span>
                    <span class="dice-pip pip-2"></span>
                    <span class="dice-pip pip-3"></span>
                    <span class="dice-pip pip-4"></span>
                    <span class="dice-pip pip-5"></span>
                </div>
            </div>
            <div class="hero-features">
                <div class="hero-feature">
                    <i class="ph ph-timer"></i>
                    <div>
                        <strong>Live Ludo timers</strong>
                        <div class="hero-subtitle">Every turn, every roll stays in perfect sync.</div>
                    </div>
                </div>
                <div class="hero-feature">
                    <i class="ph ph-sparkle"></i>
                    <div>
                        <strong>Smart scoring</strong>
                        <div class="hero-subtitle">Combos, captures and home runs that feel rewarding.</div>
                    </div>
                </div>
            </div>
        </section>
        <section class="login-card reveal delay-2">
            <form id="loginForm">
                <div class="tab-row">
                    <a href="sign-in.php" class="active">Sign In</a>
                    <a href="sign-up.php">Sign Up</a>
                </div>

                <div class="field">
                    <label for="mobile">Mobile Number</label>
                    <div class="input-shell">
                        <i class="ph ph-phone"></i>
                        <input type="tel" id="mobile" name="mobile" placeholder="Enter Mobile Number" maxlength="10" pattern="[0-9]{10}" required />
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-shell">
                        <i class="ph ph-lock-key"></i>
                        <input type="password" id="password" placeholder="*****" class="passwordField" required />
                        <i class="ph ph-eye-slash passowordShow cursor-pointer"></i>
                    </div>
                </div>

                <button type="submit" class="login-btn">Sign In</button>
            </form>
        </section>
    </main>

    <div class="login-footer reveal delay-3">
        Don't have an account? <a href="sign-up.php">Sign Up</a> here
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const mobile = document.getElementById('mobile').value.trim();
            const password = document.getElementById('password').value;

            const response = await fetch('api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mobile, password })
            });

            const result = await response.json();

            if (result.success) {
                alert('Login Successful!');
                window.location.href = 'home.php'; // ya jo aapka user dashboard hai
            } else {
                alert(result.error || 'Login failed');
            }
        });
    </script>
</body>
</html>
