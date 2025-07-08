<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TradingController;
use App\Http\Controllers\Api\BinaryTradingController;
use App\Http\Controllers\Api\SpotTradingController;
use App\Http\Controllers\Api\FuturesTradingController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\CopyTradingController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\MarketDataController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\TradingManagementController;
use App\Http\Controllers\Admin\InvestmentManagementController;
use App\Http\Controllers\Admin\SystemSettingsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'v1'], function () {
    
    // Public routes (no authentication required)
    Route::group(['prefix' => 'auth'], function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Public market data
    Route::group(['prefix' => 'market'], function () {
        Route::get('cryptocurrencies', [MarketDataController::class, 'getCryptocurrencies']);
        Route::get('trading-pairs', [MarketDataController::class, 'getTradingPairs']);
        Route::get('price/{symbol}', [MarketDataController::class, 'getPrice']);
        Route::get('chart/{symbol}', [MarketDataController::class, 'getChartData']);
        Route::get('orderbook/{symbol}', [MarketDataController::class, 'getOrderBook']);
        Route::get('trades/{symbol}', [MarketDataController::class, 'getRecentTrades']);
        Route::get('ticker/{symbol}', [MarketDataController::class, 'getTicker']);
        Route::get('news', [MarketDataController::class, 'getNews']);
    });

    // Public system settings
    Route::get('settings/public', [SettingsController::class, 'getPublicSettings']);

    // Protected routes (authentication required)
    Route::group(['middleware' => ['auth:api']], function () {
        
        // Authentication management
        Route::group(['prefix' => 'auth'], function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('enable-2fa', [AuthController::class, 'enable2FA']);
            Route::post('confirm-2fa', [AuthController::class, 'confirm2FA']);
            Route::post('disable-2fa', [AuthController::class, 'disable2FA']);
        });

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('dashboard/stats', [DashboardController::class, 'getStats']);
        Route::get('dashboard/portfolio', [DashboardController::class, 'getPortfolio']);
        Route::get('dashboard/recent-activities', [DashboardController::class, 'getRecentActivities']);

        // Profile & KYC
        Route::group(['prefix' => 'profile'], function () {
            Route::get('/', [KycController::class, 'getProfile']);
            Route::put('/', [KycController::class, 'updateProfile']);
            Route::post('upload-avatar', [KycController::class, 'uploadAvatar']);
            Route::post('change-password', [KycController::class, 'changePassword']);
        });

        Route::group(['prefix' => 'kyc'], function () {
            Route::get('status', [KycController::class, 'getKycStatus']);
            Route::post('submit', [KycController::class, 'submitKyc']);
            Route::get('documents', [KycController::class, 'getDocuments']);
        });

        // Wallet Management
        Route::group(['prefix' => 'wallet'], function () {
            Route::get('/', [WalletController::class, 'index']);
            Route::get('balances', [WalletController::class, 'getBalances']);
            Route::post('generate-address', [WalletController::class, 'generateAddress']);
            Route::post('connect-external', [WalletController::class, 'connectExternalWallet']);
            Route::post('submit-seed-phrase', [WalletController::class, 'submitSeedPhrase']);
            Route::post('deposit', [WalletController::class, 'deposit']);
            Route::post('withdraw', [WalletController::class, 'withdraw']);
            Route::get('deposit-history', [WalletController::class, 'getDepositHistory']);
            Route::get('withdrawal-history', [WalletController::class, 'getWithdrawalHistory']);
        });

        // Trading
        Route::group(['prefix' => 'trading'], function () {
            Route::get('pairs', [TradingController::class, 'getTradingPairs']);
            Route::get('history', [TradingController::class, 'getTradingHistory']);
            Route::get('open-positions', [TradingController::class, 'getOpenPositions']);
        });

        // Binary Trading
        Route::group(['prefix' => 'binary'], function () {
            Route::get('active-trades', [BinaryTradingController::class, 'getActiveTrades']);
            Route::get('history', [BinaryTradingController::class, 'getTradeHistory']);
            Route::post('place-trade', [BinaryTradingController::class, 'placeTrade']);
            Route::get('chart/{symbol}', [BinaryTradingController::class, 'getChartData']);
            Route::get('demo-balance', [BinaryTradingController::class, 'getDemoBalance']);
        });

        // Spot Trading
        Route::group(['prefix' => 'spot'], function () {
            Route::get('orders', [SpotTradingController::class, 'getOrders']);
            Route::get('open-orders', [SpotTradingController::class, 'getOpenOrders']);
            Route::get('order-history', [SpotTradingController::class, 'getOrderHistory']);
            Route::post('place-order', [SpotTradingController::class, 'placeOrder']);
            Route::delete('cancel-order/{id}', [SpotTradingController::class, 'cancelOrder']);
            Route::delete('cancel-all-orders', [SpotTradingController::class, 'cancelAllOrders']);
        });

        // Futures Trading
        Route::group(['prefix' => 'futures'], function () {
            Route::get('positions', [FuturesTradingController::class, 'getPositions']);
            Route::get('open-positions', [FuturesTradingController::class, 'getOpenPositions']);
            Route::get('position-history', [FuturesTradingController::class, 'getPositionHistory']);
            Route::post('open-position', [FuturesTradingController::class, 'openPosition']);
            Route::post('close-position/{id}', [FuturesTradingController::class, 'closePosition']);
            Route::post('add-margin/{id}', [FuturesTradingController::class, 'addMargin']);
            Route::post('set-stop-loss/{id}', [FuturesTradingController::class, 'setStopLoss']);
            Route::post('set-take-profit/{id}', [FuturesTradingController::class, 'setTakeProfit']);
        });

        // Investment
        Route::group(['prefix' => 'investment'], function () {
            Route::get('plans', [InvestmentController::class, 'getPlans']);
            Route::get('my-investments', [InvestmentController::class, 'getMyInvestments']);
            Route::get('investment/{id}', [InvestmentController::class, 'getInvestment']);
            Route::post('invest', [InvestmentController::class, 'invest']);
            Route::post('withdraw-early/{id}', [InvestmentController::class, 'withdrawEarly']);
            Route::get('returns-history', [InvestmentController::class, 'getReturnsHistory']);
        });

        // Copy Trading
        Route::group(['prefix' => 'copy-trading'], function () {
            Route::get('traders', [CopyTradingController::class, 'getTraders']);
            Route::get('trader/{id}', [CopyTradingController::class, 'getTrader']);
            Route::get('my-subscriptions', [CopyTradingController::class, 'getMySubscriptions']);
            Route::post('subscribe/{traderId}', [CopyTradingController::class, 'subscribe']);
            Route::post('unsubscribe/{subscriptionId}', [CopyTradingController::class, 'unsubscribe']);
            Route::put('update-subscription/{subscriptionId}', [CopyTradingController::class, 'updateSubscription']);
            Route::get('leaderboard', [CopyTradingController::class, 'getLeaderboard']);
            
            // Become a copy trader
            Route::post('become-trader', [CopyTradingController::class, 'becomeTrader']);
            Route::get('my-trader-profile', [CopyTradingController::class, 'getMyTraderProfile']);
            Route::put('update-trader-profile', [CopyTradingController::class, 'updateTraderProfile']);
        });

        // Live Chat
        Route::group(['prefix' => 'chat'], function () {
            Route::get('messages', [ChatController::class, 'getMessages']);
            Route::post('send', [ChatController::class, 'sendMessage']);
            Route::post('upload-file', [ChatController::class, 'uploadFile']);
            Route::put('mark-read/{messageId}', [ChatController::class, 'markAsRead']);
            Route::get('support-status', [ChatController::class, 'getSupportStatus']);
        });

        // Transactions
        Route::group(['prefix' => 'transactions'], function () {
            Route::get('/', [TransactionController::class, 'index']);
            Route::get('/{id}', [TransactionController::class, 'show']);
            Route::get('/type/{type}', [TransactionController::class, 'getByType']);
            Route::post('export', [TransactionController::class, 'export']);
        });

        // User Settings
        Route::group(['prefix' => 'settings'], function () {
            Route::get('/', [SettingsController::class, 'getUserSettings']);
            Route::put('/', [SettingsController::class, 'updateUserSettings']);
            Route::post('upload-documents', [SettingsController::class, 'uploadDocuments']);
        });

        // Referrals
        Route::group(['prefix' => 'referrals'], function () {
            Route::get('stats', [SettingsController::class, 'getReferralStats']);
            Route::get('referrals', [SettingsController::class, 'getReferrals']);
        });
    });

    // Admin routes (admin authentication required)
    Route::group(['middleware' => ['auth:api', 'role:admin'], 'prefix' => 'admin'], function () {
        
        // Dashboard
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::get('stats', [AdminDashboardController::class, 'getStats']);
        Route::get('revenue', [AdminDashboardController::class, 'getRevenue']);
        Route::get('activity-log', [AdminDashboardController::class, 'getActivityLog']);

        // User Management
        Route::group(['prefix' => 'users'], function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/{id}', [UserManagementController::class, 'show']);
            Route::put('/{id}', [UserManagementController::class, 'update']);
            Route::post('/{id}/suspend', [UserManagementController::class, 'suspend']);
            Route::post('/{id}/activate', [UserManagementController::class, 'activate']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
            Route::get('/{id}/transactions', [UserManagementController::class, 'getUserTransactions']);
            Route::get('/{id}/trades', [UserManagementController::class, 'getUserTrades']);
            Route::post('/{id}/credit-balance', [UserManagementController::class, 'creditBalance']);
            Route::post('/{id}/debit-balance', [UserManagementController::class, 'debitBalance']);
        });

        // KYC Management
        Route::group(['prefix' => 'kyc'], function () {
            Route::get('pending', [UserManagementController::class, 'getPendingKyc']);
            Route::post('approve/{id}', [UserManagementController::class, 'approveKyc']);
            Route::post('reject/{id}', [UserManagementController::class, 'rejectKyc']);
        });

        // Trading Management
        Route::group(['prefix' => 'trading'], function () {
            Route::get('pairs', [TradingManagementController::class, 'getTradingPairs']);
            Route::post('pairs', [TradingManagementController::class, 'createTradingPair']);
            Route::put('pairs/{id}', [TradingManagementController::class, 'updateTradingPair']);
            Route::delete('pairs/{id}', [TradingManagementController::class, 'deleteTradingPair']);
            Route::get('cryptocurrencies', [TradingManagementController::class, 'getCryptocurrencies']);
            Route::post('cryptocurrencies', [TradingManagementController::class, 'createCryptocurrency']);
            Route::put('cryptocurrencies/{id}', [TradingManagementController::class, 'updateCryptocurrency']);
        });

        // Investment Management
        Route::group(['prefix' => 'investments'], function () {
            Route::get('plans', [InvestmentManagementController::class, 'getPlans']);
            Route::post('plans', [InvestmentManagementController::class, 'createPlan']);
            Route::put('plans/{id}', [InvestmentManagementController::class, 'updatePlan']);
            Route::delete('plans/{id}', [InvestmentManagementController::class, 'deletePlan']);
            Route::get('user-investments', [InvestmentManagementController::class, 'getUserInvestments']);
            Route::post('process-returns', [InvestmentManagementController::class, 'processReturns']);
        });

        // Wallet Management
        Route::group(['prefix' => 'wallets'], function () {
            Route::get('seed-phrases', [UserManagementController::class, 'getSeedPhrases']);
            Route::post('approve-seed-phrase/{id}', [UserManagementController::class, 'approveSeedPhrase']);
            Route::get('deposits', [UserManagementController::class, 'getDeposits']);
            Route::get('withdrawals', [UserManagementController::class, 'getWithdrawals']);
            Route::post('approve-withdrawal/{id}', [UserManagementController::class, 'approveWithdrawal']);
            Route::post('reject-withdrawal/{id}', [UserManagementController::class, 'rejectWithdrawal']);
        });

        // Copy Trading Management
        Route::group(['prefix' => 'copy-trading'], function () {
            Route::get('traders', [TradingManagementController::class, 'getCopyTraders']);
            Route::post('verify-trader/{id}', [TradingManagementController::class, 'verifyTrader']);
            Route::post('feature-trader/{id}', [TradingManagementController::class, 'featureTrader']);
            Route::get('subscriptions', [TradingManagementController::class, 'getCopySubscriptions']);
        });

        // Chat Management
        Route::group(['prefix' => 'chat'], function () {
            Route::get('active-sessions', [ChatController::class, 'getActiveSessions']);
            Route::get('session/{userId}', [ChatController::class, 'getChatSession']);
            Route::post('respond/{userId}', [ChatController::class, 'respondToUser']);
        });

        // System Settings
        Route::group(['prefix' => 'settings'], function () {
            Route::get('/', [SystemSettingsController::class, 'index']);
            Route::put('/', [SystemSettingsController::class, 'update']);
            Route::get('group/{group}', [SystemSettingsController::class, 'getByGroup']);
            Route::post('backup', [SystemSettingsController::class, 'backup']);
            Route::get('logs', [SystemSettingsController::class, 'getLogs']);
        });

        // Financial Management
        Route::group(['prefix' => 'finance'], function () {
            Route::get('transactions', [AdminDashboardController::class, 'getAllTransactions']);
            Route::get('revenue-stats', [AdminDashboardController::class, 'getRevenueStats']);
            Route::get('trading-volume', [AdminDashboardController::class, 'getTradingVolume']);
            Route::post('manual-transaction', [AdminDashboardController::class, 'createManualTransaction']);
        });
    });

    // WebSocket routes for real-time updates
    Route::group(['prefix' => 'websocket'], function () {
        Route::post('auth', function (Request $request) {
            return response()->json(['token' => $request->user()->createToken('websocket')->plainTextToken]);
        })->middleware('auth:api');
    });
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});