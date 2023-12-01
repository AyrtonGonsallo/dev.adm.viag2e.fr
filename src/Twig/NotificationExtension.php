<?php

namespace App\Twig;

use App\Entity\Notification;
use Doctrine\Common\Persistence\ManagerRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    protected $manager;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->manager = $doctrine->getManager();
    }

    public function getGlobals()
    {
        return [
            'notifications' => $this->manager->getRepository(Notification::class)->findAll(),
        ];
    }

    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/2.x/advanced.html#automatic-escaping
            // new TwigFilter('notification', [$this, 'generateNotification']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getNotifications', [$this, 'getNotifications']),
        ];
    }

    public function getNotifications()
    {
        return $this->manager->getRepository(Notification::class)->findAll();
    }
}
