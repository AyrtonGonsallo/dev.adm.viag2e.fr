<?php

namespace App\Entity;
use JsonSerializable;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Routing\Router;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RappelRepository")
 */
class Rappel 
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
     * @ORM\Column(type="string", length=255)
     */
    private $texte;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $expiry;
    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $nextdisplay;
 /**
     * @ORM\Column(type="boolean")
     */
    private $status;
   


    public const REMIND_TYPE_MONTH = 1;
    public const REMIND_TYPE_START_TRIMESTER = 2;
    public const REMIND_TYPE_END_TRIMESTER = 3;
    public const REMIND_TYPE_YEAR = 4;

    public const REMIND_TYPES = [ 
        self::REMIND_TYPE_MONTH => 'Mensuel', // affiche chaque mois
        self::REMIND_TYPE_START_TRIMESTER => 'Debut de trimestre', // affiche si on est en  janv, avril, juill, oct
        self::REMIND_TYPE_END_TRIMESTER => 'Fin de trimestre', //affiche si on est en mars juin, sept, déc
        self::REMIND_TYPE_YEAR => 'Annuel' //affiche une fois par an
    ];

    public function __construct()
    {
        $this->date = new DateTime();
        $this->status=1;
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

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(string $texte): self
    {
        $this->texte = $texte;

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
    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }

   
    public function getExpiry(): ?string
    {
        return $this->expiry;
    }

    public function getExpiryLabel(): ?string
{
    if (!$this->expiry) {
        return null;
    }

    $date = \DateTime::createFromFormat('d-m', $this->expiry);
    if (!$date) {
        return $this->expiry;
    }

    $formatter = new \IntlDateFormatter(
        'fr_FR',
        \IntlDateFormatter::NONE,
        \IntlDateFormatter::NONE,
        null,
        null,
        'd MMMM'
    );

    return $formatter->format($date);
}



    public function setExpiry(?string $expiry): self
    {
        $this->expiry = $expiry;

        return $this;
    }

     public function getNextdisplay(): ?DateTimeInterface
    {
        return $this->nextdisplay;
    }

    public function setNextdisplay(?DateTimeInterface $nextdisplay): self
    {
        $this->nextdisplay = $nextdisplay;

        return $this;
    }

   



}
