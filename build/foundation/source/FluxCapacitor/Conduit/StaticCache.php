<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit;

class StaticCache
{
    public static $cache_array = array();

    /**
     * Read every stored value, including null, zero and empty containers.
     *
     * @param string|array $key Logical key or key pieces.
     * @param bool $key_processed Whether the key is already hashed.
     * @return mixed Cached value or false when absent.
     */
    public static function get($key, $key_processed = false)
    {
        $key = self::createKey($key, $key_processed);

        return array_key_exists($key, self::$cache_array) ? self::$cache_array[$key] : false;
    }

    /**
     * DEPRECATED - Alias of `put`.
     * @param  string|array  $key            Either a string or array of items
     * @param  array|object  $value          The array or object to store in the cache.
     * @param  boolean       $key_processed  If true, bypasses `create_key`. Used when key has already be SHA1'd.
     * @return boolean                       Indicates whether the data was able to be saved
     */
    public static function set($key, $value, $key_processed = false)
    {
        self::put($key, $value, $key_processed);
    }

    /**
     * Set the data to be stored in the cache.
     * @param  string|array  $key            Either a string or array of items
     * @param  array|object  $value          The array or object to store in the cache.
     * @param  boolean       $key_processed  If true, bypasses `create_key`. Used when key has already be SHA1'd.
     * @return boolean                       Indicates whether the data was able to be saved
     */
    public static function put($key, $value, $key_processed = false)
    {
        self::$cache_array[ self::createKey($key, $key_processed) ] = $value;
    }

    /**
     * Delete a cached key and report whether it previously existed.
     *
     * @param string|array $key Logical key or key pieces.
     * @param bool $key_processed Whether the key is already hashed.
     * @return bool Whether an existing value was removed.
     */
    public static function delete($key, $key_processed = false)
    {
        $key = self::createKey($key, $key_processed);
        $exists = array_key_exists($key, self::$cache_array);
        unset(self::$cache_array[$key]);

        return $exists;
    }

    /**
     * Find an existing cache key, including a stored null value.
     *
     * @param string|array $key Logical key or key pieces.
     * @param bool $key_processed Whether the key is already hashed.
     * @return bool Whether the key exists.
     */
    public static function has($key, $key_processed = false)
    {
        return array_key_exists(self::createKey($key, $key_processed), self::$cache_array);
    }

    /**
     * Return all of the cached items
     * @return array  An array of all the cached items
     */
    public static function all()
    {
        return self::$cache_array;
    }

    /**
     * Clear the entire cache.
     * @return boolean  True, always True.
     */
    public static function clear()
    {
        self::$cache_array = array();

        return true;
    }

    /**
     * Create the existing colon-delimited hash while retaining literal zero pieces.
     *
     * @param string|array $key Logical key or key pieces.
     * @param bool $key_processed Whether the key is already hashed.
     * @return string Cache key.
     */
    private static function createKey($key, $key_processed = false)
    {
        if ($key_processed !== false) {
            return $key;
        }

        if (!is_array($key)) {
            $key = array($key);
        }

        return sha1(implode(':', array_filter($key, static function ($part) {
            return $part === '0' || $part === 0 || (bool) $part;
        })));
    }
}
