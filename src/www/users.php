<?php
session_start();
require_once('auth_config.php');
require_once('database/users_db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Reverse Proxy UI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        [v-cloak] { display: none; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen text-white">
    <div id="app" v-cloak>
        <!-- Header -->
        <header class="bg-gray-800/50 backdrop-blur-lg border-b border-gray-700 sticky top-0 z-50">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-users text-3xl text-purple-500"></i>
                        <div>
                            <h1 class="text-2xl font-bold">User Management</h1>
                            <p class="text-sm text-gray-400">Manage system users and permissions</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="dashboard.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-br from-purple-600 to-purple-700 rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-200 text-sm">Total Users</p>
                            <p class="text-3xl font-bold mt-2">{{ users.length }}</p>
                        </div>
                        <i class="fas fa-users text-4xl text-purple-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-sm">Admins</p>
                            <p class="text-3xl font-bold mt-2">{{ adminCount }}</p>
                        </div>
                        <i class="fas fa-user-shield text-4xl text-blue-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-200 text-sm">Active Users</p>
                            <p class="text-3xl font-bold mt-2">{{ activeCount }}</p>
                        </div>
                        <i class="fas fa-user-check text-4xl text-green-300"></i>
                    </div>
                </div>
            </div>

            <!-- Add User Button -->
            <div class="mb-6">
                <button 
                    @click="showAddUserModal = true"
                    class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 rounded-lg font-semibold transition shadow-xl"
                >
                    <i class="fas fa-user-plus mr-2"></i>Add New User
                </button>
            </div>

            <!-- Users Table -->
            <div class="bg-gray-800/50 backdrop-blur-lg rounded-xl shadow-xl border border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold">ID</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Username</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Email</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Role</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Status</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Created</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Last Login</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id" class="border-t border-gray-700 hover:bg-gray-700/30 transition">
                                <td class="px-6 py-4 text-sm">{{ user.id }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-circle text-2xl text-gray-400 mr-3"></i>
                                        <span class="font-semibold">{{ user.username }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <span :class="user.role === 'admin' ? 'bg-purple-500/20 text-purple-400 border-purple-500/50' : 'bg-blue-500/20 text-blue-400 border-blue-500/50'" 
                                        class="px-3 py-1 rounded-full text-xs font-semibold border">
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="user.is_active ? 'bg-green-500/20 text-green-400 border-green-500/50' : 'bg-red-500/20 text-red-400 border-red-500/50'" 
                                        class="px-3 py-1 rounded-full text-xs font-semibold border">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-400">{{ formatDate(user.created_at) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-400">{{ user.last_login ? formatDate(user.last_login) : 'Never' }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button 
                                            @click="editUser(user)"
                                            class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs transition"
                                            title="Edit"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button 
                                            @click="toggleUserStatus(user)"
                                            :class="user.is_active ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'"
                                            class="px-3 py-1 rounded text-xs transition"
                                            :title="user.is_active ? 'Deactivate' : 'Activate'"
                                        >
                                            <i :class="user.is_active ? 'fa-ban' : 'fa-check'" class="fas"></i>
                                        </button>
                                        <button 
                                            @click="deleteUser(user)"
                                            class="px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-xs transition"
                                            title="Delete"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Add/Edit User Modal -->
        <div v-if="showAddUserModal || showEditUserModal" @click="closeModals" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div @click.stop class="bg-gray-800 rounded-xl shadow-2xl max-w-md w-full border border-gray-700">
                <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-xl font-bold">
                        <i :class="showAddUserModal ? 'fa-user-plus' : 'fa-edit'" class="fas mr-2 text-blue-500"></i>
                        {{ showAddUserModal ? 'Add New User' : 'Edit User' }}
                    </h3>
                    <button @click="closeModals" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="showAddUserModal ? createUser() : updateUser()" class="p-6 space-y-4">
                    <div v-if="showAddUserModal">
                        <label class="block text-sm font-semibold mb-2">Username</label>
                        <input 
                            v-model="userForm.username"
                            type="text" 
                            class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500"
                            required
                            minlength="3"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Email</label>
                        <input 
                            v-model="userForm.email"
                            type="email" 
                            class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500"
                            required
                        >
                    </div>
                    <div v-if="showAddUserModal">
                        <label class="block text-sm font-semibold mb-2">Password</label>
                        <input 
                            v-model="userForm.password"
                            type="password" 
                            class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500"
                            required
                            minlength="6"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Role</label>
                        <select 
                            v-model="userForm.role"
                            class="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="flex space-x-3 pt-4">
                        <button 
                            type="submit"
                            :disabled="loading"
                            class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50"
                        >
                            <i :class="showAddUserModal ? 'fa-save' : 'fa-check'" class="fas mr-2"></i>
                            {{ loading ? 'Saving...' : (showAddUserModal ? 'Create' : 'Update') }}
                        </button>
                        <button 
                            type="button"
                            @click="closeModals"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition"
                        >
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

    <script src="users.js"></script>
</body>
</html>
