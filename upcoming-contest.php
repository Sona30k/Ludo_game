<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="manifest" href="manifest.json" />
    <title>Upcoming Contest</title>
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container min-h-dvh relative overflow-hidden py-8 dark:text-white dark:bg-color1">
        <!-- Background -->
        <img src="assets/images/header-bg-1.png" alt="" class="absolute top-0 left-0 right-0 -mt-16" />
        <div class="absolute top-0 left-0 bg-p3 blur-[145px] h-[174px] w-[149px]"></div>
        <div class="absolute top-40 right-0 bg-[#0ABAC9] blur-[150px] h-[174px] w-[91px]"></div>
        <div class="absolute top-80 right-40 bg-p2 blur-[235px] h-[205px] w-[176px]"></div>
        <div class="absolute bottom-0 right-0 bg-p3 blur-[220px] h-[174px] w-[149px]"></div>

        <!-- Header -->
        <div class="relative z-10">
            <div class="flex justify-between items-center gap-4 px-6">
                <div class="flex justify-start items-center gap-4">
                    <a href="home.php" class="bg-white size-8 rounded-full flex justify-center items-center text-xl dark:bg-color10">
                        <i class="ph ph-caret-left"></i>
                    </a>
                    <h2 class="text-2xl font-semibold text-white">Upcoming Contest</h2>
                </div>
            </div>

            <!-- Search -->
            <div class="flex justify-between items-center gap-3 pt-12 px-6">
                <a href="#" class="flex justify-start items-center gap-3 bg-color24 border border-color24 p-4 rounded-full text-white w-full">
                    <i class="ph ph-magnifying-glass"></i>
                    <span class="text-white w-full text-xs">Search Contest</span>
                </a>
            </div>

            <!-- Tables List -->
            <div class="pt-24 px-6">
                <h3 class="text-xl font-semibold mb-5">Available Tables</h3>
                <div id="tablesContainer" class="flex flex-col gap-4">
                    <!-- Loading -->
                    <p class="text-center text-color5">Loading tables...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Tab -->
    <div class="fixed bottom-0 left-0 right-0 z-40">
        <div class="container bg-p2 px-6 py-3 rounded-t-2xl flex justify-around items-center dark:bg-p1">
            <a href="home.php" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-p1 dark:bg-p2">
                    <i class="ph ph-house text-xl !leading-none text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">Home</p>
            </a>
            <a href="upcoming-contest.php" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-white dark:bg-color10">
                    <i class="ph ph-squares-four text-xl !leading-none dark:text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">All Contest</p>
            </a>
            <a href="earn-rewards.html" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-white dark:bg-color10">
                    <i class="ph ph-users-three text-xl !leading-none dark:text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">Recharge</p>
            </a>
            <a href="chat.html" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-white dark:bg-color10">
                    <i class="ph ph-users-three text-xl !leading-none dark:text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">Chat</p>
            </a>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        async function loadTables() {
            try {
                const res = await fetch('api/tables/list.php');
                const data = await res.json();

                if (!data.tables || data.tables.length === 0) {
                    document.getElementById('tablesContainer').innerHTML = '<p class="text-center text-color5">No tables available right now.</p>';
                    return;
                }

                let html = '';
                data.tables.forEach(table => {
                    const players = table.type === '2-player' ? 2 : 4;
                    const spotsLeft = table.spots_left || players; // fallback

                    html += `
                    <div class="rounded-2xl overflow-hidden shadow2 border border-color21">
                        <div class="p-5 bg-white dark:bg-color10">
                            <div class="flex justify-between items-center">
                                <div class="flex justify-start items-center gap-2">
                                    <div class="py-1 px-2 text-white bg-p2 rounded-lg dark:bg-p1 dark:text-black">
                                        <p class="font-semibold text-xs">Live</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-xs">${table.type}</p>
                                        <p class="text-xs">1 Winner</p>
                                    </div>
                                </div>
                                <div class="text-sm text-p2 dark:text-p1 font-semibold">
                                    ${table.time_limit}
                                </div>
                            </div>

                            <div class="flex justify-between items-center text-xs py-3">
                                <p>${spotsLeft} spots left</p>
                                <div class="relative bg-p2 bg-opacity-10 dark:bg-color24 h-1 w-24 rounded-full">
                                    <div class="absolute h-1 bg-p2 dark:bg-p1 rounded-full" style="width: ${(players - spotsLeft)/players * 100}%"></div>
                                </div>
                            </div>

                            <div class="border-b border-dashed border-black dark:border-color24 border-opacity-10 pb-5 flex justify-between items-center text-xs">
                                <div class="flex justify-start items-center gap-2">
                                    <div class="text-white p-2 bg-p1 rounded-full"><i class="ph ph-trophy"></i></div>
                                    <div>
                                        <p>Prize Pool</p>
                                        <p class="font-semibold">Rs. ${table.prize_pool}</p>
                                    </div>
                                </div>
                                <div class="flex justify-start items-center gap-2">
                                    <button class="text-white text-xs bg-p2 py-1 px-4 rounded-full dark:bg-p1 join-btn" data-id="${table.id}">
                                        Join Now
                                    </button>
                                    <div>
                                        <p>Entry</p>
                                        <p class="font-semibold">Rs. ${table.entry_points}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });

                document.getElementById('tablesContainer').innerHTML = html;

                // Join button handlers
                document.querySelectorAll('.join-btn').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.preventDefault();
                        const tableId = btn.getAttribute('data-id');
                        
                        if (!confirm('Join this table? Entry fee will be deducted.')) return;

                        btn.disabled = true;
                        btn.textContent = 'Joining...';

                        try {
                            const res = await fetch('api/tables/join.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ table_id: tableId })
                            });
                            const result = await res.json();

                            if (result.success) {
                                // Redirect to game page
                                window.location.href = result.redirect || 'game.php?table_id=' + tableId;
                            } else {
                                alert(result.error || 'Failed to join');
                            }
                        } catch (err) {
                            alert('Network error');
                        } finally {
                            btn.disabled = false;
                            btn.textContent = 'Join Now';
                        }
                    });
                });

            } catch (err) {
                document.getElementById('tablesContainer').innerHTML = '<p class="text-center text-red-500">Error loading tables</p>';
            }
        }

        // Load on page load
        window.addEventListener('DOMContentLoaded', loadTables);
    </script>
</body>
</html>