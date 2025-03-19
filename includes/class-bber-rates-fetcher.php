<?php
/**
 * Class responsible for fetching exchange rates from Bangkok Bank.
 */
class BBER_Rates_Fetcher {
    /**
     * Bangkok Bank exchange rates URL.
     */
    const BANGKOK_BANK_URL = 'https://www.bangkokbank.com/en/Personal/Other-Services/View-Rates/Foreign-Exchange-Rates';
    
    /**
     * Alternative API endpoint for exchange rates.
     */
    const ALT_EXCHANGE_API = 'https://api.exchangerate-api.com/v4/latest/THB';
    
    /**
     * Third backup API endpoint for exchange rates.
     */
    const BACKUP_EXCHANGE_API = 'https://open.er-api.com/v6/latest/THB';

    /**
     * Proxy URL for making requests.
     */
    const PROXY_URL = 'https://proxy.example.com/api/v1/fetch';

    /**
     * Rate limit for making requests.
     */
    const RATE_LIMIT = 5; // Max requests per minute

    /**
     * Last request time for rate limiting.
     *
     * @var int
     */
    private $last_request_time = 0;

    /**
     * Request count for rate limiting.
     *
     * @var int
     */
    private $request_count = 0;

    /**
     * Check if the host is reachable.
     *
     * @param string $url URL to check.
     * @return bool Whether the host is reachable.
     */
    private function is_host_reachable($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        
        // Try DNS resolution first
        $dns_check = @dns_get_record($host, DNS_A);
        if (empty($dns_check)) {
            return false;
        }
        
        // Try a quick socket connection with short timeout
        $port = 443; // HTTPS default port
        $socket = @fsockopen('tcp://' . $host, $port, $errno, $errstr, 2);
        if (!$socket) {
            return false;
        }
        
        fclose($socket);
        return true;
    }

    /**
     * Make a request through the proxy.
     *
     * @param string $url URL to request.
     * @param array $args Arguments for the request.
     * @return array|WP_Error Response from the proxy or WP_Error on failure.
     */
    private function proxy_request($url, $args = array()) {
        // Rate limiting
        $current_time = time();
        if ($current_time - $this->last_request_time < 60) {
            if ($this->request_count >= self::RATE_LIMIT) {
                return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded');
            }
        } else {
            $this->request_count = 0;
            $this->last_request_time = $current_time;
        }

        $this->request_count++;

        // Make proxy request
        $proxy_args = array(
            'timeout' => 30,
            'body' => array(
                'url' => $url,
                'args' => $args
            )
        );

        $response = wp_remote_post(self::PROXY_URL, $proxy_args);

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Fetch exchange rates from Bangkok Bank website.
     *
     * @return array|WP_Error Array of exchange rates or WP_Error on failure.
     */
    public function fetch_rates() {
        // Get rates from cache if available and not expired
        $cached_rates = get_transient('bber_exchange_rates');
        if (false !== $cached_rates) {
            return $cached_rates;
        }
        
        // Get plugin options
        $options = get_option('bber_options', array());
        $markup_percentage = isset($options['markup_percentage']) ? floatval($options['markup_percentage']) : 3;
        $cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 3600;
        
        // Check if Bangkok Bank site is reachable before attempting request
        if (!$this->is_host_reachable(self::BANGKOK_BANK_URL)) {
            error_log('Bangkok Bank Exchange Rates: Bangkok Bank site is not reachable, using alternative source');
            return $this->handle_connectivity_fallback($markup_percentage, $cache_duration);
        }

        // Fetch the Bangkok Bank exchange rates page
        $response = $this->proxy_request(self::BANGKOK_BANK_URL, array(
            'timeout' => 30, // Increased timeout
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'sslverify' => false, // Skip SSL verification for some hosts with issues
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Connection' => 'keep-alive',
                'Cache-Control' => 'max-age=0',
            )
        ));

        // Check for errors
        if (is_wp_error($response)) {
            error_log('Bangkok Bank Exchange Rates: Error fetching from Bangkok Bank: ' . $response->get_error_message());
            return $this->handle_connectivity_fallback($markup_percentage, $cache_duration);
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            error_log('Bangkok Bank Exchange Rates: Bad response from Bangkok Bank: ' . $response_code);
            return $this->handle_connectivity_fallback($markup_percentage, $cache_duration);
        }

        // Get the body of the response
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('Bangkok Bank Exchange Rates: Empty response from Bangkok Bank');
            return $this->handle_connectivity_fallback($markup_percentage, $cache_duration);
        }

        // Parse the HTML to extract exchange rates
        $rates = $this->parse_rates($body);
        if (empty($rates)) {
            error_log('Bangkok Bank Exchange Rates: Failed to parse exchange rates from Bangkok Bank');
            return $this->handle_connectivity_fallback($markup_percentage, $cache_duration);
        }

