    </div> <!-- End of Main Content Container -->

    <!-- Footer -->
    <footer class="mt-auto py-3 bg-light fixed-bottom">
        <div class="container text-center">
            <span class="text-muted">© <?php echo date('Y'); ?> Hackhub | SLIIT</span>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script>
        // Automatically convert timestamps to time ago format
        document.addEventListener('DOMContentLoaded', function() {
            const timeAgoElements = document.querySelectorAll('.time-ago');
            
            timeAgoElements.forEach(function(element) {
                const timestamp = parseInt(element.getAttribute('data-timestamp'));
                
                if (timestamp) {
                    element.textContent = timeAgo(timestamp);
                    
                    // Update time every minute
                    setInterval(function() {
                        element.textContent = timeAgo(timestamp);
                    }, 60000);
                }
            });
            
            // Initialize all tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize all popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });
        
        // Time ago function
        function timeAgo(timestamp) {
            const seconds = Math.floor((new Date() - timestamp * 1000) / 1000);
            
            let interval = Math.floor(seconds / 31536000);
            if (interval > 1) {
                return interval + ' years ago';
            }
            if (interval === 1) {
                return '1 year ago';
            }
            
            interval = Math.floor(seconds / 2592000);
            if (interval > 1) {
                return interval + ' months ago';
            }
            if (interval === 1) {
                return '1 month ago';
            }
            
            interval = Math.floor(seconds / 86400);
            if (interval > 1) {
                return interval + ' days ago';
            }
            if (interval === 1) {
                return '1 day ago';
            }
            
            interval = Math.floor(seconds / 3600);
            if (interval > 1) {
                return interval + ' hours ago';
            }
            if (interval === 1) {
                return '1 hour ago';
            }
            
            interval = Math.floor(seconds / 60);
            if (interval > 1) {
                return interval + ' minutes ago';
            }
            if (interval === 1) {
                return '1 minute ago';
            }
            
            return 'just now';
        }
    </script>

    <style>
    body {
        margin-bottom: 70px; /* Add margin to prevent content from being hidden behind footer */
    }

    .fixed-bottom {
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    }
    </style>
</body>
</html> 