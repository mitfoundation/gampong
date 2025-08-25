<?php
$allowedUsers = ['YOUR-TELEGRAM-USERNAME'];

function fetchMetaData($url) {
  $context = stream_context_create([
    'http' => ['timeout' => 5, 'header' => "User-Agent: Mozilla/5.0"]
  ]);
  $html = @file_get_contents($url, false, $context);
  // ✅ Log failed fetches immediately
  if (!$html) {
    file_put_contents('fetch-errors.log', $url . "\n", FILE_APPEND);
    return ['title' => 'Telegram Submission', 'description' => 'Submitted via Telegram'];
  }

  libxml_use_internal_errors(true);
  $doc = new DOMDocument();
  @$doc->loadHTML($html);

  $title = $doc->getElementsByTagName('title')->item(0)?->nodeValue ?? 'Telegram Submission';

  $description = 'Submitted via Telegram';
  foreach ($doc->getElementsByTagName('meta') as $tag) {
    $name = strtolower($tag->getAttribute('name'));
    $property = strtolower($tag->getAttribute('property'));
    if ($name === 'description' || $property === 'og:description') {
      $description = $tag->getAttribute('content');
      break;
    }
    
  }

  return ['title' => $title, 'description' => $description];
  
}


function autoCategorize($url, $description) {
  $text = strtolower($url . ' ' . $description);
 if (str_contains($text, 'job') || str_contains($text, 'career')) return 'Pekerjaan & Karier';
 if (str_contains($text, 'masjid') || str_contains($text, 'mesjid')) return 'Masjid';
 if (str_contains($text, 'search engine') || str_contains($text, 'mesin pencari')) return 'Mesin Pencari';
if (str_contains($text, 'ai') || str_contains($text, 'chatgpt') || str_contains($text, 'openai')) return 'AI & Platform Chat';
if (str_contains($text, 'berita') || str_contains($text, 'artikel') || str_contains($text, 'opini')) return 'Berita & Artikel';
if (str_contains($text, 'pencarian') || str_contains($text, 'google')) return 'Pencarian & Riset';
if (str_contains($text, 'kementrian') || str_contains($text, 'pemerintah') || str_contains($text, 'dinas') || str_contains($text, 'provinsi') || str_contains($text, 'kota') || str_contains($text, 'kabupaten')) return 'Pemerintah';
if (str_contains($text, 'twitter') || str_contains($text, 'x.com')) return 'Media Sosial';
return 'Media Sosial';

}

$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message']['text'] ?? '';
$username = $data['message']['from']['username'] ?? '';

if (!in_array($username, $allowedUsers)) {
  exit('Unauthorized');
}

$newLink = null;

// Structured format: Title | URL | Description | Category
$parts = explode('|', $message);
if (count($parts) === 4 && filter_var(trim($parts[1]), FILTER_VALIDATE_URL)) {
  $newLink = [
    "title" => trim($parts[0]),
    "url" => trim($parts[1]),
    "description" => trim($parts[2]),
    "category" => trim($parts[3])
  ];
} elseif (preg_match('/https?:\/\/\S+/', $message, $matches)) {
  $url = trim($matches[0]);
  $meta = fetchMetaData($url);
  $category = autoCategorize($url, $meta['description']);

  $newLink = [
    "title" => $meta['title'],
    "url" => $url,
    "description" => $meta['description'],
    "category" => $category
  ];
}

if ($newLink) {
  $file = 'links.json';
  $links = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

  foreach ($links as $link) {
    if (trim(strtolower($link['url'])) === trim(strtolower($newLink['url']))) {
      exit('Duplicate link');
    }
  }

  $newLink['timestamp'] = date('Y-m-d H:i:s');
  $links[] = $newLink;
  file_put_contents($file, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  echo "OK";
} else {
  echo "No valid link found";
}
