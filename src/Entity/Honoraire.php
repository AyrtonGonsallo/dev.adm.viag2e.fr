<?php

namespace App\Entity;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HonoraireRepository")
 */
class Honoraire
{
   

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $nom;
/**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_mod;
/**
     * @ORM\Column(type="float")
     */
    private $minimum;
    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;
/**
     * @ORM\Column(type="float")
     */
    private $valeur;

/**
     * @ORM\OneToMany(targetEntity="App\Entity\Property", mappedBy="honorary_rates_object")
     */
    public $properties;

    public function __construct()
    {
        
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDateMod(): ?DateTimeInterface
    {
        return $this->date_mod;
    }

    public function setDateMod(?DateTimeInterface $date_mod): self
    {
        $this->date_mod = $date_mod;

        return $this;
    }
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
    public function getMinimum(): ?float
    {
        return $this->minimum;
    }

    public function setMinimum(float $minimum): self
    {
        $this->minimum = $minimum;

        return $this;
    }
    public function getValeur(): ?float
    {
        return $this->valeur;
    }

    public function setValeur(float $valeur): self
    {
        $this->valeur = $valeur;

        return $this;
    }
}
