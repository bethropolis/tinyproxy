<?php

declare(strict_types=1);

namespace TinyProxy\Util;

/**
 * Time and date manipulation helper
 */
class TimeHelper
{
    /**
     * Get current Unix timestamp
     */
    public static function now(): int
    {
        return time();
    }

    /**
     * Format timestamp to readable string
     */
    public static function format(int $timestamp, string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, $timestamp);
    }

    /**
     * Parse human-readable time to seconds
     */
    public static function parse(string $time): int
    {
        $time = strtolower(trim($time));
        
        if (is_numeric($time)) {
            return (int) $time;
        }

        $units = [
            's' => 1,
            'sec' => 1,
            'second' => 1,
            'seconds' => 1,
            'm' => 60,
            'min' => 60,
            'minute' => 60,
            'minutes' => 60,
            'h' => 3600,
            'hour' => 3600,
            'hours' => 3600,
            'd' => 86400,
            'day' => 86400,
            'days' => 86400,
            'w' => 604800,
            'week' => 604800,
            'weeks' => 604800,
        ];

        if (preg_match('/^(\d+)\s*([a-z]+)$/', $time, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            if (isset($units[$unit])) {
                return $value * $units[$unit];
            }
        }

        return strtotime($time) ?: 0;
    }

    /**
     * Get time difference in human-readable format
     */
    public static function ago(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return $diff . ' second' . ($diff !== 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 2592000) {
            $weeks = (int) floor($diff / 604800);
            return $weeks . ' week' . ($weeks !== 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 31536000) {
            $months = (int) floor($diff / 2592000);
            return $months . ' month' . ($months !== 1 ? 's' : '') . ' ago';
        }
        
        $years = (int) floor($diff / 31536000);
        return $years . ' year' . ($years !== 1 ? 's' : '') . ' ago';
    }

    /**
     * Check if timestamp is expired based on duration
     */
    public static function isExpired(int $timestamp, int $duration): bool
    {
        return (time() - $timestamp) > $duration;
    }

    /**
     * Get timestamp for start of day
     */
    public static function startOfDay(int $timestamp): int
    {
        return strtotime('midnight', $timestamp);
    }

    /**
     * Get timestamp for end of day
     */
    public static function endOfDay(int $timestamp): int
    {
        return strtotime('tomorrow', $timestamp) - 1;
    }

    /**
     * Convert seconds to human-readable duration
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        
        if ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        }
        
        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return "{$days}d {$hours}h";
    }
}
