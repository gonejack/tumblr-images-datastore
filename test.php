<?php
/**
 * Created by PhpStorm.
 * User: Youi
 * Date: 2015-12-05
 * Time: 20:18
 */

require_once('vendor/autoload.php');
require_once('config.php');

global $store;

main();

function main() {
    !isset($_GET['url']) && exit_script('Hello Tumblr!');

    $query_param = get_query_param($_GET['url']);
    if (!$query_param) {

        if (isImageUrl($_GET['url'])) {
            redirect_location($_GET['url']);
            exit_script();
        } else {
            echoTxtFile("NOT VALID TUMBLR URL: [{$_GET['url']}]");
            exit_script();
        }

    } else {

        $post_record = find_from_ds($query_param);

        if ($post_record) {

            $record_data = $post_record->getData();

            return_recorded_data($record_data);

            exit_script();

        } else {

            $post_info = query_tumblr_api($query_param);
            if (!$post_info) {
                echoTxtFile("NO POST INFO FETCHED FROM TUMBLR WITH GIVEN URL: [{$_GET['url']}], THE POST MIGHT BE DELETED");
                exit_script();
            }

            $post_info = $post_info['posts'][0];

            $record = array(
                'hash' => "{$query_param['post_domain']}|{$query_param['post_id']}",
                'domain' => $query_param['post_domain'],
                'postId' => $query_param['post_id'],
                'postType' => $post_info['type'],
                'responseType' => '',
                'data' => '',
                'time' => time()
            );

            $output = '';
            switch ($post_info['type']) {
                case 'link':
                    $output = <<< EOD
                        <p>Title: <h3>{$post_info['link-text']}</h3></p>
                        <p>link: <a href="{$post_info['link-url']}">{$post_info['link-url']}</a></p>
                        <p>Description:</p>
                        <p>{$post_info['link-description']}</p>
EOD;
                    echoHtmlFile($output);
                    write_record($record, 'html', $output);

                    exit_script();
                    break;
                case 'regular':
                    $output = "<h3>{$post_info['regular-title']}</h3>{$post_info['regular-body']}";

                    echoHtmlFile($output);
                    write_record($record, 'html', $output);

                    exit_script();
                    break;
                case 'answer':
                    $question = htmlCharsDecode($post_info['question']);
                    $answer   = htmlCharsDecode($post_info['answer']);
                    $tags     = implode(', ', isset($post_info['tags']) ? $post_info['tags'] : array());
                    $output   = "[Q&A]\r\n\r\n$question\r\n\r\n$answer\r\n\r\nTags: $tags\r\n";

                    echoHtmlFile($output);
                    write_record($record, 'html', $output);

                    exit_script();
                    break;
                case 'video':
                    $url = get_video_url($post_info);

                    redirect_location($url);
                    write_record($record, 'redirect', $url);

                    exit_script();
                    break;
                case 'photo':
                default:
                    $urls  = get_photo_urls($post_info);
                    $count = count($urls);
                    if ($count === 1) {

                        redirect_location($urls[0]);
                        write_record($record, 'redirect', $urls[0]);

                        exit_script();
                    } else {

                        $images_pack = fetch_images($urls);

                        $urls_str = implode("\r\n", $urls);

                        if ($images_pack['count'] === 0) {
                            $output = "Error: can't load this pictures\r\n$urls\r\n\r\nFrom: {$_GET['url']}";
                            echoTxtFile($output);
                        } else {
                            $output = makeZip($images_pack);
                            echoZipFile($output);
                        }

                        write_record($record, 'photoSet', $urls_str);
                        exit_script();

                    }
                    break;
            }

        }
    }
}

function return_recorded_data($record_data) {
    $data = $record_data['data'];
    switch ($record_data['responseType']) {
        case 'redirect':
            redirect_location($data);
            exit_script();
            break;
        case 'txt':
            echoTxtFile($data);
            exit_script();
            break;
        case 'html':
            echoHtmlFile($data);
            exit_script();
            break;
        case 'photoSet':
            echoTxtFile($data);
            exit_script();
            break;
        default:
            echo 'unknow response type';
            exit_script();
    }

    return true;
}

function write_record($record, $response_type, $data) {
    $record['responseType'] = $response_type;
    $record['data'] = $data;

    write_to_ds($record);

    return true;
}

function get_store() {
    global $store;

    if (!$store) {
        $schema = (new GDS\Schema('tumblr_pack'))
            ->addString('hash')
            ->addString('domain')
            ->addString('postId')
            ->addString('postType')
            ->addString('responseType')
            ->addString('data', false)
            ->addString('time');
        $store = new GDS\Store($schema);
    }

    return $store;
}

