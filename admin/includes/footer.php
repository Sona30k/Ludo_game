            <!-- Content Ends Here -->
        </div>
    </main>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('toggleSidebar');
        const mobileBtn = document.querySelector('.mobile-menu-btn');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('closed');
            mainContent.classList.toggle('shifted');
        });

        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            mainContent.classList.toggle('shifted');
        });

        // Active link highlight
        document.querySelectorAll('.admin-sidebar a').forEach(link => {
            if (link.href === window.location.href) {
                link.classList.add('bg-white/20');
            }
        });
    </script>
</body>
</html>