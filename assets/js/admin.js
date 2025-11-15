// VPN Remote - Admin Panel JavaScript

class AdminPanel {
    constructor() {
        // Store user-entered passwords to prevent overwriting
        this.userPasswords = {
            mikrotik: '',
            auth: '',
            bot_token: ''
        };
        // Connection state
        this.isConnected = false;
        this.connectionInterval = null;
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadConfigurations();
        this.initPasswordToggles();
    }
    
    bindEvents() {
        // Form submissions
        document.getElementById('mikrotik-form')?.addEventListener('submit', (e) => this.handleMikrotikForm(e));
        document.getElementById('auth-form')?.addEventListener('submit', (e) => this.handleAuthForm(e));
        document.getElementById('telegram-form')?.addEventListener('submit', (e) => this.handleTelegramForm(e));
        
        // Test buttons
        document.getElementById('test-connection')?.addEventListener('click', () => this.testMikrotikConnection());
        document.getElementById('test-telegram')?.addEventListener('click', () => this.testTelegramBot());

        // Connect button
        const connectBtn = document.getElementById('connect-mikrotik');
        console.log('Connect button found:', connectBtn);
        if (connectBtn) {
            connectBtn.addEventListener('click', () => {
                console.log('Connect button clicked!');
                this.connectMikrotik();
            });
        } else {
            console.error('Connect button NOT found in DOM');
        }
        
        // SSL Toggle button
        document.getElementById('ssl-toggle')?.addEventListener('click', () => this.toggleSSL());
        
        // Show current password button - removed, now handled by onclick in HTML
        
        // Service toggles - with more specific binding
        const l2tpBtn = document.getElementById('toggle-l2tp');
        const pptpBtn = document.getElementById('toggle-pptp');
        const sstpBtn = document.getElementById('toggle-sstp');
        
        if (l2tpBtn) {
            l2tpBtn.addEventListener('click', (e) => {
                this.toggleService(e);
            });
        }
        
        if (pptpBtn) {
            pptpBtn.addEventListener('click', (e) => {
                this.toggleService(e);
            });
        }
        
        if (sstpBtn) {
            sstpBtn.addEventListener('click', (e) => {
                this.toggleService(e);
            });
        }
        
        // Backup button
        const backupBtn = document.getElementById('backup-config');
        if (backupBtn) {
            backupBtn.addEventListener('click', (e) => {
                this.sendBackupToTelegram();
            });
        }
        
        // Profile service buttons
        const l2tpProfileBtn = document.getElementById('create-l2tp-profile');
        const pptpProfileBtn = document.getElementById('create-pptp-profile');
        const sstpProfileBtn = document.getElementById('create-sstp-profile');
        
        if (l2tpProfileBtn) {
            l2tpProfileBtn.addEventListener('click', (e) => {
                this.createServiceProfile(e);
            });
        }
        
        if (pptpProfileBtn) {
            pptpProfileBtn.addEventListener('click', (e) => {
                this.createServiceProfile(e);
            });
        }
        
        if (sstpProfileBtn) {
            sstpProfileBtn.addEventListener('click', (e) => {
                this.createServiceProfile(e);
            });
        }
        
        // NAT Masquerade button
        const natMasqueradeBtn = document.getElementById('create-nat-masquerade');
        if (natMasqueradeBtn) {
            natMasqueradeBtn.addEventListener('click', (e) => {
                this.createNATMasquerade(e);
            });
        }
        
    }
    
