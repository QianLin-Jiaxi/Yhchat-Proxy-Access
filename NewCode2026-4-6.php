<?php
// 定义允许的type和对应的URL映射
$urlMap = [
    "audio" => "https://chat-audio1.jwznb.com/",
    "file"  => "https://chat-file.jwznb.com/",
    "image" => "https://chat-img.jwznb.com/",
    "video" => "https://chat-video1.jwznb.com/"
];

// 获取type参数
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$key  = isset($_GET['key'])  ? trim($_GET['key'])  : '';

// 验证参数
if (empty($type) || empty($key)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => '缺少必要参数']);
    exit;
}

// 检查type是否有效
if (!isset($urlMap[$type])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => '未知的数据类别']);
    exit;
}

// 简单的安全验证：防止路径遍历
if (strpos($key, '..') !== false || strpos($key, '//') !== false) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => '无效的key参数']);
    exit;
}

// 构建完整URL
$url = rtrim($urlMap[$type], '/') . '/' . ltrim($key, '/');

// 设置请求头
$headers = [
    'Referer: http://myapp.jwznb.com',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
];

// 初始化cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);        // 不保存完整响应，流式输出
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, '');                 // 自动处理压缩
curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'handleHeader');
curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'handleBody');

// 全局标志：是否已发送HTTP状态行
$statusLineSent = false;

/**
 * 响应头回调：逐行发送头部（含状态码）
 */
function handleHeader($ch, $headerLine) {
    global $statusLineSent;
    if (!$statusLineSent && preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/i', $headerLine, $matches)) {
        http_response_code((int)$matches[1]);
        $statusLineSent = true;
    }
    // 发送其他头部（去除 Transfer-Encoding 避免冲突）
    if (!preg_match('/^transfer-encoding:/i', $headerLine)) {
        header($headerLine, false);
    }
    return strlen($headerLine);
}

/**
 * 主体数据回调：立即输出数据块并刷新缓冲区
 */
function handleBody($ch, $dataChunk) {
    echo $dataChunk;
    flush();  // 尽力将数据推送到客户端
    return strlen($dataChunk);
}

// 禁用PHP输出缓冲，并告知前端代理/nginx不缓冲
ob_implicit_flush(true);
header('X-Accel-Buffering: no');

// 执行cURL请求
curl_exec($ch);

// 错误处理（若头部尚未发送，可输出JSON；否则只能静默终止）
if (curl_errno($ch)) {
    if (!$statusLineSent) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => '请求失败: ' . curl_error($ch)]);
    }
    // 若已发送头部，无法再输出JSON，直接记录错误日志（可选）
    error_log('Stream proxy error: ' . curl_error($ch));
}

curl_close($ch);