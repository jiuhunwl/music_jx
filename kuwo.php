<?php
/**
*@Author: JH-Ahua
*@CreateTime: 2025/5/24 下午5:36
*@email: admin@bugpk.com
*@blog: www.jiuhunwl.cn
*@Api: api.bugpk.com
*@tip: 酷我音乐解析
*/
// 设置默认的Content-Type为JSON
header('Content-Type: application/json; charset=utf-8');

// 错误码定义
const ERROR_INVALID_PARAMS = 1001;
const ERROR_URL_PARSE_FAILED = 1002;
const ERROR_API_REQUEST_FAILED = 1003;

// 全局请求头配置
$GLOBALS['KUWO_API_HEADERS'] = [
    'Cookie: Hm_lvt_cdb524f42f0ce19b169a8071123a4797=1747998937; HMACCOUNT=3E88140C4BD6BF25; _ga=GA1.2.2122710619.1747998937; _gid=GA1.2.1827944406.1747998937; gtoken=RNbrzHWRp6DY; gid=d55a4884-42aa-4733-98eb-e7aaffc6122e; JSESSIONID=us1icx6617iy1k1ksiuykje71; Hm_lpvt_cdb524f42f0ce19b169a8071123a4797=1748000521; _gat=1; _ga_ETPBRPM9ML=GS2.2.s1747998937$o1$g1$t1748000535$j45$l0$h0; Hm_Iuvt_cdb524f42f23cer9b268564v7y735ewrq2324=jbikFazGJzBjt2bhSJGMxGfkM5zNYcis',
    'secret: 4932e2c95746126c945fe2fb3f88d3455b85b69a4fbdfa6c44b501d7dfe50cff04eb9a8e',//重要
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0'
];

/**
 * 发送HTTP请求的公共函数
 * @param string $url 请求URL
 * @return array 解析后的JSON响应
 * @throws Exception 如果请求失败
 */
function sendRequest($url)
{
    global $KUWO_API_HEADERS;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $KUWO_API_HEADERS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        throw new Exception("API请求失败，HTTP状态码: {$httpCode}");
    }

    $result = json_decode($response, true);

    if (empty($result) || !isset($result['code']) || $result['code'] !== 200) {
        throw new Exception("API返回错误: " . ($result['message'] ?? '未知错误'));
    }

    return $result;
}

// 获取并验证参数
$url = $_GET['url'] ?? '';
$type = $_GET['type'] ?? 'music'; // 默认类型为音乐

if (empty($url)) {
    exit(json_encode([
        'code' => ERROR_INVALID_PARAMS,
        'msg' => '缺少必要的参数: url',
        'data' => null
    ], 480));
}

try {

    // 从URL中提取歌曲ID
    $songId = extractSongId($url);
    if (!$songId) {
        throw new Exception("无法从URL中提取歌曲ID", ERROR_URL_PARSE_FAILED);
    }
    if ($type == 'mp4') {
        $mvinfo = '';
    } else {
        // 获取音乐信息
        $musicInfo = getMusicInfo($songId);

        // 构建响应数据
        $response = [
            'code' => 200,
            'msg' => '成功',
            'data' => [
                'song_id' => $songId,
                'title' => $musicInfo['title'],
                'artist' => $musicInfo['artist'],
                'album' => $musicInfo['album'],
                'pic' => $musicInfo['pic'],
                'releaseDate' => $musicInfo['releaseDate'],
                'albumpic' => $musicInfo['albumpic'],
                'songTimeMinutes' => $musicInfo['songTimeMinutes'],
                'pic120' => $musicInfo['pic120'],
                'albuminfo' => $musicInfo['albuminfo'],
                'music_url' => $musicInfo['musicUrl'],
                'lyrics_url' => $musicInfo['lyrics'],
            ]
        ];
    }

} catch (Exception $e) {
    $response = [
        'code' => $e->getCode() ?: ERROR_API_REQUEST_FAILED,
        'message' => $e->getMessage(),
        'data' => null
    ];
}

// 输出JSON响应
echo json_encode($response, 480);

/**
 * 从酷我音乐URL中提取歌曲ID
 * @param string $url 酷我音乐歌曲URL
 * @return string|bool 提取的歌曲ID或false
 */
function extractSongId($url)
{
    // 处理不同格式的酷我音乐URL
    $patterns = [
        '/id=(\d+)/',                  // 标准格式: https://www.kuwo.cn/play_detail/123456
        '/song\/(\d+)/',               // 可能的格式: https://www.kuwo.cn/song/123456
        '/play_detail\/(\d+)/',               // 可能的格式: https://www.kuwo.cn/play_detail/123456
        '/\?rid=MUSIC_(\d+)/',         // 可能的格式: https://kuwo.cn/yinyue/123456?rid=MUSIC_123456
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }

    return false;
}

