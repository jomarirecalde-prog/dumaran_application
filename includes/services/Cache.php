<?php
/**
 * Simple Cache Service
 * Handles basic caching functionality
 */

class Cache {
    private $cachePath;
    private $defaultTTL;

    public function __construct($cachePath = null, $defaultTTL = 3600) {
        $this->cachePath = $cachePath ?? __DIR__ . '/../../cache/';
        $this->defaultTTL = $defaultTTL;
        
        // Ensure cache directory exists
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Get cache key filename
     */
    private function getFilename($key) {
        return $this->cachePath . md5($key) . '.cache';
    }

    /**
     * Get cached value
     */
    public function get($key, $default = null) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($filename));
        
        // Check if expired
        if ($data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }
        
        return $data['value'];
    }

    /**
     * Set cache value
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTTL;
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $filename = $this->getFilename($key);
        
        return file_put_contents($filename, serialize($data)) !== false;
    }

    /**
     * Check if key exists and is not expired
     */
    public function has($key) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($filename));
        
        if ($data['expires'] < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }

    /**
     * Delete cached value
     */
    public function delete($key) {
        $filename = $this->getFilename($key);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return false;
    }

    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cachePath . '*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }

    /**
     * Clear expired cache
     */
    public function clearExpired() {
        $files = glob($this->cachePath . '*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            
            if ($data['expires'] < time()) {
                unlink($file);
                $cleared++;
            }
        }
        
        return $cleared;
    }

    /**
     * Remember value (get or set)
     */
    public function remember($key, $callback, $ttl = null) {
        if ($this->has($key)) {
            return $this->get($key);
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Increment numeric value
     */
    public function increment($key, $value = 1) {
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        
        return $new;
    }

    /**
     * Decrement numeric value
     */
    public function decrement($key, $value = 1) {
        return $this->increment($key, -$value);
    }

    /**
     * Get multiple values
     */
    public function getMultiple($keys, $default = null) {
        $values = [];
        
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        
        return $values;
    }

    /**
     * Set multiple values
     */
    public function setMultiple($values, $ttl = null) {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        
        return true;
    }

    /**
     * Delete multiple values
     */
    public function deleteMultiple($keys) {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        
        return true;
    }

    /**
     * Get cache stats
     */
    public function getStats() {
        $files = glob($this->cachePath . '*.cache');
        $total = count($files);
        $expired = 0;
        $totalSize = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] < time()) {
                $expired++;
            }
        }
        
        return [
            'total_items' => $total,
            'expired_items' => $expired,
            'active_items' => $total - $expired,
            'total_size' => $totalSize,
            'size_mb' => round($totalSize / (1024 * 1024), 2)
        ];
    }
}
