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
    <title>Database Manager - Reverse Proxy UI</title>
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
                        <i class="fas fa-database text-3xl text-yellow-500"></i>
                        <div>
                            <h1 class="text-2xl font-bold">Database Manager</h1>
                            <p class="text-sm text-gray-400">Manage and migrate database</p>
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
            <!-- Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <button 
                    @click="runMigration"
                    :disabled="loading"
                    class="p-6 bg-gradient-to-br from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 rounded-xl shadow-xl transition disabled:opacity-50"
                >
                    <i class="fas fa-sync-alt text-3xl mb-3"></i>
                    <h3 class="text-lg font-bold">Run Migration</h3>
                    <p class="text-sm text-blue-200 mt-2">Update database structure</p>
                </button>

                <button 
                    @click="testDatabase"
                    :disabled="loading"
                    class="p-6 bg-gradient-to-br from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 rounded-xl shadow-xl transition disabled:opacity-50"
                >
                    <i class="fas fa-check-circle text-3xl mb-3"></i>
                    <h3 class="text-lg font-bold">Test Database</h3>
                    <p class="text-sm text-green-200 mt-2">Check database status</p>
                </button>

                <button 
                    @click="confirmClearDatabase"
                    :disabled="loading"
                    class="p-6 bg-gradient-to-br from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 rounded-xl shadow-xl transition disabled:opacity-50"
                >
                    <i class="fas fa-trash-alt text-3xl mb-3"></i>
                    <h3 class="text-lg font-bold">Clear All Domains</h3>
                    <p class="text-sm text-red-200 mt-2">Delete all domain records</p>
                </button>
            </div>

            <!-- Results -->
            <div v-if="results" class="bg-gray-800/50 backdrop-blur-lg rounded-xl shadow-xl border border-gray-700 p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i :class="results.success ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'" class="fas mr-2"></i>
                    {{ results.success ? 'Success' : 'Error' }}
                </h2>
                
                <div v-if="results.message" class="mb-4 p-4 bg-gray-900 rounded-lg">
                    <p class="font-semibold">{{ results.message }}</p>
                </div>

                <div v-if="results.error" class="mb-4 p-4 bg-red-900/30 border border-red-500 rounded-lg">
                    <p class="text-red-400">{{ results.error }}</p>
                </div>

                <div v-if="results.logs" class="mb-4">
                    <h3 class="font-semibold mb-2">Logs:</h3>
                    <div class="bg-gray-900 p-4 rounded-lg">
                        <pre class="text-sm text-green-400 whitespace-pre-wrap">{{ results.logs.join('\n') }}</pre>
                    </div>
                </div>

                <div v-if="results.domain_count !== undefined" class="mb-4 p-4 bg-blue-900/30 border border-blue-500 rounded-lg">
                    <p class="text-blue-400">
                        <i class="fas fa-info-circle mr-2"></i>
                        Total domains in database: <strong>{{ results.domain_count }}</strong>
                    </p>
                </div>

                <div v-if="results.domains && results.domains.length > 0" class="mb-4">
                    <h3 class="font-semibold mb-2">Domains in Database:</h3>
                    <div class="bg-gray-900 rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-4 py-2 text-left">ID</th>
                                    <th class="px-4 py-2 text-left">Name</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                    <th class="px-4 py-2 text-left">Cert Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="domain in results.domains" :key="domain.id" class="border-t border-gray-700">
                                    <td class="px-4 py-2">{{ domain.id }}</td>
                                    <td class="px-4 py-2">{{ domain.name }}</td>
                                    <td class="px-4 py-2">{{ domain.status }}</td>
                                    <td class="px-4 py-2">{{ domain.cert_status }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="results.note" class="p-4 bg-yellow-900/30 border border-yellow-500 rounded-lg">
                    <p class="text-yellow-400">
                        <i class="fas fa-lightbulb mr-2"></i>
                        {{ results.note }}
                    </p>
                </div>
            </div>
        </main>

        <!-- Confirm Modal -->
        <div v-if="showConfirmModal" @click="showConfirmModal = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div @click.stop class="bg-gray-800 rounded-xl shadow-2xl max-w-md w-full border border-red-500">
                <div class="p-6 border-b border-gray-700">
                    <h3 class="text-xl font-bold text-red-400 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Confirm Action
                    </h3>
                </div>
                <div class="p-6">
                    <p class="mb-4">Are you sure you want to delete ALL domains from the database?</p>
                    <p class="text-red-400 font-semibold">This action cannot be undone!</p>
                </div>
                <div class="p-6 border-t border-gray-700 flex space-x-4">
                    <button 
                        @click="clearDatabase"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition"
                    >
                        Yes, Delete All
                    </button>
                    <button 
                        @click="showConfirmModal = false"
                        class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    loading: false,
                    results: null,
                    showConfirmModal: false
                }
            },
            methods: {
                async runMigration() {
                    this.loading = true;
                    this.results = null;
                    try {
                        const response = await axios.get('api/migrate.php');
                        this.results = response.data;
                    } catch (error) {
                        this.results = {
                            success: false,
                            error: error.response?.data?.error || error.message
                        };
                    } finally {
                        this.loading = false;
                    }
                },
                async testDatabase() {
                    this.loading = true;
                    this.results = null;
                    try {
                        const response = await axios.get('api/test.php');
                        this.results = response.data;
                    } catch (error) {
                        this.results = {
                            success: false,
                            error: error.response?.data?.error || error.message
                        };
                    } finally {
                        this.loading = false;
                    }
                },
                confirmClearDatabase() {
                    this.showConfirmModal = true;
                },
                async clearDatabase() {
                    this.showConfirmModal = false;
                    this.loading = true;
                    this.results = null;
                    try {
                        const response = await axios.get('api/migrate.php?clear=yes');
                        this.results = response.data;
                    } catch (error) {
                        this.results = {
                            success: false,
                            error: error.response?.data?.error || error.message
                        };
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }).mount('#app');
    </script>
</body>
</html>
