<?php

namespace App\Entity;
use JsonSerializable;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Routing\Router;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NotificationRepository")
 */
class Notification  implements JsonSerializable
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $expiry;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $data = [];

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Property", inversedBy="notifications")
     */
    private $property;

    public function __construct()
    {
        $this->date = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getExpiry(): ?DateTimeInterface
    {
        return $this->expiry;
    }

    public function setExpiry(?DateTimeInterface $expiry): self
    {
        $this->expiry = $expiry;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): self
    {
        $this->property = $property;

        return $this;
    }

    public function getText(): string
    {
        switch ($this->getType()) {
            case 'revaluation':
                if (!is_null($this->getProperty())) {
                    return "Revalorisation de <a href=\"{$this->getData()['route']}\">{$this->getProperty()->getTitle()}</a> dans {$this->getData()['delay']} jour(s)";
                } else {
                    return "ERROR #{$this->getId()}";
                }
            case 'terminatingcontract':
                if (!is_null($this->getProperty())) {
                    return "Le contrat de <a href=\"{$this->getData()['route']}\">{$this->getProperty()->getTitle()}</a> se termine le {$this->getData()['date']}";
                } else {
                    return "ERROR #{$this->getId()}";
                }
            case 'suivi-message-expiration':
                if (!is_null($this->getProperty())) {
                    return "Le message de suivi {$this->getData()['message']} sur la propriété <a href=\"{$this->getData()['route']}\">{$this->getProperty()->getTitle()}</a> a expiré le {$this->getData()['date']}";
                } else {
                    return "ERROR #{$this->getId()}";
                }
            case 'ipc-non-renseigne':
                return "L'ipc type ' {$this->getData()['message']} n'a pas été renseigné pour ce mois {$this->getData()['date']}";
            case 'copro_expire_sur_propriete':
                return "La date de fin d'exercice de copro sur la proprieté <a href=\"{$this->getData()['route']}\">{$this->getProperty()->getTitle()}</a> a expiré le {$this->getData()['date']}";
                    
                  
            case 'suivi-message-expiration-warning':
                if (!is_null($this->getProperty())) {
                    return "Le message de suivi {$this->getData()['message']} sur la propriété <a href=\"{$this->getData()['route']}\">{$this->getProperty()->getTitle()}</a> va expirer le {$this->getData()['date']}";
                } else {
                    return "ERROR #{$this->getId()}";
                }
            default:
                return $this->getType();
        }
    }

    public function getTime(): string
    {
        $diff = $this->getDate()->diff(new DateTime());

        if ($diff->days > 0) {
            return "Il y a {$diff->days} jours";
        }

        if ($diff->h > 1) {
            return "Il y a {$diff->h} heures";
        }

        if ($diff->i > 10) {
            return "Il y a {$diff->i} minutes";
        }

        return "À l'instant";
    }

    public function jsonSerialize()
    {
        return array(
            'id' => $this->id,
            'type' => $this->type,
            'text'=> $this->getText(),
            'date'=> $this->date,
            'time'=> $this->getTime(),
        );
    }
}
