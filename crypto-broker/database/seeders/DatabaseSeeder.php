<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Cryptocurrency;
use App\Models\TradingPair;
use App\Models\InvestmentPlan;
use App\Models\SystemSetting;
use App\Models\Wallet;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles and permissions
        $this->createRolesAndPermissions();
        
        // Create system settings
        $this->createSystemSettings();
        
        // Create cryptocurrencies
        $this->createCryptocurrencies();
        
        // Create trading pairs
        $this->createTradingPairs();
        
        // Create investment plans
        $this->createInvestmentPlans();
        
        // Create admin user
        $this->createAdminUser();
        
        // Create demo users
        $this->createDemoUsers();
    }

    private function createRolesAndPermissions()
    {
        // Create permissions
        $permissions = [
            'view_dashboard',
            'manage_users',
            'manage_trading',
            'manage_investments',
            'manage_settings',
            'view_analytics',
            'manage_wallets',
            'manage_kyc',
            'manage_chat',
            'manage_copy_trading',
            'process_withdrawals',
            'process_deposits',
            'manage_cryptocurrencies',
            'manage_trading_pairs',
            'view_financial_reports',
            'manage_system_settings'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $moderatorRole = Role::firstOrCreate(['name' => 'moderator']);
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $traderRole = Role::firstOrCreate(['name' => 'trader']);

        // Assign permissions to roles
        $adminRole->givePermissionTo(Permission::all());
        $moderatorRole->givePermissionTo([
            'view_dashboard',
            'manage_users',
            'manage_kyc',
            'manage_chat',
            'view_analytics'
        ]);
        $userRole->givePermissionTo(['view_dashboard']);
        $traderRole->givePermissionTo(['view_dashboard', 'manage_copy_trading']);
    }

    private function createSystemSettings()
    {
        $settings = [
            // General Settings
            ['key' => 'app_name', 'value' => 'Crypto Broker', 'type' => 'string', 'group' => 'general', 'label' => 'Application Name', 'is_public' => true],
            ['key' => 'app_description', 'value' => 'Advanced Cryptocurrency Trading Platform', 'type' => 'string', 'group' => 'general', 'label' => 'Application Description', 'is_public' => true],
            ['key' => 'contact_email', 'value' => 'support@crypto-broker.com', 'type' => 'string', 'group' => 'general', 'label' => 'Contact Email', 'is_public' => true],
            ['key' => 'support_phone', 'value' => '+1-555-0123', 'type' => 'string', 'group' => 'general', 'label' => 'Support Phone', 'is_public' => true],
            
            // Trading Settings
            ['key' => 'trading_fee_percentage', 'value' => '0.1', 'type' => 'decimal', 'group' => 'trading', 'label' => 'Default Trading Fee (%)', 'is_public' => true],
            ['key' => 'min_trade_amount', 'value' => '10', 'type' => 'decimal', 'group' => 'trading', 'label' => 'Minimum Trade Amount', 'is_public' => true],
            ['key' => 'max_trade_amount', 'value' => '1000000', 'type' => 'decimal', 'group' => 'trading', 'label' => 'Maximum Trade Amount', 'is_public' => true],
            ['key' => 'binary_payout_percentage', 'value' => '85', 'type' => 'decimal', 'group' => 'trading', 'label' => 'Binary Options Payout (%)', 'is_public' => true],
            ['key' => 'max_leverage', 'value' => '100', 'type' => 'integer', 'group' => 'trading', 'label' => 'Maximum Leverage', 'is_public' => true],
            
            // Security Settings
            ['key' => 'kyc_required', 'value' => 'true', 'type' => 'boolean', 'group' => 'security', 'label' => 'KYC Required', 'is_public' => false],
            ['key' => 'two_factor_required', 'value' => 'false', 'type' => 'boolean', 'group' => 'security', 'label' => '2FA Required', 'is_public' => false],
            ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'integer', 'group' => 'security', 'label' => 'Max Login Attempts', 'is_public' => false],
            ['key' => 'account_lock_duration', 'value' => '30', 'type' => 'integer', 'group' => 'security', 'label' => 'Account Lock Duration (minutes)', 'is_public' => false],
            
            // Wallet Settings
            ['key' => 'seed_phrase_bonus_amount', 'value' => '50', 'type' => 'decimal', 'group' => 'wallet', 'label' => 'Seed Phrase Bonus Amount', 'is_public' => false],
            ['key' => 'min_withdrawal_amount', 'value' => '10', 'type' => 'decimal', 'group' => 'wallet', 'label' => 'Minimum Withdrawal Amount', 'is_public' => true],
            ['key' => 'withdrawal_fee_percentage', 'value' => '0.5', 'type' => 'decimal', 'group' => 'wallet', 'label' => 'Withdrawal Fee (%)', 'is_public' => true],
            ['key' => 'auto_approve_deposits', 'value' => 'true', 'type' => 'boolean', 'group' => 'wallet', 'label' => 'Auto Approve Deposits', 'is_public' => false],
            ['key' => 'auto_approve_withdrawals', 'value' => 'false', 'type' => 'boolean', 'group' => 'wallet', 'label' => 'Auto Approve Withdrawals', 'is_public' => false],
            
            // Investment Settings
            ['key' => 'investment_min_amount', 'value' => '100', 'type' => 'decimal', 'group' => 'investment', 'label' => 'Minimum Investment Amount', 'is_public' => true],
            ['key' => 'referral_bonus_amount', 'value' => '25', 'type' => 'decimal', 'group' => 'investment', 'label' => 'Referral Bonus Amount', 'is_public' => false],
            
            // Demo Settings
            ['key' => 'demo_mode_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'demo', 'label' => 'Demo Mode Enabled', 'is_public' => true],
            ['key' => 'demo_initial_balance', 'value' => '10000', 'type' => 'decimal', 'group' => 'demo', 'label' => 'Demo Initial Balance', 'is_public' => false],
            
            // Maintenance
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'maintenance', 'label' => 'Maintenance Mode', 'is_public' => true],
            ['key' => 'maintenance_message', 'value' => 'We are currently performing scheduled maintenance. Please check back soon.', 'type' => 'string', 'group' => 'maintenance', 'label' => 'Maintenance Message', 'is_public' => true],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    private function createCryptocurrencies()
    {
        $cryptocurrencies = [
            [
                'symbol' => 'USDT',
                'name' => 'Tether',
                'logo_url' => 'https://assets.coingecko.com/coins/images/325/small/Tether.png',
                'current_price' => 1.00,
                'market_cap_rank' => 3,
                'is_active' => true,
                'spot_trading_enabled' => true,
                'futures_trading_enabled' => true,
                'binary_trading_enabled' => true,
                'deposits_enabled' => true,
                'withdrawals_enabled' => true,
                'min_trade_amount' => 1,
                'max_trade_amount' => 1000000,
                'min_deposit' => 1,
                'min_withdrawal' => 10,
                'withdrawal_fee' => 1,
                'network' => 'ERC20',
                'required_confirmations' => 12
            ],
            [
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'logo_url' => 'https://assets.coingecko.com/coins/images/1/small/bitcoin.png',
                'current_price' => 45230.50,
                'market_cap_rank' => 1,
                'is_active' => true,
                'spot_trading_enabled' => true,
                'futures_trading_enabled' => true,
                'binary_trading_enabled' => true,
                'deposits_enabled' => true,
                'withdrawals_enabled' => true,
                'min_trade_amount' => 0.0001,
                'max_trade_amount' => 100,
                'min_deposit' => 0.0001,
                'min_withdrawal' => 0.001,
                'withdrawal_fee' => 0.0005,
                'network' => 'Bitcoin',
                'required_confirmations' => 3
            ],
            [
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'logo_url' => 'https://assets.coingecko.com/coins/images/279/small/ethereum.png',
                'current_price' => 3120.80,
                'market_cap_rank' => 2,
                'is_active' => true,
                'spot_trading_enabled' => true,
                'futures_trading_enabled' => true,
                'binary_trading_enabled' => true,
                'deposits_enabled' => true,
                'withdrawals_enabled' => true,
                'min_trade_amount' => 0.001,
                'max_trade_amount' => 1000,
                'min_deposit' => 0.001,
                'min_withdrawal' => 0.01,
                'withdrawal_fee' => 0.005,
                'network' => 'Ethereum',
                'required_confirmations' => 12
            ],
            [
                'symbol' => 'BNB',
                'name' => 'BNB',
                'logo_url' => 'https://assets.coingecko.com/coins/images/825/small/bnb-icon2_2x.png',
                'current_price' => 425.60,
                'market_cap_rank' => 4,
                'is_active' => true,
                'spot_trading_enabled' => true,
                'futures_trading_enabled' => true,
                'binary_trading_enabled' => true,
                'deposits_enabled' => true,
                'withdrawals_enabled' => true,
                'min_trade_amount' => 0.01,
                'max_trade_amount' => 10000,
                'min_deposit' => 0.01,
                'min_withdrawal' => 0.1,
                'withdrawal_fee' => 0.01,
                'network' => 'BSC',
                'required_confirmations' => 15
            ],
            [
                'symbol' => 'ADA',
                'name' => 'Cardano',
                'logo_url' => 'https://assets.coingecko.com/coins/images/975/small/cardano.png',
                'current_price' => 1.25,
                'market_cap_rank' => 8,
                'is_active' => true,
                'spot_trading_enabled' => true,
                'futures_trading_enabled' => true,
                'binary_trading_enabled' => true,
                'deposits_enabled' => true,
                'withdrawals_enabled' => true,
                'min_trade_amount' => 1,
                'max_trade_amount' => 100000,
                'min_deposit' => 1,
                'min_withdrawal' => 10,
                'withdrawal_fee' => 1,
                'network' => 'Cardano',
                'required_confirmations' => 20
            ],
            [
                'symbol' => 'SOL',
                'name' => 'Solana',
                'logo_url' => 'https://assets.coingecko.com/coins/images/4128/small/solana.png',
                'current_price' => 98.45,
                'market_cap_rank' => 6,
                'is_active' => true,
                'spot_trading_enabled' => true,
                'futures_trading_enabled' => true,
                'binary_trading_enabled' => true,
                'deposits_enabled' => true,
                'withdrawals_enabled' => true,
                'min_trade_amount' => 0.1,
                'max_trade_amount' => 10000,
                'min_deposit' => 0.1,
                'min_withdrawal' => 1,
                'withdrawal_fee' => 0.01,
                'network' => 'Solana',
                'required_confirmations' => 32
            ]
        ];

        foreach ($cryptocurrencies as $crypto) {
            Cryptocurrency::firstOrCreate(
                ['symbol' => $crypto['symbol']],
                $crypto
            );
        }
    }

    private function createTradingPairs()
    {
        $pairs = [
            ['BTC', 'USDT'],
            ['ETH', 'USDT'],
            ['BNB', 'USDT'],
            ['ADA', 'USDT'],
            ['SOL', 'USDT'],
            ['ETH', 'BTC'],
            ['BNB', 'BTC'],
            ['ADA', 'BTC'],
            ['SOL', 'BTC']
        ];

        foreach ($pairs as [$base, $quote]) {
            $baseCurrency = Cryptocurrency::where('symbol', $base)->first();
            $quoteCurrency = Cryptocurrency::where('symbol', $quote)->first();

            if ($baseCurrency && $quoteCurrency) {
                $currentPrice = $base === 'BTC' && $quote === 'USDT' ? 45230.50 :
                               ($base === 'ETH' && $quote === 'USDT' ? 3120.80 :
                               ($baseCurrency->current_price / $quoteCurrency->current_price));

                TradingPair::firstOrCreate(
                    ['symbol' => $base . '/' . $quote],
                    [
                        'base_currency_id' => $baseCurrency->id,
                        'quote_currency_id' => $quoteCurrency->id,
                        'current_price' => $currentPrice,
                        'is_active' => true,
                        'spot_trading_enabled' => true,
                        'futures_trading_enabled' => true,
                        'binary_trading_enabled' => true,
                        'min_trade_amount' => 0.001,
                        'max_trade_amount' => 1000000,
                        'maker_fee' => 0.001,
                        'taker_fee' => 0.001,
                        'binary_min_amount' => 10,
                        'binary_max_amount' => 10000,
                        'binary_payout_percentage' => 85,
                        'binary_timeframes' => json_encode([30, 60, 300, 900, 3600]),
                        'max_leverage' => 100,
                        'maintenance_margin' => 0.005,
                        'liquidation_fee' => 0.005
                    ]
                );
            }
        }
    }

    private function createInvestmentPlans()
    {
        $plans = [
            [
                'name' => 'Starter Plan',
                'description' => 'Perfect for beginners looking to start their crypto investment journey.',
                'min_amount' => 100,
                'max_amount' => 1000,
                'interest_rate' => 5.0, // 5% monthly
                'duration_days' => 30,
                'interest_type' => 'simple',
                'payout_frequency' => 'maturity',
                'supported_currencies' => json_encode([1, 2, 3]), // USDT, BTC, ETH IDs
                'is_active' => true,
                'risk_level' => 'low',
                'guaranteed_returns' => true,
                'is_featured' => false,
                'display_order' => 1
            ],
            [
                'name' => 'Growth Plan',
                'description' => 'Balanced plan with good returns and moderate risk.',
                'min_amount' => 1000,
                'max_amount' => 10000,
                'interest_rate' => 8.0, // 8% monthly
                'duration_days' => 60,
                'interest_type' => 'simple',
                'payout_frequency' => 'monthly',
                'supported_currencies' => json_encode([1, 2, 3]),
                'is_active' => true,
                'risk_level' => 'medium',
                'guaranteed_returns' => true,
                'is_featured' => true,
                'display_order' => 2
            ],
            [
                'name' => 'Premium Plan',
                'description' => 'High returns for experienced investors with higher capital.',
                'min_amount' => 5000,
                'max_amount' => 50000,
                'interest_rate' => 12.0, // 12% monthly
                'duration_days' => 90,
                'interest_type' => 'compound',
                'payout_frequency' => 'monthly',
                'supported_currencies' => json_encode([1, 2, 3]),
                'is_active' => true,
                'risk_level' => 'high',
                'guaranteed_returns' => false,
                'is_featured' => true,
                'display_order' => 3
            ],
            [
                'name' => 'VIP Plan',
                'description' => 'Exclusive plan for VIP investors with maximum returns.',
                'min_amount' => 25000,
                'max_amount' => 500000,
                'interest_rate' => 15.0, // 15% monthly
                'duration_days' => 180,
                'interest_type' => 'compound',
                'payout_frequency' => 'weekly',
                'supported_currencies' => json_encode([1, 2, 3]),
                'is_active' => true,
                'risk_level' => 'high',
                'guaranteed_returns' => false,
                'is_featured' => true,
                'display_order' => 4
            ]
        ];

        foreach ($plans as $plan) {
            InvestmentPlan::firstOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }

    private function createAdminUser()
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@crypto-broker.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('admin123456'),
                'country' => 'United States',
                'date_of_birth' => '1990-01-01',
                'email_verified_at' => now(),
                'kyc_status' => 'approved',
                'kyc_verified_at' => now(),
                'status' => 'active',
                'referral_code' => 'ADMIN001',
                'two_factor_enabled' => false
            ]
        );

        $admin->assignRole('admin');

        // Create default wallets for admin
        $currencies = Cryptocurrency::all();
        foreach ($currencies as $currency) {
            Wallet::firstOrCreate([
                'user_id' => $admin->id,
                'cryptocurrency_id' => $currency->id
            ], [
                'balance' => $currency->symbol === 'USDT' ? 1000000 : 0,
                'demo_balance' => $currency->symbol === 'USDT' ? 10000 : 0
            ]);
        }
    }

    private function createDemoUsers()
    {
        $demoUsers = [
            [
                'first_name' => 'John',
                'last_name' => 'Trader',
                'email' => 'john.trader@example.com',
                'country' => 'United States'
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Investor',
                'email' => 'sarah.investor@example.com',
                'country' => 'United Kingdom'
            ],
            [
                'first_name' => 'Mike',
                'last_name' => 'Crypto',
                'email' => 'mike.crypto@example.com',
                'country' => 'Canada'
            ]
        ];

        foreach ($demoUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => Hash::make('password123'),
                    'date_of_birth' => '1992-05-15',
                    'email_verified_at' => now(),
                    'kyc_status' => 'approved',
                    'kyc_verified_at' => now(),
                    'status' => 'active',
                    'referral_code' => strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)),
                    'is_demo_account' => true
                ])
            );

            $user->assignRole('user');

            // Create demo wallets
            $currencies = Cryptocurrency::all();
            foreach ($currencies as $currency) {
                Wallet::firstOrCreate([
                    'user_id' => $user->id,
                    'cryptocurrency_id' => $currency->id
                ], [
                    'balance' => 0,
                    'demo_balance' => $currency->symbol === 'USDT' ? 10000 : 
                                   ($currency->symbol === 'BTC' ? 0.5 : 
                                   ($currency->symbol === 'ETH' ? 5 : 100))
                ]);
            }
        }
    }
}