<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class TelegramController extends Controller
{
    private string $botToken;
    private string $chatId;

    public function __construct()
    {
        // Установите токен бота и chat_id вашего канала
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_CHAT_ID');
    }

    public function fetchLatestPosts()
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/getUpdates";

        // Делаем запрос к Telegram API
        $response = Http::get($url);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch updates from Telegram'], 500);
        }

        $data = $response->json();

        // Проверяем, есть ли данные
        if (!isset($data['ok']) || !$data['ok'] || empty($data['result'])) {
            return response()->json(['error' => 'No data available'], 404);
        }

        $posts = collect($data['result'])
            ->whereNotNull('channel_post') // Берем только объекты с "channel_post"
            ->map(function ($item) {
                $post = $item['channel_post'];

                return [
                    'chat_username' => $post['chat']['username'],
                    'chat_title' => $post['chat']['title'],
                    'message_id' => $post['message_id'],
                    'date' => date('Y-m-d H:i:s', $post['date']),
                    'caption' => $post['caption'] ?? null,
                    'photo' => isset($post['photo']) ? $this->getPhotoUrl($post['photo']) : null,
                ];
            })
            ->take(10) // Берем последние 10 постов
            ->values();

        return response()->json($posts);
    }

    private function getPhotoUrl(array $photos): ?string
    {
        // Находим фото с наибольшим размером
        $largestPhoto = collect($photos)->sortByDesc('file_size')->first();

        if (!$largestPhoto || !isset($largestPhoto['file_id'])) {
            return null;
        }

        // Получаем file_path через Telegram API
        $fileResponse = Http::get("https://api.telegram.org/bot{$this->botToken}/getFile?file_id={$largestPhoto['file_id']}");

        if ($fileResponse->failed() || !isset($fileResponse->json()['result']['file_path'])) {
            return null;
        }

        $filePath = $fileResponse->json()['result']['file_path'];

        return "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
    }
}