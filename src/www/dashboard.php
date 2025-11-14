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

// Require authentication
if (!$session->validate()) {
    header("Location: index.php");
    exit;
}

// Get current user
$currentUser = $session->getUser();

// Generate CSRF token
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reverse Proxy UI - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        [v-cloak] { display: none; }
        .fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
        .fade-enter-from, .fade-leave-to { opacity: 0; }
        .slide-enter-active, .slide-leave-active { transition: all 0.3s; }
        .slide-enter-from { transform: translateY(-10px); opacity: 0; }
        .slide-leave-to { transform: translateY(10px); opacity: 0; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen text-white">
    <div id="app" v-cloak>
        <!-- Header -->
        <header class="bg-gray-800/50 backdrop-blur-lg border-b border-gray-700 sticky top-0 z-50">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-shield-alt text-3xl text-blue-500"></i>
                        <div>
                            <h1 class="text-2xl font-bold">Proxy Manager</h1>
                            <p class="text-sm text-gray-400">Modern SSL & Nginx Management</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-400 flex items-center">
                            <i class="fas fa-user-circle mr-2"></i>
                            {{ currentUser.username }}
                            <span v-if="currentUser.role === 'admin'" class="ml-2 px-2 py-1 bg-purple-600 text-xs rounded">Admin</span>
                        </span>
                        <button @click="logout" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition" title="Logout">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </div>
                </div>
                
                <!-- Navigation Tabs -->
                <div class="mt-4 flex space-x-2 border-b border-gray-700">
                    <button 
                        @click="currentPage = 'domains'"
                        :class="currentPage === 'domains' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'"
                        class="px-4 py-2 rounded-t-lg transition flex items-center space-x-2"
                    >
                        <i class="fas fa-globe"></i>
                        <span>Domains</span>
                    </button>
                    <button 
                        @click="currentPage = 'certificates'"
                        :class="currentPage === 'certificates' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'"
                        class="px-4 py-2 rounded-t-lg transition flex items-center space-x-2"
                    >
                        <i class="fas fa-certificate"></i>
                        <span>SSL Certificates</span>
                    </button>
                    <button 
                        @click="currentPage = 'activity'"
                        :class="currentPage === 'activity' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'"
                        class="px-4 py-2 rounded-t-lg transition flex items-center space-x-2"
                    >
                        <i class="fas fa-history"></i>
                        <span>Activity Log</span>
                    </button>
                    <button 
                        v-if="currentUser.role === 'admin'"
                        @click="currentPage = 'users'"
                        :class="currentPage === 'users' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'"
                        class="px-4 py-2 rounded-t-lg transition flex items-center space-x-2"
                    >
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </button>
                    <button 
                        v-if="currentUser.role === 'admin'"
                        @click="currentPage = 'settings'"
                        :class="currentPage === 'settings' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'"
                        class="px-4 py-2 rounded-t-lg transition flex items-center space-x-2"
                    >
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            
            <!-- Domains Page -->
            <div v-if="currentPage === 'domains'">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-sm">Total Domains</p>
                            <p class="text-3xl font-bold mt-2">{{ domains.length }}</p>
                        </div>
                        <i class="fas fa-globe text-4xl text-blue-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-200 text-sm">Active</p>
                            <p class="text-3xl font-bold mt-2">{{ activeDomains }}</p>
                        </div>
                        <i class="fas fa-check-circle text-4xl text-green-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-yellow-600 to-yellow-700 rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-200 text-sm">Pending</p>
                            <p class="text-3xl font-bold mt-2">{{ pendingDomains }}</p>
                        </div>
                        <i class="fas fa-clock text-4xl text-yellow-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-red-600 to-red-700 rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-200 text-sm">Errors</p>
                            <p class="text-3xl font-bold mt-2">{{ errorDomains }}</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-red-300"></i>
                    </div>
                </div>
            </div>

            <!-- Add Domain Section -->
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 shadow-xl mb-8 border border-gray-700">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-blue-500"></i>
                    Add New Domains
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">Domain Names (one per line)</label>
                        <textarea 
                            v-model="newDomains" 
                            rows="4" 
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="example.com&#10;subdomain.example.com&#10;another-domain.com"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Destination IP</label>
                        <input 
                            v-model="destinationIP" 
                            type="text" 
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition mb-4"
                            placeholder="192.168.1.100"
                        >
                        <button 
                            @click="addDomains" 
                            :disabled="loading"
                            class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 rounded-lg font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i class="fas fa-save mr-2"></i>
                            {{ loading ? 'Adding...' : 'Add Domains' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Domains Table -->
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl shadow-xl border border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <h2 class="text-xl font-bold flex items-center">
                        <i class="fas fa-list mr-2 text-blue-500"></i>
                        Managed Domains
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Domain</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Status</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Certificate</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Destination IP</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Resolved IP</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="domain in domains" :key="domain.id" class="border-t border-gray-700 hover:bg-gray-700/30 transition">
                                <td class="px-6 py-4">
                                    <a :href="'https://' + domain.name" target="_blank" class="text-blue-400 hover:text-blue-300 flex items-center">
                                        <i class="fas fa-external-link-alt mr-2 text-xs"></i>
                                        {{ domain.name }}
                                    </a>
                                    <p class="text-xs text-gray-500 mt-1">Added: {{ domain.date }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="getStatusClass(domain.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                        {{ domain.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="getCertStatusClass(domain.cert_status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                        {{ domain.cert_status || 'pending' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ domain.ip || '-' }}</td>
                                <td class="px-6 py-4">
                                    <span :class="domain.resolved_ip === getServerIP() ? 'text-green-400' : 'text-red-400'" class="text-sm font-mono">
                                        {{ domain.resolved_ip }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button 
                                            @click="editDomain(domain)"
                                            class="group relative px-3 py-1 bg-purple-600 hover:bg-purple-700 rounded text-xs transition flex items-center space-x-1"
                                        >
                                            <i class="fas fa-edit"></i>
                                            <span class="hidden md:inline">Edit</span>
                                            <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">
                                                Edit domain settings
                                            </span>
                                        </button>
                                        <button 
                                            @click="generateCertificate(domain.id)"
                                            :disabled="processing[domain.id]"
                                            class="group relative px-3 py-1 bg-green-600 hover:bg-green-700 rounded text-xs transition disabled:opacity-50 flex items-center space-x-1"
                                        >
                                            <i class="fas fa-certificate"></i>
                                            <span class="hidden md:inline">SSL</span>
                                            <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">
                                                Generate SSL certificate
                                            </span>
                                        </button>
                                        <button 
                                            @click="generateNginxConfig(domain.id)"
                                            :disabled="processing[domain.id]"
                                            class="group relative px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs transition disabled:opacity-50 flex items-center space-x-1"
                                        >
                                            <i class="fas fa-cog"></i>
                                            <span class="hidden md:inline">Config</span>
                                            <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">
                                                Generate Nginx config
                                            </span>
                                        </button>
                                        <button 
                                            @click="showLogs(domain)"
                                            class="group relative px-3 py-1 bg-yellow-600 hover:bg-yellow-700 rounded text-xs transition flex items-center space-x-1"
                                        >
                                            <i class="fas fa-file-alt"></i>
                                            <span class="hidden md:inline">Logs</span>
                                            <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">
                                                View domain logs
                                            </span>
                                        </button>
                                        <button 
                                            @click="deleteDomain(domain.id)"
                                            :disabled="processing[domain.id]"
                                            class="group relative px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-xs transition disabled:opacity-50 flex items-center space-x-1"
                                        >
                                            <i class="fas fa-trash"></i>
                                            <span class="hidden md:inline">Delete</span>
                                            <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">
                                                Delete domain
                                            </span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="domains.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-4"></i>
                                    <p>No domains added yet. Add your first domain above!</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Nginx Controls -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                <button 
                    @click="testNginx"
                    :disabled="loading"
                    class="px-6 py-4 bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-700 hover:to-yellow-800 rounded-xl font-semibold transition disabled:opacity-50 shadow-xl"
                >
                    <i class="fas fa-check-circle mr-2"></i>
                    Test Nginx Config
                </button>
                <button 
                    @click="reloadNginx"
                    :disabled="loading"
                    class="px-6 py-4 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 rounded-xl font-semibold transition disabled:opacity-50 shadow-xl"
                >
                    <i class="fas fa-sync-alt mr-2"></i>
                    Reload Nginx
                </button>
                <button 
                    @click="showSystemLogs"
                    class="px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 rounded-xl font-semibold transition shadow-xl"
                >
                    <i class="fas fa-terminal mr-2"></i>
                    System Logs
                </button>
            </div>
            </div>
            <!-- End Domains Page -->
            
            <!-- Certificates Page -->
            <div v-if="currentPage === 'certificates'" class="space-y-6">
                <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 shadow-xl border border-gray-700">
                    <h2 class="text-xl font-bold mb-4 flex items-center">
                        <i class="fas fa-certificate mr-2 text-green-500"></i>
                        SSL Certificates Management
                    </h2>
                    <p class="text-gray-400 mb-6">Manage SSL certificates for your domains. Certificates are automatically renewed before expiration.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="domain in domains.filter(d => d.cert_status && d.cert_status !== 'none' && d.cert_status !== 'pending')" :key="domain.id" class="bg-gray-900/50 rounded-lg p-4 border border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold">{{ domain.name }}</h3>
                                <span :class="getCertStatusClass(domain.cert_status)" class="px-2 py-1 rounded text-xs">
                                    {{ domain.cert_status }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-400 space-y-1">
                                <p><i class="fas fa-calendar mr-2"></i>Issued: {{ domain.cert_issued || 'N/A' }}</p>
                                <p><i class="fas fa-clock mr-2"></i>Expires: {{ domain.cert_expires || 'N/A' }}</p>
                            </div>
                            <div class="mt-4 space-y-2">
                                <div class="flex space-x-2">
                                    <button @click="renewCertificate(domain.id)" class="flex-1 px-3 py-2 bg-green-600 hover:bg-green-700 rounded text-xs transition" title="Renew certificate">
                                        <i class="fas fa-sync-alt mr-1"></i>Renew
                                    </button>
                                    <button @click="viewCertificate(domain.name)" class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded text-xs transition" title="View certificate details">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </button>
                                </div>
                                <div class="flex space-x-2">
                                    <button @click="revokeCertificate(domain.id)" class="flex-1 px-3 py-2 bg-orange-600 hover:bg-orange-700 rounded text-xs transition" title="Revoke certificate">
                                        <i class="fas fa-ban mr-1"></i>Revoke
                                    </button>
                                    <button @click="deleteCertificate(domain.id)" class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 rounded text-xs transition" title="Delete certificate">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Log Page -->
            <div v-if="currentPage === 'activity'" class="space-y-6">
                <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 shadow-xl border border-gray-700">
                    <h2 class="text-xl font-bold mb-4 flex items-center">
                        <i class="fas fa-history mr-2 text-blue-500"></i>
                        Activity Log
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Time</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">User</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Action</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Details</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in activityLogs" :key="log.id" class="border-t border-gray-700 hover:bg-gray-700/30 transition">
                                    <td class="px-4 py-3 text-sm">{{ log.created_at }}</td>
                                    <td class="px-4 py-3 text-sm">{{ log.username }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 bg-blue-600 rounded text-xs">{{ log.action }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-400">{{ log.details }}</td>
                                    <td class="px-4 py-3 text-sm font-mono">{{ log.ip_address }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Users Page -->
            <div v-if="currentPage === 'users' && currentUser.role === 'admin'" class="space-y-6">
                <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 shadow-xl border border-gray-700">
                    <h2 class="text-xl font-bold mb-4 flex items-center">
                        <i class="fas fa-users mr-2 text-purple-500"></i>
                        User Management
                    </h2>
                    <button @click="showCreateUserModal = true" class="mb-4 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition">
                        <i class="fas fa-user-plus mr-2"></i>Create New User
                    </button>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Username</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Email</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Role</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Last Login</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users" :key="user.id" class="border-t border-gray-700 hover:bg-gray-700/30 transition">
                                    <td class="px-4 py-3 text-sm">{{ user.username }}</td>
                                    <td class="px-4 py-3 text-sm">{{ user.email }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span :class="user.role === 'admin' ? 'bg-purple-600' : 'bg-blue-600'" class="px-2 py-1 rounded text-xs">
                                            {{ user.role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span :class="user.is_active ? 'bg-green-600' : 'bg-red-600'" class="px-2 py-1 rounded text-xs">
                                            {{ user.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ user.last_login || 'Never' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex space-x-2">
                                            <button @click="editUser(user)" class="px-2 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button v-if="user.id !== currentUser.id" @click="toggleUserStatus(user)" class="px-2 py-1 bg-yellow-600 hover:bg-yellow-700 rounded text-xs">
                                                <i :class="user.is_active ? 'fa-ban' : 'fa-check'"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Settings Page -->
            <div v-if="currentPage === 'settings' && currentUser.role === 'admin'" class="space-y-6">
                <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl p-6 shadow-xl border border-gray-700">
                    <h2 class="text-xl font-bold mb-4 flex items-center">
                        <i class="fas fa-cog mr-2 text-blue-500"></i>
                        System Settings
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-semibold mb-3">Security Settings</h3>
                            <div class="space-y-3">
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" v-model="settings.rateLimit" class="rounded">
                                    <span>Enable Rate Limiting</span>
                                </label>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" v-model="settings.csrfProtection" class="rounded">
                                    <span>CSRF Protection</span>
                                </label>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" v-model="settings.activityLogging" class="rounded">
                                    <span>Activity Logging</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-semibold mb-3">SSL Settings</h3>
                            <div class="space-y-3">
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" v-model="settings.autoRenew" class="rounded">
                                    <span>Auto-Renew Certificates</span>
                                </label>
                                <div>
                                    <label class="block text-sm mb-1">Admin Email</label>
                                    <input v-model="settings.adminEmail" type="email" class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button @click="saveSettings" class="mt-6 px-6 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                        <i class="fas fa-save mr-2"></i>Save Settings
                    </button>
                </div>
            </div>
            
        </main>

        <!-- Edit Domain Modal -->
        <div v-if="showEditModal" @click="showEditModal = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto">
            <div @click.stop class="bg-gray-800 rounded-xl shadow-2xl max-w-4xl w-full my-8 border border-gray-700">
                <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-xl font-bold flex items-center">
                        <i class="fas fa-cog mr-2 text-purple-500"></i>
                        Domain Settings: {{ editForm.name }}
                    </h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="saveDomainSettings" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Destination IP & Port -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Destination IP</label>
                            <input v-model="editForm.ip" type="text" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="192.168.1.100">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Destination Port</label>
                            <input v-model.number="editForm.port" type="number" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="80">
                        </div>

                        <!-- TLS Version -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">TLS Version</label>
                            <select v-model="editForm.tls_version" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500">
                                <option value="TLSv1.2 TLSv1.3">TLS 1.2 & 1.3 (Recommended)</option>
                                <option value="TLSv1.3">TLS 1.3 Only</option>
                                <option value="TLSv1.2">TLS 1.2 Only</option>
                                <option value="TLSv1 TLSv1.1 TLSv1.2 TLSv1.3">All TLS Versions</option>
                            </select>
                        </div>

                        <!-- HTTP Version -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">HTTP Version</label>
                            <select v-model="editForm.http_version" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500">
                                <option value="http2">HTTP/2 (Recommended)</option>
                                <option value="http1.1">HTTP/1.1</option>
                            </select>
                        </div>

                        <!-- Proxy Timeout -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Proxy Timeout (seconds)</label>
                            <input v-model.number="editForm.proxy_timeout" type="number" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="60">
                        </div>

                        <!-- Proxy Buffer Size -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Proxy Buffer Size</label>
                            <input v-model="editForm.proxy_buffer_size" type="text" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="4k">
                        </div>

                        <!-- Client Max Body Size -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Max Upload Size</label>
                            <input v-model="editForm.client_max_body_size" type="text" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="10m">
                        </div>

                        <!-- Checkboxes -->
                        <div class="flex items-center space-x-6">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input v-model="editForm.enable_websocket" type="checkbox" class="w-5 h-5 text-purple-600 bg-gray-900 border-gray-700 rounded focus:ring-purple-500">
                                <span>Enable WebSocket</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input v-model="editForm.enable_gzip" type="checkbox" class="w-5 h-5 text-purple-600 bg-gray-900 border-gray-700 rounded focus:ring-purple-500">
                                <span>Enable Gzip</span>
                            </label>
                        </div>
                    </div>

                    <!-- Custom Headers -->
                    <div class="mt-6">
                        <label class="block text-sm font-semibold mb-2">Custom Headers (one per line)</label>
                        <textarea v-model="editForm.custom_headers" rows="3" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500 font-mono text-sm" placeholder="X-Custom-Header 'value'&#10;X-Another-Header 'value'"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Example: X-Frame-Options 'SAMEORIGIN'</p>
                    </div>

                    <!-- Custom Config -->
                    <div class="mt-6">
                        <label class="block text-sm font-semibold mb-2">Custom Nginx Configuration</label>
                        <textarea v-model="editForm.custom_config" rows="4" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500 font-mono text-sm" placeholder="# Add custom nginx directives here&#10;# Example:&#10;# location /api {&#10;#     proxy_pass http://backend:8080;&#10;# }"></textarea>
                    </div>

                    <!-- Buttons -->
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" :disabled="loading" class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 rounded-lg font-semibold transition disabled:opacity-50">
                            <i class="fas fa-save mr-2"></i>{{ loading ? 'Saving...' : 'Save Settings' }}
                        </button>
                        <button type="button" @click="showEditModal = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Progress Modal -->
        <div v-if="showProgressModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-gray-800 rounded-xl shadow-2xl max-w-md w-full border border-gray-700">
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-4 flex items-center">
                        <i class="fas fa-spinner fa-spin mr-2 text-blue-500"></i>
                        {{ progressTitle }}
                    </h3>
                    <div class="space-y-3">
                        <div v-for="(step, index) in progressSteps" :key="index" class="flex items-center space-x-3">
                            <div v-if="index < currentStep" class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center">
                                <i class="fas fa-check text-white text-xs"></i>
                            </div>
                            <div v-else-if="index === currentStep" class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center">
                                <i class="fas fa-spinner fa-spin text-white text-xs"></i>
                            </div>
                            <div v-else class="w-6 h-6 rounded-full bg-gray-600"></div>
                            <span :class="index <= currentStep ? 'text-white' : 'text-gray-500'">{{ step }}</span>
                        </div>
                    </div>
                    <div class="mt-6">
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" :style="{width: ((currentStep / progressSteps.length) * 100) + '%'}"></div>
                        </div>
                        <p class="text-center text-sm text-gray-400 mt-2">{{ currentStep }} / {{ progressSteps.length }} steps</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Domain Modal -->
        <div v-if="showEditDomainModal" @click="showEditDomainModal = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto">
            <div @click.stop class="bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full my-8 border border-gray-700">
                <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-xl font-bold flex items-center">
                        <i class="fas fa-edit mr-2 text-purple-500"></i>
                        Edit Domain: {{ editDomainForm.name }}
                    </h3>
                    <button @click="showEditDomainModal = false" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="updateDomain" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Destination IP</label>
                            <input v-model="editDomainForm.ip" type="text" required class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="192.168.1.1">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Destination Port</label>
                            <input v-model.number="editDomainForm.port" type="number" min="1" max="65535" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="80">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">TLS Version</label>
                            <select v-model="editDomainForm.tls_version" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500">
                                <option value="TLSv1.2 TLSv1.3">TLS 1.2 & 1.3 (Recommended)</option>
                                <option value="TLSv1.3">TLS 1.3 Only</option>
                                <option value="TLSv1.2">TLS 1.2 Only</option>
                                <option value="TLSv1.1 TLSv1.2 TLSv1.3">TLS 1.1, 1.2 & 1.3</option>
                                <option value="TLSv1.1 TLSv1.2">TLS 1.1 & 1.2</option>
                                <option value="TLSv1 TLSv1.1">TLS 1.0 & 1.1 (Legacy)</option>
                                <option value="TLSv1 TLSv1.1 TLSv1.2 TLSv1.3">All TLS Versions</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">HTTP Version</label>
                            <select v-model="editDomainForm.http_version" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500">
                                <option value="http2">HTTP/2 (Recommended)</option>
                                <option value="http1.1">HTTP/1.1</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Proxy Timeout (seconds)</label>
                            <input v-model.number="editDomainForm.proxy_timeout" type="number" min="1" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="60">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Proxy Buffer Size</label>
                            <input v-model="editDomainForm.proxy_buffer_size" type="text" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="4k">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Max Upload Size</label>
                            <input v-model="editDomainForm.client_max_body_size" type="text" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="10m">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" v-model="editDomainForm.enable_websocket" class="rounded">
                            <span class="text-sm">Enable WebSocket</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" v-model="editDomainForm.enable_gzip" class="rounded">
                            <span class="text-sm">Enable Gzip</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" v-model="editDomainForm.enable_cache" class="rounded">
                            <span class="text-sm">Cache Assets</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" v-model="editDomainForm.block_exploits" class="rounded">
                            <span class="text-sm">Block Common Exploits</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" v-model="editDomainForm.include_www" class="rounded">
                            <span class="text-sm">Include www subdomain</span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2">Custom Headers (one per line)</label>
                        <textarea v-model="editDomainForm.custom_headers" rows="3" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500 font-mono text-sm" placeholder="Example: X-Frame-Options 'SAMEORIGIN'"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2">Custom Nginx Configuration</label>
                        <textarea v-model="editDomainForm.custom_config" rows="4" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-500 font-mono text-sm" placeholder="Add custom Nginx directives here"></textarea>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 rounded-lg font-semibold transition">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                        <button type="button" @click="showEditDomainModal = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Modal -->
        <div v-if="showLogsModal" @click="showLogsModal = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div @click.stop class="bg-gray-800 rounded-xl shadow-2xl max-w-4xl w-full max-h-[80vh] overflow-hidden border border-gray-700">
                <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-xl font-bold flex items-center">
                        <i class="fas fa-file-alt mr-2 text-blue-500"></i>
                        {{ logsTitle }}
                    </h3>
                    <button @click="showLogsModal = false" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <pre class="bg-gray-900 p-4 rounded-lg text-sm font-mono text-green-400 whitespace-pre-wrap">{{ logsContent }}</pre>
                </div>
            </div>
        </div>

        <!-- Create User Modal -->
        <div v-if="showCreateUserModal" @click="showCreateUserModal = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div @click.stop class="bg-gray-800 rounded-xl shadow-2xl max-w-md w-full border border-gray-700">
                <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-xl font-bold flex items-center">
                        <i class="fas fa-user-plus mr-2 text-green-500"></i>
                        Create New User
                    </h3>
                    <button @click="showCreateUserModal = false" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="createNewUser" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Username</label>
                        <input v-model="newUserForm.username" type="text" required class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Enter username">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Email</label>
                        <input v-model="newUserForm.email" type="email" required class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Password</label>
                        <input v-model="newUserForm.password" type="password" required minlength="6" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Min 6 characters">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Role</label>
                        <select v-model="newUserForm.role" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 rounded-lg font-semibold transition">
                            <i class="fas fa-save mr-2"></i>Create User
                        </button>
                        <button type="button" @click="showCreateUserModal = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Edit User Modal -->
        <div v-if="showEditUserModal" @click="showEditUserModal = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div @click.stop class="bg-gray-800 rounded-xl shadow-2xl max-w-md w-full border border-gray-700">
                <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-xl font-bold flex items-center">
                        <i class="fas fa-user-edit mr-2 text-blue-500"></i>
                        Edit User
                    </h3>
                    <button @click="showEditUserModal = false" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="updateUserData" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Email</label>
                        <input v-model="editUserForm.email" type="email" required class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Role</label>
                        <select v-model="editUserForm.role" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="border-t border-gray-700 pt-4 mt-4">
                        <p class="text-sm text-gray-400 mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Leave password fields empty to keep current password
                        </p>
                        <div>
                            <label class="block text-sm font-semibold mb-2">New Password (optional)</label>
                            <input v-model="editUserForm.password" type="password" minlength="6" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Min 6 characters">
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-semibold mb-2">Confirm New Password</label>
                            <input v-model="editUserForm.confirm_password" type="password" minlength="6" class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Confirm password">
                        </div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 rounded-lg font-semibold transition">
                            <i class="fas fa-save mr-2"></i>Update User
                        </button>
                        <button type="button" @click="showEditUserModal = false" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Toast Notifications -->
        <transition-group name="slide" tag="div" class="fixed top-20 right-6 space-y-2 z-50">
            <div v-for="toast in toasts" :key="toast.id" 
                :class="getToastClass(toast.type)"
                class="px-6 py-4 rounded-lg shadow-xl flex items-center space-x-3 min-w-[300px]">
                <i :class="getToastIcon(toast.type)" class="text-xl"></i>
                <span>{{ toast.message }}</span>
            </div>
        </transition-group>
    </div>

    <script src="app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
