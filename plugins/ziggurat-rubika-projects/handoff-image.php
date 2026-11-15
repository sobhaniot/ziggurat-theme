<?php
if (!defined('ABSPATH')) exit;

function ziggurat_handoff_image($project, $person) {
    $browser = defined('ZIGGURAT_RENDER_BROWSER') ? ZIGGURAT_RENDER_BROWSER : 'C:/Program Files/Google/Chrome/Application/chrome.exe';
    if (!is_file($browser) || !function_exists('proc_open')) return false;
    $theme = get_template_directory();
    $script = file_get_contents(__DIR__ . '/project-handoff-render.js');
    if (strpos($script, 'zigguratHandoffRender') === false) return false;
    $photos = [];
    foreach (zigurat_project_handoff_photo_slots(get_post_meta($project['id'], '_zigurat_work_project_photo', true)) as $photo) {
        $path = zigurat_resolve_private_application_file_path($photo);
        if (!$path) return false;
        $photos[] = 'data:' . $photo['mime'] . ';base64,' . base64_encode(file_get_contents($path));
    }
    $tasks = array_values(array_filter(array_map('trim', preg_split('/\R/u', $project['notes']) ?: []), 'strlen'));
    $job = [
        'logo' => 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($theme . '/assets/images/zigurat-logo.svg')),
        'card' => ['title'=>$project['title'], 'photos'=>$photos, 'selectedPerson'=>['name'=>$person],
            'tasks'=>$tasks, 'included'=>array_fill(0,count($tasks),true)],
    ];
    $font = base64_encode(file_get_contents($theme . '/assets/fonts/Tanha.woff2'));
    $file = tempnam(get_temp_dir(), 'zig-handoff-');
    if (!$file) return false;
    $html = '<meta charset="utf-8"><style>@font-face{font-family:ProjectHandoffTanha;src:url(data:font/woff2;base64,' . $font
        . ')}</style><body><script>window.zigguratHandoffRender=' . wp_json_encode($job,JSON_HEX_TAG)
        . ';</script><script>' . $script . '</script></body>';
    try {
        if (!file_put_contents($file, $html)) return false;
        $page = $file . '.html';
        if (!rename($file,$page)) return false;
        $file = $page;
        $url = 'file:///' . implode('/', array_map('rawurlencode', explode('/', str_replace('\\','/',$file))));
        $url = str_replace('%3A', ':', $url);
        $process = proc_open([$browser,'--headless','--incognito','--disable-background-networking','--no-first-run','--dump-dom','--virtual-time-budget=6000',$url],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','a']], $pipes);
        if (!is_resource($process)) return false;
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1], 30 * MB_IN_BYTES);
        fclose($pipes[1]);
        proc_close($process);
        if (!preg_match('~<body>data:image/png;base64,([A-Za-z0-9+/=]+)</body>~', $output, $match)) return false;
        $png = base64_decode($match[1], true);
        if (substr($png,0,8)!=="\x89PNG\r\n\x1a\n") return false;
        $image = tempnam(get_temp_dir(), 'zig-png-');
        if (!$image || !file_put_contents($image,$png)) {
            if ($image) unlink($image);
            return false;
        }
        return $image;
    } finally {
        if (is_file($file)) unlink($file);
    }
}

function ziggurat_bot_upload_image($url, $fields, $path, $field, $mime = 'image/png', $filename = 'execution-sheet.png') {
    if (!function_exists('curl_init') || !wp_http_validate_url($url) || wp_parse_url($url,PHP_URL_SCHEME)!=='https') return false;
    $handle = curl_init($url);
    $fields[$field] = new CURLFile($path,$mime,$filename);
    curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>35,CURLOPT_CAINFO=>ABSPATH.WPINC.'/certificates/ca-bundle.crt']);
    $body = curl_exec($handle);
    $status = curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return $body === false ? false : [$status,json_decode($body,true)];
}
