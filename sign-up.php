<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: home.html'); // ya jo aapka main dashboard hai
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
    <title>Welcome to Goodwin</title>
    <link href="style.css" rel="stylesheet">
</head>
<body class="">
    <div class="container min-h-dvh relative overflow-hidden py-8 px-6 dark:text-white dark:bg-color1">
        <!-- Background Effects -->
        <img src="assets/images/header-bg-2.png" alt="" class="absolute top-0 left-0 right-0 -mt-6" />
        <div class="absolute top-0 left-0 bg-p3 blur-[145px] h-[174px] w-[149px]"></div>
        <div class="absolute top-40 right-0 bg-[#0ABAC9] blur-[150px] h-[174px] w-[91px]"></div>
        <div class="absolute top-80 right-40 bg-p2 blur-[235px] h-[205px] w-[176px]"></div>
        <div class="absolute bottom-0 right-0 bg-p3 blur-[220px] h-[174px] w-[149px]"></div>

        <!-- Page Title -->
        <div class="flex justify-start items-center gap-4 relative z-10">
            <a href="index.php" class="bg-white p-2 rounded-full flex justify-center items-center text-xl dark:bg-color10">
                <i class="ph ph-caret-left"></i>
            </a>
            <h2 class="text-2xl font-semibold text-white">Welcome to Goodwin</h2>
        </div>

        <!-- Sign Up Form -->
        <form id="signupForm" class="relative z-20">
            <div class="bg-white py-8 px-6 rounded-xl mt-12 dark:bg-color10">
                <div class="flex justify-between items-center">
                    <a href="index.php" class="text-center text-xl font-semibold text-bgColor18 border-b-2 pb-2 border-bgColor18 w-full dark:text-color18 dark:border-color18">
                        Sign In
                    </a>
                    <a href="sign-up.php" class="text-center text-xl font-semibold text-p2 border-b-2 pb-2 border-p2 w-full dark:text-p1 dark:border-p1">
                        Sign Up
                    </a>
                </div>

                <!-- First Name -->
                <div class="pt-8">
                    <p class="text-sm font-semibold pb-2">First Name</p>
                    <div class="flex justify-between items-center py-3 px-4 border border-color21 rounded-xl dark:border-color18 gap-3">
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter Name"
                            class="outline-none bg-transparent text-n600 text-sm placeholder:text-sm w-full placeholder:text-bgColor18 dark:text-color18 dark:placeholder:text-color18"
                            required
                        />
                        <i class="ph ph-user text-xl text-bgColor18 !leading-none"></i>
                    </div>
                </div>

                <!-- Mobile Number -->
                <div class="pt-4">
                    <p class="text-sm font-semibold pb-2">Mobile Number</p>
                    <div class="flex justify-between items-center py-3 px-4 border border-color21 rounded-xl dark:border-color18 gap-3">
                        <input
                            type="tel"
                            id="mobile"
                            name="mobile"
                            placeholder="Enter Mobile Number"
                            maxlength="10"
                            pattern="[0-9]{10}"
                            class="outline-none bg-transparent text-n600 text-sm placeholder:text-sm w-full placeholder:text-bgColor18 dark:text-color18 dark:placeholder:text-color18"
                            required
                        />
                        <i class="ph ph-phone text-xl text-bgColor18 !leading-none"></i>
                    </div>
                </div>

                <!-- Password -->
                <div class="pt-4">
                    <p class="text-sm font-semibold pb-2">Password</p>
                    <div class="flex justify-between items-center py-3 px-4 border border-color21 rounded-xl dark:border-color18 gap-3">
                        <input
                            type="password"
                            id="password"
                            placeholder="*****"
                            class="outline-none bg-transparent text-n600 text-sm placeholder:text-sm w-full placeholder:text-bgColor18 dark:text-color18 dark:placeholder:text-color18 passwordField"
                            required
                        />
                        <i class="ph ph-eye-slash text-xl text-bgColor18 !leading-none passowordShow cursor-pointer dark:text-color18"></i>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="pt-4">
                    <p class="text-sm font-semibold pb-2">Confirm Password</p>
                    <div class="flex justify-between items-center py-3 px-4 border border-color21 rounded-xl dark:border-color18 gap-3">
                        <input
                            type="password"
                            id="confirm_password"
                            placeholder="*****"
                            class="outline-none bg-transparent text-n600 text-sm placeholder:text-sm w-full placeholder:text-bgColor18 dark:text-color18 dark:placeholder:text-color18 confirmPasswordField"
                            required
                        />
                        <i class="ph ph-eye-slash text-xl text-bgColor18 !leading-none confirmPasswordShow cursor-pointer dark:text-color18"></i>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button
                type="submit"
                class="bg-p2 rounded-full py-3 text-white text-sm font-semibold text-center block mt-12 dark:bg-p1 w-full"
            >
                Sign Up
            </button>
        </form>

        <div class="relative z-10">
            <p class="text-sm font-semibold text-center pt-5">
                Already have an account?
                <a href="sign-in.php" class="text-p2 dark:text-p1">Sign In</a> here
            </p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/main.js"></script>
    <script>
        document.getElementById('signupForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const mobile = document.getElementById('mobile').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Client-side validation
            if (username.length < 3) {
                alert('Name must be at least 3 characters');
                return;
            }
            if (mobile.length !== 10 || !/^\d{10}$/.test(mobile)) {
                alert('Please enter a valid 10-digit mobile number');
                return;
            }
            if (password.length < 6) {
                alert('Password must be at least 6 characters');
                return;
            }
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }

            // Send to API
            try {
                const response = await fetch('api/auth/register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username,
                        mobile: mobile,
                        password: password
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Registration Successful! Welcome ' + username);
                    window.location.href = 'home.html'; // ya aapka dashboard page
                } else {
                    alert(result.error || 'Registration failed');
                }
            } catch (err) {
                alert('Network error. Please try again.');
                console.error(err);
            }
        });
    </script>
</body>
</html>