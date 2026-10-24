<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit;

use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

// Based on CI DB caching function
class PersistentCache
{
    private static $cache_path = PATH_CACHE;
    private static $module_cache_path = PATH_CACHE . 'low_yearly_archives/';

    /**
     * Read decoded data from the logical key in memory or persistent storage.
     *
     * @param string|array $key Logical key or key pieces.
     * @return mixed Decoded cached value or false on a miss.
     */
    public static function get($key)
    {
        // Check to see if we have this stored in our StaticCache first.
        $key = self::createKey($key);

        if (StaticCache::has($key, true)) {
            return StaticCache::get($key, true);
        }

        if (! self::checkPath()) {
            return false;
        }

        ee()->load->helper('file');

        if (false === ($cache_data = read_file(self::$module_cache_path . $key))) {
            ee()->load->library('logger');
            ee()->logger->developer('Low Yearly Archives: Write Cache enabled but could not find cache for: ' . self::$module_cache_path . $key);
            return false;
        }

        // Store the cache data in the StaticCache so we don't have
        // to read it off the disk again during this execution loop.
        $cache_data = self::serveCacheData($cache_data);
        StaticCache::set($key, $cache_data, true);

        return $cache_data;
    }

     /**
     * Set the data to be stored in the cache.
     * @param  string|array  $key  Either a string or array of items
     * @return boolean             Indicates whether the data was able to be saved
     */
    public static function set($key, $value)
    {
        if (!self::checkPath()) {
            return false;
        }

        ee()->load->helper('file');

        $key = self::createKey($key);
        $cache_data = self::prepCacheData($value);

        $path = self::$module_cache_path . $key;
        if (is_dir($path) || (file_exists($path) && !is_writable($path)) || write_file($path, $cache_data) === false) {
            ee()->load->library('logger');
            ee()->logger->developer('Low Yearly Archives: Write Cache enabled but could not write cache file: ' . self::$module_cache_path . $key);

            return false;
        }

        // Try to change the folder permissions to EE's default write mode.
        @chmod(self::$module_cache_path . $key, FILE_WRITE_MODE);

        // Store the cache data in the StaticCache so we don't have
        // to read it off the disk again during this execution loop.
        // We use $value here because we don't need it in file format.
        StaticCache::set($key, $value, true);

        return true;
    }

    /**
     * Delete the cached file for a specific key for this module.
     * @return bool  Indicates if the key was successfully deleted.
     */
    public static function delete($key)
    {
        if (!self::checkPath()) {
            return false;
        }

        $key = self::createKey($key);

        if (is_file(self::$module_cache_path . $key)) {
            @unlink(self::$module_cache_path . $key);
        }

        // Remove any StaticCache we have for this key as well.
        StaticCache::delete($key, true);

        return true;
    }

    /**
     * Check a logical or already processed persistent cache key.
     *
     * @param string|array $key Logical key or key pieces.
     * @param bool $key_processed Whether the key is already hashed.
     * @return bool Whether the cache file exists.
     */
    public static function has($key, $key_processed = false)
    {
        $key = $key_processed ? $key : self::createKey($key);
        return is_file(self::$module_cache_path . $key);
    }

    /**
     * Delete all the cached files that this module may have created.
     * @return bool  Indicates if the cache path existed and we attempted to clear it.
     */
    public static function clear()
    {
        if (!self::checkPath()) {
            return false;
        }

        foreach (glob(self::$module_cache_path . '*') as $key) {
            if (is_file($key)) {
                @unlink($key);
            }

            // Remove any StaticCache we have for this key as well.
            // We don't want to clear the StaticCache globally as
            // we may only be looking to clear the persistent cache
            // and leave the other things in the StaticCache alone.
            StaticCache::delete(pathinfo($key, PATHINFO_FILENAME), true);
        }

        return true;
    }


    /************************************************************
    PRIVATE FUNCTIONS
    ************************************************************/