    initPasswordToggles() {
        // MikroTik password input handling (for storing typed password)
        const mtPassword = document.getElementById('mt_password');
        
        if (mtPassword) {
            // Store password when user types (save to class property)
            mtPassword.addEventListener('input', () => {
                // If user starts typing while field shows bullets, clear it first
                if (mtPassword.value.startsWith('••••••••') || mtPassword.value.startsWith('\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022')) {
                    mtPassword.value = '';
                    return; // Let user continue typing
                }
                this.userPasswords.mikrotik = mtPassword.value;
            });
            
            // Restore password on focus if it was masked
            mtPassword.addEventListener('focus', () => {
                if ((mtPassword.value === '••••••••' || mtPassword.value === '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022') && this.userPasswords.mikrotik) {
                    mtPassword.value = this.userPasswords.mikrotik;
                }
            });
        }
        
        // Auth password toggle
        const toggleAuthPassword = document.getElementById('toggleAuthPassword');
        const authPassword = document.getElementById('auth_password');
        
        if (toggleAuthPassword && authPassword) {
            // Store password when user types (save to class property)
            authPassword.addEventListener('input', () => {
                // If user starts typing while field shows bullets, clear it first
                if (authPassword.value.startsWith('••••••••') || authPassword.value.startsWith('\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022')) {
                    authPassword.value = '';
                    return; // Let user continue typing
                }
                this.userPasswords.auth = authPassword.value;
            });
            
            // Restore password on focus if it was masked
            authPassword.addEventListener('focus', () => {
                if ((authPassword.value === '••••••••' || authPassword.value === '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022') && this.userPasswords.auth) {
                    authPassword.value = this.userPasswords.auth;
                }
            });
            
            toggleAuthPassword.addEventListener('click', async () => {
                if (authPassword.type === 'password') {
                    // Show password - need to get actual password if field shows bullets
                    if (authPassword.value === '••••••••' || authPassword.value === '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022') {
                        if (this.userPasswords.auth) {
                            // Use stored password
                            authPassword.value = this.userPasswords.auth;
                        } else {
                            // Fetch from server
                            try {
                                const response = await fetch('../api/config.php?action=get_password&section=auth&key=password');
                                const result = await response.json();
                                if (result.success) {
                                    authPassword.value = result.password;
                                    this.userPasswords.auth = result.password;
                                }
                            } catch (error) {
                                console.error('Failed to get auth password:', error);
                            }
                        }
                    }
                    
                    authPassword.type = 'text';
                    toggleAuthPassword.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    // Hide password
                    authPassword.type = 'password';
                    toggleAuthPassword.innerHTML = '<i class="bi bi-eye"></i>';
                }
            });
        }
        
        // Bot Token toggle
        const toggleBotToken = document.getElementById('toggleBotToken');
        const botToken = document.getElementById('bot_token');
        
        if (toggleBotToken && botToken) {
            // Store bot token when user types (save to class property)
            botToken.addEventListener('input', () => {
                // If user starts typing while field shows bullets, clear it first
                if (botToken.value.startsWith('••••••••') || botToken.value.includes('••••••••')) {
                    botToken.value = '';
                    return; // Let user continue typing
                }
                this.userPasswords.bot_token = botToken.value;
            });
            
            // Restore bot token on focus if it was masked
            botToken.addEventListener('focus', () => {
                if ((botToken.value === '••••••••' || botToken.value.includes('••••••••')) && this.userPasswords.bot_token) {
                    botToken.value = this.userPasswords.bot_token;
                }
            });
            
            toggleBotToken.addEventListener('click', async () => {
                if (botToken.type === 'password') {
                    // Show bot token - need to get actual token if field shows bullets
                    if (botToken.value === '••••••••' || botToken.value.includes('••••••••')) {
                        if (this.userPasswords.bot_token) {
                            // Use stored token
                            botToken.value = this.userPasswords.bot_token;
                        } else {
                            // Fetch from server
                            try {
                                const response = await fetch('../api/config.php?action=get_password&section=telegram&key=bot_token');
                                const result = await response.json();
                                if (result.success) {
                                    botToken.value = result.password;
                                    this.userPasswords.bot_token = result.password;
                                }
                            } catch (error) {
                                console.error('Failed to get bot token:', error);
                            }
                        }
                    }
                    
                    botToken.type = 'text';
                    toggleBotToken.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    // Hide bot token
                    botToken.type = 'password';
                    toggleBotToken.innerHTML = '<i class="bi bi-eye"></i>';
                }
            });
        }
        
        // Password confirmation validation
        const authConfirm = document.getElementById('auth_confirm');
        if (authPassword && authConfirm) {
            authConfirm.addEventListener('input', () => {
                if (authPassword.value !== authConfirm.value) {
                    authConfirm.setCustomValidity('Passwords do not match');
                } else {
                    authConfirm.setCustomValidity('');
                }
            });
        }
    }
    
