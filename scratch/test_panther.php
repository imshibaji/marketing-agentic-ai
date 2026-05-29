<?php
require_once __DIR__ . '/../autoload.php';

use Symfony\Component\Panther\Client;

try {
    echo "Creating Panther Chrome client...\n";
    
    $chromedriverPath = realpath(__DIR__ . '/../chromedriver');
    echo "Chromedriver path: " . $chromedriverPath . "\n";
    
    $client = Client::createChromeClient(
        $chromedriverPath,
        [
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1200,800'
        ]
    );

    echo "Requesting URL...\n";
    $crawler = $client->request('GET', 'https://example.com');
    
    echo "Title: " . $client->getTitle() . "\n";
    echo "HTML snippet: " . substr($client->getPageSource(), 0, 200) . "\n";
    
    $client->quit();
    echo "Done!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
