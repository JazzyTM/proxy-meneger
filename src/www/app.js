const { createApp } = Vue;

createApp({
    data() {
        return {
            currentPage: 'domains',
            domains: [],
            newDomains: '',
            destinationIP: localStorage.getItem('destinationIP') || '',
            loading: false,
            processing: {},
            showLogsModal: false,
            showEditModal: false,
            showEditDomainModal: false,
            showProgressModal: false,
            showCreateUserModal: false,
            showEditUserModal: false,
            newUserForm: {
                username: '',
                email: '',
                password: '',
                role: 'user'
            },
            editUserForm: {
                user_id: null,
                email: '',
                role: '',
                password: '',
                confirm_password: ''
            },
            progressTitle: '',
            progressSteps: [],
            currentStep: 0,
            logsTitle: '',
            logsContent: '',
            toasts: [],
            toastId: 0,
            serverIP: '',
            currentUser: {
                username: '',
                role: 'user'
            },
            users: [],
            activityLogs: [],
            settings: {
                rateLimit: true,
                csrfProtection: true,
                activityLogging: true,
                autoRenew: true,
                adminEmail: ''
            },
            editForm: {
                id: null,
                name: '',
                ip: '',
                port: 80,
                tls_version: 'TLSv1.2 TLSv1.3',
                http_version: 'http2',
                proxy_timeout: 60,
                proxy_buffer_size: '4k',
                client_max_body_size: '10m',
                custom_headers: '',
                custom_config: '',
                enable_websocket: false,
                enable_gzip: true,
                enable_cache: false,
                block_exploits: true,
                include_www: false
            },
            editDomainForm: {
                id: null,
                name: '',
                ip: '',
                port: 80,
                tls_version: 'TLSv1.2 TLSv1.3',
                http_version: 'http2',
                proxy_timeout: 60,
                proxy_buffer_size: '4k',
                client_max_body_size: '10m',
                custom_headers: '',
                custom_config: '',
                enable_websocket: false,
                enable_gzip: true,
                enable_cache: false,
                block_exploits: true,
                include_www: false
            }
        }
    },
    computed: {
        activeDomains() {
            return this.domains.filter(d => d.status === 'active').length;
        },
        pendingDomains() {
            return this.domains.filter(d => d.status === 'new' || d.status === 'pending').length;
        },
        errorDomains() {
            return this.domains.filter(d => d.status === 'error').length;
        }
    },
    mounted() {
        this.checkAuth();
        this.loadDomains();
        this.fetchServerIP();
        // Auto-refresh every 30 seconds
        setInterval(() => {
            this.loadDomains();
        }, 30000);
    },
    methods: {
        async checkAuth() {
            try {
                const response = await axios.get('api/auth.php?action=check');
                if (response.data.authenticated) {
                    this.currentUser = response.data.user;
                } else {
                    window.location.href = 'index.php';
                }
            } catch (error) {
                window.location.href = 'index.php';
            }
        },
        async logout() {
            try {
                await axios.get('api/auth.php?action=logout');
                window.location.href = 'index.php';
            } catch (error) {
                window.location.href = 'index.php';
            }
        },
        async fetchServerIP() {
            try {
                const response = await axios.get('https://ipinfo.io/ip');
                this.serverIP = response.data.trim();
            } catch (error) {
                console.error('Failed to fetch server IP:', error);
            }
        },
        getServerIP() {
            return this.serverIP;
        },
        async loadDomains() {
            try {
                const response = await axios.get('api/domains.php');
                if (response.data.success) {
                    this.domains = response.data.data;
                }
            } catch (error) {
                this.showToast('Failed to load domains', 'error');
                console.error(error);
            }
        },
        async addDomains() {
            if (!this.newDomains.trim() || !this.destinationIP.trim()) {
                this.showToast('Please fill in all fields', 'error');
                return;
            }

            this.loading = true;
            try {
                const names = this.newDomains.split('\n')
                    .map(line => line.trim())
                    .filter(line => line.length > 0);

                if (names.length === 0) {
                    this.showToast('Please enter at least one domain', 'error');
                    this.loading = false;
                    return;
                }

                const response = await axios.post('api/domains.php', {
                    names: names,
                    ip: this.destinationIP
                });

                if (response.data.success) {
                    this.showToast(response.data.message, 'success');
                    this.newDomains = '';
                    localStorage.setItem('destinationIP', this.destinationIP);
                    await this.loadDomains();
                    
                    // Show details if some were skipped
                    if (response.data.skipped && response.data.skipped.length > 0) {
                        this.showToast(`Skipped (already exist): ${response.data.skipped.join(', ')}`, 'warning');
                    }
                } else {
                    let errorMsg = response.data.error || 'Failed to add domains';
                    if (response.data.errors && response.data.errors.length > 0) {
                        errorMsg += ': ' + response.data.errors.join(', ');
                    }
                    this.showToast(errorMsg, 'error');
                    console.error('Add domains error:', response.data);
                }
            } catch (error) {
                const errorMsg = error.response?.data?.error || error.message || 'Error adding domains';
                this.showToast(errorMsg, 'error');
                console.error('Add domains exception:', error);
                console.error('Response data:', error.response?.data);
            } finally {
                this.loading = false;
            }
        },
        async generateCertificate(domainId) {
            this.processing[domainId] = true;
            
            const steps = [
                'Checking DNS records',
                'Validating domain',
                'Requesting certificate from Let\'s Encrypt',
                'Installing certificate',
                'Verifying installation'
            ];
            
            this.showProgress('Generating SSL Certificate', steps);

            try {
                this.updateProgress(0);
                await new Promise(resolve => setTimeout(resolve, 500));
                
                this.updateProgress(1);
                const response = await axios.post('api/certificates.php?action=generate', {
                    domain_id: domainId
                });

                this.updateProgress(4);
                await new Promise(resolve => setTimeout(resolve, 500));
                
                this.hideProgress();

                if (response.data.success) {
                    this.showToast('Certificate generated successfully!', 'success');
                    this.showLogs({
                        name: 'Certificate Generation',
                        error_log: response.data.logs.join('\n')
                    });
                } else {
                    this.showToast(response.data.error || 'Certificate generation failed', 'error');
                    this.showLogs({
                        name: 'Certificate Generation Error',
                        error_log: response.data.logs.join('\n')
                    });
                }
                await this.loadDomains();
            } catch (error) {
                this.hideProgress();
                this.showToast('Error generating certificate', 'error');
                console.error(error);
            } finally {
                this.processing[domainId] = false;
            }
        },
        editDomain(domain) {
            this.editForm = {
                id: domain.id,
                name: domain.name,
                ip: domain.ip || this.destinationIP,
                port: domain.port || 80,
                tls_version: domain.tls_version || 'TLSv1.2 TLSv1.3',
                http_version: domain.http_version || 'http2',
                proxy_timeout: domain.proxy_timeout || 60,
                proxy_buffer_size: domain.proxy_buffer_size || '4k',
                client_max_body_size: domain.client_max_body_size || '10m',
                custom_headers: domain.custom_headers || '',
                custom_config: domain.custom_config || '',
                enable_websocket: domain.enable_websocket == 1,
                enable_gzip: domain.enable_gzip == 1
            };
            this.showEditModal = true;
        },
        async saveDomainSettings() {
            this.loading = true;
            try {
                const response = await axios.put('api/domains.php', {
                    id: this.editForm.id,
                    ip: this.editForm.ip,
                    port: this.editForm.port,
                    tls_version: this.editForm.tls_version,
                    http_version: this.editForm.http_version,
                    proxy_timeout: this.editForm.proxy_timeout,
                    proxy_buffer_size: this.editForm.proxy_buffer_size,
                    client_max_body_size: this.editForm.client_max_body_size,
                    custom_headers: this.editForm.custom_headers,
                    custom_config: this.editForm.custom_config,
                    enable_websocket: this.editForm.enable_websocket ? 1 : 0,
                    enable_gzip: this.editForm.enable_gzip ? 1 : 0
                });

                if (response.data.success) {
                    this.showToast('Settings saved! Regenerate Nginx config to apply changes.', 'success');
                    this.showEditModal = false;
                    await this.loadDomains();
                } else {
                    this.showToast(response.data.error || 'Failed to save settings', 'error');
                }
            } catch (error) {
                this.showToast('Error saving settings', 'error');
                console.error(error);
            } finally {
                this.loading = false;
            }
        },
        async generateNginxConfig(domainId) {
            this.processing[domainId] = true;
            
            const steps = [
                'Loading domain settings',
                'Checking certificate',
                'Generating configuration',
                'Writing config file',
                'Validating syntax'
            ];
            
            this.showProgress('Generating Nginx Configuration', steps);

            try {
                this.updateProgress(0);
                await new Promise(resolve => setTimeout(resolve, 300));
                
                this.updateProgress(1);
                await new Promise(resolve => setTimeout(resolve, 300));
                
                this.updateProgress(2);
                const response = await axios.post('api/nginx.php?action=generate_config', {
                    domain_id: domainId,
                    destination_ip: this.destinationIP
                });

                this.updateProgress(4);
                await new Promise(resolve => setTimeout(resolve, 300));
                
                this.hideProgress();

                if (response.data.success) {
                    this.showToast('Nginx config generated!', 'success');
                    this.showLogs({
                        name: 'Nginx Config Generation',
                        error_log: response.data.logs.join('\n')
                    });
                } else {
                    this.showToast(response.data.error || 'Config generation failed', 'error');
                    if (response.data.logs) {
                        this.showLogs({
                            name: 'Config Generation Error',
                            error_log: response.data.logs.join('\n')
                        });
                    }
                }
                await this.loadDomains();
            } catch (error) {
                this.hideProgress();
                this.showToast('Error generating config', 'error');
                console.error(error);
            } finally {
                this.processing[domainId] = false;
            }
        },
        async deleteDomain(domainId) {
            if (!confirm('Are you sure you want to delete this domain? This will also remove its Nginx configuration file.')) {
                return;
            }

            this.processing[domainId] = true;
            this.showToast('Deleting domain...', 'info');
            
            try {
                const response = await axios.delete(`api/domains.php?id=${domainId}`, {
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.data.success) {
                    this.showToast('Domain deleted successfully', 'success');
                    await this.loadDomains();
                } else {
                    this.showToast(response.data.error || 'Failed to delete domain', 'error');
                    console.error('Delete error:', response.data);
                }
            } catch (error) {
                const errorMsg = error.response?.data?.error || error.message || 'Error deleting domain';
                this.showToast(errorMsg, 'error');
                console.error('Delete exception:', error);
                console.error('Response:', error.response);
            } finally {
                this.processing[domainId] = false;
            }
        },
        editDomain(domain) {
            this.editDomainForm = {
                id: domain.id,
                name: domain.name,
                ip: domain.ip || '',
                port: domain.port || 80,
                tls_version: domain.tls_version || 'TLSv1.2 TLSv1.3',
                http_version: domain.http_version || 'http2',
                proxy_timeout: domain.proxy_timeout || 60,
                proxy_buffer_size: domain.proxy_buffer_size || '4k',
                client_max_body_size: domain.client_max_body_size || '10m',
                custom_headers: domain.custom_headers || '',
                custom_config: domain.custom_config || '',
                enable_websocket: domain.enable_websocket || false,
                enable_gzip: domain.enable_gzip !== undefined ? domain.enable_gzip : true,
                enable_cache: domain.enable_cache || false,
                block_exploits: domain.block_exploits !== undefined ? domain.block_exploits : true,
                include_www: domain.include_www || false
            };
            this.showEditDomainModal = true;
        },
        async updateDomain() {
            try {
                this.showToast('Updating domain settings...', 'info');
                
                const response = await axios.put('api/domains.php', this.editDomainForm, {
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.data.success) {
                    this.showToast('Domain updated successfully', 'success');
                    this.showEditDomainModal = false;
                    await this.loadDomains();
                    
                    // Auto-regenerate config after update
                    this.showToast('Regenerating Nginx config...', 'info');
                    await this.generateNginxConfig(this.editDomainForm.id);
                } else {
                    this.showToast(response.data.error || 'Failed to update domain', 'error');
                }
            } catch (error) {
                const errorMsg = error.response?.data?.error || error.message || 'Error updating domain';
                this.showToast(errorMsg, 'error');
                console.error('Update error:', error);
            }
        },
        async testNginx() {
            this.loading = true;
            this.showToast('Testing Nginx configuration...', 'info');

            try {
                const response = await axios.get('api/nginx.php?action=test');
                if (response.data.success) {
                    this.showToast('Nginx configuration is valid!', 'success');
                    this.showLogs({
                        name: 'Nginx Test',
                        error_log: response.data.logs.join('\n')
                    });
                } else {
                    this.showToast('Nginx configuration has errors', 'error');
                    this.showLogs({
                        name: 'Nginx Test Error',
                        error_log: response.data.logs.join('\n')
                    });
                }
            } catch (error) {
                this.showToast('Error testing Nginx', 'error');
                console.error(error);
            } finally {
                this.loading = false;
            }
        },
        async reloadNginx() {
            this.loading = true;
            this.showToast('Reloading Nginx...', 'info');

            try {
                const response = await axios.get('api/nginx.php?action=reload');
                if (response.data.success) {
                    this.showToast('Nginx reloaded successfully!', 'success');
                    this.showLogs({
                        name: 'Nginx Reload',
                        error_log: response.data.logs.join('\n')
                    });
                } else {
                    this.showToast('Nginx reload failed', 'error');
                    this.showLogs({
                        name: 'Nginx Reload Error',
                        error_log: response.data.logs.join('\n')
                    });
                }
            } catch (error) {
                this.showToast('Error reloading Nginx', 'error');
                console.error(error);
            } finally {
                this.loading = false;
            }
        },
        showLogs(domain) {
            this.logsTitle = `Logs: ${domain.name}`;
            this.logsContent = domain.error_log || 'No logs available';
            this.showLogsModal = true;
        },
        showSystemLogs() {
            this.logsTitle = 'System Logs';
            this.logsContent = 'System logs feature coming soon...';
            this.showLogsModal = true;
        },
        showProgress(title, steps) {
            this.progressTitle = title;
            this.progressSteps = steps;
            this.currentStep = 0;
            this.showProgressModal = true;
        },
        updateProgress(step) {
            this.currentStep = step;
        },
        hideProgress() {
            this.showProgressModal = false;
            this.progressSteps = [];
            this.currentStep = 0;
        },
        showToast(message, type = 'info') {
            const id = this.toastId++;
            this.toasts.push({ id, message, type });
            setTimeout(() => {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }, 5000);
        },
        getStatusClass(status) {
            const classes = {
                'active': 'bg-green-500/20 text-green-400 border border-green-500/50',
                'new': 'bg-blue-500/20 text-blue-400 border border-blue-500/50',
                'pending': 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/50',
                'error': 'bg-red-500/20 text-red-400 border border-red-500/50'
            };
            return classes[status] || classes['pending'];
        },
        getCertStatusClass(status) {
            const classes = {
                'valid': 'bg-green-500/20 text-green-400 border border-green-500/50',
                'pending': 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/50',
                'cert_failed': 'bg-red-500/20 text-red-400 border border-red-500/50',
                'dns_mismatch': 'bg-orange-500/20 text-orange-400 border border-orange-500/50'
            };
            return classes[status] || classes['pending'];
        },
        getToastClass(type) {
            const classes = {
                'success': 'bg-green-600 text-white',
                'error': 'bg-red-600 text-white',
                'info': 'bg-blue-600 text-white',
                'warning': 'bg-yellow-600 text-white'
            };
            return classes[type] || classes['info'];
        },
        getToastIcon(type) {
            const icons = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-circle',
                'info': 'fas fa-info-circle',
                'warning': 'fas fa-exclamation-triangle'
            };
            return icons[type] || icons['info'];
        },
        
        // New methods for additional pages
        async loadUsers() {
            if (this.currentUser.role !== 'admin') return;
            
            try {
                const response = await axios.get('api/users.php?action=list', {
                    withCredentials: true
                });
                if (response.data.success) {
                    this.users = response.data.users || [];
                    console.log('Users loaded:', this.users);
                } else {
                    console.error('Failed to load users:', response.data);
                }
            } catch (error) {
                console.error('Error loading users:', error);
                this.showToast(error.response?.data?.error || 'Failed to load users', 'error');
            }
        },
        
        async loadActivityLogs() {
            try {
                const response = await axios.get('api/users.php?action=activity_log', {
                    withCredentials: true
                });
                if (response.data.success) {
                    this.activityLogs = response.data.logs || [];
                }
            } catch (error) {
                console.error('Error loading activity logs:', error);
                this.showToast('Failed to load activity logs', 'error');
            }
        },
        
        async renewCertificate(domainId) {
            this.showToast('Renewing certificate...', 'info');
            await this.generateCertificate(domainId);
        },
        
        async viewCertificate(domainName) {
            try {
                const response = await axios.get(`api/certificates.php?action=view&domain=${domainName}`, {
                    withCredentials: true
                });
                if (response.data.success) {
                    this.logsTitle = `Certificate Details: ${domainName}`;
                    this.logsContent = response.data.certificate;
                    this.showLogsModal = true;
                }
            } catch (error) {
                this.showToast('Failed to load certificate', 'error');
            }
        },
        
        async revokeCertificate(domainId) {
            if (!confirm('Are you sure you want to revoke this certificate? This action cannot be undone.')) {
                return;
            }
            
            this.showProgressModal = true;
            this.progressTitle = 'Revoking Certificate';
            this.progressSteps = ['Connecting to Let\'s Encrypt', 'Revoking certificate', 'Updating status'];
            this.currentStep = 0;
            
            try {
                const response = await axios.post('api/certificates.php?action=revoke', {
                    domain_id: domainId
                }, {
                    withCredentials: true
                });
                
                this.currentStep = this.progressSteps.length;
                
                if (response.data.success) {
                    this.showToast('Certificate revoked successfully', 'success');
                    await this.loadDomains();
                } else {
                    this.showToast(response.data.error || 'Failed to revoke certificate', 'error');
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to revoke certificate', 'error');
            } finally {
                setTimeout(() => {
                    this.showProgressModal = false;
                }, 1000);
            }
        },
        
        async deleteCertificate(domainId) {
            if (!confirm('Are you sure you want to delete this certificate? The domain will revert to HTTP-only.')) {
                return;
            }
            
            this.showProgressModal = true;
            this.progressTitle = 'Deleting Certificate';
            this.progressSteps = ['Removing certificate files', 'Updating Nginx config', 'Reloading Nginx'];
            this.currentStep = 0;
            
            try {
                const response = await axios.post('api/certificates.php?action=delete', {
                    domain_id: domainId
                }, {
                    withCredentials: true
                });
                
                this.currentStep = this.progressSteps.length;
                
                if (response.data.success) {
                    this.showToast('Certificate deleted successfully', 'success');
                    await this.loadDomains();
                } else {
                    this.showToast(response.data.error || 'Failed to delete certificate', 'error');
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to delete certificate', 'error');
            } finally {
                setTimeout(() => {
                    this.showProgressModal = false;
                }, 1000);
            }
        },
        
        async toggleUserStatus(user) {
            if (!confirm(`Are you sure you want to ${user.is_active ? 'deactivate' : 'activate'} ${user.username}?`)) {
                return;
            }
            
            try {
                const response = await axios.post('api/users.php?action=toggle_status', {
                    user_id: user.id
                }, {
                    withCredentials: true
                });
                
                if (response.data.success) {
                    this.showToast(response.data.message, 'success');
                    await this.loadUsers();
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to update user status', 'error');
            }
        },
        
        async saveSettings() {
            try {
                const response = await axios.post('api/users.php?action=save_settings', this.settings, {
                    withCredentials: true
                });
                if (response.data.success) {
                    this.showToast('Settings saved successfully', 'success');
                }
            } catch (error) {
                this.showToast('Failed to save settings', 'error');
            }
        },
        
        async createNewUser() {
            try {
                const response = await axios.post('api/users.php?action=create', this.newUserForm, {
                    withCredentials: true
                });
                if (response.data.success) {
                    this.showToast('User created successfully', 'success');
                    this.showCreateUserModal = false;
                    this.newUserForm = { username: '', email: '', password: '', role: 'user' };
                    await this.loadUsers();
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to create user', 'error');
            }
        },
        
        editUser(user) {
            this.editUserForm = {
                user_id: user.id,
                email: user.email,
                role: user.role,
                password: '',
                confirm_password: ''
            };
            this.showEditUserModal = true;
        },
        
        async updateUserData() {
            // Validate passwords if provided
            if (this.editUserForm.password || this.editUserForm.confirm_password) {
                if (this.editUserForm.password !== this.editUserForm.confirm_password) {
                    this.showToast('Passwords do not match', 'error');
                    return;
                }
                if (this.editUserForm.password.length < 6) {
                    this.showToast('Password must be at least 6 characters', 'error');
                    return;
                }
            }
            
            try {
                const response = await axios.post('api/users.php?action=update', this.editUserForm, {
                    withCredentials: true
                });
                if (response.data.success) {
                    this.showToast('User updated successfully', 'success');
                    this.showEditUserModal = false;
                    await this.loadUsers();
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to update user', 'error');
            }
        }
    },
    
    watch: {
        currentPage(newPage) {
            // Load data when switching pages
            if (newPage === 'users') {
                this.loadUsers();
            } else if (newPage === 'activity') {
                this.loadActivityLogs();
            } else if (newPage === 'domains') {
                this.loadDomains();
            }
        }
    }
}).mount('#app');
