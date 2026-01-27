<?php

namespace App\DataFixtures;

use App\Entity\Notification;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationFixtures extends Fixture implements DependentFixtureInterface
{
    private $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    public function load(ObjectManager $manager)
    {
        $notification = new Notification();
        $notification->setType('revaluation');
        $notification->setData(['delay' => 3, 'route' => $this->router->generate('property_view', ['propertyId' => $this->getReference(PropertyFixtures::PROPERTY_REFERENCE)->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]);
        $notification->setProperty($this->getReference(PropertyFixtures::PROPERTY_REFERENCE));
        $manager->persist($notification);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            PropertyFixtures::class
        ];
    }
}
