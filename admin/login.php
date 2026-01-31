<?php
session_start();
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Login</title>
    <link href="../style.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --ink-900: #111827;
            --ink-700: #1f2a44;
            --panel: #f8fafc;
            --brand: #f59e0b;
            --brand-2: #1d4ed8;
            --stroke: rgba(15, 23, 42, 0.15);
            --shadow: 0 28px 60px rgba(10, 17, 32, 0.35);
        }

        body.admin-login {
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background: radial-gradient(900px 500px at 20% -10%, rgba(245, 158, 11, 0.35) 0%, rgba(245, 158, 11, 0) 60%),
                radial-gradient(900px 600px at 110% 20%, rgba(29, 78, 216, 0.5) 0%, rgba(29, 78, 216, 0) 55%),
                linear-gradient(180deg, #0b1120 0%, #0f172a 55%, #0a1020 100%);
            min-height: 100vh;
            display: grid;
            place-items: center;
            color: #e2e8f0;
            padding: 24px;
        }

        .admin-shell {
            width: min(440px, 100%);
            background: var(--panel);
            border-radius: 24px;
            padding: 30px 26px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .admin-shell::after {
            content: "";
            position: absolute;
            inset: auto -30% -45% auto;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(29, 78, 216, 0.2) 0%, rgba(29, 78, 216, 0) 70%);
        }

        .admin-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .admin-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(245, 158, 11, 0.2);
            color: #b45309;
        }

        .admin-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--ink-900);
        }

        .admin-subtitle {
            font-size: 13px;
            color: #475569;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-top: 16px;
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
            box-shadow: inset 0 1px 2px rgba(10, 20, 40, 0.08);
        }

        .input-shell input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            background: transparent;
            color: var(--ink-900);
        }

        .admin-btn {
            margin-top: 22px;
            width: 100%;
            padding: 12px 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--brand), #fbbf24);
            color: #1f2937;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: none;
            box-shadow: 0 14px 26px rgba(245, 158, 11, 0.35);
        }

        .admin-note {
            margin-top: 16px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }

        .reveal {
            opacity: 0;
            transform: translateY(10px);
            animation: reveal 650ms ease-out forwards;
        }

        @keyframes reveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="admin-login">
    <div class="admin-shell reveal">
        <div class="admin-header">
            <div class="admin-badge">
                <i class="ph ph-shield-star"></i>
            </div>
            <div>
                <div class="admin-title">Admin Access</div>
                <div class="admin-subtitle">Secure console for staff only.</div>
            </div>
        </div>

        <form id="adminLoginForm">
            <div class="field">
                <label for="mobile">Mobile</label>
                <div class="input-shell">
                    <i class="ph ph-phone"></i>
                    <input type="tel" id="mobile" maxlength="10" placeholder="9999999999" required />
                </div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="input-shell">
                    <i class="ph ph-lock-key"></i>
                    <input type="password" id="password" required />
                </div>
            </div>
            <button type="submit" class="admin-btn">Login</button>
        </form>
        <p class="admin-note">Default: 9999999999 / password</p>
    </div>

    <script>
        document.getElementById('adminLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const mobile = document.getElementById('mobile').value;
            const password = document.getElementById('password').value;

            const res = await fetch('../api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mobile, password })
            });
            const data = await res.json();

            if (data.success && data.user.role === 'admin') {
                window.location.href = 'dashboard.php';
            } else {
                alert(data.error || 'Admin access denied');
            }
        });
    </script>
</body>
</html>
