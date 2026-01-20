<?php
$token = 'XquypjKpERmGKjMRfgUbbVonxtGjHTggIeFgHxvo';
$url = "https://api.discogs.com/database/search?q=DGCD-24425&token=" . $token;

$opts = ["http" => ["header" => "User-Agent: SoundHavenApp/1.0\r\n"]];
$context = stream_context_create($opts);
echo file_get_contents($url, false, $context);