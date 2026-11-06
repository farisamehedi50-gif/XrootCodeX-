<?php
error_reporting(0);
ini_set('max_execution_time', 0);
date_default_timezone_set('Asia/Dhaka');

define('API_KEY', '8862431486:AAEr-kcPxhWc1mKsAR9Ii255kBn2UhbkW0A'); 
define('ADMIN_ID', '6759518520'); 

$bot_username = "@BestGmail_Sell_Bot";

@mkdir('data');
$db_files = [
    'users.json' => '{}', 
    'settings.json' => '{}', 
    'proofs.json' => '[]', 
    'withdraws.json' => '[]'
];

foreach ($db_files as $file => $default) { 
    if (!file_exists("data/$file")) {
        file_put_contents("data/$file", $default); 
    }
}

$master_files = [
    'accounts_master.csv' => ["Username", "Password", "2FA Key"],
    'gmail_master.csv' => ["Email", "Password"], 
    'facebook_master.csv' => ["UID", "Password", "2FA Key"], 
    'facebook_cookies_master.csv' => ["UID", "Password", "Cookies"],
    'instagram_cookies_master.csv' => ["Username", "Password", "Cookies"] 
];

foreach ($master_files as $csv_name => $headers) {
    if (!file_exists("data/$csv_name")) {
        $file = fopen("data/$csv_name", "w");
        if ($file) {
            if (flock($file, LOCK_EX)) {
                fputcsv($file, $headers);
                flock($file, LOCK_UN);
            }
            fclose($file);
        }
    }
}

function readDB($filename) { 
    $path = "data/$filename";
    $backup_path = "data/$filename.bak";
    
    if (!file_exists($path)) {
        if (file_exists($backup_path)) {
            @copy($backup_path, $path);
        } else {
            return [];
        }
    }
    
    $fp = @fopen($path, "r");
    if (!$fp) return [];
    
    $locked = false;
    for ($i = 0; $i < 5; $i++) {
        if (@flock($fp, LOCK_SH | LOCK_NB)) {
            $locked = true;
            break;
        }
        usleep(10000);
    }
    
    $size = @filesize($path);
    $content = ($size > 0) ? @fread($fp, $size) : "";
    if ($locked) {
        @flock($fp, LOCK_UN);
    }
    @fclose($fp);
    
    if (empty($content)) {
        if (file_exists($backup_path) && filesize($backup_path) > 0) {
            $content = @file_get_contents($backup_path);
            @file_put_contents($path, $content, LOCK_EX);
        } else {
            return [];
        }
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (file_exists($backup_path)) {
            $backup_content = @file_get_contents($backup_path);
            $backup_data = json_decode($backup_content, true);
            if (is_array($backup_data)) {
                @file_put_contents($path, $backup_content, LOCK_EX);
                return $backup_data;
            }
        }
        return null; 
    }
    return is_array($data) ? $data : [];
}

function writeDB($filename, $data) { 
    $path = "data/$filename";
    $backup_path = "data/$filename.bak";
    
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) return false;
    
    $fp = @fopen($path, "c+");
    if (!$fp) return false;
    
    $locked = false;
    for ($i = 0; $i < 5; $i++) {
        if (@flock($fp, LOCK_EX | LOCK_NB)) {
            $locked = true;
            break;
        }
        usleep(10000);
    }
    
    if ($locked) {
        @ftruncate($fp, 0);
        @rewind($fp);
        @fwrite($fp, $json);
        @fflush($fp);
        @flock($fp, LOCK_UN);
    } else {
        @file_put_contents($path, $json);
    }
    @fclose($fp);
    
    @file_put_contents($backup_path, $json, LOCK_EX);
    return true;
}

function setState($uid, $newState, $newTemp = '') {
    $users = readDB('users.json');
    if ($users === null) return;
    if (isset($users[$uid])) {
        $users[$uid]['state'] = $newState;
        $users[$uid]['temp'] = $newTemp;
        writeDB('users.json', $users);
    }
}

$s = readDB('settings.json');
if ($s === null) {
    exit;
}
$defaults = [
    'force_channel' => '@CobraX_Empire', 
    'force_channel_url' => 'https://t.me/CobraX_Empire',
    'support_id' => 'https://t.me/XrootCodeX',
    'official_channel' => 'https://t.me/CobraX_Empire',
    'ref_commission' => 5, 
    'min_wd_bkash' => 100,
    'bkash_charge' => 5,
    'min_wd_nagad' => 100,
    'nagad_charge' => 5,
    'min_wd_rocket' => 100, 
    'rocket_charge' => 5,   
    'min_wd_binance' => 100,
    'binance_charge' => 0,
    'status_wd_bkash' => 'open',  
    'status_wd_nagad' => 'open',  
    'status_wd_rocket' => 'open', 
    'status_wd_binance' => 'open',
    'reward_instagram' => 4.00,
    'reward_gmail' => 5.00,
    'reward_facebook' => 6.00,
    'reward_facebook_cookies' => 8.00, 
    'reward_instagram_cookies' => 8.00, 
    'video_instagram' => '',
    'video_gmail' => '',
    'video_facebook' => '',
    'video_fb_cookies' => '',
    'video_insta_cookies' => '',
    'admin_insta_password' => 'Pass@insta',   
    'admin_gmail_password' => 'Pass@gmail',   
    'admin_fb_password' => 'Pass@facebook',   
    'admin_fb_cookies_password' => 'Pass@cookies', 
    'admin_insta_cookies_password' => 'Pass@instacookies',   
    'bot_username' => '', 
    'work_videos' => ['https://youtube.com'],
    'status_instagram' => 'open',
    'status_gmail' => 'open',
    'status_facebook' => 'open',
    'status_facebook_cookies' => 'open', 
    'status_instagram_cookies' => 'open'
];
$updated = false; 
foreach ($defaults as $k => $v) { 
    if (!isset($s[$k])) { $s[$k] = $v; $updated = true; } 
}

$unsets = ['ref_bonus_amount', 'ref_lb_target', 'ref_lb_p1', 'ref_lb_p2', 'ref_lb_p3', 'task_lb_target', 'task_lb_p1', 'task_lb_p2', 'task_lb_p3'];
foreach ($unsets as $u_key) {
    if (isset($s[$u_key])) {
        unset($s[$u_key]);
        $updated = true;
    }
}
if ($updated) writeDB('settings.json', $s);

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch); 
    curl_close($ch); 
    return json_decode($res, true);
}

function sendRich($chat_id, $html_text, $reply_markup = '', $message_effect_id = '') {
    $data = [
        'chat_id' => $chat_id,
        'rich_message' => json_encode(['html' => "<table bordered><tr><td>{$html_text}</td></tr></table>", 'is_rtl' => false, 'skip_entity_detection' => false])
    ];
    if (!empty($reply_markup)) {
        $data['reply_markup'] = is_string($reply_markup) ? $reply_markup : json_encode($reply_markup);
    }
    if (!empty($message_effect_id)) {
        $data['message_effect_id'] = $message_effect_id;
    }
    $res = bot('sendRichMessage', $data);
    if (!isset($res['ok']) || !$res['ok']) {
        $fallback_data = [
            'chat_id' => $chat_id,
            'text' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $html_text)),
            'parse_mode' => 'HTML'
        ];
        if (!empty($reply_markup)) $fallback_data['reply_markup'] = $data['reply_markup'];
        return bot('sendMessage', $fallback_data);
    }
    return $res;
}

if (empty($s['bot_username'])) {
    $me = bot('getMe');
    if ($me['ok']) {
        $s['bot_username'] = $me['result']['username'];
        writeDB('settings.json', $s);
    }
}
$bot_username = $s['bot_username'] ?? "GmailSellgBot";

function rowContainsValue($row, $target) {
    $target = strtolower(trim($target));
    if (empty($target)) return false;
    foreach ($row as $cell) {
        $cell = strtolower(trim($cell));
        if ($cell === $target) return true;
        if (strpos($cell, $target) !== false) {
            if (ctype_digit($target)) {
                if (preg_match('/(?<!\d)' . preg_quote($target, '/') . '(?!\d)/', $cell)) {
                    return true;
                }
            } else {
                return true;
            }
        }
    }
    return false;
}

function generateUsername() {
    $first_names = ['rahim', 'karim', 'sajal', 'arif', 'tanvir', 'shakil', 'rony', 'mim', 'sultana', 'rifat', 'tamim', 'hasan', 'anik', 'joy', 'fahim', 'rubel', 'sumon', 'emran', 'nabil', 'sakib', 'faisal', 'ismail', 'sabbir', 'kamal', 'jamal', 'babul', 'sohel'];
    $last_names = ['khan', 'ahmed', 'hossain', 'ali', 'islam', 'rahman', 'chowdhury', 'bhuiyan', 'shaikh', 'miah', 'talukder', 'patwary', 'sarker', 'mozumder'];
    
    $fn = $first_names[array_rand($first_names)];
    $ln = $last_names[array_rand($last_names)];
    $num = rand(100, 9999);
    
    return $fn . "_" . $ln . $num;
}

function generateFbNameArray() {
    $first_names = ['rahim', 'karim', 'sajal', 'arif', 'tanvir', 'shakil', 'rony', 'mim', 'sultana', 'rifat', 'tamim', 'hasan', 'anik', 'joy', 'fahim', 'rubel', 'sumon', 'emran', 'nabil', 'sakib', 'faisal', 'ismail', 'sabbir', 'kamal', 'jamal', 'babul', 'sohel'];
    $last_names = ['khan', 'ahmed', 'hossain', 'ali', 'islam', 'rahman', 'chowdhury', 'bhuiyan', 'shaikh', 'miah', 'talukder', 'patwary', 'sarker', 'mozumder'];
    
    $fn = ucfirst($first_names[array_rand($first_names)]);
    $ln = ucfirst($last_names[array_rand($last_names)]);
    
    return ['first' => $fn, 'last' => $ln];
}

function generateGmailData() {
    $first_names = ['rahim', 'karim', 'sajal', 'arif', 'tanvir', 'shakil', 'rony', 'mim', 'sultana', 'rifat', 'tamim', 'hasan', 'anik', 'joy', 'fahim', 'rubel', 'sumon', 'emran', 'nabil', 'sakib', 'faisal', 'ismail', 'sabbir', 'kamal'];
    $last_names = ['khan', 'ahmed', 'hossain', 'ali', 'islam', 'rahman', 'chowdhury', 'bhuiyan', 'shaikh'];
    
    $fn = ucfirst($first_names[array_rand($first_names)]);
    $ln = ucfirst($last_names[array_rand($last_names)]);
    $num = rand(100, 9999);
    
    return [
        'email' => strtolower($fn) . strtolower($ln) . $num . "@gmail.com"
    ];
}

function isValidBase32Key($secret) {
    $secret = str_replace(' ', '', $secret);
    $len = strlen($secret);
    if ($len != 16 && $len != 24 && $len != 32) {
        return false;
    }
    if (preg_match('/[^A-Z2-7]/i', $secret)) {
        return false;
    }
    return true;
}

