<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ColorsExtension extends AbstractExtension
{
    private static $lastColor = null;

    public function getFunctions()
    {
        return [
            new TwigFunction('randomColor', [$this, 'getRandomColor']),
        ];
    }

    public function getRandomColor(): string
    {
        $colors = [
            1 => 'primary',
            2 => 'success',
            3 => 'warning',
            4 => 'danger',
            5 => 'info'
        ];

        do {
            $color = $colors[rand(1, 5)];
        } while ($color == self::$lastColor);

        self::$lastColor = $color;

        return $color;
    }
}