    /**
     * Create the existing colon-delimited hash while retaining literal zero pieces.
     *
     * @param string|array $key Logical key or key pieces.
     * @return string Hashed cache key.
     */
    private static function createKey($key)
    {
        if (!is_array($key)) {
            $key = array($key);
        }

        return sha1(implode(':', array_filter($key, static function ($part) {
            return $part === '0' || $part === 0 || (bool) $part;
        })));
    }

    /**
     * Prep the data to be stored in the cache. Making this it's own
     * method so we can add to it if needed.
     * @param  array|object  $value Data to store in cache
     * @return string        Serialized string of values
     */
    private static function prepCacheData($value)
    {
        return serialize($value);
    }

    /**
     * Decode a persistent cache payload using the native serialization contract.
     *
     * @param mixed $value Serialized stored bytes.
     * @return mixed Decoded value or false when invalid.
     */
    private static function serveCacheData($value)
    {
        return SerializedData::decode($value);
    }

    /**
     * Create the configured native path and handle only its known filesystem failures.
     *
     * @param string $path Original configured directory path, including alias spelling.
     * @return bool Whether native directory creation succeeded.
     * @throws \Throwable When an unexpected diagnostic or stream callback fails.
     */
    private static function createDirectory($path)
    {
        $create_line = 0;
        $previous = null;
        $previous = set_error_handler(static function ($severity, $message, $file, $line) use (&$create_line, &$previous) {
            if ($severity === E_WARNING && $file === __FILE__ && $line === $create_line
                && in_array($message, array(
                    'mkdir(): Permission denied',
                    'mkdir(): File exists',
                    'mkdir(): Not a directory',
                    'mkdir(): No such file or directory',
                    'mkdir(): Too many levels of symbolic links',
                    'mkdir(): Read-only file system',
                ), true)) {
                return true;
            }

            return $previous === null ? false : $previous(...func_get_args());
        }, -1);

        try {
            $create_line = __LINE__ + 1;
            return mkdir($path, DIR_WRITE_MODE);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Ensure the global and add-on cache directories permit writes and traversal.
     *
     * @return bool Whether both cache directories are available.
     */
    private static function checkPath()
    {
        // Add a trailing slash to the path if needed
        self::$cache_path = preg_replace("/(.+?)\/*$/", "\\1/", self::$cache_path);

        // If the generic cache folder doesn't exist, try to create it.
        if (! @is_dir(self::$cache_path)) {
            if (!self::createDirectory(self::$cache_path)) {
                ee()->load->library('logger');
                ee()->logger->developer('Low Yearly Archives: Write Cache enabled but could not create cache folder: ' . self::$cache_path);
                return false;
            }

            // Try to change the folder permissions to EE's default write mode.
            @chmod(self::$cache_path, DIR_WRITE_MODE);
        }

        // Make sure the generic cache folder is writable.
        if (!is_writable(self::$cache_path) || (DIRECTORY_SEPARATOR === '/' && !is_executable(self::$cache_path))) {
            ee()->load->library('logger');
            ee()->logger->developer('Low Yearly Archives: Write Cache enabled but cache path not writable: ' . self::$cache_path);
            return false;
        }

        // If the module-specific cache folder doesn't exist, try to create it.
        if (!@is_dir(self::$module_cache_path)) {
            if (!self::createDirectory(self::$module_cache_path)) {
                ee()->load->library('logger');
                ee()->logger->developer('Low Yearly Archives: Write Cache enabled but could not create module cache folder: ' . self::$module_cache_path);
                return false;
            }

            // Try to change the folder permissions to EE's default write mode.
            @chmod(self::$module_cache_path, DIR_WRITE_MODE);
        }

        // Make sure the module-specific cache folder is writable.
        if (!is_writable(self::$module_cache_path) || (DIRECTORY_SEPARATOR === '/' && !is_executable(self::$module_cache_path))) {
            // If the path is wrong we'll turn off caching
            ee()->load->library('logger');
            ee()->logger->developer('Low Yearly Archives: Write Cache enabled but module cache path not writable: ' . self::$module_cache_path);
            return false;
        }

        return true;
    }
}