    async loadConfigurations() {
        try {
            const response = await fetch('../api/config.php?action=get_all');
            
            // Check if response is OK
            if (!response.ok) {
                console.error('Response not OK:', response.status, response.statusText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server did not return JSON response');
            }
            
            const data = await response.json();
            
            
            if (data.success) {
                // Safely populate forms with default values if data is missing
                this.populateForm('mikrotik-form', data.config.mikrotik || {});
                this.populateForm('auth-form', data.config.auth || {});
                this.populateForm('telegram-form', data.config.telegram || {});
                
                // Update service status
                this.updateServiceStatuses(data.config.services || {});
                
                // Check profile status
                this.checkProfilesStatus();
                
                // Check NAT status
                this.checkNATStatus();
                
                // Refresh service status from MikroTik in background
                this.refreshServiceStatusFromMikroTik();
            } else {
                this.showAlert('Failed to load configuration: ' + (data.message || 'Unknown error'), 'warning');
            }
        } catch (error) {
            
            // Show user-friendly error message
            let errorMessage = 'Unable to load configuration. ';
            if (error.message.includes('JSON')) {
                errorMessage += 'Configuration file may be corrupted or missing.';
            } else if (error.message.includes('HTTP')) {
                errorMessage += 'Server connection failed.';
            } else {
                errorMessage += error.message;
            }
            
            this.showAlert(errorMessage, 'warning');
            
            // Initialize forms with empty values
            this.initializeEmptyForms();
        }
    }
    
    initializeEmptyForms() {
        // Initialize forms with default/empty values
        this.populateForm('mikrotik-form', {
            host: '',
            username: '',
            password: '',
            port: '443',
            use_ssl: true
        });
        
        this.populateForm('auth-form', {
            username: '',
            password: ''
        });
        
        this.populateForm('telegram-form', {
            bot_token: '',
            chat_id: '',
            enabled: false
        });
    }
    
    populateForm(formId, data) {
        const form = document.getElementById(formId);
        if (!form || !data) return;
        
        
        Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox' || (key === 'use_ssl' && input.type === 'hidden')) {
                    // Handle SSL toggle button
                    if (key === 'use_ssl') {
                        this.updateSSLButton(data[key]);
                        input.value = data[key] ? 'true' : 'false';
                    } else {
                        input.checked = data[key];
                    }
                } else if (input.type === 'password') {
                    
                    // Handle password fields specially - check for both unicode and string bullets
                    const isPasswordMasked = data[key] === '••••••••' || 
                                           data[key] === '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022' ||
                                           (key === 'bot_token' && data[key] && (data[key].includes('••••••••') || data[key].includes('\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022')));
                    const hasUserPassword = (formId === 'mikrotik-form' && this.userPasswords.mikrotik) || 
                                          (formId === 'auth-form' && this.userPasswords.auth) ||
                                          (formId === 'telegram-form' && key === 'bot_token' && this.userPasswords.bot_token);
                    
                    if (isPasswordMasked && hasUserPassword) {
                        // User has typed a password, keep it
                        // Restore the actual password value
                        if (formId === 'mikrotik-form') {
                            input.value = this.userPasswords.mikrotik;
                        } else if (formId === 'auth-form') {
                            input.value = this.userPasswords.auth;
                        } else if (formId === 'telegram-form' && key === 'bot_token') {
                            input.value = this.userPasswords.bot_token;
                        }
                    } else if (isPasswordMasked) {
                        // Password exists on server - show bullets in field VALUE
                        if (key === 'bot_token' && (data[key].includes('••••••••') || data[key].includes('\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022'))) {
                            // For bot token, show partial masked value
                            input.value = '••••••••';
                        } else {
                            // For passwords, show full bullets as VALUE
                            input.value = '••••••••';
                        }
                        // Clear placeholder for auth password field specifically
                        if (formId === 'auth-form' && key === 'password') {
                            input.placeholder = '';
                        } else {
                            input.placeholder = key === 'bot_token' ? 'Bot Token' : 'Password';
                        }
                    } else if (data[key] && data[key] !== '') {
                        // Valid password data from server
                        input.value = data[key];
                        // Also store it as user password
                        if (formId === 'mikrotik-form' && key === 'password') {
                            this.userPasswords.mikrotik = data[key];
                        } else if (formId === 'auth-form' && key === 'password') {
                            this.userPasswords.auth = data[key];
                        } else if (formId === 'telegram-form' && key === 'bot_token') {
                            this.userPasswords.bot_token = data[key];
                        }
                    } else {
                        // No password exists - set placeholder for empty fields
                        if (formId === 'auth-form' && key === 'password') {
                            input.placeholder = '';
                            input.value = '';
                        } else {
                            input.placeholder = 'Enter password';
                        }
                    }
                } else {
                    input.value = data[key];
                }
            }
        });
    }
    
    async handleMikrotikForm(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Convert SSL value from string to boolean
        data.use_ssl = data.use_ssl === 'true';
        
        // Fix password handling - don't save bullets, use actual password
        if (data.password === '••••••••') {
            // If password field shows bullets but user has typed a password, use that
            if (this.userPasswords.mikrotik) {
                data.password = this.userPasswords.mikrotik;
            } else {
                // Remove password from data so it won't overwrite existing
                delete data.password;
            }
        }
        
        await this.saveConfiguration('mikrotik', data, 'MikroTik configuration saved successfully!');
    }
    
    async handleAuthForm(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Only include non-empty fields (selective update)
        const updateData = {};
        
        if (data.username && data.username.trim() !== '') {
            updateData.username = data.username.trim();
        }
        
        if (data.password && data.password.trim() !== '') {
            updateData.password = data.password.trim();
        }
        
        // Check if there's anything to update
        if (Object.keys(updateData).length === 0) {
            this.showAlert('No changes to update. Please enter a new username or password.', 'warning');
            return;
        }
        
        await this.saveConfiguration('auth', updateData, 'Login credentials updated successfully!');
        
        // Clear form after successful update
        form.reset();
    }
    
    async handleTelegramForm(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Convert checkbox value
        data.enabled = formData.has('enabled');
        
        await this.saveConfiguration('telegram', data, 'Telegram settings saved successfully!');
    }
    
    async saveConfiguration(section, data, successMessage) {
        try {
            const response = await fetch('../api/config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_section',
                    section: section,
                    data: data
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server did not return JSON response');
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(successMessage, 'success');
            } else {
                this.showAlert('Error: ' + (result.message || 'Unknown error'), 'danger');
            }
        } catch (error) {
            console.error('Configuration save error:', error);
            this.showAlert('Error saving configuration: ' + error.message, 'danger');
        }
    }
    
    async testMikrotikConnection() {
        const btn = document.getElementById('test-connection');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing...';

        try {
            const response = await fetch('../api/mikrotik.php?action=test_connection');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                let message = 'Connection successful!';
                if (result.data && result.data.board) {
                    message += `<br><strong>Board:</strong> ${result.data.board}`;
                    message += `<br><strong>Version:</strong> ${result.data.version}`;
                    message += `<br><strong>Uptime:</strong> ${result.data.uptime}`;
                }
                this.showAlert(message, 'success');

                // Reload configurations to get updated service statuses
                setTimeout(() => {
                    this.loadConfigurations();
                }, 1000);
            } else {
                this.showAlert('Connection failed: ' + result.message, 'danger');
            }
        } catch (error) {
            console.error('Connection test error:', error);
            this.showAlert('Error testing connection: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async connectMikrotik() {
        console.log('connectMikrotik called');
        const btn = document.getElementById('connect-mikrotik');
        const btnText = document.getElementById('connect-text');
        const statusDiv = document.getElementById('connection-status');
        const statusInfo = document.getElementById('connection-info');

        console.log('Button:', btn);
        console.log('Button text:', btnText);
        console.log('Status div:', statusDiv);

        if (this.isConnected) {
            // Disconnect
            this.disconnectMikrotik();
            return;
        }

        const originalText = btnText.textContent;
        btn.disabled = true;
        btnText.textContent = 'Connecting...';

        try {
            const response = await fetch('../api/mikrotik.php?action=test_connection');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Connection successful
                this.isConnected = true;

                // Update button state
                btn.className = 'btn btn-danger';
                btnText.textContent = 'Disconnect';
                btn.disabled = false;

                // Show connection status
                if (result.data && result.data.board) {
                    statusInfo.textContent = `Router: ${result.data.board} | Version: ${result.data.version}`;
                } else {
                    statusInfo.textContent = 'Router: Connected';
                }
                statusDiv.style.display = 'block';

                this.showAlert('Connected to MikroTik router successfully! Auto-refresh every 2 seconds.', 'success');

                // Start periodic connection check (every 2 seconds)
                this.connectionInterval = setInterval(() => {
                    this.checkConnection();
                }, 2000);

                // Load configurations after connection
                setTimeout(() => {
                    this.loadConfigurations();
                }, 500);
            } else {
                throw new Error(result.message || 'Connection failed');
            }
        } catch (error) {
            console.error('Connection error:', error);
            this.showAlert('Failed to connect: ' + error.message, 'danger');
            btn.disabled = false;
            btnText.textContent = originalText;
        }
    }

    disconnectMikrotik() {
        const btn = document.getElementById('connect-mikrotik');
        const btnText = document.getElementById('connect-text');
        const statusDiv = document.getElementById('connection-status');

        // Stop periodic check
        if (this.connectionInterval) {
            clearInterval(this.connectionInterval);
            this.connectionInterval = null;
        }

        // Update UI
        this.isConnected = false;
        btn.className = 'btn btn-success';
        btnText.textContent = 'Connect';
        statusDiv.style.display = 'none';

        this.showAlert('Disconnected from MikroTik router', 'info');
    }

    async checkConnection() {
        if (!this.isConnected) {
            return;
        }

        try {
            const response = await fetch('../api/mikrotik.php?action=test_connection');
            const result = await response.json();

            if (!result.success) {
                // Connection lost
                this.showAlert('Connection to MikroTik lost. Please reconnect.', 'warning');
                this.disconnectMikrotik();
            } else {
                // Connection OK - refresh service statuses silently
                this.refreshServiceStatusFromMikroTik();

                // Update connection info if available
                const statusInfo = document.getElementById('connection-info');
                if (statusInfo && result.data && result.data.board) {
                    statusInfo.textContent = `Router: ${result.data.board} | Version: ${result.data.version} | Uptime: ${result.data.uptime}`;
                }
            }
        } catch (error) {
            console.error('Connection check failed:', error);
            // Connection lost
            this.showAlert('Connection to MikroTik lost. Please reconnect.', 'warning');
            this.disconnectMikrotik();
        }
    }
    
    async testTelegramBot() {
        const btn = document.getElementById('test-telegram');
        const originalText = btn.innerHTML;
        
        // Check if bot token and chat ID are filled
        const botToken = document.getElementById('bot_token').value;
        const chatId = document.getElementById('chat_id').value;
        
        // Allow testing if values are masked (means they're saved) or filled
        const botTokenValid = botToken && (botToken !== '' && botToken.length > 0);
        const chatIdValid = chatId && (chatId !== '' && chatId.length > 0);
        
        if (!botTokenValid || !chatIdValid) {
            this.showAlert('Please enter both Bot Token and Chat ID and save the configuration before testing', 'warning');
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing...';
        
        try {
            const response = await fetch('../api/telegram.php?action=test_bot', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (!response.ok) {
                // Handle server errors with more detail
                let errorMessage = `Server error (${response.status})`;
                if (result && result.message) {
                    errorMessage = result.message;
                }
                throw new Error(errorMessage);
            }
            
            if (result.success) {
                // Format the success message nicely
                let message = result.message;
                if (result.bot_info) {
                    message = `<strong>✅ Telegram Bot Test Successful!</strong><br><br>`;
                    message += `🤖 <strong>Bot Name:</strong> ${result.bot_info.name}<br>`;
                    message += `📝 <strong>Username:</strong> @${result.bot_info.username}<br>`;
                    message += `💬 <strong>Chat ID:</strong> ${result.bot_info.chat_id}<br><br>`;
                    message += `📤 A test message has been sent to your Telegram chat!`;
                }
                this.showAlert(message, 'success');
            } else {
                this.showAlert('❌ Telegram bot test failed: ' + result.message, 'danger');
            }
        } catch (error) {
            console.error('Telegram bot test error:', error);
            this.showAlert('❌ Error testing Telegram bot: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
    
    async toggleService(e) {
        e.preventDefault();
        
        const btn = e.target.closest('button');
        if (!btn) {
            console.error('Button not found');
            return;
        }
        
        // Check if button is disabled
        if (btn.disabled) {
            return;
        }
        
        const service = btn.dataset.service;
        if (!service) {
            console.error('Service not defined in button dataset');
            return;
        }
        
        
        // Determine current state from button classes
        const isCurrentlyEnabled = btn.classList.contains('btn-outline-danger');
        const newState = !isCurrentlyEnabled;
        
        
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
        
        try {
            const response = await fetch('../api/mikrotik.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_service',
                    service: service,
                    enable: newState
                })
            });
            
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.updateServiceButton(btn, newState);
                this.showAlert(`${service.toUpperCase()} server ${newState ? 'enabled' : 'disabled'} successfully!`, 'success');
                
                // Refresh all service statuses to ensure consistency
                setTimeout(() => {
                    this.refreshServiceStatusFromMikroTik();
                }, 1000);
            } else {
                // Restore original button state
                btn.innerHTML = originalHtml;
                this.showAlert('Error toggling service: ' + (result.message || 'Unknown error'), 'danger');
            }
        } catch (error) {
            console.error('Service toggle error:', error);
            // Restore original button state
            btn.innerHTML = originalHtml;
            this.showAlert('Error toggling service: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
        }
    }
    
    updateServiceButton(btn, enabled) {
        
        if (enabled === true || enabled === 'true') {
            // Service is active - disable button and show "Disable" state
            btn.className = 'btn btn-outline-danger';
            btn.innerHTML = '<i class="bi bi-power"></i> <span>Disable</span>';
            btn.disabled = true;
            btn.style.cursor = 'not-allowed';
            btn.title = 'Service is currently active';
        } else {
            // Service is inactive - enable button and show "Enable" state
            btn.className = 'btn btn-outline-success';
            btn.innerHTML = '<i class="bi bi-power"></i> <span>Enable</span>';
            btn.disabled = false;
            btn.style.cursor = 'pointer';
            btn.title = 'Click to enable service';
        }
    }
    
    updateServiceStatuses(services) {
        
        Object.keys(services).forEach(service => {
            const btn = document.getElementById(`toggle-${service}`);
            if (btn) {
                this.updateServiceButton(btn, services[service]);
            }
        });
    }
    
    async checkProfilesStatus() {
        try {
            const response = await fetch('../api/mikrotik.php?action=check_profiles_status');
            
            if (!response.ok) {
                console.error('Failed to check profiles status:', response.status);
                return;
            }
            
            const result = await response.json();
            
            if (result.success && result.profiles) {
                this.updateProfileButtons(result.profiles);
            }
        } catch (error) {
            console.error('Error checking profiles status:', error);
        }
    }
    
    async checkNATStatus() {
        try {
            const response = await fetch('../api/mikrotik.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_nat_status'
                })
            });
            
            if (!response.ok) {
                console.error('Failed to check NAT status:', response.status);
                return;
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.updateNATButton(result.nat_exists);
            }
        } catch (error) {
            console.error('Error checking NAT status:', error);
        }
    }
    
    updateProfileButtons(profilesStatus) {
        
        // Update L2TP profile button
        const l2tpBtn = document.getElementById('create-l2tp-profile');
        if (l2tpBtn) {
            if (profilesStatus.l2tp) {
                l2tpBtn.className = 'btn btn-success btn-sm w-100';
                l2tpBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Created';
                l2tpBtn.disabled = true;
                l2tpBtn.style.cursor = 'not-allowed';
            } else {
                l2tpBtn.className = 'btn btn-outline-primary btn-sm w-100';
                l2tpBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>L2TP Profile';
                l2tpBtn.disabled = false;
                l2tpBtn.style.cursor = 'pointer';
            }
        }
        
        // Update PPTP profile button
        const pptpBtn = document.getElementById('create-pptp-profile');
        if (pptpBtn) {
            if (profilesStatus.pptp) {
                pptpBtn.className = 'btn btn-success btn-sm w-100';
                pptpBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Created';
                pptpBtn.disabled = true;
                pptpBtn.style.cursor = 'not-allowed';
            } else {
                pptpBtn.className = 'btn btn-outline-primary btn-sm w-100';
                pptpBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>PPTP Profile';
                pptpBtn.disabled = false;
                pptpBtn.style.cursor = 'pointer';
            }
        }
        
        // Update SSTP profile button
        const sstpBtn = document.getElementById('create-sstp-profile');
        if (sstpBtn) {
            if (profilesStatus.sstp) {
                sstpBtn.className = 'btn btn-success btn-sm w-100';
                sstpBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Created';
                sstpBtn.disabled = true;
                sstpBtn.style.cursor = 'not-allowed';
            } else {
                sstpBtn.className = 'btn btn-outline-primary btn-sm w-100';
                sstpBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>SSTP Profile';
                sstpBtn.disabled = false;
                sstpBtn.style.cursor = 'pointer';
            }
        }
    }
    
    updateNATButton(natExists) {
        // Update NAT Masquerade button
        const natBtn = document.getElementById('create-nat-masquerade');
        if (natBtn) {
            if (natExists) {
                natBtn.className = 'btn btn-success btn-sm w-100';
                natBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Created';
                natBtn.disabled = true;
                natBtn.style.cursor = 'not-allowed';
            } else {
                natBtn.className = 'btn btn-outline-warning btn-sm w-100';
                natBtn.innerHTML = '<i class="bi bi-router me-1"></i>NAT Masquerade';
                natBtn.disabled = false;
                natBtn.style.cursor = 'pointer';
            }
        }
    }
    
    toggleSSL() {
        const sslButton = document.getElementById('ssl-toggle');
        const portInput = document.getElementById('mt_port');
        const hiddenInput = document.getElementById('mt_use_ssl');
        
        if (!sslButton || !portInput || !hiddenInput) {
            console.error('SSL toggle elements not found');
            return;
        }
        
        const currentSSL = sslButton.dataset.ssl === 'true';
        const newSSL = !currentSSL;
        
        
        // Update button appearance and data
        sslButton.dataset.ssl = newSSL.toString();
        hiddenInput.value = newSSL.toString();
        
        if (newSSL) {
            // Enable HTTPS/SSL
            sslButton.className = 'btn btn-success';
            sslButton.innerHTML = '<i class="bi bi-shield-lock me-2"></i>HTTPS/SSL';
            
            // Auto-fill port 443 if current port is 80 or default
            if (portInput.value === '80' || portInput.value === '' || !this.isCustomPort(portInput.value)) {
                portInput.value = '443';
            }
        } else {
            // Disable HTTPS/SSL
            sslButton.className = 'btn btn-primary';
            sslButton.innerHTML = '<i class="bi bi-shield me-2"></i>HTTP';
            
            // Auto-fill port 80 if current port is 443 or default
            if (portInput.value === '443' || portInput.value === '' || !this.isCustomPort(portInput.value)) {
                portInput.value = '80';
            }
        }
        
    }
    
    isCustomPort(port) {
        // Consider port as custom if it's not standard HTTP/HTTPS ports
        const standardPorts = ['80', '443', '8080', '8443'];
        return !standardPorts.includes(port);
    }
    
    async refreshServiceStatusFromMikroTik() {
        try {
            
            // Test connection silently to update service statuses
            const response = await fetch('../api/mikrotik.php?action=test_connection');
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.success) {
                    
                    // Reload configurations to get updated service statuses
                    setTimeout(async () => {
                        const configResponse = await fetch('../api/config.php?action=get_all');
                        if (configResponse.ok) {
                            const configData = await configResponse.json();
                            if (configData.success) {
                                // Update only service statuses without affecting other form data
                                this.updateServiceStatuses(configData.config.services || {});
                            }
                        }
                    }, 500);
                } else {
                }
            } else {
            }
        } catch (error) {
            console.error('Could not refresh service status from MikroTik:', error);
            // This is not critical, continue with cached status
        }
    }
    
    updateSSLButton(useSSL) {
        const sslButton = document.getElementById('ssl-toggle');
        
        if (!sslButton) {
            console.error('SSL toggle button not found');
            return;
        }
        
        // Convert to boolean if string
        const sslEnabled = useSSL === true || useSSL === 'true';
        
        sslButton.dataset.ssl = sslEnabled.toString();
        
        if (sslEnabled) {
            // HTTPS/SSL enabled
            sslButton.className = 'btn btn-success';
            sslButton.innerHTML = '<i class="bi bi-shield-lock me-2"></i>HTTPS/SSL';
        } else {
            // HTTPS/SSL disabled
            sslButton.className = 'btn btn-primary';
            sslButton.innerHTML = '<i class="bi bi-shield me-2"></i>HTTP';
        }
        
    }
    
    async showCurrentPassword() {
        
        const btn = document.getElementById('showCurrentPassword');
        const passwordInput = document.getElementById('auth_password');
        const usernameInput = document.getElementById('auth_username');
        
        
        if (!btn || !passwordInput || !usernameInput) {
            console.error('Auth elements not found');
            this.showAlert('Interface elements not found', 'danger');
            return;
        }
        
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
        
        try {
            const apiUrl = '../api/config.php?action=get_auth_credentials';
            
            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
            }
            
            const responseText = await response.text();
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid JSON response from server');
            }
            
            
            if (result.success && result.credentials) {
                // Show current credentials in placeholder
                usernameInput.placeholder = `Current: ${result.credentials.username}`;
                
                // Handle password display
                if (result.credentials.password.startsWith('[Password is')) {
                    // Password is hashed/encrypted
                    passwordInput.placeholder = `Current: ${result.credentials.password}`;
                    this.showAlert('Username shown. Password is encrypted and cannot be displayed.', 'info');
                } else {
                    // Password can be shown (plain text)
                    const hiddenPassword = `Current: ${'•'.repeat(result.credentials.password.length)}`;
                    passwordInput.placeholder = hiddenPassword;
                    
                    // Temporarily show actual password
                    setTimeout(() => {
                        passwordInput.placeholder = `Current: ${result.credentials.password}`;
                    }, 100);
                    
                    // Hide password again after 3 seconds
                    setTimeout(() => {
                        passwordInput.placeholder = hiddenPassword;
                    }, 3000);
                    
                    this.showAlert('Current credentials displayed. Password will hide after 3 seconds.', 'info');
                }
            } else {
                this.showAlert('Unable to load current credentials', 'danger');
            }
        } catch (error) {
            console.error('Show current password error:', error);
            this.showAlert('Error loading current credentials: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
    
    // Test method to verify API works
    async testShowCredentials() {
        try {
            const response = await fetch('../api/config.php?action=get_auth_credentials');
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Direct test error:', error);
        }
    }
    
    async sendBackupToTelegram() {
        const btn = document.getElementById('backup-config');
        if (!btn) {
            console.error('Backup button not found');
            return;
        }
        
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating backup...';
        
        try {
            
            const response = await fetch('../api/mikrotik.php?action=send_backup', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Server returned invalid response');
            }
            
            
            if (result.success) {
                this.showAlert(result.message || 'Backup created successfully!', 'success');
            } else {
                this.showAlert('Error creating backup: ' + (result.message || 'Unknown error'), 'danger');
            }
        } catch (error) {
            console.error('Backup error:', error);
            this.showAlert('Error creating backup: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
    
    async createServiceProfile(e) {
        e.preventDefault();
        
        const btn = e.target.closest('button');
        if (!btn) {
            console.error('Profile service button not found');
            return;
        }
        
        // Prevent action if button is already disabled (profile already created)
        if (btn.disabled) {
            return;
        }
        
        const service = btn.dataset.service;
        if (!service) {
            console.error('Service not defined in button dataset');
            return;
        }
        
        
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';
        
        try {
            const response = await fetch('../api/mikrotik.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_service_profile',
                    service: service
                })
            });
            
            
            const responseText = await response.text();
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Server returned invalid response');
            }
            
            
            if (result.success) {
                this.showAlert(result.message, 'success');
                
                // Update button to indicate profile was created and disable it
                setTimeout(() => {
                    btn.className = 'btn btn-success btn-sm w-100';
                    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Created';
                    btn.disabled = true;
                    btn.style.cursor = 'not-allowed';
                }, 500);
                
            } else {
                // Restore original button state
                btn.innerHTML = originalHtml;
                this.showAlert('Error creating profile: ' + (result.message || 'Unknown error'), 'danger');
            }
        } catch (error) {
            console.error('Profile creation error:', error);
            // Restore original button state
            btn.innerHTML = originalHtml;
            this.showAlert('Error creating profile: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
        }
    }
    
    showAlert(message, type = 'info') {
        const alertsContainer = document.getElementById('alerts-container');
        const alertId = 'alert-' + Date.now();
        
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                <i class="bi bi-${this.getAlertIcon(type)} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        alertsContainer.insertAdjacentHTML('beforeend', alertHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }
    
    getAlertIcon(type) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-triangle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    async createNATMasquerade(e) {
        e.preventDefault();
        
        const btn = e.target.closest('button');
        if (!btn) {
            return;
        }
        
        // Prevent action if button is already disabled (NAT already created)
        if (btn.disabled) {
            return;
        }
        
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';
        
        try {
            const response = await fetch('../api/mikrotik.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_nat_masquerade'
                })
            });
            
            const responseText = await response.text();
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Server returned invalid response');
            }
            
            if (result.success) {
                this.showAlert(result.message || 'NAT masquerade rule created successfully!', 'success');
                
                // Update button immediately to show created state
                this.updateNATButton(true);
                
                // Also refresh NAT status to ensure consistency
                setTimeout(() => {
                    this.checkNATStatus();
                }, 1000);
            } else {
                throw new Error(result.message || 'Failed to create NAT masquerade rule');
            }
        } catch (error) {
            this.showAlert('Error creating NAT masquerade: ' + error.message, 'danger');
        } finally {
            // Reset button state only if creation failed
            if (!btn.classList.contains('btn-success')) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }
}

// Initialize admin panel
window.adminPanelInstance = null;

window.initializeAdminPanel = function() {
    try {
        if (!window.adminPanelInstance) {
            window.adminPanelInstance = new AdminPanel();
        }
    } catch (error) {
        console.error('Failed to initialize AdminPanel:', error.message, error);
    }
};

// Try immediate initialization if DOM is already ready
if (document.readyState === 'loading') {
    // DOM still loading, wait for it
    document.addEventListener('DOMContentLoaded', window.initializeAdminPanel);
} else {
    // DOM already loaded, initialize immediately
    window.initializeAdminPanel();
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.adminPanelInstance && window.adminPanelInstance.connectionInterval) {
        clearInterval(window.adminPanelInstance.connectionInterval);
    }
});