        // Apply markup to rates
        $rates = $this->apply_markup($rates, $markup_percentage);

        // Cache the rates
        set_transient('bber_exchange_rates', $rates, $cache_duration);

        // Update last updated timestamp
        $options['last_updated'] = current_time('mysql');
        update_option('bber_options', $options);

        return $rates;
    }
    
    /**
     * Handle connectivity fallback when primary source fails.
     *
     * @param float $markup_percentage Markup percentage to apply.
     * @param int $cache_duration Cache duration in seconds.
     * @return array Array of exchange rates.
     */
    private function handle_connectivity_fallback($markup_percentage, $cache_duration) {
        // Try alternative API
        $alt_rates = $this->fetch_alternative_rates();
        
        if (!is_wp_error($alt_rates) && !empty($alt_rates)) {
            // Apply markup to rates
            $alt_rates = $this->apply_markup($alt_rates, $markup_percentage);
            
            // Cache the rates for a shorter time
            set_transient('bber_exchange_rates', $alt_rates, min($cache_duration, 1800)); // Max 30 minutes
            
            // Update last updated timestamp
            $options = get_option('bber_options', array());
            $options['last_updated'] = current_time('mysql') . ' (Alternative Source)';
            update_option('bber_options', $options);
            
            return $alt_rates;
        }
        
        // Try backup API if alternative fails
        $backup_rates = $this->fetch_backup_rates();
        
        if (!is_wp_error($backup_rates) && !empty($backup_rates)) {
            // Apply markup to rates
            $backup_rates = $this->apply_markup($backup_rates, $markup_percentage);
            
            // Cache the rates for a shorter time
            set_transient('bber_exchange_rates', $backup_rates, min($cache_duration, 1800)); // Max 30 minutes
            
            // Update last updated timestamp
            $options = get_option('bber_options', array());
            $options['last_updated'] = current_time('mysql') . ' (Backup Source)';
            update_option('bber_options', $options);
            
            return $backup_rates;
        }
        
        // Use fallback data if all external sources fail
        $fallback_rates = $this->get_fallback_rates();
        $fallback_rates = $this->apply_markup($fallback_rates, $markup_percentage);
        
        // Cache the fallback rates for a very short time
        set_transient('bber_exchange_rates', $fallback_rates, min($cache_duration, 900)); // Max 15 minutes
        
        // Update last updated timestamp
        $options = get_option('bber_options', array());
        $options['last_updated'] = current_time('mysql') . ' (Fallback Data)';
        update_option('bber_options', $options);
        
        return $fallback_rates;
    }
    
    /**
     * Fetch rates from alternative API.
     *
     * @return array|WP_Error Array of exchange rates or WP_Error on failure.
     */
    private function fetch_alternative_rates() {
        // Check if API is reachable
        if (!$this->is_host_reachable(self::ALT_EXCHANGE_API)) {
            return new WP_Error('host_unreachable', 'Alternative API host is not reachable');
        }
        
        $response = $this->proxy_request(self::ALT_EXCHANGE_API, array(
            'timeout' => 10,
            'sslverify' => false,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['rates'])) {
            return new WP_Error('invalid_response', 'Invalid response from alternative API');
        }
        
        $rates = array();
        $common_currencies = array('USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'SGD', 'HKD', 'CAD', 'CHF', 'MYR', 'KRW', 'TWD');
        
        foreach ($common_currencies as $code) {
            if (isset($data['rates'][$code])) {
                // Convert rate (this API gives foreign currency per 1 THB, we need THB per 1 foreign currency)
                $rate = 1 / $data['rates'][$code];
                
                $rates[$code] = array(
                    'code' => $code,
                    'name' => $this->get_currency_name($code),
                    'buying_sight' => $rate * 0.98, // Apply spread
                    'buying_transfer' => $rate * 0.99,
                    'selling' => $rate * 1.01,
                    'average' => $rate,
                );
            }
        }
        
        return $rates;
    }
    
    /**
     * Fetch rates from backup API.
     *
     * @return array|WP_Error Array of exchange rates or WP_Error on failure.
     */
    private function fetch_backup_rates() {
        // Check if API is reachable
        if (!$this->is_host_reachable(self::BACKUP_EXCHANGE_API)) {
            return new WP_Error('host_unreachable', 'Backup API host is not reachable');
        }
        
        $response = $this->proxy_request(self::BACKUP_EXCHANGE_API, array(
            'timeout' => 10,
            'sslverify' => false,
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['rates'])) {
            return new WP_Error('invalid_response', 'Invalid response from backup API');
        }
        
        $rates = array();
        $common_currencies = array('USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'SGD', 'HKD', 'CAD', 'CHF', 'MYR', 'KRW', 'TWD');
        
        foreach ($common_currencies as $code) {
            if (isset($data['rates'][$code])) {
                // Convert rate (this API gives foreign currency per 1 THB, we need THB per 1 foreign currency)
                $rate = 1 / $data['rates'][$code];
                
                $rates[$code] = array(
                    'code' => $code,
                    'name' => $this->get_currency_name($code),
                    'buying_sight' => $rate * 0.98, // Apply spread
                    'buying_transfer' => $rate * 0.99,
                    'selling' => $rate * 1.01,
                    'average' => $rate,
                );
            }
        }
        
        return $rates;
    }
    
    /**
     * Get fallback exchange rates when all connections fail.
     *
     * @return array Array of fallback exchange rates.
     */
    private function get_fallback_rates() {
        // Sample exchange rates (fixed data)
        $fallback_rates = array(
            'USD' => array(
                'code' => 'USD',
                'name' => 'US Dollar',
                'buying_sight' => 33.89,
                'buying_transfer' => 34.01,
                'selling' => 34.76,
                'average' => 34.385,
            ),
            'EUR' => array(
                'code' => 'EUR',
                'name' => 'Euro',
                'buying_sight' => 36.52,
                'buying_transfer' => 36.68,
                'selling' => 37.51,
                'average' => 37.095,
            ),
            'GBP' => array(
                'code' => 'GBP',
                'name' => 'British Pound',
                'buying_sight' => 43.17,
                'buying_transfer' => 43.38,
                'selling' => 44.75,
                'average' => 44.065,
            ),
            'JPY' => array(
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'buying_sight' => 0.221,
                'buying_transfer' => 0.223,
                'selling' => 0.232,
                'average' => 0.2275,
            ),
            'CNY' => array(
                'code' => 'CNY',
                'name' => 'Chinese Yuan',
                'buying_sight' => 4.67,
                'buying_transfer' => 4.72,
                'selling' => 4.91,
                'average' => 4.815,
            ),
            'AUD' => array(
                'code' => 'AUD',
                'name' => 'Australian Dollar',
                'buying_sight' => 22.34,
                'buying_transfer' => 22.45,
                'selling' => 23.10,
                'average' => 22.775,
            ),
            'SGD' => array(
                'code' => 'SGD',
                'name' => 'Singapore Dollar',
                'buying_sight' => 25.28,
                'buying_transfer' => 25.38,
                'selling' => 26.01,
                'average' => 25.695,
            ),
        );
        
        return $fallback_rates;
    }
    
    /**
     * Get currency name by code.
     *
     * @param string $code Currency code.
     * @return string Currency name.
     */
    private function get_currency_name($code) {
        $names = array(
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen',
            'CNY' => 'Chinese Yuan',
            'AUD' => 'Australian Dollar',
            'SGD' => 'Singapore Dollar',
            'HKD' => 'Hong Kong Dollar',
            'CAD' => 'Canadian Dollar',
            'CHF' => 'Swiss Franc',
            'MYR' => 'Malaysian Ringgit',
            'KRW' => 'South Korean Won',
            'TWD' => 'Taiwan Dollar',
        );
        
        return isset($names[$code]) ? $names[$code] : $code;
    }

    /**
     * Parse HTML content to extract exchange rates.
     *
     * @param string $html HTML content from Bangkok Bank website.
     * @return array Array of exchange rates.
     */
    private function parse_rates($html) {
        $rates = array();
        
        // Create a DOMDocument object
        $dom = new DOMDocument();
        
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        // Create a DOMXPath object
        $xpath = new DOMXPath($dom);
        
        // Find the exchange rate table
        $tables = $xpath->query('//table[contains(@class, "table-rate")]');
        
        if ($tables->length > 0) {
            $table = $tables->item(0);
            
            // Get table rows
            $rows = $xpath->query('.//tr', $table);
            
            // Skip header row
            for ($i = 1; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $cells = $xpath->query('.//td', $row);
                
                if ($cells->length >= 5) {
                    $currency_code = trim($xpath->query('.//div[contains(@class, "currency-code")]', $cells->item(0))->item(0)->nodeValue);
                    $currency_name = trim($xpath->query('.//div[contains(@class, "currency-name")]', $cells->item(0))->item(0)->nodeValue);
                    
                    // Get buying and selling rates
                    $buying_sight = $this->clean_rate($cells->item(1)->nodeValue);
                    $buying_transfer = $this->clean_rate($cells->item(2)->nodeValue);
                    $selling = $this->clean_rate($cells->item(3)->nodeValue);
                    $average = $this->clean_rate($cells->item(4)->nodeValue);
                    
                    if (!empty($currency_code)) {
                        $rates[$currency_code] = array(
                            'code' => $currency_code,
                            'name' => $currency_name,
                            'buying_sight' => $buying_sight,
                            'buying_transfer' => $buying_transfer,
                            'selling' => $selling,
                            'average' => $average,
                        );
                    }
                }
            }
        }
        
        // If the above method fails, try an alternative parsing approach
        if (empty($rates)) {
            // Look for JSON data in the page
            if (preg_match('/var\s+exchangeRates\s*=\s*(\[.*?\]);/s', $html, $matches)) {
                $json_data = $matches[1];
                $data = json_decode($json_data, true);
                
                if (is_array($data)) {
                    foreach ($data as $item) {
                        if (isset($item['currencyCode']) && isset($item['buyingRateSight']) && isset($item['buyingRateTransfer']) && isset($item['sellingRate'])) {
                            $currency_code = $item['currencyCode'];
                            $rates[$currency_code] = array(
                                'code' => $currency_code,
                                'name' => isset($item['currencyName']) ? $item['currencyName'] : $currency_code,
                                'buying_sight' => $this->clean_rate($item['buyingRateSight']),
                                'buying_transfer' => $this->clean_rate($item['buyingRateTransfer']),
                                'selling' => $this->clean_rate($item['sellingRate']),
                                'average' => isset($item['averageRate']) ? $this->clean_rate($item['averageRate']) : '',
                            );
                        }
                    }
                }
            }
        }
        
        return $rates;
    }

    /**
     * Clean rate value by removing non-numeric characters.
     *
     * @param string $rate Rate value from the website.
     * @return float Cleaned rate value.
     */
    private function clean_rate($rate) {
        // Remove non-numeric characters except decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', $rate);
        
        // Convert to float
        return !empty($cleaned) ? floatval($cleaned) : 0;
    }

    /**
     * Apply markup percentage to rates.
     *
     * @param array $rates Array of exchange rates.
     * @param float $markup_percentage Markup percentage to apply.
     * @return array Updated array of exchange rates with markup applied.
     */
    private function apply_markup($rates, $markup_percentage) {
        $markup_factor = 1 + ($markup_percentage / 100);
        
        foreach ($rates as $code => &$rate) {
            // Apply markup to selling rate (increase)
            if (!empty($rate['selling']) && $rate['selling'] > 0) {
                $rate['selling'] = $rate['selling'] * $markup_factor;
                $rate['original_selling'] = $rate['selling'] / $markup_factor; // Store original rate
            }
            
            // Apply markup to buying rates (decrease)
            if (!empty($rate['buying_sight']) && $rate['buying_sight'] > 0) {
                $rate['original_buying_sight'] = $rate['buying_sight']; // Store original rate
                $rate['buying_sight'] = $rate['buying_sight'] / $markup_factor;
            }
            
            if (!empty($rate['buying_transfer']) && $rate['buying_transfer'] > 0) {
                $rate['original_buying_transfer'] = $rate['buying_transfer']; // Store original rate
                $rate['buying_transfer'] = $rate['buying_transfer'] / $markup_factor;
            }
            
            // Recalculate average if needed
            if (!empty($rate['buying_transfer']) && !empty($rate['selling'])) {
                $rate['average'] = ($rate['buying_transfer'] + $rate['selling']) / 2;
            }
        }
        
        return $rates;
    }

    /**
     * Convert amount from one currency to another.
     *
     * @param float $amount Amount to convert.
     * @param string $from_currency Currency code to convert from.
     * @param string $to_currency Currency code to convert to.
     * @param array $rates Array of exchange rates.
     * @return float|WP_Error Converted amount or WP_Error on failure.
     */
    public function convert_currency($amount, $from_currency, $to_currency, $rates = null) {
        // Fetch rates if not provided
        if (null === $rates) {
            $rates = $this->fetch_rates();
            if (is_wp_error($rates)) {
                return $rates;
            }
        }
        
        // Check if currencies exist in rates
        if (!isset($rates[$from_currency]) || !isset($rates[$to_currency])) {
            return new WP_Error('invalid_currency', 'One or both currencies are not available');
        }
        
        // If converting to THB
        if ('THB' === $to_currency) {
            // Use selling rate for foreign currency to THB conversion
            $rate = $rates[$from_currency]['selling'];
            return $amount * $rate;
        }
        
        // If converting from THB
        if ('THB' === $from_currency) {
            // Use buying rate for THB to foreign currency conversion
            $rate = $rates[$to_currency]['buying_transfer'];
            return $amount / $rate;
        }
        
        // Cross-currency conversion (via THB)
        $to_thb = $this->convert_currency($amount, $from_currency, 'THB', $rates);
        return $this->convert_currency($to_thb, 'THB', $to_currency, $rates);
    }
}
