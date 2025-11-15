<?php
header('Content-Type: application/json');

$TELEGRAM_BOT_TOKEN = "8218756776:AAFp4Y---2ZIJfrgC8u43AROPFtk1PK3NoA";
$SECRET_TOKEN = "soulista_secret_2024";

// Get Supabase credentials from environment or config
$SUPABASE_URL = getenv('VITE_SUPABASE_URL') ?: "https://qwcddnoieksbunyuotww.supabase.co";
$SUPABASE_KEY = getenv('VITE_SUPABASE_PUBLISHABLE_KEY') ?: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InF3Y2Rkbm9pZWtzYnVueXVvdHd3Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjE1NDEyNjMsImV4cCI6MjA3NzExNzI2M30.dzH1fYAfdXk3jjQi5E3YjKsG3kmsER29ZJn5CapGIwg";

// Get the update from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Log for debugging
error_log("Telegram update: " . print_r($update, true));

if (isset($update['message']['text'])) {
    $text = trim($update['message']['text']);
    $chatId = $update['message']['chat']['id'];
    $username = $update['message']['from']['username'] ?? 'Unknown';

    if (strpos($text, '/start') === 0) {
        $parts = explode(' ', $text);
        $token = isset($parts[1]) ? $parts[1] : '';

        if ($token === $SECRET_TOKEN) {
            // Save to Supabase
            $data = [
                'chat_id' => (string)$chatId,
                'username' => $username,
                'is_active' => true,
                'subscribed_at' => date('c')
            ];

            $ch = curl_init("$SUPABASE_URL/rest/v1/telegram_subscribers");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $SUPABASE_KEY,
                'Authorization: Bearer ' . $SUPABASE_KEY,
                'Prefer: resolution=merge-duplicates'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                sendTelegramMessage($chatId, "✅ Successfully subscribed to order notifications!\n\nYou will now receive notifications when new orders are placed.", $TELEGRAM_BOT_TOKEN);
            } else {
                error_log("Supabase error: " . $response);
                sendTelegramMessage($chatId, "❌ Error subscribing. Please try again later.", $TELEGRAM_BOT_TOKEN);
            }
        } elseif ($token) {
            sendTelegramMessage($chatId, "❌ Invalid token. Please use the correct secret token to subscribe.", $TELEGRAM_BOT_TOKEN);
        } else {
            sendTelegramMessage($chatId, "👋 Welcome to Soulista Order Notifications!\n\nTo subscribe, use:\n/start YOUR_SECRET_TOKEN", $TELEGRAM_BOT_TOKEN);
        }
    } elseif ($text === '/stop') {
        // Unsubscribe
        $data = ['is_active' => false];
        
        $ch = curl_init("$SUPABASE_URL/rest/v1/telegram_subscribers?chat_id=eq.$chatId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $SUPABASE_KEY,
            'Authorization: Bearer ' . $SUPABASE_KEY
        ]);
        
        curl_exec($ch);
        curl_close($ch);
        
        sendTelegramMessage($chatId, "🔕 You have been unsubscribed from order notifications.", $TELEGRAM_BOT_TOKEN);
    } elseif ($text === '/status') {
        // Check status
        $ch = curl_init("$SUPABASE_URL/rest/v1/telegram_subscribers?chat_id=eq.$chatId&select=is_active");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $SUPABASE_KEY,
            'Authorization: Bearer ' . $SUPABASE_KEY
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!empty($data) && $data[0]['is_active']) {
            sendTelegramMessage($chatId, "✅ You are currently subscribed to order notifications.", $TELEGRAM_BOT_TOKEN);
        } else {
            sendTelegramMessage($chatId, "❌ You are not subscribed to order notifications.", $TELEGRAM_BOT_TOKEN);
        }
    }
}

echo json_encode(['ok' => true]);

function sendTelegramMessage($chatId, $text, $botToken) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init("https://api.telegram.org/bot$botToken/sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    curl_exec($ch);
    curl_close($ch);
}
?>