/**
 * 获取音乐信息，包括音乐文件链接和歌词链接
 * @param string $songId 歌曲ID
 * @return array 包含音乐信息的数组
 * @throws Exception 如果API请求失败
 */
function getMusicInfo($songId)
{
    // 获取音乐文件URL
    $musicUrl = getMusiurl($songId) ?? '';
    //获取歌词
    $lyrics = getLyrics($songId) ?? '';

    // 获取歌曲详情信息
    $songInfo = getSongDetails($songId) ?? '';

    return [
        'musicUrl' => $musicUrl,
        'lyrics' => $lyrics,
        'title' => $songInfo['title'],
        'artist' => $songInfo['artist'],
        'album' => $songInfo['album'],
        'pic' => $songInfo['pic'] ?? '未知pic',
        'releaseDate' => $songInfo['releaseDate'] ?? '未知releaseDate',
        'albumpic' => $songInfo['albumpic'] ?? '未知albumpic',
        'songTimeMinutes' => $songInfo['songTimeMinutes'] ?? '未知songTimeMinutes',
        'pic120' => $songInfo['pic120'] ?? '未知pic120',
        'albuminfo' => $songInfo['albuminfo'] ?? '未知albuminfo',
    ];
}

function getMusiurl($songId, $type = 'music')
{
    // 酷我音乐API，用于获取歌曲url
    $apiUrl = "https://www.kuwo.cn/api/v1/www/music/playUrl?mid={$songId}&type={$type}&httpsStatus=1";
    // 使用公共请求函数
    $result = sendRequest($apiUrl);
    $url = $result['data']['url'] ?? '';
    if (empty($url)){
        $apiUrl2 = "https://antiserver.kuwo.cn/anti.s?type=convert_url3&rid={$songId}&format=mp3";
        $result = json_decode(sendRequest($apiUrl2),true);
        $url = $result['data']['url'] ?? '';
    }
    return $url;
}

function getlyrics($songId)
{
    // 获取歌词URL
    $lyricsUrl = "https://www.kuwo.cn/openapi/v1/www/lyric/getlyric?musicId={$songId}";
    // 使用公共请求函数
    $result = sendRequest($lyricsUrl);
    $lyrics = convertLyricsToLrc($result['data']['lrclist'] ?? '');
    if (empty($lyrics)){
        $apiUrl2 = "https://www.kuwo.cn/newh5/singles/songinfoandlrc?musicId={$songId}";
        $result = json_decode(sendRequest($apiUrl2),true);
        $lyrics = convertLyricsToLrc($result['data']['lrclist'] ?? '');
    }
    return $lyrics;
}

function getSongDetails($songId)
{
    $apiUrl = "https://www.kuwo.cn/api/www/music/musicInfo?mid={$songId}&httpsStatus=1";

    // 使用公共请求函数
    $result = sendRequest($apiUrl);
    $data = $result['data'] ?? '';
    return [
        'title' => $data['name'] ?? '未知标题',
        'artist' => $data['artist'] ?? '未知艺术家',
        'album' => $data['album'] ?? '未知专辑',
        'pic' => $data['pic'] ?? '未知pic',
        'releaseDate' => $data['releaseDate'] ?? '未知releaseDate',
        'albumpic' => $data['albumpic'] ?? '未知albumpic',
        'songTimeMinutes' => $data['songTimeMinutes'] ?? '未知songTimeMinutes',
        'pic120' => $data['pic120'] ?? '未知pic120',
        'albuminfo' => $data['albuminfo'] ?? '未知albuminfo',
    ];
}

/**
 * 将歌词数组转换为标准LRC格式
 * @param array $lyricArray 歌词数组，每个元素包含time和lineLyric字段
 * @return string 标准LRC格式的歌词字符串
 */
function convertLyricsToLrc($lyricArray)
{
    $lrcContent = '';

    foreach ($lyricArray as $line) {
        if (!isset($line['time']) || !isset($line['lineLyric'])) {
            continue; // 跳过格式不正确的行
        }

        // 将时间戳转换为分:秒.毫秒格式
        $time = floatval($line['time']);
        $minutes = floor($time / 60);
        $seconds = floor($time % 60);
        $milliseconds = round(($time - floor($time)) * 1000); // 计算毫秒部分（精确到千分之一秒）

        // 格式化为标准LRC时间格式：[分:秒.毫秒]
        $timeFormat = sprintf("[%02d:%02d.%03d]", $minutes, $seconds, $milliseconds);

        // 添加到LRC内容
        $lrcContent .= "{$timeFormat}{$line['lineLyric']}\n";
    }

    return $lrcContent;
}

?>