function base32_decode($secret) {
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    if (empty($secret)) return '';
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    foreach (str_split($secret) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = str_split($binary, 8);
    $out = '';
    foreach ($bytes as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function getTOTP($secret) {
    $time_slice = floor(time() / 30);
    $secret_key = base32_decode($secret);
    if (empty($secret_key)) return false;
    
    $time = pack('N*', 0) . pack('N*', $time_slice);
    $hmac = hash_hmac('sha1', $time, $secret_key, true);
    $offset = ord(substr($hmac, -1)) & 0x0F;
    $hash_part = substr($hmac, $offset, 4);
    $value = unpack('N', $hash_part);
    $value = $value[1] & 0x7FFFFFFF;
    
    return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
}

function getTOTPWithOffset($secret, $offset_seconds = 0) {
    $time_slice = floor((time() + $offset_seconds) / 30);
    $secret_key = base32_decode($secret);
    if (empty($secret_key)) return false;
    
    $time = pack('N*', 0) . pack('N*', $time_slice);
    $hmac = hash_hmac('sha1', $time, $secret_key, true);
    $offset = ord(substr($hmac, -1)) & 0x0F;
    $hash_part = substr($hmac, $offset, 4);
    $value = unpack('N', $hash_part);
    $value = $value[1] & 0x7FFFFFFF;
    
    return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
}

function removeCSVRowsBulk($accounts, $filename) {
    $file_path = "data/$filename";
    if (!file_exists($file_path) || empty($accounts)) return;
    
    $lookup = [];
    foreach ($accounts as $acc) {
        if ($filename == 'gmail_master.csv') {
            $key = trim($acc['email']);
        } elseif ($filename == 'facebook_cookies_master.csv') {
            $key = trim($acc['uid']);
        } elseif ($filename == 'instagram_cookies_master.csv') {
            $key = trim($acc['username']);
        } else {
            $key = trim($acc['id']) . "|" . trim(str_replace(' ', '', $acc['two_fa']));
        }
        $lookup[$key] = true;
    }
    
    $rows = [];
    $handle = @fopen($file_path, "r");
    if ($handle) {
        @flock($handle, LOCK_SH);
        $headers = fgetcsv($handle);
        $rows[] = $headers;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if ($filename == 'gmail_master.csv') {
                $row_key = trim($data[0]);
            } elseif ($filename == 'facebook_cookies_master.csv') {
                $row_key = trim($data[0]);
            } elseif ($filename == 'instagram_cookies_master.csv') {
                $row_key = trim($data[0]);
            } else {
                $row_key = trim($data[0]) . "|" . trim(str_replace(' ', '', $data[2]));
            }
            if (isset($lookup[$row_key])) {
                continue;
            }
            $rows[] = $data;
        }
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    
    $handle = @fopen($file_path, "w");
    if ($handle) {
        if (@flock($handle, LOCK_EX)) {
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            @flock($handle, LOCK_UN);
        }
        @fclose($handle);
    }
}

function makeBtn($text, $color = 'primary') {
    $btn = ['text' => $text];
    if (in_array($color, ['red', 'danger', 'লাল'])) $btn['style'] = 'danger';
    elseif (in_array($color, ['green', 'success', 'সবুজ'])) $btn['style'] = 'success';
    elseif (in_array($color, ['blue', 'primary', 'নীল'])) $btn['style'] = 'primary';
    return $btn;
}

function getMainMenu($uid) {
    $menu = [
        [makeBtn('📝 কাজ ▸', 'primary'), makeBtn('💵 ব্যালেন্স', 'success')],
        [makeBtn('💰 টাকা উত্তোলন', 'success'), makeBtn('🎁 My Referrals', 'primary')],
        [makeBtn('🆕 আমি নতুন', 'success'), makeBtn('👨‍✈️ এডমিন সাপোর্ট', 'primary')]
    ];
    
    if (strval($uid) === strval(ADMIN_ID)) {
        $menu[] = [makeBtn('⚙️ এডমিন প্যানেল', 'danger')];
    }
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminMenu() {
    $menu = [
        [makeBtn('📊 সেন্ট্রাল শিট', 'primary'), makeBtn('📥 পেন্ডিং উইথড্র', 'success')],
        [makeBtn('🔍 ইউজার সার্চ', 'primary'), makeBtn('📢 ব্রডকাস্ট', 'primary')],
        [makeBtn('⚙️ বটের সেটিংস', 'success'), makeBtn('🔙 ফিরে যান', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminCentralSheetMenu() {
    $menu = [
        [makeBtn('📸 ইনস্টাগ্রাম কন্ট্রোল', 'primary'), makeBtn('📧 জিমেইল কন্ট্রোল', 'success')],
        [makeBtn('👥 ফেসবুক কন্ট্রোল', 'success'), makeBtn('👥 কুকিজ কন্ট্রোল', 'primary')], 
        [makeBtn('📸 ইনস্টা কুকিজ কন্ট্রোল', 'primary'), makeBtn('🧹 ডাটাবেজ ক্লিনআপ', 'danger')],
        [makeBtn('🔙 এডমিন মেনু', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getInstaControlMenu() {
    $menu = [
        [makeBtn('📥 ইনস্টাগ্রাম শিট ডাউনলোড', 'primary')],
        [makeBtn('✅ ইনস্টা ভালো আইডি', 'success'), makeBtn('❌ ইনস্টা খারাপ আইডি', 'danger')],
        [makeBtn('🔙 সেন্ট্রাল শিট', 'primary')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getCookiesControlMenu() {
    $menu = [
        [makeBtn('📥 কুকিজ শিট ডাউনলোড', 'primary')],
        [makeBtn('✅ কুকিজ ভালো আইডি', 'success'), makeBtn('❌ কুকিজ খারাপ আইডি', 'danger')],
        [makeBtn('🔙 সেন্ট্রাল শিট', 'primary')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getInstaCookiesControlMenu() {
    $menu = [
        [makeBtn('📥 ইনস্টা কুকিজ শিট ডাউনলোড', 'primary')],
        [makeBtn('✅ ইনস্টা কুকিজ ভালো আইডি', 'success'), makeBtn('❌ ইনস্টা কুকিজ খারাপ আইডি', 'danger')],
        [makeBtn('🔙 সেন্ট্রাল শিট', 'primary')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getGmailControlMenu() {
    $menu = [
        [makeBtn('📥 জিমেইল শিট ডাউনলোড', 'primary')],
        [makeBtn('✅ জিমেইল ভালো আইডি', 'success'), makeBtn('❌ জিমেইল খারাপ আইডি', 'danger')],
        [makeBtn('🔙 সেন্ট্রাল শিট', 'primary')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getFacebookControlMenu() {
    $menu = [
        [makeBtn('📥 ফেসবুক শিট ডাউনলোড', 'primary')],
        [makeBtn('✅ ফেসবুক ভালো আইডি', 'success'), makeBtn('❌ ফেসবুক খারাপ আইডি', 'danger')],
        [makeBtn('🔙 সেন্ট্রাল শিট', 'primary')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminSettingsMenu() {
    $menu = [
        [makeBtn('💰 কাজের মূল্য সেট', 'primary'), makeBtn('🔐 পাসওয়ার্ড সেটিংস', 'success')],
        [makeBtn('🎥 কাজের ভিডিও সেট', 'success'), makeBtn('👥 কাজের পার্সেন্ট কমিশন', 'primary')],
        [makeBtn('📱 লিমিট ও চার্জ সেট', 'primary'), makeBtn('📢 চ্যানেল সেটিংস', 'success')],
        [makeBtn('⚙️ কাজ চালু/বন্ধ', 'primary'), makeBtn('⚙️ পেমেন্ট অন/অফ', 'success')],
        [makeBtn('🔙 এডমিন মেনু', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminVideoSettingsMenu() {
    $menu = [
        [makeBtn('📹 ইনস্টা ভিডিও সেট', 'primary'), makeBtn('📹 জিমেইল ভিডিও সেট', 'success')],
        [makeBtn('📹 ফেসবুক ভিডিও সেট', 'success'), makeBtn('📹 কুকিজ ভিডিও সেট', 'primary')],
        [makeBtn('🔙 বটের সেটিংস', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminPaymentToggleMenu($s) {
    $bkash_status = ($s['status_wd_bkash'] ?? 'open') == 'open' ? '🔴 বিকাশ উইথড্র বন্ধ' : '🟢 বিকাশ উইথড্র চালু';
    $nagad_status = ($s['status_wd_nagad'] ?? 'open') == 'open' ? '🔴 নগদ উইথড্র বন্ধ' : '🟢 নগদ উইথড্র চালু';
    $rocket_status = ($s['status_wd_rocket'] ?? 'open') == 'open' ? '🔴 রকেট উইথড্র বন্ধ' : '🟢 রকেট উইথড্র চালু';
    $binance_status = ($s['status_wd_binance'] ?? 'open') == 'open' ? '🔴 বাইনান্স উইথড্র বন্ধ' : '🟢 বাইনান্স উইথড্র চালু';
    
    $menu = [
        [makeBtn($bkash_status, 'primary'), makeBtn($nagad_status, 'success')],
        [makeBtn($rocket_status, 'primary'), makeBtn($binance_status, 'success')],
        [makeBtn('🔙 বটের সেটিংস', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminTaskToggleMenu($s) {
    $insta_status = ($s['status_instagram'] ?? 'open') == 'open' ? '🔴 ইনস্টা বন্ধ করুন' : '🟢 ইনস্টা চালু করুন';
    $gmail_status = ($s['status_gmail'] ?? 'open') == 'open' ? '🔴 জিমেইল বন্ধ করুন' : '🟢 জিমেইল চালু করুন';
    $fb_status = ($s['status_facebook'] ?? 'open') == 'open' ? '🔴 ফেসবুক বন্ধ করুন' : '🟢 ফেসবুক চালু করুন';
    $cookies_status = ($s['status_facebook_cookies'] ?? 'open') == 'open' ? '🔴 কুকিজ বন্ধ করুন' : '🟢 কুকিজ চালু করুন';
    $insta_cookies_status = ($s['status_instagram_cookies'] ?? 'open') == 'open' ? '🔴 ইনস্টা কুকিজ বন্ধ করুন' : '🟢 ইনস্টা কুকিজ চালু করুন';
    
    $menu = [
        [makeBtn($insta_status, 'primary'), makeBtn($gmail_status, 'success')],
        [makeBtn($fb_status, 'success'), makeBtn($cookies_status, 'primary')],
        [makeBtn($insta_cookies_status, 'primary')],
        [makeBtn('🔙 বটের সেটিংস', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminPasswordSettingsMenu() {
    $menu = [
        [makeBtn('🔐 ইনস্টা পাসওয়ার্ড', 'primary'), makeBtn('🔐 জিমেইল পাসওয়ার্ড', 'success')],
        [makeBtn('🔐 ফেসবুক পাসওয়ার্ড', 'success'), makeBtn('🔐 কুকিজ পাসওয়ার্ড', 'primary')],
        [makeBtn('🔐 ইনস্টা কুকিজ পাসওয়ার্ড', 'primary')],
        [makeBtn('🔙 বটের সেটিংস', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminRewardSettingsMenu() {
    $menu = [
        [makeBtn('💰 ইনস্টা কাজের মূল্য', 'primary'), makeBtn('💰 জিমেইল কাজের মূল্য', 'success')],
        [makeBtn('💰 ফেসবুক কাজের মূল্য', 'success'), makeBtn('💰 কুকিজ কাজের মূল্য', 'primary')],
        [makeBtn('💰 ইনস্টা কুকিজ কাজের মূল্য', 'primary')],
        [makeBtn('🔙 বটের সেটিংস', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminLimitSettingsMenu() {
    $menu = [
        [makeBtn('📱 বিকাশ লিমিট', 'primary'), makeBtn('📱 বিকাশ চার্জ', 'success')],
        [makeBtn('📱 নগদ লিমিট', 'success'), makeBtn('📱 নগদ চার্জ', 'primary')],
        [makeBtn('📱 রকেট লিমিট', 'primary'), makeBtn('📱 রকেট চার্জ', 'success')],
        [makeBtn('📱 বাইনান্স লিমিট', 'primary'), makeBtn('📱 বাইনান্স চার্জ', 'success')],
        [makeBtn('🔙 বটের সেটিংস', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function getAdminChannelSettingsMenu() {
    $menu = [
        [makeBtn('📢 ফোর্স ইউজারনেম', 'primary'), makeBtn('🔗 অফিশিয়াল লিংক', 'success')],
        [makeBtn('📞 সাপোর্ট লিংক সেট', 'primary'), makeBtn('🔙 বটের সেটিংস', 'danger')]
    ];
    return json_encode(['keyboard' => $menu, 'resize_keyboard' => true]);
}

function showAdminWithdrawList($chat_id) {
    $withdraws = readDB('withdraws.json');
    if ($withdraws === null) return;
    $inline_kb = [];
    foreach ($withdraws as $w) {
        if ($w['status'] == 'pending') {
            $method_upper = strtoupper($w['method']);
            $inline_kb[] = [
                ['text' => "📱 [{$method_upper}] ৳{$w['amount']} - User: {$w['user_id']}", 'callback_data' => "view_wd_{$w['id']}"]
            ];
        }
    }
    
    if (empty($inline_kb)) {
        sendRich($chat_id, "📥 বর্তমানে কোনো পেন্ডিং উইথড্রাল রিকোয়েস্ট নেই।");
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📥 *পেন্ডিং উইথড্রাল তালিকা:*\n\nযেকোনো উইথড্রর বিস্তারিত দেখতে এবং অ্যাকশন নিতে সেটির উপর ক্লিক করুন:",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => $inline_kb])
        ]);
    }
}

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$message = $update['message'] ?? null; 
$callback = $update['callback_query'] ?? null;
$chat_id = $message['chat']['id'] ?? $callback['message']['chat']['id'] ?? null;
$from_id = $message['from']['id'] ?? $callback['from']['id'] ?? null;
$text = $message['text'] ?? ""; 
$name = $message['from']['first_name'] ?? $callback['from']['first_name'] ?? "User";
$safe_name = htmlspecialchars($name);

if (!$chat_id) exit;

$users = readDB('users.json');
if ($users === null) exit;

$ref_by = 0; 
if (strpos($text, '/start ') === 0) { 
    $ref_by = trim(str_replace('/start ', '', $text)); 
}

$tg_username = $message['from']['username'] ?? $callback['from']['username'] ?? "";
$username_changed = false;

if (!isset($users[$from_id]) || !is_array($users[$from_id])) {
    $users[$from_id] = [
        'name' => $name, 
        'username' => $tg_username,
        'balance' => 0.00, 
        'pending_withdraw' => 0.00,
        'total_income' => 0.00,
        'completed_tasks' => 0,
        'review_tasks' => 0,
        'rejected_tasks' => 0, 
        'ref_by' => $ref_by, 
        'ref_rewarded' => false,
        'total_refs' => 0,
        'ref_income' => 0.00,
        'state' => '', 
        'temp' => ''
    ];
    $username_changed = true;
} else {
    if (!isset($users[$from_id]['username']) || $users[$from_id]['username'] !== $tg_username) {
        $users[$from_id]['username'] = $tg_username;
        $username_changed = true;
    }
}

if ($username_changed) {
    writeDB('users.json', $users);
}

$u = $users[$from_id]; 
$state = $u['state'] ?? ''; 
$temp = $u['temp'] ?? '';

$menu_commands = [
    '⚙️ এডমিন প্যানেল', '🔙 এডমিন মেনু', '⚙️ বটের সেটিংস', '🔙 বটের সেটিংস', 
    '🔙 ফিরে যান', '❌ বাতিল', '🔙 সেন্ট্রাল শিট', '📝 কাজ ▸', '💵 ব্যালেন্স', 
    '💰 টাকা উত্তোলন', '🎁 My Referrals', '🆕 আমি নতুন', '🎥 কাজের ভিডিও', '👨‍✈️ এডমিন সাপোর্ট',
    '📸 ইনস্টাগ্রাম কন্ট্রোল', '📧 জিমেইল কন্ট্রোল', '👥 ফেসবুক কন্ট্রোল', '👥 কুকিজ কন্ট্রোল', '📸 ইনস্টা কুকিজ কন্ট্রোল',
    '🧹 ডাটাবেজ ক্লিনআপ', '📢 ব্রডকাস্ট', '⚙️ কাজ চালু/বন্ধ', '⚙️ পেমেন্ট অন/অফ',
    '🟢 ইনস্টা চালু করুন', '🔴 ইনস্টা বন্ধ করুন', '🟢 জিমেইল চালু করুন', '🔴 জিমেইল বন্ধ করুন', '🟢 ফেসবুক চালু করুন', '🔴 ফেসবুক বন্ধ করুন',
    '🟢 কুকিজ চালু করুন', '🔴 কুকিজ বন্ধ করুন', '🟢 ইনস্টা কুকিজ চালু করুন', '🔴 ইনস্টা কুকিজ বন্ধ করুন',
    '🟢 বিকাশ উইথড্র চালু', '🔴 বিকাশ উইথড্র বন্ধ', '🟢 নগদ উইথড্র চালু', '🔴 নগদ উইথড্র বন্ধ', '🟢 রকেট উইথড্র চালু', '🔴 রকেট উইথড্র বন্ধ',
    '🟢 বাইনান্স উইথড্র চালু', '🔴 বাইনান্স উইথড্র বন্ধ',
    '💰 কাজের মূল্য সেট', '🔐 পাসওয়ার্ড সেটিংস', '🎥 কাজের ভিডিও সেট', '👥 কাজের পার্সেন্ট কমিশন', '📱 লিমিট ও চার্জ সেট', '📢 চ্যানেল সেটিংস',
    '💰 ইনস্টা কাজের মূল্য', '💰 জিমেইল কাজের মূল্য', '💰 ফেসবুক কাজের মূল্য', '💰 কুকিজ কাজের মূল্য', '💰 ইনস্টা কুকিজ কাজের মূল্য',
    '📱 বিকাশ লিমিট', '📱 বিকাশ চার্জ', '📱 নগদ লিমিট', '📱 নগদ চার্জ', '📱 রকেট লিমিট', '📱 রকেট চার্জ', '📱 বাইনান্স লিমিট', '📱 বাইনান্স চার্জ'
];
if (in_array($text, $menu_commands)) {
    setState($from_id, "");
    $state = "";
    $temp = "";
}

// --- ফোর্সিং চ্যানেল চেক ---
if (strval($from_id) !== strval(ADMIN_ID) && !empty($s['force_channel'])) {
    $res = bot('getChatMember', ['chat_id' => $s['force_channel'], 'user_id' => $from_id]);
    $status = $res['result']['status'] ?? 'left';
    $is_joined = in_array($status, ['member', 'administrator', 'creator']);

    if (!$is_joined) {
        if ($callback && $callback['data'] == 'check_force_join') {
            bot('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => '❌ আপনি এখনো জয়েন করেননি!', 'show_alert' => true]);
            exit;
        }
        
        $inline_kb = [
            [['text' => '📢 অফিশিয়াল চ্যানেলে জয়েন করুন', 'url' => $s['force_channel_url']]],
            [['text' => '✅ জয়েন করেছি', 'callback_data' => 'check_force_join']]
        ];
        
        $join_msg = "⚠️ <b>হ্যালো {$safe_name},</b><br><br>বটটি ব্যবহার করতে আপনাকে অবশ্যই আমাদের অফিশিয়াল চ্যানেলে জয়েন করতে হবে। জয়েন করে নিচের 'জয়েন করেছি' বাটনে ক্লিক করুন।";
            
        sendRich($chat_id, $join_msg, json_encode(['inline_keyboard' => $inline_kb]));
        exit;
    } else {
        $ref_parent = $users[$from_id]['ref_by'] ?? 0;
        $already_rewarded = $users[$from_id]['ref_rewarded'] ?? false;

        if (!$already_rewarded && strval($ref_parent) != "0" && strval($ref_parent) != strval($from_id) && isset($users[$ref_parent])) {
            $users[$ref_parent]['total_refs'] = ($users[$ref_parent]['total_refs'] ?? 0) + 1;
            
            $users[$from_id]['ref_rewarded'] = true;
            writeDB('users.json', $users);

            sendRich($ref_parent, "🎉 <b>নতুন রেফারেল সদস্য জয়েন করেছে!</b><br><br>আপনার রেফারেল লিংকের মাধ্যমে একজন নতুন সদস্য যুক্ত হয়েছেন। তার সম্পন্ন করা কাজের ওপর আপনি আজীবন কমিশন পাবেন।");
        }
    }
}

if ($callback && $callback['data'] == 'check_force_join') {
    bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $callback['message']['message_id']]);
    sendRich($chat_id, "😊 স্বাগতম! আপনি সফলভাবে জয়েন করেছেন।", getMainMenu($from_id));
    exit;
}

if (strpos($text, '/start') === 0 || $text == '🔙 ফিরে যান' || $text == '❌ বাতিল' || $text == '🔙 সেন্ট্রাল শিট') {
    setState($from_id, "");
    sendRich($chat_id, "😊 স্বাগতম! কাজ শুরু করতে নিচের অপশনগুলো ব্যবহার করুন ⬇️", getMainMenu($from_id));
    exit;
}

if ($text == '📝 কাজ ▸') {
    $reward_insta = number_format($s['reward_instagram'], 2);
    $reward_gmail = number_format($s['reward_gmail'], 2);
    $reward_fb = number_format($s['reward_facebook'], 2);
    $reward_cookies = number_format($s['reward_facebook_cookies'] ?? 8.00, 2);
    $reward_insta_cookies = number_format($s['reward_instagram_cookies'] ?? 8.00, 2);
    
    $menu = [
        [makeBtn("📸 ইনস্টা 2FA (৳{$reward_insta})", 'primary'), makeBtn("📧 জিমেইল কাজ (৳{$reward_gmail})", 'success')],
        [makeBtn("👥 ফেসবুক 2FA (৳{$reward_fb})", 'success'), makeBtn("🍪 ফেসবুক কুকিজ (৳{$reward_cookies})", 'primary')],
        [makeBtn("🍪 ইনস্টা কুকিজ (৳{$reward_insta_cookies})", 'primary')], 
        [makeBtn('❌ বাতিল', 'danger')]
    ];
    $txt = "নিচের তালিকা থেকে একটি কাজ সিলেক্ট করুন:";
    
    sendRich($chat_id, $txt, json_encode(['keyboard' => $menu, 'resize_keyboard' => true]));
    exit;
}

if (strpos($text, 'ইনস্টা 2FA') !== false || strpos($text, 'ইনস্টাগ্রাম 2FA') !== false) {
    if (($s['status_instagram'] ?? 'open') !== 'open') {
        sendRich($chat_id, "⚠️ দুঃখিত, বর্তমানে ইনস্টাগ্রামের কাজ বন্ধ আছে।");
        exit;
    }
    $gen_username = generateUsername();
    $admin_password = $s['admin_insta_password'] ?? 'Pass@insta';
    
    $menu = [
        [makeBtn('🔐 2FA Set', 'success')],
        [makeBtn('🔙 ফিরে যান', 'danger')]
    ];
    
    $task_txt = "🟢 <b>আপনার জন্য ইনস্টাগ্রাম আইডি বরাদ্ধ করা হয়েছে!</b><br><br>👤 Username: <code>{$gen_username}</code><br>🔐 Password: <code>{$admin_password}</code><br><br>📸 উপরের ইউজারনেম এবং পাসওয়ার্ড দিয়ে অ্যাকাউন্ট খুলুন এবং ২এফএ অন করুন। তারপর নিচে <b>🔐 2FA Set</b> বাটনে চাপ দিন।";
        
    $msg_res = bot('sendMessage', [
        'chat_id' => $chat_id, 
        'text' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $task_txt)),
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => $menu, 'resize_keyboard' => true])
    ]);
    $msg_id = $msg_res['result']['message_id'] ?? '';
    
    setState($from_id, 'view_task', $gen_username . "|" . $admin_password . "|" . $msg_id);
    exit;
}

if ($text == '🔐 2FA Set') {
    sendRich($chat_id, "🔑 এখন আপনার তৈরি করা অ্যাকাউন্টের সঠিক <b>2FA Key</b> টি এখানে লিখে দিন: ⤵️", json_encode(['keyboard' => [[makeBtn('❌ বাতিল', 'danger')]], 'resize_keyboard' => true]));
    setState($from_id, 'wait_2fa_key', $temp); 
    exit;
}

if ($state == 'wait_2fa_key' && $text != '❌ বাতিল') {
    if (!isValidBase32Key($text)) {
        sendRich($chat_id, "❌ ২এফএ কী (Key) ফরম্যাটটি সঠিক নয়!");
        exit;
    }
    
    $live_code = getTOTPWithOffset($text, 0);
    $next_code = getTOTPWithOffset($text, 30);
    
    if (!$live_code) {
        sendRich($chat_id, "❌ ২এফএ কী (Key) টি সঠিক নয়। পুনরায় চেষ্টা করুন:");
        exit;
    }
    
    setState($from_id, 'wait_work_otp_confirm', $temp . "|" . $text . "|" . $live_code);
    
    $msg = "🔐 <b>আপনার ২এফএ কী সফলভাবে যাচাই করা হয়েছে।</b><br><br>🔔 <b>অ্যাকাউন্ট খোলা শেষ হলে নিচের কোডগুলো ইনস্টাগ্রামে ট্রাই করুন:</b><br><br>🛡 <b>কোড ১ (বর্তমান):</b> <code>{$live_code}</code><br>⏳ <b>কোড ২ (পরবর্তী ৩০ সেকেন্ড):</b> <code>{$next_code}</code><br><br>কাজটি জমা দিতে নিচে <b>✅ কাজ শেষ</b> বাটনে ক্লিক করুন।";
        
    sendRich($chat_id, $msg, json_encode(['keyboard' => [[makeBtn('✅ কাজ শেষ', 'success')]], 'resize_keyboard' => true]));
    exit;
}

if ($state == 'wait_work_otp_confirm' && $text == '✅ কাজ শেষ') {
    $parts = explode('|', $temp);
    $username = $parts[0];
    $password = $parts[1];
    $msg_id = $parts[2];
    $saved_key = $parts[3];
    
    $proof_id = uniqid();
    $proofs = readDB('proofs.json');
    if ($proofs === null) exit;
    $proofs[] = [
        'id' => $proof_id,
        'user_id' => $from_id,
        'task_type' => 'instagram',
        'username' => $username,
        'password' => $password,
        'data' => $saved_key,
        'status' => 'pending'
    ];
    writeDB('proofs.json', $proofs);
    
    $saved_key_clean = str_replace(' ', '', $saved_key);
    $csv_file = fopen("data/accounts_master.csv", "a");
    if ($csv_file) {
        if (flock($csv_file, LOCK_EX)) {
            fputcsv($csv_file, [$username, $password, $saved_key_clean]);
            flock($csv_file, LOCK_UN);
        }
        fclose($csv_file);
    }
    
    $users[$from_id]['review_tasks'] = ($users[$from_id]['review_tasks'] ?? 0) + 1;
    writeDB('users.json', $users);
    
    if (!empty($msg_id)) {
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
    }
    
    sendRich($chat_id, "✅ আপনার কাজটি সফলভাবে জমা হয়েছে এবং রিভিউতে পাঠানো হলো।", getMainMenu($from_id));
    setState($from_id, "");
    exit;
}

if (strpos($text, 'জিমেইল কাজ') !== false || $text == '📧 জিমেইল কাজ') {
    if (($s['status_gmail'] ?? 'open') !== 'open') {
        sendRich($chat_id, "⚠️ দুঃখিত, বর্তমানে জিমেইলের কাজ বন্ধ আছে।");
        exit;
    }
    $gmail_data = generateGmailData();
    $admin_password = $s['admin_gmail_password'] ?? 'Pass@gmail';
    
    $menu = [
        [makeBtn('📤 কাজ সাবমিট করুন', 'success')],
        [makeBtn('🔙 ফিরে যান', 'danger')]
    ];
    
    $msg_txt = "📧 <b>আপনার জন্য নতুন জিমেইল কাজ বরাদ্ধ করা হয়েছে!</b><br><br>✉️ Email: <code>{$gmail_data['email']}</code><br>🔐 Password: <code>{$admin_password}</code><br><br>⚠️ <b>বিশেষ সতর্কবার্তা:</b> ফোন থেকে জিমেইল রিমুভ না করলে পেমেন্ট পাবেন না বিস্তারিত জানতে ভিডিও দেখুন ধন্যবাদ。<br><br>📌 উপরের ইমেইল এবং পাসওয়ার্ড দিয়ে একটি জিমেইল অ্যাকাউন্ট তৈরি করুন। অ্যাকাউন্ট তৈরি শেষ হলে নিচে <b>📤 কাজ সাবমিট করুন</b> বাটনে চাপ দিন।";
        
    $msg_res = bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $msg_txt)),
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => $menu, 'resize_keyboard' => true])
    ]);
    $msg_id = $msg_res['result']['message_id'] ?? '';
    
    setState($from_id, 'gmail_task_active', "|" . $gmail_data['email'] . "|" . $admin_password . "|" . $msg_id);
    exit;
}

if ($state == 'gmail_task_active' && $text == '📤 কাজ সাবমিট করুন') {
    $parts = explode('|', $temp);
    $email = $parts[1];
    $password = $parts[2];
    $msg_id = $parts[3];
    
    $proofs = readDB('proofs.json');
    if ($proofs === null) exit;
    $proofs[] = [
        'id' => uniqid(),
        'user_id' => $from_id,
        'task_type' => 'gmail',
        'email' => $email,
        'password' => $password,
        'status' => 'pending'
    ];
    writeDB('proofs.json', $proofs);
    
    $csv_file = fopen("data/gmail_master.csv", "a");
    if ($csv_file) {
        if (flock($csv_file, LOCK_EX)) {
            fputcsv($csv_file, [$email, $password]);
            flock($csv_file, LOCK_UN);
        }
        fclose($csv_file);
    }
    
    $users[$from_id]['review_tasks'] = ($users[$from_id]['review_tasks'] ?? 0) + 1;
    writeDB('users.json', $users);
    
    if (!empty($msg_id)) {
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
    }
    
    sendRich($chat_id, "✅ আপনার জিমেইল কাজটি সফলভাবে সাবমিট হয়েছে এবং রিভিউতে পাঠানো হলো।", getMainMenu($from_id));
    setState($from_id, "");
    exit;
}

if (strpos($text, 'ফেসবুক 2FA') !== false && strpos($text, 'কুকিজ') === false) {
    if (($s['status_facebook'] ?? 'open') !== 'open') {
        sendRich($chat_id, "⚠️ দুঃখিত, বর্তমানে ফেসবুকের কাজ বন্ধ আছে।");
        exit;
    }
    $reward_fb = number_format($s['reward_facebook'], 2);
    $fb_name_arr = generateFbNameArray();
    $first_name = $fb_name_arr['first'];
    $last_name = $fb_name_arr['last'];
    $admin_password = $s['admin_fb_password'] ?? 'Pass@facebook';
    
    $menu = [
        [makeBtn('📤 ফেসবুক আইডি দিন', 'success')],
        [makeBtn('🔙 ফিরে যান', 'danger')]
    ];
    
    $fb_txt = "👥 <b>ফেসবুক অ্যাকাউন্ট তৈরির কাজ (৳{$reward_fb})</b><br><br>👤 First Name: <code>{$first_name}</code><br>👤 Last Name: <code>{$last_name}</code><br>🔐 Password: <code>{$admin_password}</code><br><br>📌 উপরের ফার্স্ট নেম, লাস্ট নেম এবং পাসওয়ার্ড দিয়ে একটি ফেসবুক অ্যাকাউন্ট তৈরি করুন এবং সেটির ২এফএ চালু করুন।<br>এরপর নিচে <b>📤 ফেসবুক আইডি দিন</b> বাটনে ক্লিক করে সাবমিট করুন।";
        
    $msg_res = bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $fb_txt)),
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => $menu, 'resize_keyboard' => true])
    ]);
    $msg_id = $msg_res['result']['message_id'] ?? '';
    
    setState($from_id, 'fb_task_active', $first_name . "|" . $last_name . "|" . $admin_password . "|" . $msg_id);
    exit;
}

if ($text == '📤 ফেসবুক আইডি দিন' && $state == 'fb_task_active') {
    sendRich($chat_id, "🆔 আপনার ফেসবুক অ্যাকাউন্টের <b>UID (ইউজার আইডি)</b> টি লিখে পাঠান:", json_encode(['keyboard' => [[makeBtn('❌ বাতিল', 'danger')]], 'resize_keyboard' => true]));
    setState($from_id, 'fb_wait_uid', $temp);
    exit;
}

if ($state == 'fb_wait_uid' && $text != '❌ বাতিল') {
    if (!ctype_digit($text) || strlen($text) < 9 || strlen($text) > 16) {
        sendRich($chat_id, "❌ এটি একটি সঠিক ফেসবুক ইউআইডি (UID) নয়!<br><br>ফেসবুক ইউআইডি সাধারণত ৯ থেকে ১৬ ডিজিটের শুধুমাত্র সংখ্যা হয়ে থাকে।");
        exit;
    }
    setState($from_id, 'fb_wait_2fa', $temp . "|" . $text); 
    
    sendRich($chat_id, "🔐 আপনার ফেসবুক অ্যাকাউন্টের <b>2FA Key</b> টি লিখে পাঠান:", json_encode(['keyboard' => [[makeBtn('❌ বাতিল', 'danger')]], 'resize_keyboard' => true]));
    exit;
}

if ($state == 'fb_wait_2fa' && $text != '❌ বাতিল') {
    if (!isValidBase32Key($text)) {
        sendRich($chat_id, "❌ ফেসবুক ২এফএ কী ফরম্যাটটি সঠিক নয়। পুনরায় দিন:");
        exit;
    }
    $live_code = getTOTP($text);
    if (!$live_code) {
        sendRich($chat_id, "❌ ২এফএ কী ভুল। পুনরায় দিন:");
        exit;
    }
    
    setState($from_id, 'fb_wait_confirm', $temp . "|" . $text . "|" . $live_code);
    
    $confirm_txt = "🔐 ফেসবুক ২এফএ কী সফলভাবে যাচাই করা হয়েছে।<br><br>📌 ২এফএ লাইভ কোড: <code>{$live_code}</code><br><br>কাজটি ফাইনাল সাবমিট করতে নিচে <b>✅ সাবমিট সম্পূর্ণ করুন</b> বাটনে চাপুন।";
        
    sendRich($chat_id, $confirm_txt, json_encode(['keyboard' => [[makeBtn('✅ সাবমিট সম্পূর্ণ করুন', 'success')]], 'resize_keyboard' => true]));
    exit;
}

if ($state == 'fb_wait_confirm' && $text == '✅ সাবমিট সম্পূর্ণ করুন') {
    $parts = explode('|', $temp);
    $first_name = $parts[0];
    $last_name = $parts[1];
    $password = $parts[2];
    $msg_id = $parts[3];
    $fb_uid = $parts[4];
    $fb_2fa = $parts[5];
    
    $proofs = readDB('proofs.json');
    if ($proofs === null) exit;
    $proofs[] = [
        'id' => uniqid(),
        'user_id' => $from_id,
        'task_type' => 'facebook',
        'uid' => $fb_uid,
        'password' => $password,
        'data' => $fb_2fa,
        'status' => 'pending'
    ];
    writeDB('proofs.json', $proofs);
    
    $fb_2fa_clean = str_replace(' ', '', $fb_2fa);
    $csv_file = fopen("data/facebook_master.csv", "a");
    if ($csv_file) {
        if (flock($csv_file, LOCK_EX)) {
            fputcsv($csv_file, [$fb_uid, $password, $fb_2fa_clean]);
            flock($csv_file, LOCK_UN);
        }
        fclose($csv_file);
    }
    
    $users[$from_id]['review_tasks'] = ($users[$from_id]['review_tasks'] ?? 0) + 1;
    writeDB('users.json', $users);
    
    if (!empty($msg_id)) {
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
    }
    
    sendRich($chat_id, "✅ আপনার ফেসবুক কাজটি সফলভাবে সাবমিট হয়েছে এবং রিভিউতে পাঠানো হলো।", getMainMenu($from_id));
    setState($from_id, "");
    exit;
}

if (strpos($text, 'ফেসবুক কুকিজ') !== false) {
    if (($s['status_facebook_cookies'] ?? 'open') !== 'open') {
        sendRich($chat_id, "⚠️ দুঃখিত, বর্তমানে ফেসবুক কুকিজের কাজ বন্ধ আছে।");
        exit;
    }
    $reward_cookies = number_format($s['reward_facebook_cookies'] ?? 8.00, 2);
    $fb_name_arr = generateFbNameArray();
    $first_name = $fb_name_arr['first'];
    $last_name = $fb_name_arr['last'];
    $gen_fb_name = $first_name . " " . $last_name;
    $admin_password = $s['admin_fb_cookies_password'] ?? 'Pass@cookies';
    
    $menu = [
        [makeBtn('📤 ফেসবুক আইডি দিন', 'success')],
        [makeBtn('🔙 ফিরে যান', 'danger')]
    ];
    
    $txt = "👥 <b>ফেসবুক কুকিজ তৈরির কাজ (৳{$reward_cookies})</b><br><br>👤 First Name: <code>{$first_name}</code><br>👤 Last Name: <code>{$last_name}</code><br>🔐 Password: <code>{$admin_password}</code><br><br>📌 উপরের ফার্স্ট নেম, লাস্ট নেম এবং পাসওয়ার্ড দিয়ে অ্যাকাউন্ট তৈরি করে <b>📤 ফেসবুক আইডি দিন</b> বাটনে ক্লিক করুন।";
        
    $msg_res = bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $txt)),
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => $menu, 'resize_keyboard' => true])
    ]);
    $msg_id = $msg_res['result']['message_id'] ?? '';
    
    setState($from_id, 'fb_cookies_task_active', $gen_fb_name . "|" . $admin_password . "|" . $msg_id);
    exit;
}

if ($text == '📤 ফেসবুক আইডি দিন' && $state == 'fb_cookies_task_active') {
    sendRich($chat_id, "🆔 আপনার ফেসবুক অ্যাকাউন্টের <b>UID (ইউজার আইডি)</b> টি লিখে পাঠান:", json_encode(['keyboard' => [[makeBtn('❌ বাতিল', 'danger')]], 'resize_keyboard' => true]));
    setState($from_id, 'fb_cookies_wait_uid', $temp);
    exit;
}

if ($state == 'fb_cookies_wait_uid' && $text != '❌ বাতিল') {
    if (!ctype_digit($text) || strlen($text) < 9 || strlen($text) > 16) {
        sendRich($chat_id, "❌ এটি একটি সঠিক ফেসবুক ইউআইডি (UID) নয়!");
        exit;
    }
    setState($from_id, 'fb_cookies_wait_data', $temp . "|" . $text); 
    sendRich($chat_id, "🍪 আপনার ফেসবুক অ্যাকাউন্টের <b>Cookies</b> টি এখানে পেস্ট করে পাঠান:", json_encode(['keyboard' => [[makeBtn('❌ বাতিল', 'danger')]], 'resize_keyboard' => true]));
    exit;
}

if ($state == 'fb_cookies_wait_data' && $text != '❌ বাতিল') {
    $cookies = trim($text);
    if (empty($cookies) || strlen($cookies) < 10) {
        sendRich($chat_id, "❌ ফেসবুক কুকিজ সঠিক ফরম্যাটে দিন:");
        exit;
    }
    
    setState($from_id, 'fb_cookies_wait_confirm', $temp . "|" . $cookies);
    
    $txt = "🔐 ফেসবুক কুকিজ ডাটা record করা হয়েছে。<br><br>কাজটি ফাইনাল সাবমিট করতে নিচে <b>✅ কুকিজ সাবমিট সম্পূর্ণ করুন</b> বাটনে চাপুন।";
        
    sendRich($chat_id, $txt, json_encode(['keyboard' => [[makeBtn('✅ কুকিজ সাবমিট সম্পূর্ণ করুন', 'success')]], 'resize_keyboard' => true]));
    exit;
}

if ($state == 'fb_cookies_wait_confirm' && $text == '✅ কুকিজ সাবমিট সম্পূর্ণ করুন') {
    $parts = explode('|', $temp);
    $name = $parts[0];
    $password = $parts[1];
    $msg_id = $parts[2];
    $fb_uid = $parts[3];
    $fb_cookies = $parts[4] ?? '';
    
    $proofs = readDB('proofs.json');
    if ($proofs === null) exit;
    $proofs[] = [
        'id' => uniqid(),
        'user_id' => $from_id,
        'task_type' => 'facebook_cookies',
        'uid' => $fb_uid,
        'password' => $password,
        'cookies' => $fb_cookies,
        'status' => 'pending'
    ];
    writeDB('proofs.json', $proofs);
    
    $csv_file = fopen("data/facebook_cookies_master.csv", "a");
    if ($csv_file) {
        if (flock($csv_file, LOCK_EX)) {
            fputcsv($csv_file, [$fb_uid, $password, $fb_cookies]);
            flock($csv_file, LOCK_UN);
        }
        fclose($csv_file);
    }
    
    $users[$from_id]['review_tasks'] = ($users[$from_id]['review_tasks'] ?? 0) + 1;
    writeDB('users.json', $users);
    
    if (!empty($msg_id)) {
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
    }
    
    sendRich($chat_id, "✅ আপনার ফেসবুক কুকিজ কাজটি সফলভাবে সাবমিট হয়েছে এবং রিভিউতে পাঠানো হলো।", getMainMenu($from_id));
    setState($from_id, "");
    exit;
}

if (strpos($text, 'ইনস্টা কুকিজ') !== false && strpos($text, 'টাকা') === false && strpos($text, 'মূল্য') === false && strpos($text, 'পাসওয়ার্ড') === false && strpos($text, 'বন্ধ') === false && strpos($text, 'চালু') === false) {
    if (($s['status_instagram_cookies'] ?? 'open') !== 'open') {
        sendRich($chat_id, "⚠️ দুঃখিত, বর্তমানে ইনস্টাগ্রাম কুকিজের কাজ বন্ধ আছে।");
        exit;
    }
    $reward_insta_cookies = number_format($s['reward_instagram_cookies'] ?? 8.00, 2);
    $gen_username = generateUsername();
    $admin_password = $s['admin_insta_cookies_password'] ?? 'Pass@instacookies';
    
    $menu = [
        [makeBtn('📤 ইনস্টাগ্রাম কুকিজ দিন', 'success')],
        [makeBtn('🔙 ফিরে যান', 'danger')]
    ];
    
    $txt = "📸 <b>ইনস্টাগ্রাম কুকিজ তৈরির কাজ (৳{$reward_insta_cookies})</b><br><br>👤 Username: <code>{$gen_username}</code><br>🔐 Password: <code>{$admin_password}</code><br><br>📌 উপরের ইউজারনেম এবং পাসওয়ার্ড দিয়ে একটি অ্যাকাউন্ট তৈরি করে <b>📤 ইনস্টাগ্রাম কুকিজ দিন</b> বাটনে চাপুন।";
        
    $msg_res = bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $txt)),
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => $menu, 'resize_keyboard' => true])
    ]);
    $msg_id = $msg_res['result']['message_id'] ?? '';
    
    setState($from_id, 'insta_cookies_task_active', $gen_username . "|" . $admin_password . "|" . $msg_id);
    exit;
}

if ($text == '📤 ইনস্টাগ্রাম কুকিজ দিন' && $state == 'insta_cookies_task_active') {
    sendRich($chat_id, "🍪 আপনার ইনস্টাগ্রাম অ্যাকাউন্টের <b>Cookies</b> টি এখানে পেস্ট করে পাঠান:", json_encode(['keyboard' => [[makeBtn('❌ বাতিল', 'danger')]], 'resize_keyboard' => true]));
    setState($from_id, 'insta_cookies_wait_data', $temp);
    exit;
}

if ($state == 'insta_cookies_wait_data' && $text != '❌ বাতিল') {
    $cookies = trim($text);
    if (empty($cookies) || strlen($cookies) < 10) {
        sendRich($chat_id, "❌ ইনস্টাগ্রাম কুকিজ সঠিক ফরম্যাটে দিন:");
        exit;
    }
    
    setState($from_id, 'insta_cookies_wait_confirm', $temp . "|" . $cookies);
    
    $txt = "🔐 ইনস্টাগ্রাম কুকিজ ডাটা record করা হয়েছে。<br><br>কাজটি ফাইনাল সাবমিট করতে নিচে <b>✅ কুকিজ সাবমিট সম্পূর্ণ করুন</b> বাটনে চাপুন।";
        
    sendRich($chat_id, $txt, json_encode(['keyboard' => [[makeBtn('✅ কুকিজ সাবমিট সম্পূর্ণ করুন', 'success')]], 'resize_keyboard' => true]));
    exit;
}

if ($state == 'insta_cookies_wait_confirm' && $text == '✅ কুকিজ সাবমিট সম্পূর্ণ করুন') {
    $parts = explode('|', $temp);
    $username = $parts[0];
    $password = $parts[1];
    $msg_id = $parts[2];
    $insta_cookies = $parts[3] ?? '';
    
    $proofs = readDB('proofs.json');
    if ($proofs === null) exit;
    $proofs[] = [
        'id' => uniqid(),
        'user_id' => $from_id,
        'task_type' => 'instagram_cookies',
        'username' => $username,
        'password' => $password,
        'cookies' => $insta_cookies,
        'status' => 'pending'
    ];
    writeDB('proofs.json', $proofs);
    
    $csv_file = fopen("data/instagram_cookies_master.csv", "a");
    if ($csv_file) {
        if (flock($csv_file, LOCK_EX)) {
            fputcsv($csv_file, [$username, $password, $insta_cookies]);
            flock($csv_file, LOCK_UN);
        }
        fclose($csv_file);
    }
    
    $users[$from_id]['review_tasks'] = ($users[$from_id]['review_tasks'] ?? 0) + 1;
    writeDB('users.json', $users);
    
    if (!empty($msg_id)) {
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
    }
    
    sendRich($chat_id, "✅ আপনার ইনস্টাগ্রাম কুকিজ কাজটি সফলভাবে সাবমিট হয়েছে এবং রিভিউতে পাঠানো হলো।", getMainMenu($from_id));
    setState($from_id, "");
    exit;
}

// --- নতুন ইউজারদের জন্য টিউটোরিয়াল মেনু ---
if ($text == '🆕 আমি নতুন' || $text == '🎥 কাজের ভিডিও') {
    $inline_kb = [
        [['text' => '📷 ইনস্টাগ্রাম ক্রিয়েট ভিডিও', 'callback_data' => 'vid_cat_insta']],
        [['text' => '📧 জিমেইল ক্রিয়েট ভিডিও', 'callback_data' => 'vid_cat_gmail']],
        [['text' => '👥 ফেসবুক ক্রিয়েট ভিডিও', 'callback_data' => 'vid_cat_fb']],
        [['text' => '🍪 ফেসবুক কুকিজ ভিডিও', 'callback_data' => 'vid_cat_cookies']]
    ];
    
    $learn_msg = "🆕 <b>আপনি যেহেতু নতুন, তাই আগে কাজ শিখতে হবে।</b><br><br>"
               . "🎥 <b>নিচের ভিডিওগুলো মনোযোগ দিয়ে দেখলেই আপনি বুঝতে পারবেন কীভাবে কাজ করবেন এবং মাসে ১০K–২০K পর্যন্ত আয় করতে পারবেন</b> 🤑🚀<br><br>"
               . "⏩ <b>আগে শিখুন, তারপর আয় শুরু করুন।</b>";
               
    sendRich($chat_id, $learn_msg, json_encode(['inline_keyboard' => $inline_kb]));
    exit;
}

if ($text == '💵 ব্যালেন্স') {
    $bal = number_format($u['balance'], 2);
    $pending = number_format($u['pending_withdraw'], 2);
    $total_inc = number_format($u['total_income'], 2);
    $done = $u['completed_tasks'] ?? 0;
    $rev = $u['review_tasks'] ?? 0;
    $rejected = $u['rejected_tasks'] ?? 0; 
    
    $msg = "💲 <b>আপনার ব্যালেন্স</b><br>"
         . "━━━━━━━━━━━━━━━━━<br>"
         . "💰 <b>ব্যালেন্স:</b> {$bal} BDT<br>"
         . "🔐 <b>পেন্ডিং (উইথড্র):</b> {$pending} BDT<br>"
         . "💰 <b>Total Income:</b> {$total_inc} BDT<br>"
         . "━━━━━━━━━━━━━━━━━<br>"
         . "✅ <b>সম্পন্ন কাজ:</b> {$done} টি<br>"
         . "⏳ <b>রিভিউতে আছে:</b> {$rev} টি<br>"
         . "❌ <b>বাতিল কাজ:</b> {$rejected} টি"; 
         
    sendRich($chat_id, $msg);
    exit;
}

if ($text == '💰 টাকা উত্তোলন') {
    $menu = [];
    $any_open = false;
    
    if (($s['status_wd_bkash'] ?? 'open') == 'open') {
        $min_bkash = $s['min_wd_bkash'];
        $charge_bkash = $s['bkash_charge'];
        $label = "🔸 বিকাশ -> সর্বনিম্ন: {$min_bkash}৳(-{$charge_bkash})";
        $menu[] = [makeBtn($label, 'primary')];
        $any_open = true;
    }
    if (($s['status_wd_nagad'] ?? 'open') == 'open') {
        $min_nagad = $s['min_wd_nagad'];
        $charge_nagad = $s['nagad_charge'];
        $label = "🔸 নগদ -> সর্বনিম্ন: {$min_nagad}৳(-{$charge_nagad})";
        $menu[] = [makeBtn($label, 'success')];
        $any_open = true;
    }
    if (($s['status_wd_rocket'] ?? 'open') == 'open') {
        $min_rocket = $s['min_wd_rocket'];
        $charge_rocket = $s['rocket_charge'];
        $label = "🔸 রকেট -> সর্বনিম্ন: {$min_rocket}৳(-{$charge_rocket})";
        $menu[] = [makeBtn($label, 'primary')];
        $any_open = true;
    }
    if (($s['status_wd_binance'] ?? 'open') == 'open') {
        $min_binance = $s['min_wd_binance'];
        $charge_binance = $s['binance_charge'];
        $label = "🔸 বাইনান্স -> সর্বনিম্ন: {$min_binance}৳(-{$charge_binance})";
        $menu[] = [makeBtn($label, 'success')];
        $any_open = true;
    }
    
    if (!$any_open) {
        sendRich($chat_id, "⚠️ দুঃখিত, বর্তমানে সকল পেমেন্ট উইথড্রাল অপশন সাময়িকভাবে বন্ধ রয়েছে।");
        exit;
    }
    
    $menu[] = [makeBtn('🔙 ফিরে যান', 'danger')];
    
    sendRich($chat_id, "📥 টাকা তোলার মাধ্যম সিলেক্ট করুন:", json_encode(['keyboard' => $menu, 'resize_keyboard' => true]));
    exit;
}

// উইথড্রাল সিলেকশন চেক
if (strpos($text, '🔸 বিকাশ') === 0) {
    $min = $s['min_wd_bkash'];
    if ($u['balance'] < $min) {
        $bal_fmt = number_format($u['balance'], 2);
        sendRich($chat_id, "❌ <b>আপনার ব্যালেন্স পর্যাপ্ত নয়।</b><br>বিকাশে উইথড্র দিতে কমপক্ষে {$min} টাকা প্রয়োজন。<br>আপনার বর্তমান ব্যালেন্স: {$bal_fmt} ৳");
        exit;
    }
    sendRich($chat_id, "📱 আপনার বিকাশ পার্সোনাল মোবাইল নাম্বারটি দিন:");
    setState($from_id, 'wait_wd_number', 'bkash');
    exit;
}

if (strpos($text, '🔸 নগদ') === 0) {
    $min = $s['min_wd_nagad'];
    if ($u['balance'] < $min) {
        $bal_fmt = number_format($u['balance'], 2);
        sendRich($chat_id, "❌ <b>আপনার ব্যালেন্স পর্যাপ্ত নয়।</b><br>নগদে উইথড্র দিতে কমপক্ষে {$min} টাকা প্রয়োজন。<br>আপনার বর্তমান ব্যালেন্স: {$bal_fmt} ৳");
        exit;
    }
    sendRich($chat_id, "📱 আপনার নগদ পার্সোনাল মোবাইল নাম্বারটি দিন:");
    setState($from_id, 'wait_wd_number', 'nagad');
    exit;
}

if (strpos($text, '🔸 রকেট') === 0) {
    $min = $s['min_wd_rocket'];
    if ($u['balance'] < $min) {
        $bal_fmt = number_format($u['balance'], 2);
        sendRich($chat_id, "❌ <b>আপনার ব্যালেন্স পর্যাপ্ত নয়।</b><br>রকেটে উইথড্র দিতে কমপক্ষে {$min} টাকা প্রয়োজন。<br>আপনার বর্তমান ব্যালেন্স: {$bal_fmt} ৳");
        exit;
    }
    sendRich($chat_id, "📱 আপনার রকেট পার্সোনাল ১২ ডিজিটের মোবাইল নাম্বারটি দিন:");
    setState($from_id, 'wait_wd_number', 'rocket');
    exit;
}

if (strpos($text, '🔸 বাইনান্স') === 0) {
    $min = $s['min_wd_binance'];
    if ($u['balance'] < $min) {
        $bal_fmt = number_format($u['balance'], 2);
        sendRich($chat_id, "❌ <b>আপনার ব্যালেন্স পর্যাপ্ত নয়।</b><br>বাইনান্সে উইথড্র দিতে কমপক্ষে {$min} টাকা প্রয়োজন。<br>আপনার বর্তমান ব্যালেন্স: {$bal_fmt} ৳");
        exit;
    }
    sendRich($chat_id, "🟡 আপনার <b>Binance Pay ID</b> অথবা <b>USDT (BEP20/TRC20)</b> অ্যাড্রেসটি দিন:");
    setState($from_id, 'wait_wd_number', 'binance');
    exit;
}

if ($state == 'wait_wd_number') {
    $method = $temp; 
    setState($from_id, 'wait_wd_amount', $method . "|" . $text);
    sendRich($chat_id, "💰 উত্তোলনের পরিমাণ লিখুন (BDT):");
    exit;
}

if ($state == 'wait_wd_amount' && is_numeric($text)) {
    $parts = explode('|', $temp);
    $method = $parts[0];
    $account = $parts[1];
    $amount = floatval($text);
    $min = $s['min_wd_'.$method] ?? 100;
    
    if ($amount < $min || $amount > $u['balance']) {
        sendRich($chat_id, "❌ লিমিট বা ব্যালেন্স সমস্যা। পুনরায় চেষ্টা করুন।", getMainMenu($from_id));
        setState($from_id, "");
        exit;
    }
    
    $users[$from_id]['balance'] -= $amount;
    $users[$from_id]['pending_withdraw'] += $amount;
    writeDB('users.json', $users);
    
    $wd_id = uniqid();
    $withdraws = readDB('withdraws.json');
    if ($withdraws === null) exit;
    $withdraws[] = [
        'id' => $wd_id,
        'user_id' => $from_id,
        'method' => $method,
        'account' => $account,
        'amount' => $amount,
        'status' => 'pending'
    ];
    writeDB('withdraws.json', $withdraws);
    
    sendRich($chat_id, "⏳ উইথড্রাল রিকোয়েস্টটি সফলভাবে জমা হয়েছে। অ্যাডমিন প্যানেল থেকে চেক করে দ্রুত পেমেন্ট সম্পন্ন করা হবে।", getMainMenu($from_id));
    setState($from_id, "");
    exit;
}

if ($text == '🎁 My Referrals') {
    $total_ref = $u['total_refs'] ?? 0;
    $ref_inc = number_format($u['ref_income'] ?? 0, 2);
    $ref_comm = $s['ref_commission'] ?? 5;
    
    $msg = "🎁 <b>My Referrals</b><br>"
         . "👤 <b>Total Refer:</b> {$total_ref}<br>"
         . "💲 <b>Total Refer Income:</b> {$ref_inc} BDT<br>"
         . "🔗 <b>আপনার রেফার লিংক:</b><br>"
         . "https://t.me/{$bot_username}?start={$from_id}<br><br>"
         . "ℹ️ <b>রেফার ইনকাম সুবিধা:</b><br>"
         . "• রেফারেল সদস্যদের সম্পন্ন প্রতি কাজের আয়ের ওপর <b>{$ref_comm}%</b> লাইফটাইম কমিশন।";
         
    sendRich($chat_id, $msg);
    exit;
}

// --- এডমিন সাপোর্ট বাটন ---
if ($text == '👨‍✈️ এডমিন সাপোর্ট' || $text == '💫 সাপোর্ট') {
    $inline_kb = [
        [['text' => '✔️ 👨‍✈️ এডমিন সাপোর্ট ↗️', 'url' => $s['support_id']]],
        [['text' => '✈️ 📢 অফিসিয়াল চ্যানেল ↗️', 'url' => $s['official_channel']]]
    ];
    
    $body = "📞 <b>এডমিন সাপোর্ট ও গ্রাহক সেবা কেন্দ্র</b><br><br>আপনার যেকোনো সমস্যা বা জিজ্ঞাসার জন্য সরাসরি আমাদের এডমিন সাপোর্টে যোগাযোগ করুন।";
        
    sendRich($chat_id, $body, json_encode(['inline_keyboard' => $inline_kb]));
    exit;
}

// --- এডমিন প্যানেল কন্ট্রোল ---
if (strval($from_id) === strval(ADMIN_ID)) {

    if ($text == '⚙️ এডমিন প্যানেল' || $text == '🔙 এডমিন মেনু') {
        setState($from_id, "");
        sendRich($chat_id, "⚙️ <b>এডমিন কন্ট্রোল প্যানেল</b>", getAdminMenu());
        exit;
    }

    if ($text == '🔍 ইউজার সার্চ') {
        setState($from_id, 'wait_search_user_id');
        sendRich($chat_id, "🔍 ইউজার ডেটা দেখতে সঠিক <b>Telegram ID</b> অথবা <b>Username</b> পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 এডমিন মেনু', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }

    if ($state == 'wait_search_user_id' && $text != '🔙 এডমিন মেনু') {
        $search_query = trim($text);
        $search_query_clean = ltrim($search_query, '@');
        $found_uid = null;
        
        if (isset($users[$search_query])) {
            $found_uid = $search_query;
        } else {
            foreach ($users as $uid => $target_user) {
                if (isset($target_user['username']) && strcasecmp(trim($target_user['username']), $search_query_clean) === 0) {
                    $found_uid = $uid;
                    break;
                }
            }
        }
        
        if ($found_uid !== null) {
            $target_user = $users[$found_uid];
            $msg = "👤 <b>ইউজার তথ্য (ID: {$found_uid})</b><br><br>"
                 . "🏷️ নাম: {$target_user['name']}<br>"
                 . "💰 ব্যালেন্স: {$target_user['balance']} BDT<br>"
                 . "⏳ পেন্ডিং উইথড্র: {$target_user['pending_withdraw']} BDT<br>"
                 . "✅ সম্পন্ন কাজ: {$target_user['completed_tasks']} টি<br>"
                 . "⏳ রিভিউ কাজ: {$target_user['review_tasks']} টি";
            
            $inline_kb = [
                [
                    ['text' => '➕ ব্যালেন্স বাড়ান', 'callback_data' => "bal_add_{$found_uid}"],
                    ['text' => '➖ ব্যালেন্স কমান', 'callback_data' => "bal_sub_{$found_uid}"]
                ]
            ];
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $msg)),
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => $inline_kb])
            ]);
            setState($from_id, "");
        } else {
            sendRich($chat_id, "❌ কোনো ইউজার পাওয়া যায়নি।");
        }
        exit;
    }

    if ($text == '⚙️ বটের সেটিংস' || $text == '🔙 বটের সেটিংস') {
        setState($from_id, "");
        sendRich($chat_id, "⚙️ <b>বটের গ্লোবাল সেটিংস</b>", getAdminSettingsMenu());
        exit;
    }

    if ($text == '⚙️ পেমেন্ট অন/অফ') {
        sendRich($chat_id, "⚙️ <b>পেমেন্ট উইথড্র অন/অফ প্যানেল:</b><br><br>নিচের বাটনগুলোতে চাপ দিয়ে যেকোনো মেথড সচল অথবা বন্ধ করতে পারবেন।", getAdminPaymentToggleMenu($s));
        exit;
    }
    
    if ($text == '🟢 বিকাশ উইথড্র চালু' || $text == '🔴 বিকাশ উইথড্র বন্ধ') {
        $s['status_wd_bkash'] = ($s['status_wd_bkash'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ বিকাশ উইথড্রাল স্ট্যাটাস আপডেট করা হয়েছে।", getAdminPaymentToggleMenu($s));
        exit;
    }

    if ($text == '🟢 নগদ উইথড্র চালু' || $text == '🔴 নগদ উইথড্র বন্ধ') {
        $s['status_wd_nagad'] = ($s['status_wd_nagad'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ নগদ উইথড্রাল স্ট্যাটাস আপডেট করা হয়েছে।", getAdminPaymentToggleMenu($s));
        exit;
    }

    if ($text == '🟢 রকেট উইথড্র চালু' || $text == '🔴 রকেট উইথড্র বন্ধ') {
        $s['status_wd_rocket'] = ($s['status_wd_rocket'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ রকেট উইথড্রাল স্ট্যাটাস আপডেট করা হয়েছে।", getAdminPaymentToggleMenu($s));
        exit;
    }

    if ($text == '🟢 বাইনান্স উইথড্র চালু' || $text == '🔴 বাইনান্স উইথড্র বন্ধ') {
        $s['status_wd_binance'] = ($s['status_wd_binance'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ বাইনান্স উইথড্রাল স্ট্যাটাস আপডেট করা হয়েছে।", getAdminPaymentToggleMenu($s));
        exit;
    }

    if ($text == '⚙️ কাজ চালু/বন্ধ') {
        sendRich($chat_id, "⚙️ <b>কাজ চালু অথবা বন্ধ করার প্যানেল:</b><br><br>নিচের বাটনগুলোতে চাপ দিয়ে যেকোনো কাজ অন/অফ করতে পারবেন।", getAdminTaskToggleMenu($s));
        exit;
    }
    
    if ($text == '🟢 ইনস্টা চালু করুন' || $text == '🔴 ইনস্টা বন্ধ করুন') {
        $s['status_instagram'] = ($s['status_instagram'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রাম কাজের স্ট্যাটাস আপডেট করা হয়েছে।", getAdminTaskToggleMenu($s));
        exit;
    }

    if ($text == '🟢 জিমেইল চালু করুন' || $text == '🔴 জিমেইল বন্ধ করুন') {
        $s['status_gmail'] = ($s['status_gmail'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ জিমেইল কাজের স্ট্যাটাস আপডেট করা হয়েছে।", getAdminTaskToggleMenu($s));
        exit;
    }

    if ($text == '🟢 ফেসবুক চালু করুন' || $text == '🔴 ফেসবুক বন্ধ করুন') {
        $s['status_facebook'] = ($s['status_facebook'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুক কাজের স্ট্যাটাস আপডেট করা হয়েছে।", getAdminTaskToggleMenu($s));
        exit;
    }

    if ($text == '🟢 কুকিজ চালু করুন' || $text == '🔴 কুকিজ বন্ধ করুন') {
        $s['status_facebook_cookies'] = ($s['status_facebook_cookies'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুক কুকিজ কাজের স্ট্যাটাস আপডেট করা হয়েছে।", getAdminTaskToggleMenu($s));
        exit;
    }

    if ($text == '🟢 ইনস্টা কুকিজ চালু করুন' || $text == '🔴 ইনস্টা কুকিজ বন্ধ করুন') {
        $s['status_instagram_cookies'] = ($s['status_instagram_cookies'] ?? 'open') == 'open' ? 'closed' : 'open';
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রাম কুকিজ কাজের স্ট্যাটাস আপডেট করা হয়েছে।", getAdminTaskToggleMenu($s));
        exit;
    }

    if ($text == '🔐 পাসওয়ার্ড সেটিংস') {
        sendRich($chat_id, "🔐 <b>পাসওয়ার্ড কন্ট্রোল প্যানেল</b><br><br>কোনটির পাসওয়ার্ড পরিবর্তন করতে চান সিলেক্ট করুন:", getAdminPasswordSettingsMenu());
        exit;
    }

    if ($text == '🔐 ইনস্টা পাসওয়ার্ড') {
        setState($from_id, 'set_pass_insta');
        sendRich($chat_id, "🔐 ইনস্টাগ্রামের কমন পাসওয়ার্ড দিন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_pass_insta' && $text != '🔙 বটের সেটিংস') {
        $s['admin_insta_password'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রামের পাসওয়ার্ড আপডেট সম্পন্ন!", getAdminSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '🔐 জিমেইল পাসওয়ার্ড') {
        setState($from_id, 'set_pass_gmail');
        sendRich($chat_id, "🔐 জিমেইলের কমন পাসওয়ার্ড দিন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_pass_gmail' && $text != '🔙 বটের সেটিংস') {
        $s['admin_gmail_password'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ জিমেইলের পাসওয়ার্ড আপডেট সম্পন্ন!", getAdminSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '🔐 ফেসবুক পাসওয়ার্ড') {
        setState($from_id, 'set_pass_fb');
        sendRich($chat_id, "🔐 ফেসবুকের কমন পাসওয়ার্ড দিন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_pass_fb' && $text != '🔙 বটের সেটিংস') {
        $s['admin_fb_password'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুকের পাসওয়ার্ড আপডেট সম্পন্ন!", getAdminSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '🔐 কুকিজ পাসওয়ার্ড') {
        setState($from_id, 'set_pass_fb_cookies');
        sendRich($chat_id, "🔐 ফেসবুক কুকিজের কমন পাসওয়ার্ড দিন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_pass_fb_cookies' && $text != '🔙 বটের সেটিংস') {
        $s['admin_fb_cookies_password'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুক কুকিজের পাসওয়ার্ড আপডেট সম্পন্ন!", getAdminSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '🔐 ইনস্টা কুকিজ পাসওয়ার্ড') {
        setState($from_id, 'set_pass_insta_cookies');
        sendRich($chat_id, "🔐 ইনস্টাগ্রাম কুকিজের কমন পাসওয়ার্ড দিন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_pass_insta_cookies' && $text != '🔙 বটের সেটিংস') {
        $s['admin_insta_cookies_password'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রাম কুকিজের পাসওয়ার্ড আপডেট সম্পন্ন!", getAdminSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '🎥 কাজের ভিডিও সেট') {
        sendRich($chat_id, "🎥 <b>টিউটোরিয়াল ভিডিও ম্যানেজমেন্ট</b><br><br>আপনি কোন কাজের ভিডিও সেট বা আপডেট করতে চান সিলেক্ট করুন:", getAdminVideoSettingsMenu());
        exit;
    }

    if ($text == '📹 ইনস্টা ভিডিও সেট') {
        setState($from_id, 'wait_set_insta_video');
        sendRich($chat_id, "📸 ইনস্টাগ্রাম কাজ শেখার <b>ইউটিউব লিংক</b> অথবা সরাসরি একটি <b>ভিডিও ফাইল</b> চ্যাটে পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'wait_set_insta_video' && $text != '🔙 বটের সেটিংস' && !empty($text)) {
        $s['video_instagram'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রাম ভিডিও লিংক সফলভাবে সেট করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📹 জিমেইল ভিডিও সেট') {
        setState($from_id, 'wait_set_gmail_video');
        sendRich($chat_id, "📧 জিমেইল কাজ শেখার <b>ইউটিউব লিংক</b> অথবা সরাসরি একটি <b>ভিডিও ফাইল</b> চ্যাটে পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'wait_set_gmail_video' && $text != '🔙 বটের সেটিংস' && !empty($text)) {
        $s['video_gmail'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ জিমেইল ভিডিও লিংক সফলভাবে সেট করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📹 ফেসবুক ভিডিও সেট') {
        setState($from_id, 'wait_set_fb_video');
        sendRich($chat_id, "👥 ফেসবুক কাজ শেখার <b>ইউটিউব লিংক</b> অথবা সরাসরি একটি <b>ভিডিও ফাইল</b> চ্যাটে পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'wait_set_fb_video' && $text != '🔙 বটের সেটিংস' && !empty($text)) {
        $s['video_facebook'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুক ভিডিও লিংক সফলভাবে সেট করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📹 কুকিজ ভিডিও সেট') {
        setState($from_id, 'wait_set_cookies_video');
        sendRich($chat_id, "🍪 কুকিজ কাজ শেখার <b>ইউটিউব লিংক</b> অথবা সরাসরি একটি <b>ভিডিও ফাইল</b> চ্যাটে পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'wait_set_cookies_video' && $text != '🔙 বটের সেটিংস' && !empty($text)) {
        $s['video_fb_cookies'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ কুকিজ কাজের ভিডিও লিংক সফলভাবে সেট করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '👥 কাজের পার্সেন্ট কমিশন') {
        setState($from_id, 'set_ref_comm_pct');
        sendRich($chat_id, "👥 রেফারেল মেম্বারের সম্পন্ন কাজের ওপর রেফারার কত পার্সেন্ট (%) কমিশন পাবে লিখুন (যেমন: 5):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_ref_comm_pct' && is_numeric($text)) {
        $s['ref_commission'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ কাজের রেফারাল কমিশন পার্সেন্টেজ সফলভাবে আপডেট সম্পন্ন!", getAdminSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 লিমিট ও চার্জ সেট') {
        sendRich($chat_id, "📱 <b>উইথড্র লিমিট এবং চার্জ সেটিংস</b>", getAdminLimitSettingsMenu());
        exit;
    }

    if ($text == '📱 বিকাশ লিমিট') {
        setState($from_id, 'set_limit_bkash');
        sendRich($chat_id, "💰 বিকাশ সর্বনিম্ন উইথড্র লিমিট (BDT) লিখুন (যেমন: 100):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_limit_bkash' && is_numeric($text)) {
        $s['min_wd_bkash'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ বিকাশ সর্বনিম্ন উইথড্র লিমিট আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 বিকাশ চার্জ') {
        setState($from_id, 'set_charge_bkash');
        sendRich($chat_id, "💰 বিকাশে প্রতি উইথড্রতে কত টাকা চার্জ হিসেবে কাটবে লিখুন (যেমন: 5):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_charge_bkash' && is_numeric($text)) {
        $s['bkash_charge'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ বিকাশ উইথড্র চার্জ আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 নগদ লিমিট') {
        setState($from_id, 'set_limit_nagad');
        sendRich($chat_id, "💰 নগদ সর্বনিম্ন উইথড্র লিমিট (BDT) লিখুন (যেমন: 100):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_limit_nagad' && is_numeric($text)) {
        $s['min_wd_nagad'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ নগদ সর্বনিম্ন উইথড্র লিমিট আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 নগদ চার্জ') {
        setState($from_id, 'set_charge_nagad');
        sendRich($chat_id, "💰 নগদে প্রতি উইথড্রতে কত টাকা চার্জ হিসেবে কাটবে লিখুন (যেমন: 5):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_charge_nagad' && is_numeric($text)) {
        $s['nagad_charge'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ নগদ উইথড্র চার্জ আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 রকেট লিমিট') {
        setState($from_id, 'set_limit_rocket');
        sendRich($chat_id, "💰 রকেট সর্বনিম্ন উইথড্র লিমিট (BDT) লিখুন (যেমন: 100):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_limit_rocket' && is_numeric($text)) {
        $s['min_wd_rocket'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ রকেট সর্বনিম্ন উইথড্র লিমিট আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 রকেট চার্জ') {
        setState($from_id, 'set_charge_rocket');
        sendRich($chat_id, "💰 রকেটে প্রতি উইথড্রতে কত টাকা চার্জ হিসেবে কাটবে লিখুন (যেমন: 5):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_charge_rocket' && is_numeric($text)) {
        $s['rocket_charge'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ রকেট উইথড্র চার্জ আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 বাইনান্স লিমিট') {
        setState($from_id, 'set_limit_binance');
        sendRich($chat_id, "💰 বাইনান্স সর্বনিম্ন উইথড্র লিমিট (BDT) লিখুন (যেমন: 100):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_limit_binance' && is_numeric($text)) {
        $s['min_wd_binance'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ বাইনান্স সর্বনিম্ন উইথড্র লিমিট আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📱 বাইনান্স চার্জ') {
        setState($from_id, 'set_charge_binance');
        sendRich($chat_id, "💰 বাইনান্সে প্রতি উইথড্রতে কত টাকা চার্জ হিসেবে কাটবে লিখুন (যেমন: 0 বা 5):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_charge_binance' && is_numeric($text)) {
        $s['binance_charge'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ বাইনান্স উইথড্র চার্জ আপডেট সম্পন্ন!", getAdminLimitSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📢 চ্যানেল সেটিংস') {
        sendRich($chat_id, "📢 <b>চ্যানেল এবং সাপোর্ট লিংক সেটিংস</b>", getAdminChannelSettingsMenu());
        exit;
    }

    if ($text == '📢 ফোর্স ইউজারনেম') {
        setState($from_id, 'set_f_channel');
        sendRich($chat_id, "📢 বাধ্যতামূলক চ্যানেল ইউজারনেমটি দিন (@ চিহ্নে সহ):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_f_channel' && $text != '🔙 বটের সেটিংস') {
        $s['force_channel'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফোর্স চ্যানেল ইউজারনেম আপডেট সম্পন্ন!", getAdminChannelSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '🔗 অফিশিয়াল লিংক') {
        setState($from_id, 'set_f_channel_url');
        sendRich($chat_id, "🔗 অফিশিয়াল চ্যানেল লিংক (URL) দিন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_f_channel_url' && $text != '🔙 বটের সেটিংস') {
        $s['force_channel_url'] = $text;
        $s['official_channel'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ অফিশিয়াল চ্যানেল ইউআরএল আপডেট সম্পন্ন!", getAdminChannelSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📞 সাপোর্ট লিংক সেট') {
        setState($from_id, 'set_support_id_link');
        sendRich($chat_id, "📞 আপনার নতুন সাপোর্ট লিংকটি (URL) দিন (যেমন: https://t.me/YourUsername):", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_support_id_link' && $text != '🔙 বটের সেটিংস') {
        $s['support_id'] = $text;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ এডমিন সাপোর্ট লিংক সফলভাবে আপডেট সম্পন্ন হয়েছে!", getAdminChannelSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📊 সেন্ট্রাল শিট') {
        sendRich($chat_id, "📊 <b>সেন্ট্রাল শিট ম্যানেজমেন্ট</b><br><br>নিচের কোন প্ল্যাটফর্মের কাজ প্রসেস বা ডাউনলোড করতে চান সিলেক্ট করুন:", getAdminCentralSheetMenu());
        exit;
    }

    if ($text == '📸 ইনস্টাগ্রাম কন্ট্রোল') {
        sendRich($chat_id, "📸 <b>ইনস্টাগ্রাম শিট কন্ট্রোল প্যানেল</b>", getInstaControlMenu());
        exit;
    }

    if ($text == '📧 জিমেইল কন্ট্রোল') {
        sendRich($chat_id, "📧 <b>জিমেইল শিট কন্ট্রোল প্যানেল</b>", getGmailControlMenu());
        exit;
    }

    if ($text == '👥 ফেসবুক কন্ট্রোল') {
        sendRich($chat_id, "👥 <b>ফেসবুক শিট কন্ট্রোল প্যানেল</b>", getFacebookControlMenu());
        exit;
    }

    if ($text == '👥 কুকিজ কন্ট্রোল') {
        sendRich($chat_id, "👥 <b>ফেসবুক কুকিজ শিট কন্ট্রোল প্যানেল</b>", getCookiesControlMenu());
        exit;
    }

    if ($text == '📸 ইনস্টা কুকিজ কন্ট্রোল') {
        sendRich($chat_id, "📸 <b>ইনস্টাগ্রাম কুকিজ শিট কন্ট্রোল প্যানেল</b>", getInstaCookiesControlMenu());
        exit;
    }

    if ($text == '📥 ইনস্টাগ্রাম শিট ডাউনলোড') {
        $path = "data/accounts_master.csv";
        if (file_exists($path)) {
            bot('sendDocument', ['chat_id' => $chat_id, 'document' => new CURLFile($path), 'caption' => "📥 ইনস্টাগ্রাম পেন্ডিং শিট ডাউনলোড সম্পন্ন!"]);
            
            $file = fopen($path, "w");
            if ($file) {
                if (flock($file, LOCK_EX)) {
                    fputcsv($file, ["Username", "Password", "2FA Key"]);
                    flock($file, LOCK_UN);
                }
                fclose($file);
            }
        } else {
            sendRich($chat_id, "📥 কোনো পেন্ডিং ইনস্টাগ্রাম ডাটা নেই।");
        }
        exit;
    }

    if ($text == '📥 জিমেইল শিট ডাউনলোড') {
        $path = "data/gmail_master.csv";
        if (file_exists($path)) {
            bot('sendDocument', ['chat_id' => $chat_id, 'document' => new CURLFile($path), 'caption' => "📥 জিমেইল পেন্ডিং শিট ডাউনলোড সম্পন্ন!"]);
            
            $file = fopen($path, "w");
            if ($file) {
                if (flock($file, LOCK_EX)) {
                    fputcsv($file, ["Email", "Password"]);
                    flock($file, LOCK_UN);
                }
                fclose($file);
            }
        } else {
            sendRich($chat_id, "📥 কোনো পেন্ডিং জিমেইল ডাটা নেই।");
        }
        exit;
    }

    if ($text == '📥 ফেসবুক শিট ডাউনলোড') {
        $path = "data/facebook_master.csv";
        if (file_exists($path)) {
            bot('sendDocument', ['chat_id' => $chat_id, 'document' => new CURLFile($path), 'caption' => "📥 ফেসবুক পেন্ডিং শিট ডাউনলোড সম্পন্ন!"]);
            
            $file = fopen($path, "w");
            if ($file) {
                if (flock($file, LOCK_EX)) {
                    fputcsv($file, ["UID", "Password", "2FA Key"]);
                    flock($file, LOCK_UN);
                }
                fclose($file);
            }
        } else {
            sendRich($chat_id, "📥 কোনো পেন্ডিং ফেসবুক ডাটা নেই।");
        }
        exit;
    }

    if ($text == '📥 কুকিজ শিট ডাউনলোড') {
        $path = "data/facebook_cookies_master.csv";
        if (file_exists($path)) {
            bot('sendDocument', ['chat_id' => $chat_id, 'document' => new CURLFile($path), 'caption' => "📥 ফেসবুক কুকিজ পেন্ডিং শিট ডাউনলোড সম্পন্ন!"]);
            
            $file = fopen($path, "w");
            if ($file) {
                if (flock($file, LOCK_EX)) {
                    fputcsv($file, ["UID", "Password", "Cookies"]);
                    flock($file, LOCK_UN);
                }
                fclose($file);
            }
        } else {
            sendRich($chat_id, "📥 কোনো পেন্ডিং ফেসবুক কুকিজ ডাটা নেই।");
        }
        exit;
    }

    if ($text == '📥 ইনস্টা কুকিজ শিট ডাউনলোড' || $text == '📥 ইনস্টাগ্রাম কুকিজ শিট ডাউনলোড') {
        $path = "data/instagram_cookies_master.csv";
        if (file_exists($path)) {
            bot('sendDocument', ['chat_id' => $chat_id, 'document' => new CURLFile($path), 'caption' => "📥 ইনস্টাগ্রাম কুকিজ পেন্ডিং শিট ডাউনলোড সম্পন্ন!"]);
            
            $file = fopen($path, "w");
            if ($file) {
                if (flock($file, LOCK_EX)) {
                    fputcsv($file, ["Username", "Password", "Cookies"]);
                    flock($file, LOCK_UN);
                }
                fclose($file);
            }
        } else {
            sendRich($chat_id, "📥 কোনো পেন্ডিং ইনস্টাগ্রাম কুকিজ ডাটা নেই।");
        }
        exit;
    }

    if ($text == '🧹 ডাটাবেজ ক্লিনআপ') {
        $proofs = readDB('proofs.json');
        if ($proofs === null) exit;
        $pending_only = [];
        $cleared_count = 0;
        
        foreach ($proofs as $p) {
            if ($p['status'] == 'pending') {
                $pending_only[] = $p;
            } else {
                $cleared_count++;
            }
        }
        
        writeDB('proofs.json', $pending_only);
        
        sendRich($chat_id, "🧹 <b>ডাটাবেজ ক্লিনআপ সম্পন্ন!</b><br><br>ডাটাবেজ (<code>proofs.json</code>) থেকে মোট <b>{$cleared_count}</b> টি পুরনো সম্পন্ন হওয়া কাজ (Approved/Rejected) স্থায়ীভাবে ডিলেট করা হয়েছে。<br><br>বর্তমানে শুধুমাত্র পেন্ডিং কাজগুলো ডাটাবেজে সুরক্ষিত রয়েছে। বটের ডাটাবেজ এখন সম্পূর্ণ ফ্রেশ ও গতিশীল!", getAdminCentralSheetMenu());
        exit;
    }

    if ($text == '📢 ব্রডকাস্ট') {
        setState($from_id, 'wait_broadcast_msg');
        sendRich($chat_id, "📢 <b>ব্রডকাস্ট প্যানেল</b><br><br>আপনি সকল ইউজারের কাছে যে মেসেজটি পাঠাতে চান তা এখানে পাঠান。<br><br>আপনি যেকোনো ফরম্যাটে পাঠাতে পারেন: <b>টেক্সট, ফটো, ভিডিও, ফাইল, ভয়েস, অডিও, অ্যানিমেশন (GIF)</b> ইত্যাদি।", json_encode(['keyboard' => [[makeBtn('🔙 এডমিন মেনু', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }

    if ($text == '✅ ইনস্টা ভালো আইডি') {
        setState($from_id, 'wait_insta_approved_csv');
        sendRich($chat_id, "📸 বায়ারের চেক করা এপ্রুভড (Approved) <b>ইনস্টাগ্রাম .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($text == '❌ ইনস্টা খারাপ আইডি') {
        setState($from_id, 'wait_insta_rejected_csv');
        sendRich($chat_id, "📸 বায়ারের চেক করা রিজেক্টেড (Rejected) <b>ইনস্টাগ্রাম .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }

    if ($text == '✅ জিমেইল ভালো আইডি') {
        setState($from_id, 'wait_gmail_approved_csv');
        sendRich($chat_id, "📧 বায়ারের চেক করা এপ্রুভড (Approved) <b>জিমেইল .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($text == '❌ জিমেইল খারাপ আইডি') {
        setState($from_id, 'wait_gmail_rejected_csv');
        sendRich($chat_id, "📧 বায়ারের চেক করা রিজেক্টেড (Rejected) <b>জিমেইল .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }

    if ($text == '✅ ফেসবুক ভালো আইডি') {
        setState($from_id, 'wait_fb_approved_csv');
        sendRich($chat_id, "👥 বায়ারের চেক করা এপ্রুভড (Approved) <b>ফেসবুক .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($text == '❌ ফেসবুক খারাপ আইডি') {
        setState($from_id, 'wait_fb_rejected_csv');
        sendRich($chat_id, "👥 বায়ারের চেক করা রিজেক্টেড (Rejected) <b>ফেসবুক .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }

    if ($text == '✅ কুকিজ ভালো আইডি') {
        setState($from_id, 'wait_cookies_approved_csv');
        sendRich($chat_id, "👥 বায়ারের চেক করা এপ্রুভড (Approved) <b>ফেসবুক কুকিজ .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($text == '❌ কুকিজ খারাপ আইডি') {
        setState($from_id, 'wait_cookies_rejected_csv');
        sendRich($chat_id, "👥 বায়ারের চেক করা রিজেক্টেড (Rejected) <b>ফেসবুক কুকিজ .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }

    if ($text == '✅ ইনস্টা কুকিজ ভালো আইডি') {
        setState($from_id, 'wait_insta_cookies_approved_csv');
        sendRich($chat_id, "📸 বায়ারের চেক করা এপ্রুভড (Approved) <b>ইনস্টাগ্রাম কুকিজ .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($text == '❌ ইনস্টা কুকিজ খারাপ আইডি') {
        setState($from_id, 'wait_insta_cookies_rejected_csv');
        sendRich($chat_id, "📸 বায়ারের চেক করা রিজেক্টেড (Rejected) <b>ইনস্টাগ্রাম কুকিজ .csv</b> ফাইলটি পাঠান:", json_encode(['keyboard' => [[makeBtn('🔙 সেন্ট্রাল শিট', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }

    if ($text == '💰 কাজের মূল্য সেট') {
        sendRich($chat_id, "⚙️ প্রতিটি কাজের মূল্য পরিবর্তনের অপশন:", getAdminRewardSettingsMenu());
        exit;
    }

    if ($text == '💰 ইনস্টা কাজের মূল্য') {
        setState($from_id, 'set_val_insta');
        sendRich($chat_id, "💰 প্রতি ইনস্টা কাজের জন্য ইউজার কত পাবে লিখুন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_val_insta' && is_numeric($text)) {
        $s['reward_instagram'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রাম কাজের রেট আপডেট সম্পন্ন!", getAdminRewardSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '💰 জিমেইল কাজের মূল্য') {
        setState($from_id, 'set_val_gmail');
        sendRich($chat_id, "💰 প্রতি জিমেইল কাজের জন্য ইউজার কত পাবে লিখুন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_val_gmail' && is_numeric($text)) {
        $s['reward_gmail'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ জিমেইল কাজের রেট আপডেট সম্পন্ন!", getAdminRewardSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '💰 ফেসবুক কাজের মূল্য') {
        setState($from_id, 'set_val_fb');
        sendRich($chat_id, "💰 প্রতি ফেসবুক কাজের জন্য ইউজার কত পাবে লিখুন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_val_fb' && is_numeric($text)) {
        $s['reward_facebook'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুক কাজের রেট আপডেট সম্পন্ন!", getAdminRewardSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '💰 কুকিজ কাজের মূল্য') {
        setState($from_id, 'set_val_fb_cookies');
        sendRich($chat_id, "💰 প্রতি ফেসবুক কুকিজ কাজের জন্য ইউজার কত পাবে লিখুন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_val_fb_cookies' && is_numeric($text)) {
        $s['reward_facebook_cookies'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুক কুকিজ কাজের রেট আপডেট সম্পন্ন!", getAdminRewardSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '💰 ইনস্টা কুকিজ কাজের মূল্য') {
        setState($from_id, 'set_val_insta_cookies');
        sendRich($chat_id, "💰 প্রতি ইনস্টাগ্রাম কুকিজ কাজের জন্য ইউজার কত পাবে লিখুন:", json_encode(['keyboard' => [[makeBtn('🔙 বটের সেটিংস', 'danger')]], 'resize_keyboard' => true]));
        exit;
    }
    if ($state == 'set_val_insta_cookies' && is_numeric($text)) {
        $s['reward_instagram_cookies'] = floatval($text);
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রাম কুকিজ কাজের রেট আপডেট সম্পন্ন!", getAdminRewardSettingsMenu());
        setState($from_id, "");
        exit;
    }

    if ($text == '📥 পেন্ডিং উইথড্র') {
        showAdminWithdrawList($chat_id);
        exit;
    }
}

if (strval($from_id) === strval(ADMIN_ID) && $state == 'wait_broadcast_msg' && $text != '🔙 এডমিন মেনু') {
    setState($from_id, "");

    $all_users = readDB('users.json');
    if ($all_users === null) exit;
    $total_sent = 0;
    $total_failed = 0;
    
    $status_msg = bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "⏳ ব্রডকাস্ট পাঠানো শুরু হয়েছে... দয়া করে অপেক্ষা করুন।"
    ]);
    $status_msg_id = $status_msg['result']['message_id'] ?? null;
    
    $unique_uids = array_unique(array_keys($all_users));

    foreach ($unique_uids as $uid) {
        $res = bot('copyMessage', [
            'chat_id' => $uid,
            'from_chat_id' => $chat_id,
            'message_id' => $message['message_id']
        ]);
        
        if (isset($res['ok']) && $res['ok'] == true) {
            $total_sent++;
        } else {
            $total_failed++;
        }
    }
    
    if ($status_msg_id) {
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $status_msg_id]);
    }
    
    sendRich($chat_id, "📢 <b>ব্রডকাস্ট সম্পন্ন!</b><br><br>✅ সফলভাবে পাঠানো হয়েছে: <b>{$total_sent}</b> জন ইউজারকে。<br>❌ ব্যর্থ হয়েছে: <b>{$total_failed}</b> জনের কাছে (বট ব্লক করার কারণে)।", getAdminMenu());
    exit;
}

$document = $message['document'] ?? null;
if ($document && strval($from_id) === strval(ADMIN_ID)) {
    $file_id = $document['file_id'];
    $file_info = bot('getFile', ['file_id' => $file_id]);
    $file_path = $file_info['result']['file_path'] ?? null;
    
    if ($file_path) {
        $file_url = "https://api.telegram.org/file/bot" . API_KEY . "/" . $file_path;
        $csv_data = file_get_contents($file_url);
        
        $bom = pack('H*','EFBBBF');
        $csv_data = preg_replace("/^$bom/", '', $csv_data);
        $lines = explode("\n", str_replace("\r", "", $csv_data));
        
        $processed_count = 0;
        
        // 1. ইনস্টা এপ্রুভড
        if ($state == 'wait_insta_approved_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'instagram') {
                        $username = trim($p['username']);
                        if (rowContainsValue($row, $username)) {
                            $proofs[$k]['status'] = 'approved';
                            
                            $matched_user_id = $p['user_id'];
                            $reward = floatval($s['reward_instagram']);
                            $users[$matched_user_id]['balance'] += $reward;
                            $users[$matched_user_id]['total_income'] += $reward;
                            $users[$matched_user_id]['completed_tasks'] += 1;
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            
                            $accounts_to_remove[] = ['id' => $username, 'two_fa' => $p['data']];
                            
                            $referrer = $users[$matched_user_id]['ref_by'] ?? 0;
                            if (strval($referrer) != "0" && isset($users[$referrer])) {
                                $comm_pct = floatval($s['ref_commission'] ?? 5);
                                $comm = $reward * ($comm_pct / 100);
                                $users[$referrer]['balance'] += $comm;
                                $users[$referrer]['ref_income'] += $comm;
                                sendRich($referrer, "👥 <b>রেফার কমিশন!</b><br>আপনার রেফারেল সদস্যের ইনস্টাগ্রাম কাজ এপ্রুভ হওয়ায় আপনি ৳<code>{$comm}</code> কমিশন পেয়েছেন।");
                            }
                            
                            sendRich($matched_user_id, "✅ কাজ এপ্রুভ! আপনার ইনস্টাগ্রাম অ্যাকাউন্টের ({$username}) জন্য ৳{$reward} যোগ করা হয়েছে।");
                            usleep(30000); 
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'accounts_master.csv');
            sendRich($chat_id, "✅ ইনস্টাগ্রাম ভালো আইডি প্রসেস সম্পন্ন! মোট এপ্রুভড: <b>{$processed_count}</b> টি কাজ।", getInstaControlMenu());
            exit;
        }

        // 2. ইনস্টা রিজেক্টেড
        if ($state == 'wait_insta_rejected_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'instagram') {
                        $username = trim($p['username']);
                        if (rowContainsValue($row, $username)) {
                            $proofs[$k]['status'] = 'rejected';
                            $matched_user_id = $p['user_id'];
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            $users[$matched_user_id]['rejected_tasks'] = ($users[$matched_user_id]['rejected_tasks'] ?? 0) + 1; 
                            
                            $accounts_to_remove[] = ['id' => $username, 'two_fa' => $p['data']];
                            sendRich($matched_user_id, "❌ ইনস্টাগ্রাম কাজ বাতিল! ভুল তথ্যের জন্য আপনার {$username} অ্যাকাউন্টটি বাতিল করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'accounts_master.csv');
            sendRich($chat_id, "❌ ইনস্টাগ্রাম খারাপ আইডি প্রসেস সম্পন্ন! মোট রিজেক্টেড: <b>{$processed_count}</b> টি কাজ।", getInstaControlMenu());
            exit;
        }

        // 3. জিমেইল এপ্রুভড
        if ($state == 'wait_gmail_approved_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'gmail') {
                        $email = trim($p['email']);
                        if (rowContainsValue($row, $email)) {
                            $proofs[$k]['status'] = 'approved';
                            
                            $matched_user_id = $p['user_id'];
                            $reward = floatval($s['reward_gmail']);
                            $users[$matched_user_id]['balance'] += $reward;
                            $users[$matched_user_id]['total_income'] += $reward;
                            $users[$matched_user_id]['completed_tasks'] += 1;
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            
                            $accounts_to_remove[] = ['email' => $email];
                            
                            $referrer = $users[$matched_user_id]['ref_by'] ?? 0;
                            if (strval($referrer) != "0" && isset($users[$referrer])) {
                                $comm_pct = floatval($s['ref_commission'] ?? 5);
                                $comm = $reward * ($comm_pct / 100);
                                $users[$referrer]['balance'] += $comm;
                                $users[$referrer]['ref_income'] += $comm;
                                sendRich($referrer, "👥 <b>রেফার কমিশন!</b><br>আপনার রেফারেল সদস্যের জিমেইল কাজ এপ্রুভ হওয়ায় আপনি ৳<code>{$comm}</code> কমিশন পেয়েছেন।");
                            }
                            
                            sendRich($matched_user_id, "✅ কাজ এপ্রুভ! আপনার জিমেইল অ্যাকাউন্টের ({$email}) জন্য ৳{$reward} যোগ করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'gmail_master.csv');
            sendRich($chat_id, "✅ জিমেইল ভালো আইডি প্রসেস সম্পন্ন! মোট এপ্রুভড: <b>{$processed_count}</b> টি কাজ।", getGmailControlMenu());
            exit;
        }

        // 4. জিমেইল রিজেক্টেড
        if ($state == 'wait_gmail_rejected_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'gmail') {
                        $email = trim($p['email']);
                        if (rowContainsValue($row, $email)) {
                            $proofs[$k]['status'] = 'rejected';
                            
                            $matched_user_id = $p['user_id'];
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            $users[$matched_user_id]['rejected_tasks'] = ($users[$matched_user_id]['rejected_tasks'] ?? 0) + 1; 
                            
                            $accounts_to_remove[] = ['email' => $email];
                            sendRich($matched_user_id, "❌ জিমেইল কাজ বাতিল! ভুল তথ্যের জন্য আপনার {$email} অ্যাকাউন্টটি বাতিল করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'gmail_master.csv');
            sendRich($chat_id, "❌ জিমেইল খারাপ আইডি প্রসেস সম্পন্ন! মোট রিজেক্টেড: <b>{$processed_count}</b> টি কাজ।", getGmailControlMenu());
            exit;
        }

        // 5. ফেসবুক এপ্রুভড
        if ($state == 'wait_fb_approved_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'facebook') {
                        $uid = trim($p['uid']);
                        if (rowContainsValue($row, $uid)) {
                            $proofs[$k]['status'] = 'approved';
                            
                            $matched_user_id = $p['user_id'];
                            $reward = floatval($s['reward_facebook']);
                            $users[$matched_user_id]['balance'] += $reward;
                            $users[$matched_user_id]['total_income'] += $reward;
                            $users[$matched_user_id]['completed_tasks'] += 1;
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            
                            $accounts_to_remove[] = ['id' => $uid, 'two_fa' => $p['data']];
                            
                            $referrer = $users[$matched_user_id]['ref_by'] ?? 0;
                            if (strval($referrer) != "0" && isset($users[$referrer])) {
                                $comm_pct = floatval($s['ref_commission'] ?? 5);
                                $comm = $reward * ($comm_pct / 100);
                                $users[$referrer]['balance'] += $comm;
                                $users[$referrer]['ref_income'] += $comm;
                                sendRich($referrer, "👥 <b>রেফার কমিশন!</b><br>আপনার রেফারেল সদস্যের ফেসবুক কাজ এপ্রুভ হওয়ায় আপনি ৳<code>{$comm}</code> কমিশন পেয়েছেন।");
                            }
                            
                            sendRich($matched_user_id, "✅ কাজ এপ্রুভ! আপনার ফেসবুক অ্যাকাউন্টের (UID: {$uid}) জন্য ৳{$reward} যোগ করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'facebook_master.csv');
            sendRich($chat_id, "✅ ফেসবুক ভালো আইডি প্রসেস সম্পন্ন! মোট এপ্রুভড: <b>{$processed_count}</b> টি কাজ।", getFacebookControlMenu());
            exit;
        }

        // 6. ফেসবুক রিজেক্টেড
        if ($state == 'wait_fb_rejected_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'facebook') {
                        $uid = trim($p['uid']);
                        if (rowContainsValue($row, $uid)) {
                            $proofs[$k]['status'] = 'rejected';
                            
                            $matched_user_id = $p['user_id'];
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            $users[$matched_user_id]['rejected_tasks'] = ($users[$matched_user_id]['rejected_tasks'] ?? 0) + 1; 
                            
                            $accounts_to_remove[] = ['id' => $uid, 'two_fa' => $p['data']];
                            sendRich($matched_user_id, "❌ ফেসবুক কাজ বাতিল! ভুল তথ্যের জন্য আপনার ফেসবুক অ্যাকাউন্টটি (UID: {$uid}) বাতিল করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'facebook_master.csv');
            sendRich($chat_id, "❌ ফেসবুক খারাপ আইডি প্রসেস সম্পন্ন! মোট রিজেক্টেড: <b>{$processed_count}</b> টি কাজ।", getFacebookControlMenu());
            exit;
        }

        // 7. ফেসবুক কুকিজ এপ্রুভড
        if ($state == 'wait_cookies_approved_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'facebook_cookies') {
                        $uid = trim($p['uid']);
                        if (rowContainsValue($row, $uid)) {
                            $proofs[$k]['status'] = 'approved';
                            
                            $matched_user_id = $p['user_id'];
                            $reward = floatval($s['reward_facebook_cookies'] ?? 8.00);
                            $users[$matched_user_id]['balance'] += $reward;
                            $users[$matched_user_id]['total_income'] += $reward;
                            $users[$matched_user_id]['completed_tasks'] += 1;
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            
                            $accounts_to_remove[] = ['uid' => $uid];
                            
                            $referrer = $users[$matched_user_id]['ref_by'] ?? 0;
                            if (strval($referrer) != "0" && isset($users[$referrer])) {
                                $comm_pct = floatval($s['ref_commission'] ?? 5);
                                $comm = $reward * ($comm_pct / 100);
                                $users[$referrer]['balance'] += $comm;
                                $users[$referrer]['ref_income'] += $comm;
                                sendRich($referrer, "👥 <b>রেফার কমিশন!</b><br>আপনার রেফারেল সদস্যের ফেসবুক কুকিজ কাজ এপ্রুভ হওয়ায় আপনি ৳<code>{$comm}</code> কমিশন পেয়েছেন।");
                            }
                            
                            sendRich($matched_user_id, "✅ কাজ এপ্রুভ! আপনার ফেসবুক কুকিজ অ্যাকাউন্টের (UID: {$uid}) জন্য ৳{$reward} যোগ করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'facebook_cookies_master.csv');
            sendRich($chat_id, "✅ ফেসবুক কুকিজ ভালো আইডি প্রসেস সম্পন্ন! মোট এপ্রুভড: <b>{$processed_count}</b> টি কাজ।", getCookiesControlMenu());
            exit;
        }

        // 8. ফেসবুক কুকিজ রিজেক্টেড
        if ($state == 'wait_cookies_rejected_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'facebook_cookies') {
                        $uid = trim($p['uid']);
                        if (rowContainsValue($row, $uid)) {
                            $proofs[$k]['status'] = 'rejected';
                            
                            $matched_user_id = $p['user_id'];
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            $users[$matched_user_id]['rejected_tasks'] = ($users[$matched_user_id]['rejected_tasks'] ?? 0) + 1; 
                            
                            $accounts_to_remove[] = ['uid' => $uid];
                            sendRich($matched_user_id, "❌ ফেসবুক কুকিজ কাজ বাতিল! ভুল তথ্যের জন্য আপনার ফেসবুক কুকিজ অ্যাকাউন্টটি (UID: {$uid}) বাতিল করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'facebook_cookies_master.csv');
            sendRich($chat_id, "❌ ফেসবুক কুকিজ খারাপ আইডি প্রসেস সম্পন্ন! মোট রিজেক্টেড: <b>{$processed_count}</b> টি কাজ।", getCookiesControlMenu());
            exit;
        }

        // 9. ইনস্টা কুকিজ এপ্রুভড
        if ($state == 'wait_insta_cookies_approved_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'instagram_cookies') {
                        $username = trim($p['username']);
                        if (rowContainsValue($row, $username)) {
                            $proofs[$k]['status'] = 'approved';
                            
                            $matched_user_id = $p['user_id'];
                            $reward = floatval($s['reward_instagram_cookies'] ?? 8.00);
                            $users[$matched_user_id]['balance'] += $reward;
                            $users[$matched_user_id]['total_income'] += $reward;
                            $users[$matched_user_id]['completed_tasks'] += 1;
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            
                            $accounts_to_remove[] = ['username' => $username];
                            
                            $referrer = $users[$matched_user_id]['ref_by'] ?? 0;
                            if (strval($referrer) != "0" && isset($users[$referrer])) {
                                $comm_pct = floatval($s['ref_commission'] ?? 5);
                                $comm = $reward * ($comm_pct / 100);
                                $users[$referrer]['balance'] += $comm;
                                $users[$referrer]['ref_income'] += $comm;
                                sendRich($referrer, "👥 <b>রেফার কমিশন!</b><br>আপনার রেফারেল সদস্যের ইনস্টাগ্রাম কুকিজ কাজ এপ্রুভ হওয়ায় আপনি ৳<code>{$comm}</code> কমিশন পেয়েছেন।");
                            }
                            
                            sendRich($matched_user_id, "✅ কাজ এপ্রুভ! আপনার ইনস্টাগ্রাম কুকিজ অ্যাকাউন্টের ({$username}) জন্য ৳{$reward} যোগ করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'instagram_cookies_master.csv');
            sendRich($chat_id, "✅ ইনস্টাগ্রাম কুকিজ ভালো আইডি প্রসেস সম্পন্ন! মোট এপ্রুভড: <b>{$processed_count}</b> টি কাজ।", getInstaCookiesControlMenu());
            exit;
        }

        // 10. ইনস্টা কুকিজ রিজেক্টেড
        if ($state == 'wait_insta_cookies_rejected_csv') {
            setState($from_id, "");
            $accounts_to_remove = [];
            $delimiter = ',';
            foreach ($lines as $first_line) {
                if (!empty(trim($first_line))) {
                    if (substr_count($first_line, ';') > substr_count($first_line, ',')) $delimiter = ';';
                    break;
                }
            }
            
            $proofs = readDB('proofs.json');
            if ($proofs === null) exit;
            $db_updated = false;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if (empty($row)) continue;
                
                foreach ($proofs as $k => $p) {
                    if ($p['status'] == 'pending' && $p['task_type'] == 'instagram_cookies') {
                        $username = trim($p['username']);
                        if (rowContainsValue($row, $username)) {
                            $proofs[$k]['status'] = 'rejected';
                            
                            $matched_user_id = $p['user_id'];
                            $users[$matched_user_id]['review_tasks'] = max(0, $users[$matched_user_id]['review_tasks'] - 1);
                            $users[$matched_user_id]['rejected_tasks'] = ($users[$matched_user_id]['rejected_tasks'] ?? 0) + 1; 
                            
                            $accounts_to_remove[] = ['username' => $username];
                            sendRich($matched_user_id, "❌ ইনস্টাগ্রাম কুকিজ কাজ বাতিল! ভুল তথ্যের জন্য আপনার ইনস্টাগ্রাম কুকিজ অ্যাকাউন্টটি ({$username}) বাতিল করা হয়েছে।");
                            usleep(30000);
                            
                            $processed_count++;
                            $db_updated = true;
                            break;
                        }
                    }
                }
            }
            if ($db_updated) { writeDB('proofs.json', $proofs); writeDB('users.json', $users); }
            removeCSVRowsBulk($accounts_to_remove, 'instagram_cookies_master.csv');
            sendRich($chat_id, "❌ ইনস্টাগ্রাম কুকিজ খারাপ আইডি প্রসেস সম্পন্ন! মোট রিজেক্টেড: <b>{$processed_count}</b> টি কাজ।", getInstaCookiesControlMenu());
            exit;
        }
    }
}

if ($callback) {
    $cb_data = $callback['data'];
    $cb_id = $callback['id'];
    $msg_id = $callback['message']['message_id'];
    
    // --- ইউজার নির্দিষ্ট কাজের ভিডিও দেখার কলব্যাক ---
    if (strpos($cb_data, 'vid_cat_') === 0) {
        $cat = str_replace('vid_cat_', '', $cb_data);
        bot('answerCallbackQuery', ['callback_query_id' => $cb_id]);
        
        $target_vid = '';
        $cat_title = '';
        
        if ($cat == 'insta') {
            $target_vid = $s['video_instagram'] ?? '';
            $cat_title = 'ইনস্টাগ্রাম অ্যাকাউন্ট ক্রিয়েট';
        } elseif ($cat == 'gmail') {
            $target_vid = $s['video_gmail'] ?? '';
            $cat_title = 'জিমেইল অ্যাকাউন্ট ক্রিয়েট';
        } elseif ($cat == 'fb') {
            $target_vid = $s['video_facebook'] ?? '';
            $cat_title = 'ফেসবুক অ্যাকাউন্ট ক্রিয়েট';
        } elseif ($cat == 'cookies') {
            $target_vid = $s['video_fb_cookies'] ?? '';
            $cat_title = 'ফেসবুক কুকিজ অ্যাকাউন্ট ক্রিয়েট';
        }
        
        if (empty($target_vid)) {
            sendRich($chat_id, "😔 দুঃখিত, <b>{$cat_title}</b> এর কোনো ভিডিও টিউটোরিয়াল এখনো সেট করা হয়নি।");
        } else {
            $cap = "🎬 📷 <b>{$cat_title} ভিডিওটি মনোযোগ দিয়ে দেখুন</b>";
            if (filter_var($target_vid, FILTER_VALIDATE_URL)) {
                sendRich($chat_id, "{$cap}<br><br>🔗 <a href='{$target_vid}'>টিউটোরিয়াল ভিডিও দেখতে এখানে ক্লিক করুন</a>");
            } else {
                bot('sendVideo', [
                    'chat_id' => $chat_id,
                    'video' => $target_vid,
                    'caption' => strip_tags(str_replace(['<br>', '<br/>'], "\n", $cap))
                ]);
            }
        }
        exit;
    }
    
    if (strval($from_id) === strval(ADMIN_ID)) {
        if ($cb_data == 'list_wd') {
            bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
            showAdminWithdrawList($chat_id);
            exit;
        }

        if (strpos($cb_data, 'bal_add_') === 0) {
            $target_uid = str_replace('bal_add_', '', $cb_data);
            bot('answerCallbackQuery', ['callback_query_id' => $cb_id]);
            setState($from_id, 'wait_balance_add_amount', $target_uid);
            sendRich($chat_id, "➕ ইউজার আইডি <code>{$target_uid}</code> এর ব্যালেন্সে কত টাকা যোগ করতে চান লিখুন:", json_encode(['keyboard' => [[makeBtn('🔙 এডমিন মেনু', 'danger')]], 'resize_keyboard' => true]));
            exit;
        }

        if (strpos($cb_data, 'bal_sub_') === 0) {
            $target_uid = str_replace('bal_sub_', '', $cb_data);
            bot('answerCallbackQuery', ['callback_query_id' => $cb_id]);
            setState($from_id, 'wait_balance_sub_amount', $target_uid);
            sendRich($chat_id, "➖ ইউজার আইডি <code>{$target_uid}</code> এর ব্যালেন্স থেকে কত টাকা কমাতে চান লিখুন:", json_encode(['keyboard' => [[makeBtn('🔙 এডমিন মেনু', 'danger')]], 'resize_keyboard' => true]));
            exit;
        }

        if (strpos($cb_data, 'view_wd_') === 0) {
            $wd_id = str_replace('view_wd_', '', $cb_data);
            $withdraws = readDB('withdraws.json');
            if ($withdraws === null) exit;
            foreach ($withdraws as $w) {
                if ($w['id'] == $wd_id) {
                    $method_upper = strtoupper($w['method']);
                    $inline_kb = [
                        [
                            ['text' => '✅ Approve', 'callback_data' => "app_wd_{$w['id']}"],
                            ['text' => '❌ Reject', 'callback_data' => "rej_wd_{$w['id']}"]
                        ],
                        [['text' => '🔙 তালিকায় ফিরে যান', 'callback_data' => 'list_wd']]
                    ];
                    bot('editMessageText', [
                        'chat_id' => $chat_id,
                        'message_id' => $msg_id,
                        'text' => "📥 *উইথড্রাল রিকোয়েস্ট বিস্তারিত:*\n\n👤 ইউজার আইডি: `{$w['user_id']}`\n📱 মেথড: *{$method_upper}*\n💳 নম্বর/অ্যাড্রেস: `{$w['account']}`\n💰 পরিমাণ: ৳*{$w['amount']}*",
                        'parse_mode' => 'Markdown',
                        'reply_markup' => json_encode(['inline_keyboard' => $inline_kb])
                    ]);
                    exit;
                }
            }
        }

        if (strpos($cb_data, 'app_wd_') === 0) {
            $wd_id = str_replace('app_wd_', '', $cb_data);
            $withdraws = readDB('withdraws.json');
            if ($withdraws === null) exit;
            foreach ($withdraws as $k => $w) {
                if ($w['id'] == $wd_id && $w['status'] == 'pending') {
                    $withdraws[$k]['status'] = 'approved';
                    writeDB('withdraws.json', $withdraws);
                    
                    $users[$w['user_id']]['pending_withdraw'] = max(0, $users[$w['user_id']]['pending_withdraw'] - $w['amount']);
                    $users[$w['user_id']]['total_income'] += $w['amount'];
                    writeDB('users.json', $users);
                    
                    bot('answerCallbackQuery', ['callback_query_id' => $cb_id, 'text' => '✅ উইথড্রাল এপ্রুভড!', 'show_alert' => true]);
                    bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
                    
                    $method_upper = strtoupper($w['method']);
                    sendRich($w['user_id'], "✅ <b>পেমেন্ট সফল!</b><br><br>Corporate ৳<b>{$w['amount']}</b> ({$method_upper}) উইথড্রাল আবেদনটি এপ্রুভ করা হয়েছে ও পেমেন্ট পাঠানো হয়েছে। ধন্যবাদ!");
                    showAdminWithdrawList($chat_id);
                    exit;
                }
            }
        }
        
        if (strpos($cb_data, 'rej_wd_') === 0) {
            $wd_id = str_replace('rej_wd_', '', $cb_data);
            $withdraws = readDB('withdraws.json');
            if ($withdraws === null) exit;
            foreach ($withdraws as $k => $w) {
                if ($w['id'] == $wd_id && $w['status'] == 'pending') {
                    $withdraws[$k]['status'] = 'rejected';
                    writeDB('withdraws.json', $withdraws);
                    
                    $users[$w['user_id']]['balance'] += $w['amount'];
                    $users[$w['user_id']]['pending_withdraw'] = max(0, $users[$w['user_id']]['pending_withdraw'] - $w['amount']);
                    writeDB('users.json', $users);
                    
                    bot('answerCallbackQuery', ['callback_query_id' => $cb_id, 'text' => '❌ উইথড্রাল রিজেক্ট করা হয়েছে!', 'show_alert' => true]);
                    bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
                    
                    $method_upper = strtoupper($w['method']);
                    sendRich($w['user_id'], "❌ আপনার ৳<b>{$w['amount']}</b> ({$method_upper}) টাকা উত্তোলনের রিকোয়েস্টটি রিজেক্ট করা হয়েছে এবং আপনার মূল অ্যাকাউন্টে ব্যালেন্স ফেরত দেওয়া হয়েছে।");
                    showAdminWithdrawList($chat_id);
                    exit;
                }
            }
        }
    }
}

// --- অ্যাডমিন ভিডিও ফাইল আপলোড প্রসেসিং ---
$video_file = $message['video'] ?? null;
if ($video_file && strval($from_id) === strval(ADMIN_ID)) {
    $file_id = $video_file['file_id'];
    if ($state == 'wait_set_insta_video') {
        $s['video_instagram'] = $file_id;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ইনস্টাগ্রাম ভিডিও ফাইল সফলভাবে ডাটাবেজে সংরক্ষণ করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    } elseif ($state == 'wait_set_gmail_video') {
        $s['video_gmail'] = $file_id;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ জিমেইল ভিডিও ফাইল সফলভাবে ডাটাবেজে সংরক্ষণ করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    } elseif ($state == 'wait_set_fb_video') {
        $s['video_facebook'] = $file_id;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ ফেসবুক ভিডিও ফাইল সফলভাবে ডাটাবেজে সংরক্ষণ করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    } elseif ($state == 'wait_set_cookies_video') {
        $s['video_fb_cookies'] = $file_id;
        writeDB('settings.json', $s);
        sendRich($chat_id, "✅ কুকিজ ভিডিও ফাইল সফলভাবে ডাটাবেজে সংরক্ষণ করা হয়েছে!", getAdminVideoSettingsMenu());
        setState($from_id, "");
        exit;
    }
}

if (strval($from_id) === strval(ADMIN_ID)) {
    if ($state == 'wait_balance_add_amount' && is_numeric($text)) {
        $target_uid = $temp;
        $add_amount = floatval($text);
        
        if (isset($users[$target_uid])) {
            $users[$target_uid]['balance'] += $add_amount;
            $users[$target_uid]['total_income'] += $add_amount;
            writeDB('users.json', $users);
            
            sendRich($chat_id, "✅ ইউজার আইডি <code>{$target_uid}</code> এর ব্যালেন্সে সফলভাবে ৳<b>{$add_amount}</b> যোগ করা হয়েছে!", getAdminMenu());
            
            sendRich($target_uid, "💰 <b>ব্যালেন্স রিসিভ!</b><br><br>অ্যাডমিন কর্তৃক আপনার এককাউন্টে ৳<b>{$add_amount}</b> যোগ করা হয়েছে। আপনার বর্তমান ব্যালেন্স: ৳<b>{$users[$target_uid]['balance']}</b>");
        } else {
            sendRich($chat_id, "❌ ইউজার খুঁজে পাওয়া যায়নি।", getAdminMenu());
        }
        setState($from_id, "");
        exit;
    }
    
    if ($state == 'wait_balance_sub_amount' && is_numeric($text)) {
        $target_uid = $temp;
        $sub_amount = floatval($text);
        
        if (isset($users[$target_uid])) {
            $users[$target_uid]['balance'] = max(0, $users[$target_uid]['balance'] - $sub_amount);
            writeDB('users.json', $users);
            
            sendRich($chat_id, "✅ ইউজার আইডি <code>{$target_uid}</code> এর ব্যালেন্স থেকে সফলভাবে ৳<b>{$sub_amount}</b> কেটে নেওয়া হয়েছে!", getAdminMenu());
            
            sendRich($target_uid, "⚠️ <b>ব্যালেন্স কর্তন!</b><br><br>অ্যাডমিন কর্তৃক আপনার একাউন্ট থেকে ৳<b>{$sub_amount}</b> কেটে নেওয়া হয়েছে। আপনার বর্তমান ব্যালেন্স: ৳<b>{$users[$target_uid]['balance']}</b>");
        } else {
            sendRich($chat_id, "❌ ইউজার খুঁজে পাওয়া যায়নি।", getAdminMenu());
        }
        setState($from_id, "");
        exit;
    }
}
?>
