<?php

namespace Bot;

require_once __DIR__ . '/../Helpers/Functions.php';
require_once __DIR__ . '/../Services/ServiceHandler.php';
require_once __DIR__ . '/../Helpers/UserDataHandler.php';

use Helpers\UserDataHandler;
use Services\ServiceHandler;

class TelegramBot
{
    private const API_URL = 'https://api.telegram.org/bot';

    private string $token;
    private int $chatId;
    private ServiceHandler $serviceHandler;

    public function __construct(ServiceHandler $serviceHandler)
    {
        $this->serviceHandler = $serviceHandler;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getData(): ?object
    {
        error_log("Fetching data from Telegram...");
        $data = json_decode(file_get_contents('php://input'));

        if (isset($data->message)) {
            $this->chatId = $data->message->chat->id;
            error_log("Received message from chat ID: " . $this->chatId);
            return $data->message;
        } elseif (isset($data->callback_query)) {
            $this->chatId = $data->callback_query->message->chat->id;
            error_log("Received callback query from chat ID: " . $this->chatId);
            return $data->callback_query;
        }
        return null;
    }

    public function setWebhook(string $url): string
    {
        error_log("Setting webhook to URL: $url");
        return $this->requests('setWebhook', ['url' => $url]);
    }

    public function sendMessage(string $message): string
    {
        error_log("Sending message: $message");
        return $this->requests('sendMessage', [
            'chat_id' => $this->chatId,
            'text' => $message,
        ]);
    }

    public function sendInlineKeyboard(string $text, array $buttons): string
    {
        error_log("Sending inline keyboard...");
        return $this->requests('sendMessage', [
            'chat_id' => $this->chatId,
            'text' => $text,
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    private function requests(string $method, array $posts): string
    {
        $ch = curl_init();
        $url = self::API_URL . $this->token . '/' . $method;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($posts));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        error_log("Telegram response: $response");
        return $response;
    }

    public function processMessage(): void
    {
        $messageData = $this->getData();
        if ($messageData !== null && isset($messageData->text)) {
            $text = $messageData->text;
            $state = UserDataHandler::getUserState($this->chatId);
            $userData = UserDataHandler::getUserData($this->chatId);

            if ($text === '/start') {
                $this->sendMessage('Salam! Xoş gəldiniz. Servisi seçin zəhmət olmasa.');
                $this->serviceHandler->displayServices($this);
                UserDataHandler::setUserState($this->chatId, 'awaiting_service_selection');
            } elseif ($state === 'awaiting_service_selection' && strpos($text, 'service_') === 0) {
                $serviceId = str_replace('service_', '', $text);
                UserDataHandler::setLastSelectedService($this->chatId, $serviceId);
                $this->sendMessage("Tarixi daxil edin (YYYY-MM-DD formatında):");
                UserDataHandler::setUserState($this->chatId, 'awaiting_date');
            } elseif ($state === 'awaiting_date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
                $selectedDate = $text;
                UserDataHandler::setLastSelectedDate($this->chatId, $selectedDate);
                $serviceId = UserDataHandler::getLastSelectedService($this->chatId);
                if ($serviceId) {
                    $this->serviceHandler->sendAvailableTimes($this, $serviceId, $selectedDate);
                    UserDataHandler::setUserState($this->chatId, 'awaiting_time');
                } else {
                    $this->sendMessage("Əvvəlcə bir xidmət seçin.");
                }
            } elseif ($state === 'awaiting_time' && preg_match('/^\d{2}:\d{2}$/', $text)) {
                $selectedTime = $text;
                UserDataHandler::setLastSelectedTime($this->chatId, $selectedTime);
                $this->sendMessage("Adınızı daxil edin:");
                UserDataHandler::setUserState($this->chatId, 'awaiting_name');
            } elseif ($state === 'awaiting_name') {
                $userData['name'] = $text;
                UserDataHandler::setUserData($this->chatId, $userData);
                $this->sendMessage("Soyadınızı daxil edin:");
                UserDataHandler::setUserState($this->chatId, 'awaiting_surname');
            } elseif ($state === 'awaiting_surname') {
                $userData['surname'] = $text;
                UserDataHandler::setUserData($this->chatId, $userData);
                $this->sendMessage("Emailinizi daxil edin:");
                UserDataHandler::setUserState($this->chatId, 'awaiting_email');
            } elseif ($state === 'awaiting_email') {
                $userData['email'] = $text;
                UserDataHandler::setUserData($this->chatId, $userData);
                $this->sendMessage("Telefon nömrənizi daxil edin:");
                UserDataHandler::setUserState($this->chatId, 'awaiting_phone');
            } elseif ($state === 'awaiting_phone') {
                $userData['phone'] = $text;
                UserDataHandler::setUserData($this->chatId, $userData);

                $serviceId = UserDataHandler::getLastSelectedService($this->chatId);
                $selectedDate = UserDataHandler::getLastSelectedDate($this->chatId);
                $selectedTime = UserDataHandler::getLastSelectedTime($this->chatId);
                if ($serviceId && $selectedDate && $selectedTime) {
                    $userData['date'] = $selectedDate;
                    $userData['time'] = $selectedTime;
                    $userData['service_id'] = $serviceId;
                    $this->sendConfirmation($userData);
                    UserDataHandler::setUserState($this->chatId, 'confirmation_pending');
                } else {
                    $this->sendMessage("Xidmət, tarix və ya istifadəçi məlumatları tam deyil.");
                }
            } else {
                $this->sendMessage('Anlaşılan komanda tapılmadı. Xahiş edirik, düzgün komanda daxil edin.');
            }
        } elseif ($messageData !== null && isset($messageData->data)) {
            $callbackData = $messageData->data;
            if (strpos($callbackData, 'service_') === 0) {
                $serviceId = str_replace('service_', '', $callbackData);
                UserDataHandler::setLastSelectedService($this->chatId, $serviceId);
                $this->sendMessage("Tarixi daxil edin (YYYY-MM-DD formatında):");
                UserDataHandler::setUserState($this->chatId, 'awaiting_date');
            } elseif (strpos($callbackData, 'time_') === 0) {
                $selectedTime = str_replace('time_', '', $callbackData);
                UserDataHandler::setLastSelectedTime($this->chatId, $selectedTime);
                $this->sendMessage("Siz saatı seçdiniz: $selectedTime. İndi isə adınızı daxil edin:");
                UserDataHandler::setUserState($this->chatId, 'awaiting_name');
            } elseif (strpos($callbackData, 'confirm_booking_') === 0) {
                $dataParts = explode('_', str_replace('confirm_booking_', '', $callbackData));
                $serviceId = $dataParts[0];
                $selectedTime = urldecode($dataParts[1]);
                $selectedDate = $dataParts[2];

                $userData = UserDataHandler::getUserData($this->chatId);
                $userData['date'] = $selectedDate;
                $userData['time'] = $selectedTime;
                $userData['service_id'] = $serviceId;

                // Ensure date is passed as a string
                $this->serviceHandler->submitForm($serviceId, $selectedTime, $selectedDate, $userData);
                $this->sendMessage("Rezervasiya uğurla tamamlandı." . '-' . $serviceId . '-' . $selectedTime . '-' . $selectedDate . '-' . $userData);
            }
        }
    }


    public function sendConfirmation(array $userData): void
    {
        $message = "Məlumatlar uğurla daxil edildi. \n";
        $message .= "Seçilmiş xidmət ID-si: " . $userData['service_id'] . "\n";
        $message .= "Tarix: " . $userData['date'] . "\n";
        $message .= "Saat: " . $userData['time'] . "\n";
        $message .= "Ad: " . $userData['name'] . "\n";
        $message .= "Soyad: " . $userData['surname'] . "\n";
        $message .= "Email: " . $userData['email'] . "\n";
        $message .= "Nömrə: " . $userData['phone'] . "\n";
        $message .= $userData['service_id'] . 'mxd' . $userData['time'] . 'mxd' . $userData['date'];

        $buttons = [
            [
                [
                    'text' => 'Rezerv edilsin',
                    'callback_data' => 'confirm_booking_' . $userData['service_id'] . '_' . urlencode($userData['time']) . '_' . $userData['date']
                ]
            ]
        ];

        $this->requests('sendMessage', [
            'chat_id' => $this->chatId,
            'text' => $message,
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

}
