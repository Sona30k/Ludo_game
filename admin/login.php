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
</head>
<body class="bg-color1 dark:bg-black min-h-screen flex items-center justify-center">
    <div class="bg-white dark:bg-color10 rounded-2xl p-8 w-96 shadow-lg">
        <h2 class="text-2xl font-bold text-center mb-8 text-p2 dark:text-p1">Admin Login</h2>
        <form id="adminLoginForm">
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2">Mobile</label>
                <input type="tel" id="mobile" maxlength="10" class="w-full px-4 py-3 rounded-xl border border-color21 dark:border-color18" placeholder="9999999999" required />
            </div>
            <div class="mb-8">
                <label class="block text-sm font-semibold mb-2">Password</label>
                <input type="password" id="password" class="w-full px-4 py-3 rounded-xl border border-color21 dark:border-color18" required />
            </div>
            <button type="submit" class="w-full bg-p2 dark:bg-p1 text-white py-3 rounded-xl font-semibold">
                Login
            </button>
        </form>
        <p class="text-center mt-4 text-sm text-color5">Default: 9999999999 / password</p>
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