<?php

namespace Helpers;

class UserDataHandler
{
    private static function getFilePath(int $chatId, string $type): string
    {
        $dir = __DIR__ . '/../user_data';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return "{$dir}/{$chatId}_{$type}.txt";
    }

    public static function getLastSelectedService(int $chatId): ?string
    {
        error_log("Fetching last selected service for chat ID: $chatId");
        $filePath = self::getFilePath($chatId, 'last_service');
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }
        return null;
    }

    public static function setLastSelectedService(int $chatId, string $serviceId): void
    {
        error_log("Setting last selected service for chat ID: $chatId to service ID: $serviceId");
        $filePath = self::getFilePath($chatId, 'last_service');
        file_put_contents($filePath, $serviceId);
    }

    public static function getLastSelectedDate(int $chatId): ?string
    {
        error_log("Fetching last selected date for chat ID: $chatId");
        $filePath = self::getFilePath($chatId, 'last_date');
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }
        return null;
    }

    public static function setLastSelectedDate(int $chatId, string $selectedDate): void
    {
        error_log("Setting last selected date for chat ID: $chatId to date: $selectedDate");
        $filePath = self::getFilePath($chatId, 'last_date');
        file_put_contents($filePath, $selectedDate);
    }

    public static function getLastSelectedTime(int $chatId): ?string
    {
        error_log("Fetching last selected time for chat ID: $chatId");
        $filePath = self::getFilePath($chatId, 'last_time');
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }
        return null;
    }

    public static function setLastSelectedTime(int $chatId, string $selectedTime): void
    {
        error_log("Setting last selected time for chat ID: $chatId to time: $selectedTime");
        $filePath = self::getFilePath($chatId, 'last_time');
        file_put_contents($filePath, $selectedTime);
    }

    public static function getUserState(int $chatId): ?string
    {
        error_log("Fetching user state for chat ID: $chatId");
        $filePath = self::getFilePath($chatId, 'state');
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }
        return null;
    }

    public static function setUserState(int $chatId, string $state): void
    {
        error_log("Setting user state for chat ID: $chatId to state: $state");
        $filePath = self::getFilePath($chatId, 'state');
        file_put_contents($filePath, $state);
    }

    public static function getUserData(int $chatId): array
    {
        error_log("Fetching user data for chat ID: $chatId");
        $filePath = self::getFilePath($chatId, 'userdata');
        if (file_exists($filePath)) {
            $data = json_decode(file_get_contents($filePath), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    public static function setUserData(int $chatId, array $userData): void
    {
        error_log("Setting user data for chat ID: $chatId to data: " . json_encode($userData));
        $filePath = self::getFilePath($chatId, 'userdata');
        file_put_contents($filePath, json_encode($userData));
    }
}




