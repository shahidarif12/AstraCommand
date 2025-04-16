/**
 * Astra C2 - Dashboard JavaScript
 * Handles dashboard interactivity and charts
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize charts if they exist on the page
    initializeCharts();
    
    // Add event listeners for filter forms
    setupFilterResets();
    
    // Setup auto-update for device status
    setupDeviceStatusTimer();
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize charts if Chart.js is loaded and canvas elements exist
 */
function initializeCharts() {
    // Only proceed if Chart is defined
    if (typeof Chart !== 'undefined') {
        // Device status chart
        const deviceStatusCanvas = document.getElementById('deviceStatusChart');
        if (deviceStatusCanvas) {
            renderDeviceStatusChart(deviceStatusCanvas);
        }
        
        // Command status chart
        const commandStatusCanvas = document.getElementById('commandStatusChart');
        if (commandStatusCanvas) {
            renderCommandStatusChart(commandStatusCanvas);
        }
        
        // Log types chart
        const logTypesCanvas = document.getElementById('logTypesChart');
        if (logTypesCanvas) {
            renderLogTypesChart(logTypesCanvas);
        }
    }
}

/**
 * Render device status chart
 * @param {HTMLElement} canvas The canvas element to render chart on
 */
function renderDeviceStatusChart(canvas) {
    // Try to get data from the data attributes or use default values
    const activeDevices = parseInt(canvas.getAttribute('data-active') || 0);
    const offlineDevices = parseInt(canvas.getAttribute('data-offline') || 0);
    
    const chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Online', 'Offline'],
            datasets: [{
                data: [activeDevices, offlineDevices],
                backgroundColor: ['#4CAF50', '#9E9E9E'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

/**
 * Render command status chart
 * @param {HTMLElement} canvas The canvas element to render chart on
 */
function renderCommandStatusChart(canvas) {
    // Try to get data from the data attributes or use default values
    const pendingCommands = parseInt(canvas.getAttribute('data-pending') || 0);
    const completedCommands = parseInt(canvas.getAttribute('data-completed') || 0);
    const inProgressCommands = parseInt(canvas.getAttribute('data-in-progress') || 0);
    
    const chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'In Progress', 'Completed'],
            datasets: [{
                data: [pendingCommands, inProgressCommands, completedCommands],
                backgroundColor: ['#FFC107', '#2196F3', '#4CAF50'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

/**
 * Render log types chart
 * @param {HTMLElement} canvas The canvas element to render chart on
 */
function renderLogTypesChart(canvas) {
    // Try to get data from the data attributes or use default values
    const gpsLogs = parseInt(canvas.getAttribute('data-gps') || 0);
    const smsLogs = parseInt(canvas.getAttribute('data-sms') || 0);
    const notificationLogs = parseInt(canvas.getAttribute('data-notification') || 0);
    const otherLogs = parseInt(canvas.getAttribute('data-other') || 0);
    
    const chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['GPS', 'SMS', 'Notifications', 'Other'],
            datasets: [{
                data: [gpsLogs, smsLogs, notificationLogs, otherLogs],
                backgroundColor: ['#2196F3', '#9C27B0', '#FF9800', '#9E9E9E'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

/**
 * Set up event listeners for filter reset buttons
 */
function setupFilterResets() {
    const resetButtons = document.querySelectorAll('.filter-reset');
    resetButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const formId = this.getAttribute('data-form');
            const form = document.getElementById(formId);
            if (form) {
                const inputs = form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.type === 'text' || input.type === 'search' || input.tagName === 'SELECT') {
                        input.value = '';
                    } else if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    }
                });
            }
        });
    });
}

/**
 * Set up a timer to update device status indicators
 */
function setupDeviceStatusTimer() {
    // Function to update device status based on last seen time
    function updateDeviceStatus() {
        const statusCells = document.querySelectorAll('[data-last-seen]');
        
        statusCells.forEach(cell => {
            const lastSeen = new Date(cell.getAttribute('data-last-seen'));
            const now = new Date();
            const diffMinutes = Math.floor((now - lastSeen) / (1000 * 60));
            
            // Get the status badge element
            const badge = cell.querySelector('.badge');
            if (badge) {
                if (diffMinutes < 10) { // Online if seen in the last 10 minutes
                    badge.className = 'badge bg-success';
                    badge.textContent = 'Online';
                } else {
                    badge.className = 'badge bg-secondary';
                    badge.textContent = 'Offline';
                }
            }
            
            // Update the time ago text if it exists
            const timeAgo = cell.querySelector('.time-ago');
            if (timeAgo) {
                timeAgo.textContent = formatTimeAgo(lastSeen);
            }
        });
    }
    
    // Run once on load
    updateDeviceStatus();
    
    // Then set interval to run every minute
    setInterval(updateDeviceStatus, 60000);
}

/**
 * Format a date into a "time ago" string
 * @param {Date} date The date to format
 * @return {string} Formatted time ago string
 */
function formatTimeAgo(date) {
    const now = new Date();
    const diffSeconds = Math.floor((now - date) / 1000);
    
    if (diffSeconds < 60) {
        return 'Just now';
    }
    
    const diffMinutes = Math.floor(diffSeconds / 60);
    if (diffMinutes < 60) {
        return diffMinutes + ' minute' + (diffMinutes !== 1 ? 's' : '') + ' ago';
    }
    
    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) {
        return diffHours + ' hour' + (diffHours !== 1 ? 's' : '') + ' ago';
    }
    
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) {
        return diffDays + ' day' + (diffDays !== 1 ? 's' : '') + ' ago';
    }
    
    // For older dates, return the actual date
    return date.toLocaleDateString();
}

/**
 * Format bytes to human-readable format
 * @param {number} bytes The number of bytes
 * @param {number} decimals The number of decimal places
 * @return {string} Formatted string
 */
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}
