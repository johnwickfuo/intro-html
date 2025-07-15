<?php
class CryptoAPI {
    private $db;
    private $apiKey;
    private $baseUrl = 'https://api.coingecko.com/api/v3';
    private $cacheExpiry = 300; // 5 minutes in seconds
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadAPIKey();
    }
    
    /**
     * Load API key from database settings
     */
    private function loadAPIKey() {
        $stmt = $this->db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'coingecko_api_key'");
        $stmt->execute();
        $result = $stmt->fetch();
        $this->apiKey = $result['setting_value'] ?? '';
    }
    
    /**
     * Get cryptocurrency prices
     */
    public function getPrices($ids = ['bitcoin', 'ethereum', 'tether'], $vsCurrency = 'usd') {
        $cacheKey = "prices_" . implode('_', $ids) . "_" . $vsCurrency;
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $idsString = implode(',', $ids);
        $url = "{$this->baseUrl}/simple/price?ids={$idsString}&vs_currencies={$vsCurrency}&include_24hr_change=true&include_24hr_vol=true";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return null;
    }
    
    /**
     * Get single coin price
     */
    public function getCoinPrice($coinId, $vsCurrency = 'usd') {
        $prices = $this->getPrices([$coinId], $vsCurrency);
        return $prices[$coinId] ?? null;
    }
    
    /**
     * Get coin market data
     */
    public function getCoinMarketData($coinId) {
        $cacheKey = "market_data_{$coinId}";
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/coins/{$coinId}?localization=false&tickers=false&market_data=true&community_data=false&developer_data=false&sparkline=false";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $marketData = [
                'id' => $response['id'],
                'symbol' => $response['symbol'],
                'name' => $response['name'],
                'current_price' => $response['market_data']['current_price']['usd'],
                'market_cap' => $response['market_data']['market_cap']['usd'],
                'market_cap_rank' => $response['market_data']['market_cap_rank'],
                'total_volume' => $response['market_data']['total_volume']['usd'],
                'high_24h' => $response['market_data']['high_24h']['usd'],
                'low_24h' => $response['market_data']['low_24h']['usd'],
                'price_change_24h' => $response['market_data']['price_change_24h'],
                'price_change_percentage_24h' => $response['market_data']['price_change_percentage_24h'],
                'circulating_supply' => $response['market_data']['circulating_supply'],
                'total_supply' => $response['market_data']['total_supply'],
                'max_supply' => $response['market_data']['max_supply'],
                'ath' => $response['market_data']['ath']['usd'],
                'ath_change_percentage' => $response['market_data']['ath_change_percentage']['usd'],
                'ath_date' => $response['market_data']['ath_date']['usd'],
                'atl' => $response['market_data']['atl']['usd'],
                'atl_change_percentage' => $response['market_data']['atl_change_percentage']['usd'],
                'atl_date' => $response['market_data']['atl_date']['usd'],
                'last_updated' => $response['last_updated']
            ];
            
            $this->saveToCache($cacheKey, $marketData);
            return $marketData;
        }
        
        return null;
    }
    
    /**
     * Get top cryptocurrencies by market cap
     */
    public function getTopCoins($limit = 20, $page = 1) {
        $cacheKey = "top_coins_{$limit}_{$page}";
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/coins/markets?vs_currency=usd&order=market_cap_desc&per_page={$limit}&page={$page}&sparkline=false&price_change_percentage=1h%2C24h%2C7d";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return null;
    }
    
    /**
     * Get historical price data
     */
    public function getHistoricalPrices($coinId, $days = 30, $interval = 'daily') {
        $cacheKey = "historical_{$coinId}_{$days}_{$interval}";
        
        // Check cache first (longer cache for historical data)
        $cached = $this->getFromCache($cacheKey, 3600); // 1 hour cache
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/coins/{$coinId}/market_chart?vs_currency=usd&days={$days}&interval={$interval}";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $this->saveToCache($cacheKey, $response, 3600);
            return $response;
        }
        
        return null;
    }
    
    /**
     * Search cryptocurrencies
     */
    public function searchCoins($query) {
        $cacheKey = "search_" . md5($query);
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/search?query=" . urlencode($query);
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return null;
    }
    
    /**
     * Get trending cryptocurrencies
     */
    public function getTrendingCoins() {
        $cacheKey = "trending_coins";
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/search/trending";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return null;
    }
    
    /**
     * Get global market data
     */
    public function getGlobalMarketData() {
        $cacheKey = "global_market_data";
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/global";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $globalData = [
                'active_cryptocurrencies' => $response['data']['active_cryptocurrencies'],
                'upcoming_icos' => $response['data']['upcoming_icos'],
                'ongoing_icos' => $response['data']['ongoing_icos'],
                'ended_icos' => $response['data']['ended_icos'],
                'markets' => $response['data']['markets'],
                'total_market_cap' => $response['data']['total_market_cap']['usd'],
                'total_volume' => $response['data']['total_volume']['usd'],
                'market_cap_percentage' => $response['data']['market_cap_percentage'],
                'market_cap_change_percentage_24h_usd' => $response['data']['market_cap_change_percentage_24h_usd'],
                'updated_at' => $response['data']['updated_at']
            ];
            
            $this->saveToCache($cacheKey, $globalData);
            return $globalData;
        }
        
        return null;
    }
    
    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies() {
        $cacheKey = "supported_currencies";
        
        // Check cache first (longer cache for currencies)
        $cached = $this->getFromCache($cacheKey, 86400); // 24 hours cache
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/simple/supported_vs_currencies";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $this->saveToCache($cacheKey, $response, 86400);
            return $response;
        }
        
        return null;
    }
    
    /**
     * Get exchange rates
     */
    public function getExchangeRates() {
        $cacheKey = "exchange_rates";
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $url = "{$this->baseUrl}/exchange_rates";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response) {
            $this->saveToCache($cacheKey, $response);
            return $response;
        }
        
        return null;
    }
    
    /**
     * Convert between currencies
     */
    public function convertCurrency($amount, $fromCurrency, $toCurrency) {
        $rates = $this->getExchangeRates();
        
        if (!$rates) {
            return null;
        }
        
        $fromRate = $rates['rates'][$fromCurrency]['value'] ?? 1;
        $toRate = $rates['rates'][$toCurrency]['value'] ?? 1;
        
        // Convert to USD first, then to target currency
        $usdAmount = $amount / $fromRate;
        $convertedAmount = $usdAmount * $toRate;
        
        return $convertedAmount;
    }
    
    /**
     * Get fear and greed index
     */
    public function getFearGreedIndex() {
        $cacheKey = "fear_greed_index";
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        // Alternative API for fear and greed index
        $url = "https://api.alternative.me/fng/";
        
        $response = $this->makeAPIRequest($url);
        
        if ($response && isset($response['data'])) {
            $indexData = [
                'value' => $response['data'][0]['value'],
                'value_classification' => $response['data'][0]['value_classification'],
                'timestamp' => $response['data'][0]['timestamp'],
                'time_until_update' => $response['data'][0]['time_until_update']
            ];
            
            $this->saveToCache($cacheKey, $indexData);
            return $indexData;
        }
        
        return null;
    }
    
    /**
     * Make API request with error handling
     */
    private function makeAPIRequest($url) {
        $options = [
            'http' => [
                'header' => [
                    'User-Agent: CryptoTrade Pro',
                    'Accept: application/json'
                ],
                'method' => 'GET',
                'timeout' => 30
            ]
        ];
        
        // Add API key if available
        if ($this->apiKey) {
            $options['http']['header'][] = 'x-cg-pro-api-key: ' . $this->apiKey;
        }
        
        $context = stream_context_create($options);
        
        try {
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                $this->logError("Failed to fetch data from: $url");
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError("JSON decode error: " . json_last_error_msg());
                return null;
            }
            
            return $data;
            
        } catch (Exception $e) {
            $this->logError("API request exception: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get data from cache
     */
    private function getFromCache($key, $customExpiry = null) {
        $expiry = $customExpiry ?? $this->cacheExpiry;
        
        $stmt = $this->db->prepare("
            SELECT data FROM api_cache 
            WHERE cache_key = ? AND expires_at > NOW()
        ");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        if ($result) {
            return json_decode($result['data'], true);
        }
        
        return null;
    }
    
    /**
     * Save data to cache
     */
    private function saveToCache($key, $data, $customExpiry = null) {
        $expiry = $customExpiry ?? $this->cacheExpiry;
        $expiresAt = date('Y-m-d H:i:s', time() + $expiry);
        
        // Create cache table if it doesn't exist
        $this->createCacheTable();
        
        $stmt = $this->db->prepare("
            INSERT INTO api_cache (cache_key, data, expires_at) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)
        ");
        
        $stmt->execute([$key, json_encode($data), $expiresAt]);
    }
    
    /**
     * Create cache table if it doesn't exist
     */
    private function createCacheTable() {
        $stmt = $this->db->prepare("
            CREATE TABLE IF NOT EXISTS api_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) NOT NULL UNIQUE,
                data LONGTEXT NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cache_key (cache_key),
                INDEX idx_expires (expires_at)
            )
        ");
        $stmt->execute();
    }
    
    /**
     * Clean expired cache entries
     */
    public function cleanExpiredCache() {
        $stmt = $this->db->prepare("DELETE FROM api_cache WHERE expires_at < NOW()");
        $stmt->execute();
    }
    
    /**
     * Log errors
     */
    private function logError($message) {
        error_log("CryptoAPI Error: " . $message);
        
        // Also log to database if audit_logs table exists
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (action, new_values, ip_address, created_at) 
                VALUES ('crypto_api_error', ?, ?, NOW())
            ");
            $stmt->execute([
                json_encode(['error' => $message]),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            // Ignore database logging errors
        }
    }
    
    /**
     * Get formatted price with symbol
     */
    public function formatPrice($price, $currency = 'USD') {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'BTC' => '₿',
            'ETH' => 'Ξ'
        ];
        
        $symbol = $symbols[strtoupper($currency)] ?? strtoupper($currency);
        
        if ($price >= 1000000000) {
            return $symbol . number_format($price / 1000000000, 2) . 'B';
        } elseif ($price >= 1000000) {
            return $symbol . number_format($price / 1000000, 2) . 'M';
        } elseif ($price >= 1000) {
            return $symbol . number_format($price / 1000, 2) . 'K';
        } else {
            return $symbol . number_format($price, 2);
        }
    }
    
    /**
     * Get price change color class
     */
    public function getPriceChangeClass($change) {
        if ($change > 0) {
            return 'text-success';
        } elseif ($change < 0) {
            return 'text-danger';
        } else {
            return 'text-muted';
        }
    }
    
    /**
     * Get supported trading pairs
     */
    public function getSupportedPairs() {
        return [
            'BTC/USD' => ['base' => 'bitcoin', 'quote' => 'usd'],
            'ETH/USD' => ['base' => 'ethereum', 'quote' => 'usd'],
            'BTC/ETH' => ['base' => 'bitcoin', 'quote' => 'ethereum'],
            'LTC/USD' => ['base' => 'litecoin', 'quote' => 'usd'],
            'XRP/USD' => ['base' => 'ripple', 'quote' => 'usd'],
            'ADA/USD' => ['base' => 'cardano', 'quote' => 'usd'],
            'DOT/USD' => ['base' => 'polkadot', 'quote' => 'usd'],
            'LINK/USD' => ['base' => 'chainlink', 'quote' => 'usd'],
            'UNI/USD' => ['base' => 'uniswap', 'quote' => 'usd'],
            'DOGE/USD' => ['base' => 'dogecoin', 'quote' => 'usd']
        ];
    }
    
    /**
     * Get real-time price for trading pair
     */
    public function getTradingPairPrice($pair) {
        $pairs = $this->getSupportedPairs();
        
        if (!isset($pairs[$pair])) {
            return null;
        }
        
        $baseId = $pairs[$pair]['base'];
        $quoteId = $pairs[$pair]['quote'];
        
        if ($quoteId === 'usd') {
            return $this->getCoinPrice($baseId);
        } else {
            $basePrice = $this->getCoinPrice($baseId);
            $quotePrice = $this->getCoinPrice($quoteId);
            
            if ($basePrice && $quotePrice) {
                return $basePrice['usd'] / $quotePrice['usd'];
            }
        }
        
        return null;
    }
}
?>