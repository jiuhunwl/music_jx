<?php
/**
*@Author: JH-Ahua
*@CreateTime: 2025/5/8 下午11:49
*@email: admin@bugpk.com
*@blog: www.jiuhunwl.cn
*@Api: api.bugpk.com
*@tip: 汽水音乐解析
*/
header('Content-type: text/json;charset=utf-8');
$url = (isset($_GET['url'])) ? $_GET['url'] : '';
$type = (isset($_GET['type'])) ? $_GET['type'] : 'json';
if ($type !== '' && $url !== '') {
    echo getMusicInfo($type, $url);
}else{
    echo json_encode(['code' => 404,'msg'=>'请补全参数'],480);
}

function getMusicInfo($type = 'json', $url = '')
{
    if (strstr($url, 'qishui.douyin.com')) {
        $url = headers($url);
        preg_match('/track_id=(\d+)/', $url, $match);
    } else {
        preg_match('/track_id=(\d+)/', $url, $match);
    }
    $curl = curl('https://music.douyin.com/qishui/share/track?track_id=' . $match[1]);

    // 匹配 application/ld+json 数据，获取标题和封面
    preg_match('/<script data-react-helmet="true" type="application\/ld\+json">(.*?)<\/script>/s', $curl, $ldJsonMatches);
    if (isset($ldJsonMatches[1])) {
        $ldJsonData = json_decode(urldecode($ldJsonMatches[1]), true);
        $title = $ldJsonData['title'] ?? '';
        $cover = isset($ldJsonData['images']) && count($ldJsonData['images']) > 0 ? $ldJsonData['images'][0] : '';
    } else {
        $title = '';
        $cover = '';
    }

    $jsJsonPattern = '/<script\s+async=""\s+data-script-src="modern-inline">_ROUTER_DATA\s*=\s*({[\s\S]*?});/';
    preg_match($jsJsonPattern, $curl, $jsJsonMatches);

    if (isset($jsJsonMatches[1])) {
        $jsonStr = $jsJsonMatches[1];
        $jsonData = json_decode(trim($jsonStr), true);
        if ($jsonData !== null && isset($jsonData['loaderData']['track_page']['audioWithLyricsOption']['url'])) {
            $musicUrl = $jsonData['loaderData']['track_page']['audioWithLyricsOption']['url'];
        } else {
            $musicUrl = '';
        }

        // 提取歌词 text 数据并按时间分割
        $lrcLyrics = [];
        if ($jsonData !== null && isset($jsonData['loaderData']['track_page']['audioWithLyricsOption']['lyrics']['sentences'])) {
            $sentences = $jsonData['loaderData']['track_page']['audioWithLyricsOption']['lyrics']['sentences'];
            foreach ($sentences as $sentence) {
                if (isset($sentence['startMs']) && isset($sentence['endMs']) && isset($sentence['words'])) {
                    $startMs = $sentence['startMs'];
                    $endMs = $sentence['endMs'];
                    $sentenceText = '';
                    foreach ($sentence['words'] as $word) {
                        if (isset($word['text'])) {
                            $sentenceText .= $word['text'];
                        }
                    }
                    // 将毫秒转换为 LRC 格式的时间标签
                    $minutes = floor($startMs / 60000);
                    $seconds = floor(($startMs % 60000) / 1000);
                    $milliseconds = $startMs % 1000;
                    $timeTag = sprintf("[%02d:%02d.%03d]", $minutes, $seconds, $milliseconds);
                    $lrcLyrics[] = $timeTag . $sentenceText;
                }
            }
        }
        // 将歌词数组拼接成字符串
        $lyrics = implode("\n", $lrcLyrics);
    } else {
        $musicUrl = '';
        $lyrics = '';
    }

    // 构建结果数组
    $info = array(
        'name' => $title,
        'url' => $musicUrl,
        'cover' => $cover,
        'lyrics' => $lyrics,
        'core' => '抖音汽水音乐解析',
        'copyright' => '接口编写:JH-Ahua 接口编写:JH-Ahua 2025-4-20'
    );

    if (!empty($info)) {
        return json_encode($info, 480);
    } else {
        return json_encode(array('msg' => '没有找到相关音乐'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

//解析需要的参数 请勿乱动！
function curl($url, $data = '', $cookie = '', $headers = [])
{
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    if (!empty($headers)) {
        curl_setopt($con, CURLOPT_HTTPHEADER, $headers);
    }
    if (!empty($cookie)) {
        curl_setopt($con, CURLOPT_COOKIE, $cookie);
    }
    if (!empty($data)) {
        curl_setopt($con, CURLOPT_POST, true);
        curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($con, CURLOPT_TIMEOUT, 5000);
    $result = curl_exec($con);
    return $result;
}

//取出真实链接！
function headers($url)
{
    $headers = get_headers($url, 1);
    if (isset($headers['Location'])) {
        if (is_array($headers['Location'])) {
            $url = end($headers['Location']);
        } else {
            $url = $headers['Location'];
        }
    }
    return $url;
}

?>
