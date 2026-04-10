<?php
namespace App\Service;

use Psr\Log\LoggerInterface;

class Logger
{
    private static ?Logger $instance = null;
    private static ?LoggerInterface $logger = null;

    // constructeur privé : pas d'arguments
    private function __construct() {}

    // initialisation du logger (une seule fois)
    public static function init(LoggerInterface $logger): void
    {
        self::$logger = $logger;
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    // récupérer l'instance
    public static function getInstance(): self
    {
        if (self::$instance === null || self::$logger === null) {
            throw new \Exception('Logger non initialisé. Appelle Logger::init($logger) une fois avant.');
        }
        return self::$instance;
    }

    // méthodes log
    public function logInfo(string $text, array $context = []): void
    {
        self::$logger->info($text, $context);
    }

    public function logError(string $text, array $context = []): void
    {
        self::$logger->error($text, $context);
    }
}