            </main>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar on mobile
        const menuToggle = document.querySelector('.menu-toggle');
        const adminSidebar = document.getElementById('adminSidebar');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                adminSidebar.classList.toggle('show');
            });
        }
        
        // Dropdown menus
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.nextElementSibling.classList.toggle('show');
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });
        
        // Tabs functionality
        const tabLinks = document.querySelectorAll('.tab-item');
        
        tabLinks.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to current tab and content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
    });
    </script>
</body>
</html>
