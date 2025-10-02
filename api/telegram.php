<?php
header('Content-Type: application/json');
session_start();

require_once '../includes/auth.php';
require_once '../includes/config.php';

// Check authentication for all API calls
requireAuth();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? $_POST['action'] ?? '', "\\ \t\n\r\0\x0B");

try {
    switch ($action) {
        case 'test_bot':
            testBot();
            break;
            
        case 'test_bot_simple':
            testBotSimple();
            break;
            
        case 'send_message':
            sendMessage();
            break;
            
        case 'send_file':
            sendFile();
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log('Telegram API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'action' => $action,
            'method' => $method,
            'file' => __FILE__,
            'line' => $e->getLine()
        ]
    ]);
}

function testBot() {
    
    $telegram_config = getConfig('telegram');
    
    
    if (!$telegram_config) {
        throw new Exception('Failed to load Telegram configuration. Please save Telegram settings first.');
    }
    
    if (empty($telegram_config['bot_token'])) {
        throw new Exception('Bot token is required. Please enter a valid bot token.');
    }
    
    if (empty($telegram_config['chat_id'])) {
        throw new Exception('Chat ID is required. Please enter a valid chat ID.');
    }
    
    $bot_token = trim($telegram_config['bot_token']);
    $chat_id = trim($telegram_config['chat_id']);
    
    // Check if bot token is masked (not yet saved properly)
    if ($bot_token === '••••••••' || strpos($bot_token, '••••••••') !== false) {
        throw new Exception('Please enter your actual bot token and save the configuration first.');
    }
    
    
    // Test 1: Get bot info using getMe API
    $bot_info_url = "https://api.telegram.org/bot{$bot_token}/getMe";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $bot_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $bot_response = curl_exec($ch);
    $bot_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $bot_error = curl_error($ch);
    curl_close($ch);
    
    if ($bot_error) {
        throw new Exception('cURL error while testing bot: ' . $bot_error);
    }
    
    if ($bot_http_code !== 200) {
        throw new Exception('HTTP error ' . $bot_http_code . ' while testing bot');
    }
    
    $bot_data = json_decode($bot_response, true);
    
    if (!$bot_data || !$bot_data['ok']) {
        $error_msg = isset($bot_data['description']) ? $bot_data['description'] : 'Unknown error';
        throw new Exception('Bot token invalid: ' . $error_msg);
    }
    
    $bot_username = $bot_data['result']['username'];
    $bot_name = $bot_data['result']['first_name'];
    
    // Test 2: Send a test message to verify chat ID
    $test_message = "🤖 VPN Remote Test Message\n\n";
    $test_message .= "✅ Bot: @{$bot_username}\n";
    $test_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n";
    $test_message .= "🔧 Status: Connection test successful!";
    
    $send_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_data = [
        'chat_id' => $chat_id,
        'text' => $test_message
    ];
    
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $send_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $send_response = curl_exec($ch);
    $send_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $send_error = curl_error($ch);
    curl_close($ch);
    
    
    if ($send_error) {
        throw new Exception('cURL error while sending test message: ' . $send_error);
    }
    
    if ($send_http_code !== 200) {
        $send_data = json_decode($send_response, true);
        $error_detail = '';
        if ($send_data && isset($send_data['description'])) {
            $error_detail = ' - ' . $send_data['description'];
        }
        throw new Exception('HTTP error ' . $send_http_code . ' while sending test message' . $error_detail);
    }
    
    $send_data = json_decode($send_response, true);
    
    if (!$send_data || !$send_data['ok']) {
        $error_msg = isset($send_data['description']) ? $send_data['description'] : 'Unknown error';
        throw new Exception('Failed to send test message: ' . $error_msg);
    }
    
    // If we reach here, both bot token and chat ID are working
    echo json_encode([
        'success' => true,
        'message' => "✅ Telegram bot test successful!\n\n🤖 Bot: {$bot_name} (@{$bot_username})\n📤 Test message sent to chat ID: {$chat_id}",
        'bot_info' => [
            'name' => $bot_name,
            'username' => $bot_username,
            'chat_id' => $chat_id
        ]
    ]);
}