function write_to_ds($data) {
    $store = get_store();
    $store->upsert($store->createEntity($data));
    return true;
}

function find_from_ds($query_param) {
    $store = get_store();
    $hash = "{$query_param['post_domain']}|{$query_param['post_id']}";
    return $store->fetchOne("SELECT * FROM tumblr_pack WHERE hash = '$hash'");
}

function isImageUrl($url) {
    $pattern = "<https?://\d+\.media\.tumblr\.com/(\w+/)?tumblr_\w+_(1280|540|500|400|250)\.(png|jpg|gif)>";

    return !!preg_match($pattern, $url);
}

function get_query_param($url) {
    if (preg_match('<https?://(.+)/post/(\d+)>', $url, $match)) {
        return array(
            'post_domain' => $match[1],
            'post_id'     => $match[2]
        );
    } else {
        return false;
    }
};

function query_tumblr_api($query_param) {
    $api_url = "http://{$query_param['post_domain']}/api/read/json?id={$query_param['post_id']}";

    $i = 0;
    do {
        $json_str    = file_get_contents($api_url);
        $status_code = (int)parseHeaders($http_response_header, 'status');
    } while (strlen($json_str) < 10 && $i++ < 3 && $status_code !== 404);

    if (preg_match('<\{.+\}>', $json_str, $match)) {
        return json_decode($match[0], true);
    } else {
        return false;
    }
}

function parseHeaders(array $headers, $header = null) {
    $output = array();

    if ('HTTP' === substr($headers[0], 0, 4)) {
        list(, $output['status'], $output['status_text']) = explode(' ', $headers[0]);
        unset($headers[0]);
    }

    foreach ($headers as $v) {
        $h                         = preg_split('/:\s*/', $v);
        $output[strtolower($h[0])] = $h[1];
    }

    if (null !== $header) {
        if (isset($output[strtolower($header)])) {
            return $output[strtolower($header)];
        }

        return null;
    }

    return $output;
}

function get_photo_urls($post_info) {
    $urls = array();

    if ($post_info['photos']) {
        foreach ($post_info['photos'] as $item) {
            $urls[] = $item['photo-url-1280'];
        }
    } else {
        $urls[] = $post_info['photo-url-1280'];
    }

    return $urls;
}

function get_video_url($post_info) {
    $video_source = $post_info['video-source'];
    if ($video_info = unserialize($video_source)) {
        $video_info = $video_info['o1'];
        $video_id   = substr($video_info['video_preview_filename_prefix'], 0, -1);

        return "http://vt.tumblr.com/$video_id.mp4";
    }

    if (preg_match('<src="(.+?)">', $video_source, $match)) {
        return $match[1];
    }

    return false;
}

function redirect_location($redirect_url) {
    header('Location: ' . $redirect_url, true, 301);

    return true;
}

function htmlCharsDecode($str) {
    $convertMap = array(0x0, 0x2FFFF, 0, 0xFFFF);

    return mb_decode_numericentity($str, $convertMap, 'UTF-8');
}

function fetch_images($urls) {

    $images_pack = array('images' => array(), 'fileNames' => array(), 'count' => 0);

    $valid_status = array(200, 301, 304);

    foreach ($urls as $url) {

        $image_str = @file_get_contents($url);
        if ($image_str === false) {
            continue;
        }

        $status = parseHeaders($http_response_header, 'status');

        $fetched = in_array($status, $valid_status);
        if ($fetched) {
            $images_pack['images'][]    = $image_str;
            $images_pack['fileNames'][] = basename($url);
            $images_pack['count']++;
        }

    }

    return $images_pack;
}

function makeZip($images_pack) {
    require_once('zip.lib.php');
    $zipGenerator = new ZipFile();

    for ($i = 0; $i < $images_pack['count']; $i++) {
        $image_str = $images_pack['images'][$i];
        $filename  = $images_pack['fileNames'][$i];

        $zipGenerator->addFile($image_str, $filename);
    }

    return $zipGenerator->file();
}

function echoZipFile($zip_str) {
    header('Content-Type: application/zip');
    header('Content-Length: ' . strlen($zip_str));
    header('Content-Disposition: attachment; filename=' . date('Y/M/j/D G:i:s') . '.zip');

    echo $zip_str;

    return true;
}

function echoTxtFile($content) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename=' . date('Y/M/j/D G:i:s') . '.txt');

    echo $content;

    return true;
}

function echoHtmlFile($content) {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename=' . date('Y/M/j/D G:i:s') . '.htm');

    echo $content;

    return true;
}

function exit_script($message = null) {
    exit($message);
}