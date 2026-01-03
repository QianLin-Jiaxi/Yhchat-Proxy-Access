<?php
// 定义允许的type和对应的URL映射
$urlMap = [
    "audio" => "https://chat-audio1.jwznb.com/",
    "file" => "https://chat-file.jwznb.com/",
    "image" => "https://chat-img.jwznb.com/",
    "video" => "https://chat-video1.jwznb.com/"
];

// 获取type参数
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$key = isset($_GET['key']) ? trim($_GET['key']) : '';

// 验证参数
if (empty($type) || empty($key)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'msg' => '缺少必要参数'
    ]);
    exit;
}

// 检查type是否有效
if (isset($urlMap[$type])) {
    // 简单的安全验证：防止路径遍历
    if (strpos($key, '..') !== false || strpos($key, '//') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'msg' => '无效的key参数'
        ]);
        exit;
    }
    
    // 构建完整URL
    $url = rtrim($urlMap[$type], '/') . '/' . ltrim($key, '/');
    
    // 初始化cURL
    $ch = curl_init();
    
    // 设置请求头，UA其实可以删掉
    $headers = [
        'Referer: http://myapp.jwznb.com',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ];
    
    // 设置cURL选项
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '', // 自动处理gzip/deflate编码
    ];
    
    curl_setopt_array($ch, $options);
    
    // 执行请求
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    if (curl_errno($ch)) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'msg' => '请求失败: ' . curl_error($ch)
        ]);
    } else {
        // 传递原始Content-Type
        if ($contentType) {
            header('Content-Type: ' . $contentType);
        }
        
        // 传递HTTP状态码
        http_response_code($httpCode);
        
        echo $response;
    }
    
    curl_close($ch);
    
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'msg' => '未知的数据类别'
    ]);
}
?>
