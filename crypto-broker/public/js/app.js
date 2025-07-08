// Main Vue.js Application for Crypto Broker Platform
const { createApp, ref, reactive, computed, onMounted, onUnmounted, watch } = Vue;
const { createRouter, createWebHistory } = VueRouter;

// API Configuration
const API_BASE_URL = window.location.origin + '/api/v1';
const WS_URL = window.location.protocol === 'https:' ? 'wss://' : 'ws://' + window.location.host;

// Global State Management
const globalState = reactive({
    user: null,
    isAuthenticated: false,
    token: localStorage.getItem('token'),
    sidebarOpen: false,
    darkMode: true,
    notifications: [],
    socket: null,
    prices: {},
    markets: [],
    isLoading: false
});

// API Helper
const api = {
    async request(url, options = {}) {
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(globalState.token && { 'Authorization': `Bearer ${globalState.token}` })
            },
            ...options
        };

        try {
            const response = await fetch(`${API_BASE_URL}${url}`, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'API request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    get(url) {
        return this.request(url);
    },

    post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    put(url, data) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    delete(url) {
        return this.request(url, {
            method: 'DELETE'
        });
    }
};

// Authentication Service
const auth = {
    async login(credentials) {
        try {
            const response = await api.post('/auth/login', credentials);
            if (response.success) {
                globalState.token = response.data.token;
                globalState.user = response.data.user;
                globalState.isAuthenticated = true;
                localStorage.setItem('token', response.data.token);
                return response;
            }
            throw new Error(response.message);
        } catch (error) {
            throw error;
        }
    },

    async register(userData) {
        try {
            const response = await api.post('/auth/register', userData);
            if (response.success) {
                globalState.token = response.data.token;
                globalState.user = response.data.user;
                globalState.isAuthenticated = true;
                localStorage.setItem('token', response.data.token);
                return response;
            }
            throw new Error(response.message);
        } catch (error) {
            throw error;
        }
    },

    async logout() {
        try {
            await api.post('/auth/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            globalState.token = null;
            globalState.user = null;
            globalState.isAuthenticated = false;
            localStorage.removeItem('token');
        }
    },

    async getUser() {
        try {
            const response = await api.get('/auth/me');
            if (response.success) {
                globalState.user = response.data;
                globalState.isAuthenticated = true;
                return response.data;
            }
        } catch (error) {
            console.error('Get user error:', error);
            this.logout();
        }
    }
};

// WebSocket Service
const websocket = {
    connect() {
        if (globalState.socket) return;

        globalState.socket = io(WS_URL, {
            auth: {
                token: globalState.token
            }
        });

        globalState.socket.on('connect', () => {
            console.log('WebSocket connected');
        });

        globalState.socket.on('price_update', (data) => {
            globalState.prices[data.symbol] = data;
        });

        globalState.socket.on('notification', (notification) => {
            notifications.show(notification.message, notification.type);
        });

        globalState.socket.on('disconnect', () => {
            console.log('WebSocket disconnected');
        });
    },

    disconnect() {
        if (globalState.socket) {
            globalState.socket.disconnect();
            globalState.socket = null;
        }
    },

    emit(event, data) {
        if (globalState.socket) {
            globalState.socket.emit(event, data);
        }
    }
};

// Notification Service
const notifications = {
    show(message, type = 'info', duration = 5000) {
        const notification = {
            id: Date.now(),
            message,
            type,
            duration
        };

        globalState.notifications.push(notification);

        setTimeout(() => {
            this.remove(notification.id);
        }, duration);
    },

    remove(id) {
        const index = globalState.notifications.findIndex(n => n.id === id);
        if (index > -1) {
            globalState.notifications.splice(index, 1);
        }
    },

    success(message) {
        this.show(message, 'success');
    },

    error(message) {
        this.show(message, 'error');
    },

    warning(message) {
        this.show(message, 'warning');
    }
};

// Components
const Dashboard = {
    template: `
        <div class="p-6">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">Welcome back, {{ user?.first_name }}!</h1>
                <p class="text-gray-400">Here's what's happening with your trading portfolio today.</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card p-6" v-for="stat in stats" :key="stat.label">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">{{ stat.label }}</p>
                            <p class="text-2xl font-bold text-white">{{ stat.value }}</p>
                            <p :class="stat.change >= 0 ? 'text-green-400' : 'text-red-400'" class="text-sm">
                                {{ stat.change >= 0 ? '+' : '' }}{{ stat.change }}%
                            </p>
                        </div>
                        <div :class="stat.iconBg" class="p-3 rounded-lg">
                            <i :class="stat.icon" class="text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Portfolio Balance</h3>
                    <canvas id="portfolioChart" width="400" height="200"></canvas>
                </div>
                <div class="card p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Trading Activity</h3>
                    <canvas id="activityChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Recent Activity</h3>
                <div class="space-y-4">
                    <div v-for="activity in recentActivity" :key="activity.id" 
                         class="flex items-center justify-between p-4 bg-gray-800 rounded-lg">
                        <div class="flex items-center space-x-4">
                            <div :class="activity.iconBg" class="p-2 rounded-lg">
                                <i :class="activity.icon" class="text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-white font-medium">{{ activity.title }}</p>
                                <p class="text-gray-400 text-sm">{{ activity.description }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p :class="activity.amount > 0 ? 'text-green-400' : 'text-red-400'" class="font-semibold">
                                {{ activity.amount > 0 ? '+' : '' }}${{ Math.abs(activity.amount).toLocaleString() }}
                            </p>
                            <p class="text-gray-400 text-sm">{{ activity.time }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,
    setup() {
        const stats = ref([
            { label: 'Total Balance', value: '$125,430.50', change: 12.5, icon: 'fas fa-wallet', iconBg: 'bg-green-500' },
            { label: 'Today P&L', value: '$2,340.20', change: 5.8, icon: 'fas fa-chart-line', iconBg: 'bg-blue-500' },
            { label: 'Open Positions', value: '8', change: -2.1, icon: 'fas fa-chart-bar', iconBg: 'bg-purple-500' },
            { label: 'Win Rate', value: '78.5%', change: 3.2, icon: 'fas fa-trophy', iconBg: 'bg-yellow-500' }
        ]);

        const recentActivity = ref([
            {
                id: 1,
                title: 'Binary Trade Won',
                description: 'BTC/USDT Call Option',
                amount: 850.00,
                time: '2 minutes ago',
                icon: 'fas fa-arrow-up',
                iconBg: 'bg-green-500'
            },
            {
                id: 2,
                title: 'Investment Return',
                description: 'Gold Plan - Monthly Return',
                amount: 1200.00,
                time: '1 hour ago',
                icon: 'fas fa-piggy-bank',
                iconBg: 'bg-blue-500'
            },
            {
                id: 3,
                title: 'Spot Trade',
                description: 'Sold 0.5 ETH',
                amount: -1650.00,
                time: '3 hours ago',
                icon: 'fas fa-exchange-alt',
                iconBg: 'bg-purple-500'
            }
        ]);

        onMounted(() => {
            initCharts();
        });

        const initCharts = () => {
            // Portfolio Chart
            const portfolioCtx = document.getElementById('portfolioChart');
            if (portfolioCtx) {
                new Chart(portfolioCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Portfolio Value',
                            data: [100000, 105000, 98000, 110000, 120000, 125430],
                            borderColor: '#00FF88',
                            backgroundColor: 'rgba(0, 255, 136, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                grid: {
                                    color: '#2D3748'
                                },
                                ticks: {
                                    color: '#A0AEC0'
                                }
                            },
                            x: {
                                grid: {
                                    color: '#2D3748'
                                },
                                ticks: {
                                    color: '#A0AEC0'
                                }
                            }
                        }
                    }
                });
            }

            // Activity Chart
            const activityCtx = document.getElementById('activityChart');
            if (activityCtx) {
                new Chart(activityCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Binary Trading', 'Spot Trading', 'Futures', 'Investments'],
                        datasets: [{
                            data: [35, 25, 20, 20],
                            backgroundColor: ['#00FF88', '#00D9FF', '#9F7AEA', '#FFA726']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#A0AEC0'
                                }
                            }
                        }
                    }
                });
            }
        };

        return {
            stats,
            recentActivity,
            user: computed(() => globalState.user)
        };
    }
};

const Trading = {
    template: `
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-white mb-2">Spot Trading</h1>
                <p class="text-gray-400">Trade cryptocurrencies with advanced charting and order management.</p>
            </div>

            <!-- Trading Pairs -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                <div class="lg:col-span-1">
                    <div class="card p-4">
                        <h3 class="text-lg font-semibold text-white mb-4">Markets</h3>
                        <div class="space-y-2">
                            <div v-for="pair in tradingPairs" :key="pair.symbol" 
                                 @click="selectPair(pair)"
                                 :class="selectedPair?.symbol === pair.symbol ? 'bg-green-900' : 'hover:bg-gray-700'"
                                 class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-colors">
                                <div>
                                    <p class="text-white font-medium">{{ pair.symbol }}</p>
                                    <p class="text-gray-400 text-sm">{{ pair.name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-white font-semibold">\${{ pair.price.toLocaleString() }}</p>
                                    <p :class="pair.change >= 0 ? 'text-green-400' : 'text-red-400'" class="text-sm">
                                        {{ pair.change >= 0 ? '+' : '' }}{{ pair.change.toFixed(2) }}%
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="lg:col-span-2">
                    <div class="card p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">{{ selectedPair?.symbol || 'BTC/USDT' }}</h3>
                            <div class="flex space-x-2">
                                <button v-for="timeframe in timeframes" :key="timeframe"
                                        @click="selectTimeframe(timeframe)"
                                        :class="selectedTimeframe === timeframe ? 'bg-green-500' : 'bg-gray-600'"
                                        class="px-3 py-1 rounded text-white text-sm">
                                    {{ timeframe }}
                                </button>
                            </div>
                        </div>
                        <div class="trading-chart">
                            <canvas id="tradingChart" width="800" height="400"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Order Form -->
                <div class="lg:col-span-1">
                    <div class="card p-4">
                        <div class="flex mb-4">
                            <button @click="orderType = 'buy'"
                                    :class="orderType === 'buy' ? 'bg-green-500' : 'bg-gray-600'"
                                    class="flex-1 py-2 text-white font-semibold rounded-l">
                                Buy
                            </button>
                            <button @click="orderType = 'sell'"
                                    :class="orderType === 'sell' ? 'bg-red-500' : 'bg-gray-600'"
                                    class="flex-1 py-2 text-white font-semibold rounded-r">
                                Sell
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Order Type</label>
                                <select v-model="order.type" class="w-full p-3 bg-gray-700 text-white rounded-lg border border-gray-600">
                                    <option value="market">Market</option>
                                    <option value="limit">Limit</option>
                                </select>
                            </div>

                            <div v-if="order.type === 'limit'">
                                <label class="block text-gray-400 text-sm mb-2">Price</label>
                                <input v-model="order.price" type="number" 
                                       class="w-full p-3 bg-gray-700 text-white rounded-lg border border-gray-600"
                                       placeholder="0.00">
                            </div>

                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Amount</label>
                                <input v-model="order.amount" type="number" 
                                       class="w-full p-3 bg-gray-700 text-white rounded-lg border border-gray-600"
                                       placeholder="0.00">
                            </div>

                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Total</label>
                                <input :value="orderTotal" readonly
                                       class="w-full p-3 bg-gray-600 text-white rounded-lg border border-gray-600">
                            </div>

                            <button @click="placeOrder"
                                    :class="orderType === 'buy' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'"
                                    class="w-full py-3 text-white font-semibold rounded-lg transition-colors">
                                {{ orderType === 'buy' ? 'Buy' : 'Sell' }} {{ selectedPair?.symbol?.split('/')[0] || 'BTC' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Open Orders -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Open Orders</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-gray-400 text-sm">
                                <th class="text-left p-3">Pair</th>
                                <th class="text-left p-3">Type</th>
                                <th class="text-left p-3">Side</th>
                                <th class="text-left p-3">Amount</th>
                                <th class="text-left p-3">Price</th>
                                <th class="text-left p-3">Total</th>
                                <th class="text-left p-3">Status</th>
                                <th class="text-left p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in openOrders" :key="order.id" class="border-t border-gray-700">
                                <td class="p-3 text-white">{{ order.pair }}</td>
                                <td class="p-3 text-gray-400">{{ order.type }}</td>
                                <td class="p-3">
                                    <span :class="order.side === 'buy' ? 'text-green-400' : 'text-red-400'">
                                        {{ order.side.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="p-3 text-white">{{ order.amount }}</td>
                                <td class="p-3 text-white">\${{ order.price.toLocaleString() }}</td>
                                <td class="p-3 text-white">\${{ order.total.toLocaleString() }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-1 bg-yellow-500 text-yellow-900 rounded text-xs">
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <button @click="cancelOrder(order.id)"
                                            class="text-red-400 hover:text-red-300 text-sm">
                                        Cancel
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `,
    setup() {
        const tradingPairs = ref([
            { symbol: 'BTC/USDT', name: 'Bitcoin', price: 45230.50, change: 2.35 },
            { symbol: 'ETH/USDT', name: 'Ethereum', price: 3120.80, change: -1.25 },
            { symbol: 'BNB/USDT', name: 'Binance Coin', price: 425.60, change: 4.12 },
            { symbol: 'ADA/USDT', name: 'Cardano', price: 1.25, change: -0.85 },
            { symbol: 'SOL/USDT', name: 'Solana', price: 98.45, change: 6.78 }
        ]);

        const selectedPair = ref(tradingPairs.value[0]);
        const selectedTimeframe = ref('1H');
        const timeframes = ['1M', '5M', '15M', '1H', '4H', '1D'];
        const orderType = ref('buy');
        
        const order = reactive({
            type: 'market',
            amount: '',
            price: ''
        });

        const openOrders = ref([
            {
                id: 1,
                pair: 'BTC/USDT',
                type: 'Limit',
                side: 'buy',
                amount: '0.1',
                price: 44500,
                total: 4450,
                status: 'Open'
            },
            {
                id: 2,
                pair: 'ETH/USDT',
                type: 'Limit',
                side: 'sell',
                amount: '2.5',
                price: 3200,
                total: 8000,
                status: 'Partial'
            }
        ]);

        const orderTotal = computed(() => {
            if (!order.amount) return '0.00';
            const price = order.type === 'market' ? selectedPair.value?.price || 0 : parseFloat(order.price) || 0;
            const amount = parseFloat(order.amount) || 0;
            return (price * amount).toFixed(2);
        });

        const selectPair = (pair) => {
            selectedPair.value = pair;
            updateChart();
        };

        const selectTimeframe = (timeframe) => {
            selectedTimeframe.value = timeframe;
            updateChart();
        };

        const placeOrder = async () => {
            try {
                // API call to place order
                notifications.success(`${orderType.value.toUpperCase()} order placed successfully!`);
                // Reset form
                order.amount = '';
                order.price = '';
            } catch (error) {
                notifications.error('Failed to place order: ' + error.message);
            }
        };

        const cancelOrder = async (orderId) => {
            try {
                // API call to cancel order
                const index = openOrders.value.findIndex(o => o.id === orderId);
                if (index > -1) {
                    openOrders.value.splice(index, 1);
                }
                notifications.success('Order cancelled successfully!');
            } catch (error) {
                notifications.error('Failed to cancel order: ' + error.message);
            }
        };

        const updateChart = () => {
            // Update chart with new data
            console.log(`Updating chart for ${selectedPair.value?.symbol} - ${selectedTimeframe.value}`);
        };

        onMounted(() => {
            initTradingChart();
        });

        const initTradingChart = () => {
            const ctx = document.getElementById('tradingChart');
            if (ctx) {
                // Generate sample price data
                const labels = Array.from({ length: 50 }, (_, i) => i + 1);
                const prices = [];
                let price = 45000;
                
                for (let i = 0; i < 50; i++) {
                    price += (Math.random() - 0.5) * 1000;
                    prices.push(price);
                }

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Price',
                            data: prices,
                            borderColor: '#00FF88',
                            backgroundColor: 'rgba(0, 255, 136, 0.1)',
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                grid: {
                                    color: '#2D3748'
                                },
                                ticks: {
                                    color: '#A0AEC0'
                                }
                            },
                            x: {
                                grid: {
                                    color: '#2D3748'
                                },
                                ticks: {
                                    color: '#A0AEC0'
                                }
                            }
                        }
                    }
                });
            }
        };

        return {
            tradingPairs,
            selectedPair,
            selectedTimeframe,
            timeframes,
            orderType,
            order,
            openOrders,
            orderTotal,
            selectPair,
            selectTimeframe,
            placeOrder,
            cancelOrder
        };
    }
};

const BinaryTrading = {
    template: `
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-white mb-2">Binary Options Trading</h1>
                <p class="text-gray-400">Predict price movements and earn up to 85% returns.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Chart Section -->
                <div class="lg:col-span-2">
                    <div class="card p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">{{ selectedAsset.symbol }}</h3>
                            <div class="flex items-center space-x-4">
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-white">\${{ selectedAsset.price.toLocaleString() }}</p>
                                    <p :class="selectedAsset.change >= 0 ? 'text-green-400' : 'text-red-400'" class="text-sm">
                                        {{ selectedAsset.change >= 0 ? '+' : '' }}{{ selectedAsset.change.toFixed(2) }}%
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="trading-chart">
                            <canvas id="binaryChart" width="800" height="400"></canvas>
                        </div>
                    </div>

                    <!-- Active Trades -->
                    <div class="card p-6 mt-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Active Trades</h3>
                        <div class="space-y-4">
                            <div v-for="trade in activeTrades" :key="trade.id"
                                 class="flex items-center justify-between p-4 bg-gray-800 rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div :class="trade.direction === 'call' ? 'bg-green-500' : 'bg-red-500'"
                                         class="p-2 rounded-lg">
                                        <i :class="trade.direction === 'call' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
                                           class="text-white text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-white font-medium">{{ trade.asset }}</p>
                                        <p class="text-gray-400 text-sm">{{ trade.direction.toUpperCase() }} - {{ trade.timeLeft }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-white font-semibold">\${{ trade.amount }}</p>
                                    <p class="text-green-400 text-sm">+\${{ trade.potentialProfit }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trading Panel -->
                <div class="lg:col-span-1">
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Place Trade</h3>
                        
                        <!-- Asset Selection -->
                        <div class="mb-4">
                            <label class="block text-gray-400 text-sm mb-2">Asset</label>
                            <select v-model="selectedAsset" class="w-full p-3 bg-gray-700 text-white rounded-lg border border-gray-600">
                                <option v-for="asset in assets" :key="asset.symbol" :value="asset">
                                    {{ asset.symbol }}
                                </option>
                            </select>
                        </div>

                        <!-- Direction -->
                        <div class="mb-4">
                            <label class="block text-gray-400 text-sm mb-2">Direction</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button @click="trade.direction = 'call'"
                                        :class="trade.direction === 'call' ? 'bg-green-500' : 'bg-gray-600'"
                                        class="p-3 text-white font-semibold rounded-lg transition-colors">
                                    <i class="fas fa-arrow-up mr-2"></i>Call
                                </button>
                                <button @click="trade.direction = 'put'"
                                        :class="trade.direction === 'put' ? 'bg-red-500' : 'bg-gray-600'"
                                        class="p-3 text-white font-semibold rounded-lg transition-colors">
                                    <i class="fas fa-arrow-down mr-2"></i>Put
                                </button>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-4">
                            <label class="block text-gray-400 text-sm mb-2">Investment Amount</label>
                            <input v-model="trade.amount" type="number" 
                                   class="w-full p-3 bg-gray-700 text-white rounded-lg border border-gray-600"
                                   placeholder="10.00" min="10" max="10000">
                            <div class="flex space-x-2 mt-2">
                                <button v-for="amount in quickAmounts" :key="amount"
                                        @click="trade.amount = amount"
                                        class="flex-1 p-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-500">
                                    \${{ amount }}
                                </button>
                            </div>
                        </div>

                        <!-- Duration -->
                        <div class="mb-4">
                            <label class="block text-gray-400 text-sm mb-2">Duration</label>
                            <select v-model="trade.duration" class="w-full p-3 bg-gray-700 text-white rounded-lg border border-gray-600">
                                <option value="30">30 seconds</option>
                                <option value="60">1 minute</option>
                                <option value="300">5 minutes</option>
                                <option value="900">15 minutes</option>
                                <option value="3600">1 hour</option>
                            </select>
                        </div>

                        <!-- Payout Info -->
                        <div class="mb-6 p-4 bg-gray-800 rounded-lg">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Investment:</span>
                                <span class="text-white">\${{ trade.amount || 0 }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Payout:</span>
                                <span class="text-green-400">{{ payoutPercentage }}%</span>
                            </div>
                            <div class="flex justify-between text-sm font-semibold">
                                <span class="text-gray-400">Potential Profit:</span>
                                <span class="text-green-400">\${{ potentialProfit }}</span>
                            </div>
                        </div>

                        <!-- Trade Button -->
                        <button @click="placeBinaryTrade"
                                :disabled="!canPlaceTrade"
                                :class="trade.direction === 'call' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'"
                                class="w-full py-3 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Place {{ trade.direction?.toUpperCase() }} Trade
                        </button>
                    </div>

                    <!-- Demo Balance -->
                    <div class="card p-4 mt-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Demo Balance</p>
                                <p class="text-lg font-semibold text-white">\${{ demoBalance.toLocaleString() }}</p>
                            </div>
                            <button @click="resetDemoBalance" class="text-blue-400 hover:text-blue-300 text-sm">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,
    setup() {
        const assets = ref([
            { symbol: 'BTC/USD', price: 45230.50, change: 2.35 },
            { symbol: 'ETH/USD', price: 3120.80, change: -1.25 },
            { symbol: 'EUR/USD', price: 1.0821, change: 0.15 },
            { symbol: 'GBP/USD', price: 1.2634, change: -0.28 }
        ]);

        const selectedAsset = ref(assets.value[0]);
        const payoutPercentage = ref(85);
        const demoBalance = ref(10000);
        const quickAmounts = [10, 25, 50, 100];

        const trade = reactive({
            direction: 'call',
            amount: '',
            duration: '60'
        });

        const activeTrades = ref([
            {
                id: 1,
                asset: 'BTC/USD',
                direction: 'call',
                amount: 50,
                potentialProfit: 42.50,
                timeLeft: '45s'
            },
            {
                id: 2,
                asset: 'ETH/USD',
                direction: 'put',
                amount: 100,
                potentialProfit: 85.00,
                timeLeft: '2m 15s'
            }
        ]);

        const potentialProfit = computed(() => {
            const amount = parseFloat(trade.amount) || 0;
            return ((amount * payoutPercentage.value) / 100).toFixed(2);
        });

        const canPlaceTrade = computed(() => {
            return trade.direction && trade.amount && parseFloat(trade.amount) >= 10 && parseFloat(trade.amount) <= demoBalance.value;
        });

        const placeBinaryTrade = async () => {
            if (!canPlaceTrade.value) return;

            try {
                const tradeData = {
                    asset: selectedAsset.value.symbol,
                    direction: trade.direction,
                    amount: parseFloat(trade.amount),
                    duration: parseInt(trade.duration)
                };

                // Simulate API call
                const newTrade = {
                    id: Date.now(),
                    asset: tradeData.asset,
                    direction: tradeData.direction,
                    amount: tradeData.amount,
                    potentialProfit: parseFloat(potentialProfit.value),
                    timeLeft: formatDuration(tradeData.duration)
                };

                activeTrades.value.unshift(newTrade);
                demoBalance.value -= tradeData.amount;

                notifications.success('Binary trade placed successfully!');

                // Reset form
                trade.amount = '';
                trade.direction = 'call';

                // Simulate trade completion
                setTimeout(() => {
                    const index = activeTrades.value.findIndex(t => t.id === newTrade.id);
                    if (index > -1) {
                        activeTrades.value.splice(index, 1);
                        const won = Math.random() > 0.5;
                        if (won) {
                            demoBalance.value += newTrade.amount + newTrade.potentialProfit;
                            notifications.success(`Trade won! +$${newTrade.potentialProfit.toFixed(2)}`);
                        } else {
                            notifications.error('Trade lost!');
                        }
                    }
                }, tradeData.duration * 1000);

            } catch (error) {
                notifications.error('Failed to place trade: ' + error.message);
            }
        };

        const resetDemoBalance = () => {
            demoBalance.value = 10000;
            notifications.success('Demo balance reset to $10,000');
        };

        const formatDuration = (seconds) => {
            if (seconds < 60) return `${seconds}s`;
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return remainingSeconds > 0 ? `${minutes}m ${remainingSeconds}s` : `${minutes}m`;
        };

        onMounted(() => {
            initBinaryChart();
        });

        const initBinaryChart = () => {
            const ctx = document.getElementById('binaryChart');
            if (ctx) {
                // Generate real-time like data
                const labels = Array.from({ length: 100 }, (_, i) => i + 1);
                const prices = [];
                let price = selectedAsset.value.price;
                
                for (let i = 0; i < 100; i++) {
                    price += (Math.random() - 0.5) * 50;
                    prices.push(price);
                }

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Price',
                            data: prices,
                            borderColor: '#00D9FF',
                            backgroundColor: 'rgba(0, 217, 255, 0.1)',
                            tension: 0.1,
                            fill: true,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                grid: {
                                    color: '#2D3748'
                                },
                                ticks: {
                                    color: '#A0AEC0'
                                }
                            },
                            x: {
                                grid: {
                                    color: '#2D3748'
                                },
                                ticks: {
                                    color: '#A0AEC0'
                                }
                            }
                        }
                    }
                });
            }
        };

        return {
            assets,
            selectedAsset,
            payoutPercentage,
            demoBalance,
            quickAmounts,
            trade,
            activeTrades,
            potentialProfit,
            canPlaceTrade,
            placeBinaryTrade,
            resetDemoBalance
        };
    }
};

// Router Configuration
const routes = [
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', component: Dashboard },
    { path: '/trading', component: Trading },
    { path: '/binary', component: BinaryTrading },
    // Add more routes as needed
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

// Main App
const app = createApp({
    setup() {
        const sidebarOpen = ref(false);

        const toggleSidebar = () => {
            sidebarOpen.value = !sidebarOpen.value;
        };

        // Initialize app
        onMounted(async () => {
            if (globalState.token) {
                await auth.getUser();
                websocket.connect();
            }
        });

        onUnmounted(() => {
            websocket.disconnect();
        });

        return {
            sidebarOpen,
            toggleSidebar,
            notifications: globalState.notifications,
            isAuthenticated: computed(() => globalState.isAuthenticated)
        };
    }
});

app.use(router);
app.mount('#app');