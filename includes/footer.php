        </div>
    </div>
    
    <!-- Mobile Menu Script -->
    <script>
        // Mobile menu toggle functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleMobileMenu() {
            mobileMenuToggle.classList.toggle('active');
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            // Prevent body scroll when menu is open
            if (sidebar.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
        
        function closeMobileMenu() {
            mobileMenuToggle.classList.remove('active');
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        // Toggle menu on button click
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        }
        
        // Close menu when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeMobileMenu);
        }
        
        // Close menu when clicking a link (on mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeMobileMenu();
                }
            });
        });
        
        // Close menu on window resize if going to desktop
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (window.innerWidth > 768) {
                    closeMobileMenu();
                }
            }, 250);
        });
        
        // Handle swipe gestures to close menu
        let touchStartX = 0;
        let touchEndX = 0;
        
        if (sidebar) {
            sidebar.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });
            
            sidebar.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });
        }
        
        function handleSwipe() {
            // Swipe left to close
            if (touchStartX - touchEndX > 50) {
                closeMobileMenu();
            }
        }
    </script>
</body>
</html>
