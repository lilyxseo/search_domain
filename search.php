<?php
define("API_KEY", "8M8M6L6IUh8Z7z7Kq849Q7D6n6t8D7CE9G8AY9UQE");
define("CHECK_URL", "https://api.dynadot.com/api3.json");
define("OUTPUT_FILE", "domain.txt");
define("COMMON_TLDS", ['com','net','org','info','biz','me','io','co','us','xyz']);

// Fungsi utama
function main() {
    echo "Pilih jenis input:\n";
    echo "1. Huruf awal (a-z)\n2. Custom name\n3. File\n";
    $inputType = getValidInput("Masukkan pilihan (1-3): ", function($v) {
        return in_array($v, ['1','2','3']);
    });

    $domains = [];
    switch($inputType) {
        case '1':
            $startChar = getValidInput("Masukkan huruf awal (a-z): ", function($v) {
                return ctype_alpha($v) && strlen($v) === 1;
            });
            $length = getValidInput("Masukkan jumlah karakter (3-6): ", function($v) {
                return in_array($v, ['3','4','5','6']);
            });
            $domains = generateNDomains(strtolower($startChar), $length);
            break;
            
        case '2':
            $name = getValidInput("Masukkan nama domain: ", function($v) {
                return preg_match('/^[a-z0-9-]{1,63}$/i', $v);
            });
            $domains = [strtolower($name)];
            break;
            
        case '3':
            $filename = getValidInput("Masukkan nama file: ", function($v) {
                return file_exists($v);
            });
            $domains = array_filter(array_map('trim', file($filename)));
            break;
    }

    $extInput = getValidInput("Masukkan ekstensi (contoh: .me atau .com,.net atau all): ");
    $extensions = processExtensions($extInput);

    $total = count($domains) * count($extensions);
    echo "Memulai pencarian domain...\n";
    
    $results = [];
    $processed = 0;
    
    foreach($domains as $base) {
        foreach($extensions as $ext) {
            $processed++;
            $domain = "{$base}.{$ext}";
            
            if(checkDomainAvailability($domain)) {
                $results[] = $domain;
                echo "✅ $domain\n";
            }
            
            usleep(100000);
        }
    }
    
    file_put_contents(OUTPUT_FILE, implode("\n", $results)."\n", FILE_APPEND);
    echo "\nPencarian selesai! Domain yang tersedia disimpan di ".OUTPUT_FILE."\n";
}

// Fungsi pembantu
function getValidInput($prompt, $validator = null) {
    do {
        echo $prompt;
        $input = trim(fgets(STDIN));
        if($validator && !$validator($input)) {
            echo "Input tidak valid!\n";
            continue;
        }
        return $input;
    } while(true);
}

function generateNDomains($startChar, $length) {
    $letters = range('a', 'z');
    $iterations = array_fill(0, $length-1, $letters);
    
    return array_reduce($iterations, function($carry, $chars) {
        $result = [];
        foreach($carry as $prefix) {
            foreach($chars as $char) {
                $result[] = $prefix . $char;
            }
        }
        return $result;
    }, [$startChar]);
}

function processExtensions($input) {
    if(strtolower($input) === 'all') {
        return COMMON_TLDS;
    }
    
    $exts = array_map(function($e) {
        $e = trim($e, " .");
        return strtolower($e);
    }, explode(',', $input));
    
    return array_unique($exts);
}

function checkDomainAvailability($domain) {
    $url = CHECK_URL."?key=".API_KEY."&command=search&domain0=".urlencode($domain);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if($httpCode !== 200) {
        return false;
    }
    
    $data = json_decode($response, true);
    return isset($data['SearchResponse']['SearchResults'][0]['Available']) && 
           $data['SearchResponse']['SearchResults'][0]['Available'] === 'yes';
}

main();
