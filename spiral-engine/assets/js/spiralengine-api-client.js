/**
 * SpiralEngine API Client
 * 
 * @package    SpiralEngine
 * @subpackage Assets
 * @since      1.0.0
 */

// assets/js/spiralengine-api-client.js

(function(window, $) {
    'use strict';

    /**
     * SpiralEngine API Client
     * 
     * Promise-based API client for interacting with the SpiralEngine REST API
     */
    class SpiralEngineAPI {
        /**
         * Constructor
         * 
         * @param {Object} options Configuration options
         */
        constructor(options = {}) {
            this.baseURL = options.baseURL || spiralengine_api.rest_url;
            this.nonce = options.nonce || spiralengine_api.nonce;
            this.namespace = 'spiralengine/v1';
            this.timeout = options.timeout || 30000;
            this.retryAttempts = options.retryAttempts || 3;
            this.retryDelay = options.retryDelay || 1000;
            
            // Request queue for rate limiting
            this.requestQueue = [];
            this.activeRequests = 0;
            this.maxConcurrentRequests = 5;
            
            // Cache configuration
            this.cache = new Map();
            this.cacheExpiry = options.cacheExpiry || 300000; // 5 minutes
            
            // Loading state management
            this.loadingStates = new Map();
            
            // Event emitter for global events
            this.events = {};
        }

        /**
         * Make API request
         * 
         * @param {string} method HTTP method
         * @param {string} endpoint API endpoint
         * @param {Object} data Request data
         * @param {Object} options Request options
         * @return {Promise}
         */
        async request(method, endpoint, data = {}, options = {}) {
            const url = `${this.baseURL}${this.namespace}/${endpoint}`;
            const cacheKey = `${method}:${url}:${JSON.stringify(data)}`;
            
            // Check cache for GET requests
            if (method === 'GET' && !options.noCache) {
                const cached = this.getCache(cacheKey);
                if (cached) {
                    return Promise.resolve(cached);
                }
            }
            
            // Set loading state
            this.setLoadingState(endpoint, true);
            
            // Queue request if too many concurrent
            if (this.activeRequests >= this.maxConcurrentRequests) {
                await this.waitForSlot();
            }
            
            this.activeRequests++;
            
            try {
                const response = await this.makeRequest(method, url, data, options);
                
                // Cache successful GET responses
                if (method === 'GET' && response.success) {
                    this.setCache(cacheKey, response);
                }
                
                return response;
            } catch (error) {
                throw error;
            } finally {
                this.activeRequests--;
                this.setLoadingState(endpoint, false);
                this.processQueue();
            }
        }

        /**
         * Make actual HTTP request
         * 
         * @private
         * @param {string} method HTTP method
         * @param {string} url Full URL
         * @param {Object} data Request data
         * @param {Object} options Request options
         * @param {number} attempt Current attempt number
         * @return {Promise}
         */
        async makeRequest(method, url, data, options, attempt = 1) {
            const config = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                    ...options.headers
                },
                credentials: 'same-origin'
            };
            
            // Add body for non-GET requests
            if (method !== 'GET' && method !== 'HEAD') {
                config.body = JSON.stringify(data);
            } else if (Object.keys(data).length > 0) {
                // Add query parameters for GET requests
                const params = new URLSearchParams(data);
                url += '?' + params.toString();
            }
            
            // Create abort controller for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);
            config.signal = controller.signal;
            
            try {
                const response = await fetch(url, config);
                clearTimeout(timeoutId);
                
                // Handle rate limiting
                if (response.status === 429) {
                    const retryAfter = response.headers.get('X-RateLimit-Reset') || 60;
                    this.emit('rateLimited', { retryAfter });
                    
                    if (options.waitOnRateLimit !== false) {
                        await this.delay(retryAfter * 1000);
                        return this.makeRequest(method, url, data, options, attempt);
                    }
                }
                
                // Parse response
                const responseData = await response.json();
                
                // Handle errors
                if (!response.ok) {
                    const error = new Error(responseData.message || `HTTP ${response.status}`);
                    error.status = response.status;
                    error.code = responseData.code;
                    error.data = responseData.data;
                    
                    // Retry on server errors
                    if (response.status >= 500 && attempt < this.retryAttempts) {
                        await this.delay(this.retryDelay * attempt);
                        return this.makeRequest(method, url, data, options, attempt + 1);
                    }
                    
                    throw error;
                }
                
                // Update rate limit info
                this.updateRateLimitInfo(response.headers);
                
                return responseData;
                
            } catch (error) {
                if (error.name === 'AbortError') {
                    throw new Error('Request timeout');
                }
                
                // Retry on network errors
                if (attempt < this.retryAttempts && !options.noRetry) {
                    await this.delay(this.retryDelay * attempt);
                    return this.makeRequest(method, url, data, options, attempt + 1);
                }
                
                throw error;
            }
        }

        /**
         * Assessment endpoints
         */
        assessments = {
            /**
             * Get all assessments
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            list: (params = {}) => {
                return this.request('GET', 'assessments', params);
            },

            /**
             * Get single assessment
             * 
             * @param {number} id Assessment ID
             * @return {Promise}
             */
            get: (id) => {
                return this.request('GET', `assessments/${id}`);
            },

            /**
             * Create assessment
             * 
             * @param {Object} data Assessment data
             * @return {Promise}
             */
            create: (data) => {
                return this.request('POST', 'assessments', data);
            },

            /**
             * Update assessment
             * 
             * @param {number} id Assessment ID
             * @param {Object} data Update data
             * @return {Promise}
             */
            update: (id, data) => {
                return this.request('PUT', `assessments/${id}`, data);
            },

            /**
             * Delete assessment
             * 
             * @param {number} id Assessment ID
             * @return {Promise}
             */
            delete: (id) => {
                return this.request('DELETE', `assessments/${id}`);
            },

            /**
             * Get assessment history
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            history: (params = {}) => {
                return this.request('GET', 'assessments/history', params);
            },

            /**
             * Get current assessment
             * 
             * @return {Promise}
             */
            current: () => {
                return this.request('GET', 'assessments/current');
            }
        };

        /**
         * Episode endpoints
         */
        episodes = {
            /**
             * Get all episodes
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            list: (params = {}) => {
                return this.request('GET', 'episodes', params);
            },

            /**
             * Get single episode
             * 
             * @param {number} id Episode ID
             * @return {Promise}
             */
            get: (id) => {
                return this.request('GET', `episodes/${id}`);
            },

            /**
             * Create episode
             * 
             * @param {Object} data Episode data
             * @return {Promise}
             */
            create: (data) => {
                return this.request('POST', 'episodes', data);
            },

            /**
             * Update episode
             * 
             * @param {number} id Episode ID
             * @param {Object} data Update data
             * @return {Promise}
             */
            update: (id, data) => {
                return this.request('PUT', `episodes/${id}`, data);
            },

            /**
             * Delete episode
             * 
             * @param {number} id Episode ID
             * @return {Promise}
             */
            delete: (id) => {
                return this.request('DELETE', `episodes/${id}`);
            },

            /**
             * Quick log episode
             * 
             * @param {Object} data Quick log data
             * @return {Promise}
             */
            quickLog: (data) => {
                return this.request('POST', 'episodes/quick-log', data);
            },

            /**
             * Get episode correlations
             * 
             * @param {number} id Episode ID
             * @return {Promise}
             */
            correlations: (id) => {
                return this.request('GET', `episodes/${id}/correlations`);
            },

            /**
             * Get episode patterns
             * 
             * @param {number} id Episode ID
             * @return {Promise}
             */
            patterns: (id) => {
                return this.request('GET', `episodes/${id}/patterns`);
            }
        };

        /**
         * Insight endpoints
         */
        insights = {
            /**
             * Get all insights
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            list: (params = {}) => {
                return this.request('GET', 'insights', params);
            },

            /**
             * Get single insight
             * 
             * @param {number} id Insight ID
             * @return {Promise}
             */
            get: (id) => {
                return this.request('GET', `insights/${id}`);
            },

            /**
             * Update insight
             * 
             * @param {number} id Insight ID
             * @param {Object} data Update data
             * @return {Promise}
             */
            update: (id, data) => {
                return this.request('PUT', `insights/${id}`, data);
            },

            /**
             * Generate insights
             * 
             * @param {Object} options Generation options
             * @return {Promise}
             */
            generate: (options = {}) => {
                return this.request('POST', 'insights/generate', options);
            }
        };

        /**
         * Pattern endpoints
         */
        patterns = {
            /**
             * Get all patterns
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            list: (params = {}) => {
                return this.request('GET', 'patterns', params);
            },

            /**
             * Get single pattern
             * 
             * @param {number} id Pattern ID
             * @return {Promise}
             */
            get: (id) => {
                return this.request('GET', `patterns/${id}`);
            },

            /**
             * Detect patterns
             * 
             * @param {Object} options Detection options
             * @return {Promise}
             */
            detect: (options = {}) => {
                return this.request('POST', 'patterns/detect', options);
            },

            /**
             * Get pattern forecast
             * 
             * @param {Object} params Forecast parameters
             * @return {Promise}
             */
            forecast: (params = {}) => {
                return this.request('GET', 'patterns/forecast', params);
            }
        };

        /**
         * User endpoints
         */
        users = {
            /**
             * Get current user
             * 
             * @return {Promise}
             */
            me: () => {
                return this.request('GET', 'users/me');
            },

            /**
             * Update current user
             * 
             * @param {Object} data Update data
             * @return {Promise}
             */
            updateMe: (data) => {
                return this.request('PUT', 'users/me', data);
            },

            /**
             * Get user settings
             * 
             * @return {Promise}
             */
            settings: () => {
                return this.request('GET', 'users/me/settings');
            },

            /**
             * Update user settings
             * 
             * @param {Object} data Settings data
             * @return {Promise}
             */
            updateSettings: (data) => {
                return this.request('PUT', 'users/me/settings', data);
            },

            /**
             * Get user statistics
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            stats: (params = {}) => {
                return this.request('GET', 'users/me/stats', params);
            },

            /**
             * Get user timeline
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            timeline: (params = {}) => {
                return this.request('GET', 'users/me/timeline', params);
            },

            /**
             * Export user data
             * 
             * @param {Object} options Export options
             * @return {Promise}
             */
            export: (options = {}) => {
                return this.request('POST', 'users/me/export', options);
            }
        };

        /**
         * Widget endpoints
         */
        widgets = {
            /**
             * Get all widgets
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            list: (params = {}) => {
                return this.request('GET', 'widgets', params);
            },

            /**
             * Get single widget
             * 
             * @param {string} id Widget ID
             * @return {Promise}
             */
            get: (id) => {
                return this.request('GET', `widgets/${id}`);
            },

            /**
             * Get widget data
             * 
             * @param {string} id Widget ID
             * @return {Promise}
             */
            getData: (id) => {
                return this.request('GET', `widgets/${id}/data`);
            },

            /**
             * Save widget data
             * 
             * @param {string} id Widget ID
             * @param {Object} data Widget data
             * @return {Promise}
             */
            saveData: (id, data) => {
                return this.request('POST', `widgets/${id}/data`, data);
            },

            /**
             * Get widget preview
             * 
             * @param {string} id Widget ID
             * @return {Promise}
             */
            preview: (id) => {
                return this.request('GET', `widgets/${id}/preview`);
            }
        };

        /**
         * Analytics endpoints
         */
        analytics = {
            /**
             * Get episode analytics
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            episodes: (params = {}) => {
                return this.request('GET', 'analytics/episodes', params);
            },

            /**
             * Get pattern analytics
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            patterns: (params = {}) => {
                return this.request('GET', 'analytics/patterns', params);
            },

            /**
             * Get correlation analytics
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            correlations: (params = {}) => {
                return this.request('GET', 'analytics/correlations', params);
            },

            /**
             * Get progress analytics
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            progress: (params = {}) => {
                return this.request('GET', 'analytics/progress', params);
            }
        };

        /**
         * Webhook endpoints (admin only)
         */
        webhooks = {
            /**
             * Get all webhooks
             * 
             * @param {Object} params Query parameters
             * @return {Promise}
             */
            list: (params = {}) => {
                return this.request('GET', 'webhooks', params);
            },

            /**
             * Get single webhook
             * 
             * @param {number} id Webhook ID
             * @return {Promise}
             */
            get: (id) => {
                return this.request('GET', `webhooks/${id}`);
            },

            /**
             * Create webhook
             * 
             * @param {Object} data Webhook data
             * @return {Promise}
             */
            create: (data) => {
                return this.request('POST', 'webhooks', data);
            },

            /**
             * Update webhook
             * 
             * @param {number} id Webhook ID
             * @param {Object} data Update data
             * @return {Promise}
             */
            update: (id, data) => {
                return this.request('PUT', `webhooks/${id}`, data);
            },

            /**
             * Delete webhook
             * 
             * @param {number} id Webhook ID
             * @return {Promise}
             */
            delete: (id) => {
                return this.request('DELETE', `webhooks/${id}`);
            },

            /**
             * Test webhook
             * 
             * @param {number} id Webhook ID
             * @return {Promise}
             */
            test: (id) => {
                return this.request('POST', `webhooks/${id}/test`);
            }
        };

        /**
         * System endpoints
         */
        system = {
            /**
             * Get system health
             * 
             * @return {Promise}
             */
            health: () => {
                return this.request('GET', 'system/health');
            },

            /**
             * Get API info
             * 
             * @return {Promise}
             */
            info: () => {
                return this.request('GET', 'info');
            }
        };

        /**
         * Helper methods
         */

        /**
         * Wait for available request slot
         * 
         * @private
         * @return {Promise}
         */
        waitForSlot() {
            return new Promise((resolve) => {
                this.requestQueue.push(resolve);
            });
        }

        /**
         * Process request queue
         * 
         * @private
         */
        processQueue() {
            if (this.requestQueue.length > 0 && this.activeRequests < this.maxConcurrentRequests) {
                const resolve = this.requestQueue.shift();
                resolve();
            }
        }

        /**
         * Delay execution
         * 
         * @private
         * @param {number} ms Milliseconds to delay
         * @return {Promise}
         */
        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        /**
         * Get cached data
         * 
         * @private
         * @param {string} key Cache key
         * @return {*}
         */
        getCache(key) {
            const cached = this.cache.get(key);
            
            if (cached && cached.expiry > Date.now()) {
                return cached.data;
            }
            
            this.cache.delete(key);
            return null;
        }

        /**
         * Set cache data
         * 
         * @private
         * @param {string} key Cache key
         * @param {*} data Data to cache
         */
        setCache(key, data) {
            this.cache.set(key, {
                data: data,
                expiry: Date.now() + this.cacheExpiry
            });
            
            // Limit cache size
            if (this.cache.size > 100) {
                const firstKey = this.cache.keys().next().value;
                this.cache.delete(firstKey);
            }
        }

        /**
         * Clear cache
         * 
         * @param {string} pattern Optional pattern to match
         */
        clearCache(pattern = null) {
            if (pattern) {
                for (const key of this.cache.keys()) {
                    if (key.includes(pattern)) {
                        this.cache.delete(key);
                    }
                }
            } else {
                this.cache.clear();
            }
        }

        /**
         * Set loading state
         * 
         * @private
         * @param {string} key Loading key
         * @param {boolean} isLoading Loading state
         */
        setLoadingState(key, isLoading) {
            this.loadingStates.set(key, isLoading);
            this.emit('loadingStateChange', { key, isLoading });
        }

        /**
         * Get loading state
         * 
         * @param {string} key Loading key
         * @return {boolean}
         */
        isLoading(key) {
            return this.loadingStates.get(key) || false;
        }

        /**
         * Update rate limit info
         * 
         * @private
         * @param {Headers} headers Response headers
         */
        updateRateLimitInfo(headers) {
            const info = {
                limit: headers.get('X-RateLimit-Limit'),
                remaining: headers.get('X-RateLimit-Remaining'),
                reset: headers.get('X-RateLimit-Reset')
            };
            
            if (info.limit) {
                this.emit('rateLimitInfo', info);
            }
        }

        /**
         * Event handling
         */

        /**
         * Subscribe to event
         * 
         * @param {string} event Event name
         * @param {Function} callback Callback function
         * @return {Function} Unsubscribe function
         */
        on(event, callback) {
            if (!this.events[event]) {
                this.events[event] = [];
            }
            
            this.events[event].push(callback);
            
            // Return unsubscribe function
            return () => {
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            };
        }

        /**
         * Emit event
         * 
         * @private
         * @param {string} event Event name
         * @param {*} data Event data
         */
        emit(event, data) {
            if (this.events[event]) {
                this.events[event].forEach(callback => callback(data));
            }
        }

        /**
         * Batch operations
         */

        /**
         * Batch multiple requests
         * 
         * @param {Array} requests Array of request configurations
         * @return {Promise}
         */
        async batch(requests) {
            const results = await Promise.allSettled(
                requests.map(req => this.request(req.method, req.endpoint, req.data, req.options))
            );
            
            return results.map((result, index) => ({
                request: requests[index],
                status: result.status,
                value: result.status === 'fulfilled' ? result.value : null,
                error: result.status === 'rejected' ? result.reason : null
            }));
        }

        /**
         * Utility methods
         */

        /**
         * Set authentication token
         * 
         * @param {string} nonce New nonce value
         */
        setNonce(nonce) {
            this.nonce = nonce;
        }

        /**
         * Get current rate limit info
         * 
         * @return {Object}
         */
        getRateLimitInfo() {
            return this.rateLimitInfo || null;
        }

        /**
         * Handle authentication errors globally
         * 
         * @param {Function} callback Callback for auth errors
         */
        onAuthError(callback) {
            return this.on('authError', callback);
        }

        /**
         * Handle network errors globally
         * 
         * @param {Function} callback Callback for network errors
         */
        onNetworkError(callback) {
            return this.on('networkError', callback);
        }
    }

    // Create global instance
    window.SpiralEngineAPI = SpiralEngineAPI;

    // Create default instance if jQuery is available
    if (typeof $ !== 'undefined') {
        $(document).ready(function() {
            window.spiralengineAPI = new SpiralEngineAPI({
                baseURL: spiralengine_api.rest_url,
                nonce: spiralengine_api.nonce
            });

            // Update nonce on heartbeat
            $(document).on('heartbeat-tick', function(event, data) {
                if (data.spiralengine_nonce) {
                    window.spiralengineAPI.setNonce(data.spiralengine_nonce);
                }
            });
        });
    }

})(window, jQuery);
