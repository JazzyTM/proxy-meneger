const { createApp } = Vue;

createApp({
    data() {
        return {
            users: [],
            loading: false,
            showAddUserModal: false,
            showEditUserModal: false,
            userForm: {
                id: null,
                username: '',
                email: '',
                password: '',
                role: 'user'
            },
            toasts: [],
            toastId: 0
        }
    },
    computed: {
        adminCount() {
            return this.users.filter(u => u.role === 'admin').length;
        },
        activeCount() {
            return this.users.filter(u => u.is_active === 1).length;
        }
    },
    mounted() {
        this.loadUsers();
    },
    methods: {
        async loadUsers() {
            try {
                const response = await axios.get('api/users.php');
                if (response.data.success) {
                    this.users = response.data.data;
                }
            } catch (error) {
                this.showToast('Failed to load users', 'error');
                console.error(error);
            }
        },
        async createUser() {
            this.loading = true;
            try {
                const response = await axios.post('api/users.php', this.userForm);
                if (response.data.success) {
                    this.showToast('User created successfully', 'success');
                    this.closeModals();
                    await this.loadUsers();
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to create user', 'error');
            } finally {
                this.loading = false;
            }
        },
        editUser(user) {
            this.userForm = {
                id: user.id,
                username: user.username,
                email: user.email,
                password: '',
                role: user.role
            };
            this.showEditUserModal = true;
        },
        async updateUser() {
            this.loading = true;
            try {
                const data = new URLSearchParams();
                data.append('id', this.userForm.id);
                data.append('email', this.userForm.email);
                data.append('role', this.userForm.role);
                
                const response = await axios.put('api/users.php', data);
                if (response.data.success) {
                    this.showToast('User updated successfully', 'success');
                    this.closeModals();
                    await this.loadUsers();
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to update user', 'error');
            } finally {
                this.loading = false;
            }
        },
        async toggleUserStatus(user) {
            try {
                const data = new URLSearchParams();
                data.append('id', user.id);
                data.append('is_active', user.is_active ? 0 : 1);
                
                const response = await axios.put('api/users.php', data);
                if (response.data.success) {
                    this.showToast(`User ${user.is_active ? 'deactivated' : 'activated'}`, 'success');
                    await this.loadUsers();
                }
            } catch (error) {
                this.showToast('Failed to update user status', 'error');
            }
        },
        async deleteUser(user) {
            if (!confirm(`Are you sure you want to delete user "${user.username}"?`)) {
                return;
            }
            
            try {
                const response = await axios.delete(`api/users.php?id=${user.id}`);
                if (response.data.success) {
                    this.showToast('User deleted successfully', 'success');
                    await this.loadUsers();
                }
            } catch (error) {
                this.showToast(error.response?.data?.error || 'Failed to delete user', 'error');
            }
        },
        closeModals() {
            this.showAddUserModal = false;
            this.showEditUserModal = false;
            this.userForm = {
                id: null,
                username: '',
                email: '',
                password: '',
                role: 'user'
            };
        },
        formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleString();
        },
        showToast(message, type = 'info') {
            const id = this.toastId++;
            this.toasts.push({ id, message, type });
            setTimeout(() => {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }, 5000);
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
        }
    }
}).mount('#app');