function testBotSimple() {
    
    $telegram_config = getConfig('telegram');
    
    
    if (!$telegram_config) {
        throw new Exception('Failed to load Telegram configuration. Please save Telegram settings first.');
    }
    
    if (empty($telegram_config['bot_token'])) {
        throw new Exception('Bot token is required. Please enter a valid bot token.');
    }
    
    $bot_token = trim($telegram_config['bot_token']);
    
    // Check if bot token is masked (not yet saved properly)
    if ($bot_token === '••••••••' || strpos($bot_token, '••••••••') !== false) {
        throw new Exception('Please enter your actual bot token and save the configuration first.');
    }
    
    // Test: Get bot info using getMe API
    $bot_info_url = "https://api.telegram.org/bot{$bot_token}/getMe";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $bot_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $bot_response = curl_exec($ch);
    $bot_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $bot_error = curl_error($ch);
    curl_close($ch);
    
    if ($bot_error) {
        throw new Exception('cURL error while testing bot: ' . $bot_error);
    }
    
    if ($bot_http_code !== 200) {
        throw new Exception('HTTP error ' . $bot_http_code . ' while testing bot');
    }
    
    $bot_data = json_decode($bot_response, true);
    
    if (!$bot_data || !$bot_data['ok']) {
        $error_msg = isset($bot_data['description']) ? $bot_data['description'] : 'Unknown error';
        throw new Exception('Bot token invalid: ' . $error_msg);
    }
    
    $bot_username = $bot_data['result']['username'];
    $bot_name = $bot_data['result']['first_name'];
    
    echo json_encode([
        'success' => true,
        'message' => "✅ Bot token is valid!\n\n🤖 Bot: {$bot_name} (@{$bot_username})\n\nNote: Chat ID not tested yet. Click full 'Test Bot' to test message sending.",
        'bot_info' => [
            'name' => $bot_name,
            'username' => $bot_username,
            'chat_id' => $telegram_config['chat_id'] ?? ''
        ]
    ]);
}

function sendMessage() {
    $telegram_config = getConfig('telegram');
    
    if (!$telegram_config || empty($telegram_config['bot_token']) || empty($telegram_config['chat_id'])) {
        throw new Exception('Telegram bot token and chat ID are required');
    }
    
    if (!$telegram_config['enabled']) {
        throw new Exception('Telegram bot is not enabled');
    }
    
    // Get message from POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    
    if (empty($message)) {
        throw new Exception('Message text is required');
    }
    
    $bot_token = $telegram_config['bot_token'];
    $chat_id = $telegram_config['chat_id'];
    
    $send_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $send_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL error while sending message: ' . $error);
    }
    
    if ($http_code !== 200) {
        throw new Exception('HTTP error ' . $http_code . ' while sending message');
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !$data['ok']) {
        $error_msg = isset($data['description']) ? $data['description'] : 'Unknown error';
        throw new Exception('Failed to send message: ' . $error_msg);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully to Telegram'
    ]);
}

function sendFile() {
    $telegram_config = getConfig('telegram');
    
    if (!$telegram_config || empty($telegram_config['bot_token']) || empty($telegram_config['chat_id'])) {
        throw new Exception('Telegram bot token and chat ID are required');
    }
    
    if (!$telegram_config['enabled']) {
        throw new Exception('Telegram bot is not enabled');
    }
    
    // Get file data from POST
    $input = json_decode(file_get_contents('php://input'), true);
    $filename = $input['filename'] ?? '';
    $content = $input['content'] ?? '';
    $caption = $input['caption'] ?? '';
    
    if (empty($filename) || empty($content)) {
        throw new Exception('Filename and content are required');
    }
    
    $bot_token = $telegram_config['bot_token'];
    $chat_id = $telegram_config['chat_id'];
    
    // Create temporary file
    $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($temp_file, $content);
    
    $send_url = "https://api.telegram.org/bot{$bot_token}/sendDocument";
    
    // Prepare multipart form data
    $post_data = [
        'chat_id' => $chat_id,
        'document' => new CURLFile($temp_file, 'text/plain', $filename)
    ];
    
    if (!empty($caption)) {
        $post_data['caption'] = $caption;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $send_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Clean up temporary file
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    if ($error) {
        throw new Exception('cURL error while sending file: ' . $error);
    }
    
    if ($http_code !== 200) {
        throw new Exception('HTTP error ' . $http_code . ' while sending file');
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !$data['ok']) {
        $error_msg = isset($data['description']) ? $data['description'] : 'Unknown error';
        throw new Exception('Failed to send file: ' . $error_msg);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'File sent successfully to Telegram'
    ]);
}
?>