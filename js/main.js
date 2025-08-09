// Main JavaScript file for Hackathon Management System

document.addEventListener('DOMContentLoaded', function() {
    // Manual implementation of dropdowns since Bootstrap may not be initializing them correctly
    document.querySelectorAll('.dropdown-toggle').forEach(function(dropdownToggle) {
        dropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle dropdown menu
            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                // Close all other dropdowns first
                document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                    if (menu !== dropdownMenu) {
                        menu.classList.remove('show');
                    }
                });
                
                // Toggle this dropdown
                dropdownMenu.classList.toggle('show');
                
                // Add click event to document to close dropdown when clicking outside
                setTimeout(function() {
                    document.addEventListener('click', function closeDropdown(e) {
                        if (!dropdownMenu.contains(e.target) && e.target !== dropdownToggle) {
                            dropdownMenu.classList.remove('show');
                            document.removeEventListener('click', closeDropdown);
                        }
                    });
                }, 10);
            }
        });
    });
    
    // Bootstrap initialization (as a fallback)
    try {
        // Initialize all dropdowns with Bootstrap's API
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
        
        // Ensure the user dropdown works properly
        var userDropdown = document.getElementById('navbarDropdown');
        if (userDropdown) {
            userDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                var dropdown = bootstrap.Dropdown.getInstance(userDropdown);
                if (!dropdown) {
                    dropdown = new bootstrap.Dropdown(userDropdown);
                }
                dropdown.toggle();
            });
        }
    } catch (e) {
        console.error('Error initializing Bootstrap dropdowns:', e);
    }
    
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Add animation to elements with fade-in class
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        element.style.opacity = '0';
        setTimeout(() => {
            element.style.animation = 'fadeIn 0.6s ease-out forwards';
        }, 100 * index);
    });

    // Team invitation functionality
    const inviteButtons = document.querySelectorAll('.invite-member-btn');
    inviteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const inviteForm = this.nextElementSibling;
            if (inviteForm.style.display === 'none' || inviteForm.style.display === '') {
                inviteForm.style.display = 'block';
            } else {
                inviteForm.style.display = 'none';
            }
        });
    });

    // Project submission form validation
    const projectForm = document.getElementById('project-form');
    if (projectForm) {
        projectForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate required fields
            const requiredFields = projectForm.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // GitHub URL validation if provided
            const githubUrl = document.getElementById('github-url');
            if (githubUrl && githubUrl.value.trim() !== '') {
                const githubPattern = /^https?:\/\/(www\.)?github\.com\/[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+\/?$/;
                if (!githubPattern.test(githubUrl.value.trim())) {
                    isValid = false;
                    githubUrl.classList.add('is-invalid');
                } else {
                    githubUrl.classList.remove('is-invalid');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                document.getElementById('form-error-msg').style.display = 'block';
            }
        });
    }

    // Judging score validation
    const scoreInputs = document.querySelectorAll('.score-input');
    if (scoreInputs.length > 0) {
        scoreInputs.forEach(input => {
            input.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (isNaN(value) || value < 0 || value > 10) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
                
                // Calculate total
                if (this.closest('form')) {
                    const form = this.closest('form');
                    const scores = form.querySelectorAll('.score-input');
                    let total = 0;
                    scores.forEach(score => {
                        const val = parseFloat(score.value) || 0;
                        if (val >= 0 && val <= 10) {
                            total += val;
                        }
                    });
                    const totalElement = form.querySelector('.total-score');
                    if (totalElement) {
                        totalElement.textContent = total.toFixed(1);
                    }
                }
            });
        });
    }

    // File upload preview
    const fileUpload = document.getElementById('project-file');
    const filePreview = document.getElementById('file-preview');
    
    if (fileUpload && filePreview) {
        fileUpload.addEventListener('change', function() {
            filePreview.innerHTML = '';
            
            if (this.files && this.files.length > 0) {
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item p-2 border rounded mb-2 d-flex align-items-center';
                    
                    let fileIcon = 'fa-file';
                    if (file.type.includes('image')) {
                        fileIcon = 'fa-file-image';
                    } else if (file.type.includes('pdf')) {
                        fileIcon = 'fa-file-pdf';
                    } else if (file.type.includes('word')) {
                        fileIcon = 'fa-file-word';
                    } else if (file.type.includes('excel') || file.type.includes('sheet')) {
                        fileIcon = 'fa-file-excel';
                    } else if (file.type.includes('zip') || file.type.includes('archive')) {
                        fileIcon = 'fa-file-archive';
                    } else if (file.type.includes('text')) {
                        fileIcon = 'fa-file-alt';
                    }
                    
                    fileItem.innerHTML = `
                        <i class="fas ${fileIcon} me-2 text-primary"></i>
                        <span>${file.name}</span>
                        <small class="ms-auto text-muted">${(file.size / 1024).toFixed(2)} KB</small>
                    `;
                    
                    filePreview.appendChild(fileItem);
                }
            }
        });
    }

    // Profile tabs
    const profileTabs = document.querySelectorAll('#profile-tabs .nav-link');
    const profileContents = document.querySelectorAll('.profile-tab-content');
    
    if (profileTabs.length > 0 && profileContents.length > 0) {
        profileTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all tabs and hide all contents
                profileTabs.forEach(t => t.classList.remove('active'));
                profileContents.forEach(c => c.style.display = 'none');
                
                // Add active class to clicked tab and show corresponding content
                this.classList.add('active');
                const target = this.getAttribute('href').substring(1);
                document.getElementById(target).style.display = 'block';
            });
        });
    }

    // Handle delete confirmations
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
}); 