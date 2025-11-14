<?php
require_once(__DIR__ . '/includes/Security.php');
require_once(__DIR__ . '/includes/Session.php');
require_once(__DIR__ . '/database/users_db.php');

// Configure secure session
Security::configureSession();
Security::setSecurityHeaders();

// Initialize session manager
$db = new UsersDB();
$session = new Session($db);

// Check if already logged in
if ($session->validate()) {
    header("Location: dashboard.php");
    exit;
}

// Generate CSRF token for forms
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Reverse Proxy UI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        [v-cloak] { display: none; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen flex items-center justify-center p-6">
    <div id="app" v-cloak class="w-full max-w-md">
        <div class="bg-gray-800/50 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-gray-700">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-full mb-4">
                    <i class="fas fa-shield-alt text-3xl text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Reverse Proxy UI</h1>
                <p class="text-gray-400">Modern SSL & Nginx Management</p>
            </div>

            <!-- Tabs -->
            <div class="flex mb-6 bg-gray-900/50 rounded-lg p-1">
                <button 
                    @click="activeTab = 'login'"
                    :class="activeTab === 'login' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'"
                    class="flex-1 py-2 rounded-lg transition font-semibold"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
                <button 
                    @click="activeTab = 'register'"
                    :class="activeTab === 'register' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'"
                    class="flex-1 py-2 rounded-lg transition font-semibold"
                >
                    <i class="fas fa-user-plus mr-2"></i>Register
                </button>
            </div>

            <!-- Alert Messages -->
            <div v-if="message" :class="messageType === 'error' ? 'bg-red-500/20 border-red-500 text-red-400' : 'bg-green-500/20 border-green-500 text-green-400'" 
                class="border px-4 py-3 rounded-lg mb-6 flex items-center">
                <i :class="messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'" class="fas mr-3"></i>
                <span>{{ message }}</span>
            </div>

            <!-- Login Form -->
            <form v-if="activeTab === 'login'" @submit.prevent="login">
                <div class="mb-4">
                    <label class="block text-gray-300 text-sm font-semibold mb-2">
                        <i class="fas fa-user mr-2"></i>Username or Email
                    </label>
                    <input 
                        v-model="loginForm.username"
                        type="text" 
                        class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        placeholder="Enter username or email"
                        required
                    >
                </div>

                <div class="mb-6">
                    <label class="block text-gray-300 text-sm font-semibold mb-2">
                        <i class="fas fa-lock mr-2"></i>Password
                    </label>
                    <div class="relative">
                        <input 
                            v-model="loginForm.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="Enter your password"
                            required
                        >
                        <button 
                            type="button" 
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white transition"
                        >
                            <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                        </button>
                    </div>
                </div>

                <button 
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 rounded-lg transition transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    {{ loading ? 'Signing in...' : 'Sign In' }}
                </button>
            </form>

            <!-- Register Form -->
            <form v-if="activeTab === 'register'" @submit.prevent="register">
                <div class="mb-4">
                    <label class="block text-gray-300 text-sm font-semibold mb-2">
                        <i class="fas fa-user mr-2"></i>Username
                    </label>
                    <input 
                        v-model="registerForm.username"
                        type="text" 
                        class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        placeholder="Choose a username"
                        required
                        minlength="3"
                    >
                </div>

                <div class="mb-4">
                    <label class="block text-gray-300 text-sm font-semibold mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email
                    </label>
                    <input 
                        v-model="registerForm.email"
                        type="email" 
                        class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        placeholder="your@email.com"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label class="block text-gray-300 text-sm font-semibold mb-2">
                        <i class="fas fa-lock mr-2"></i>Password
                    </label>
                    <div class="relative">
                        <input 
                            v-model="registerForm.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="Choose a password"
                            required
                            minlength="6"
                        >
                        <button 
                            type="button" 
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white transition"
                        >
                            <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-300 text-sm font-semibold mb-2">
                        <i class="fas fa-lock mr-2"></i>Confirm Password
                    </label>
                    <input 
                        v-model="registerForm.confirm_password"
                        :type="showPassword ? 'text' : 'password'"
                        class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        placeholder="Confirm your password"
                        required
                    >
                </div>

                <button 
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold py-3 rounded-lg transition transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-user-plus mr-2"></i>
                    {{ loading ? 'Creating account...' : 'Create Account' }}
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-700 text-center text-sm text-gray-400">
                <p>© 2025 Reverse Proxy UI</p>
                <p class="mt-2">Made with <i class="fas fa-heart text-red-500"></i> by <a href="https://t.me/jazzytm" target="_blank" class="text-blue-400 hover:text-blue-300">@jazzytm</a></p>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script>
        const { createApp } = Vue;
        
        // Configure axios to send cookies
        axios.defaults.withCredentials = true;
        
        createApp({
            data() {
                return {
                    activeTab: 'login',
                    showPassword: false,
                    loading: false,
                    message: '',
                    messageType: '',
                    loginForm: {
                        username: '',
                        password: ''
                    },
                    registerForm: {
                        username: '',
                        email: '',
                        password: '',
                        confirm_password: ''
                    }
                }
            },
            methods: {
                async login() {
                    this.loading = true;
                    this.message = '';
                    
                    try {
                        const response = await axios.post('api/auth.php?action=login', this.loginForm, {
                            withCredentials: true
                        });
                        
                        if (response.data.success) {
                            // Redirect immediately
                            window.location.href = 'dashboard.php';
                        }
                    } catch (error) {
                        this.message = error.response?.data?.error || 'Login failed';
                        this.messageType = 'error';
                    } finally {
                        this.loading = false;
                    }
                },
                async register() {
                    this.loading = true;
                    this.message = '';
                    
                    try {
                        const response = await axios.post('api/auth.php?action=register', this.registerForm, {
                            withCredentials: true
                        });
                        
                        if (response.data.success) {
                            this.message = response.data.message;
                            this.messageType = 'success';
                            this.registerForm = { username: '', email: '', password: '', confirm_password: '' };
                            setTimeout(() => {
                                this.activeTab = 'login';
                                this.message = '';
                            }, 2000);
                        }
                    } catch (error) {
                        this.message = error.response?.data?.error || 'Registration failed';
                        this.messageType = 'error';
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }).mount('#app');
    </script>
</body>
</html>
